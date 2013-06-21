<?php
define("SWIFT_AREA", 40);

// error_log("In WorldPay IPN");

require_once("../../../../swift.php");

if (!defined("INSWIFT")) {
	error_log("Ignoring WorldPay IPN - INSWIFT not set");
	echo "Not enabled";
	exit;
}

/*
foreach ($_POST as $name => $value) {
	error_log($name . " => " . $value);
}
*/

// check callbackPW if it exists against the local copy.

// All looks good, accept the payment.
global $dbCore, $_SWIFT, $settings, $template;

// Shopper Response
$template->loadLanguageSection("supportpay",TEMPLATE_FILE);
$template->loadLanguageSection($template->activeLanguage,TEMPLATE_FILE);

// Fake a logged-in screen
$_SWIFT["user"]["loggedin"] = true;
$template->assign("_USER", $_SWIFT["user"]);
$template->assign("userdisplayname",$_POST["email"]);
$template->assign("cansearch",true);

$template->assign("sectiontitle", $_SWIFT["language"]["sp_paydetails"]);
$template->assign("navigation", '<a href="index.php" id="navlink">'.$_SWIFT["language"]["navhome"].'</a> &raquo; '.$_SWIFT["language"]["sp_paydetails"]);

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

if (isset($_SWIFT["settings"]["sp_wp_callbackpw"]) || isset($_POST["callbackPW"])) {
	if (getSecureSetting("sp_wp_callbackpw") != $_POST["callbackPW"]) {
		paymentErrorEmail("WorldPay callback password was incorrect.", SP_PROCESSOR_WORLDPAY);
		header("HTTP/1.0 503 Service Unavailable");
		exit;
	}
}

if ($_POST["transStatus"] == "Y") {
	$errmsg = "";
	$cartData = decodeData($_SWIFT["settings"]["sp_worldpayinstid"], $_POST["MC_cartdata"]);
	if ($cartData) {
		// Tax is to be found in floatval($_POST["MC_taxamt"])
		addPayment($errmsg, $cartData["u"],$cartData["m"],$cartData["t"],$cartData["c"],
			$_POST["name"],"Tx#".$_POST["transId"],
			$cartData["p"],null,SP_PROCESSOR_WORLDPAY,$_POST["transId"],
			null,$_POST["MC_taxamt"]);
	} else {
		paymentErrorEmail("Unable to understand payment data", SP_PROCESSOR_WORLDPAY);
		$errmsg = "Unable to understand payment data";
	}
}

$template->assign("errormessage",$errmsg);
require_once (SP_INCLUDE_PATH . "/functions_html.php");

mapWorldPayIPN($_POST);
$template->assign("transdetails","<WPDISPLAY ITEM=banner>");
$template->assign("paydetails",$_POST);
$op = $template->displayTemplate("sp_dopay");
$op = str_ireplace("<head>","<head><base href='".$_SWIFT["swiftpath"]."'/>",$op);
echo $op;

?>