<?php

global $dbCore, $_SWIFT, $datastore, $_fieldpointer, $settings, $template, $sp_currencylist;
$template->loadLanguageSection($template->activeLanguage,"F");

if (empty($_SWIFT["user"]["userid"])) {
	$template->assign("errormessage", $_SWIFT["language"]["perminvalid"]);
	echo $template->displayTemplate("header");
	echo $template->displayTemplate("footer");
	exit;
}

require_once (SP_INCLUDE_PATH . "/functions_html.php");
SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

$license = array();
readLicense($_SWIFT["settings"]["sp_license"], $license);

showPaymentWarnings($template);

// WorldPay doesn't do Tax.
$totalAmount = floatval($_SESSION["Tax_Amount"]) + floatval($_SESSION["Payment_Amount"]);
	
if ($_SWIFT["settings"]["sp_worldpayinstid"] == "" || $license["status"] != SP_LICENSE_GOOD || intval($license["death"]) <= time()) {
	$template->assign("errormessage","We are unable to process payments at the moment. Apologies for the inconvenience. ".
			"Please contact <strong><a href='mailto:" . $_SWIFT["settings"]["general_returnemail"] . "'>support</a></strong> for more information.");
	echo $template->displayTemplate("header");
	echo $template->displayTemplate("footer");
	exit;
}

if (!is_array($_SESSION["items"]) || count($_SESSION["items"]) == 0 || 
	$totalAmount < floatval($_SWIFT["settings"]["sp_minvalue"])) {
	$template->assign("errormessage","Unspecified error in payment.");
	echo $template->displayTemplate("header");
	echo $template->displayTemplate("footer");
	exit;
}

// Technically don't care about items because WorldPay only wants the final amount.
if($_SERVER["HTTPS"] != "on" && $_SWIFT["settings"]["sp_worldpaylive"]) {
	echo "Not secure";
	
	exit();
}

// Simple - pass through the items and amounts to the template:
$template->assign("items",$_SESSION["items"]);
$template->assign("total",sprintf("%0.2f",$totalAmount));
$template->assign("tax",sprintf("%0.2f",floatval($_SESSION["Tax_Amount"])));
$template->assign("cursymbol",$sp_currencylist[$_SWIFT["settings"]["sp_currency"]]["symbol"]);
if ($_SWIFT["settings"]["sp_worldpaylive"]) {
	$host = "secure.wp3.rbsworldpay.com";
	$test = 0;
} else {
	$host = "select-test.wp3.rbsworldpay.com";
	$test = 100;
}

$_MD5Pass = "";
if ($_SWIFT["settings"]["sp_wp_md5pass"] != "") {
	$_MD5Fields = getSecureSetting("sp_wp_md5pass").":".$totalAmount.":".$_SWIFT["settings"]["sp_currency"].":".$_SWIFT["user"]["primaryemail"];
	$_MD5Pass = "<input type='hidden' name='signatureFields' value='amount:currency:email'/>\n";
	$_MD5Pass.= "<input type='hidden' name='signature' value='".md5($_MD5Fields,false)."'/>\n";
}

$cartId = genCartId();
$myData = encodeCartData($_SWIFT["user"]["userid"],$_SESSION["buyMinutes"],$_SESSION["buyTickets"],
		$_SESSION["pkgid"],$totalAmount,
		$_SWIFT["settings"]["sp_worldpayinstid"]);

$template->assign("formstart",'<form name="myform" action="https://'.$host.'/wcc/purchase" method="post">
<input type="hidden" name="instId" value="'.htmlspecialchars($_SWIFT["settings"]["sp_worldpayinstid"]).'"> 
<input type="hidden" name="MC_cartdata" value="'.$myData.'">
<input type="hidden" name="MC_taxamt" value="'.$_SESSION["Tax_Amount"].'">
<input type="hidden" name="cartId" value="'.$cartId.'">
<input type="hidden" name="amount" value="'.$totalAmount.'">
<input type="hidden" name="testMode" value="'.$test.'">
<input type="hidden" name="currency" value="'.$_SWIFT["settings"]["sp_currency"].'">
<input type="hidden" name="name" value="'.$_SWIFT["user"]["fullname"].'">
<input type="hidden" name="email" value="'.$_SWIFT["user"]["primaryemail"].'">
<input type="hidden" name="lang" value="'.$_SWIFT["clientlanguage"].'">
'.$_MD5Pass);

echo $template->displayTemplate("sp_wpconfirm");

?>
