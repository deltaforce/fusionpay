<?php

/* Authorize.net */

global $sp_currencylist, $SPFunctions, $sp_license;
$_SWIFT = SWIFT::GetInstance();

$userid = $_SWIFT->User->GetUserID();

if (empty($userid)) {
	SWIFT::Error("SupportPay",$_SWIFT->Language->Get("perminvalid"));
	$_SWIFT->Template->Render("header");
	$_SWIFT->Template->Render("footer");
	exit;
}

if (!$SPFunctions->checkPaymentMethod() || $sp_license["status"] != SP_LICENSE_GOOD || intval($sp_license["death"]) <= time()) {
	SWIFT::Error("SupportPay","We are unable to process payments at the moment. Apologies for the inconvenience. ".
		"Please contact <strong><a href='mailto:" . $_SWIFT->Settings->getKey("settings","general_returnemail") . "'>support</a></strong> for more information.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}

$fromemail = $_SWIFT->User->GetEmailList();
if (count($fromemail) == 0) {
	// No email address configured.
	SWIFT::Error("SupportPay","Your account has no email address configured.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}
/*
if($_SERVER["HTTPS"] != "on" && $_SWIFT->Settings->getKey("settings","sp_anlive")) {
	echo "Not secure";
	exit;
}
*/
// Need list of items in $_SESSION
if (!empty($_SESSION["items"]) && is_array($_SESSION["items"]) && !empty($_SESSION["paymentType"])) {
	$itemDesc = "";
	$recurIndex = 0;

	// POST the entire cart through to Authorize.net .
	if ($_SWIFT->Settings->getKey("settings","sp_anlive")) {
		$host = "https://secure.authorize.net/gateway/";
	} else {
		$host = "https://test.authorize.net/gateway/";
	}

	$cartId = $_SESSION["cart_id"];
	
	// Timestamp *must* be in UTC for Authorize.net .
	date_default_timezone_set("UTC");
	$t = time();

	// Bug; time() returns seconds in GMT already, don't need to take account of timezone.
	//$timestamp = $t+date("Z",$t);
	$timestamp = $t;
	$clientName = $_SWIFT->User->GetFullName();
	$clientName = explode(" ",$clientName,2);
	if (count($clientName) < 2) {
		$clientName[1] = "";
	}
	
	$IPNURL = $_SWIFT->Settings->getKey("settings","general_producturl").
		SWIFT_MODULESDIRECTORY."/supportpay/client/authnet/relay.php";
	$IPNURL = str_replace("127.0.0.1","jimkeir.dyndns.org",$IPNURL);
	
	switch ($_SESSION["paymentType"]) {
		case "Authorization":
			$procType = "AUTH_ONLY";	// Authorize and hold.
			break;
		case "Sale":
		default:
			$procType = "AUTH_CAPTURE";	
	}
	
	$hash = hash_hmac("md5",$_SWIFT->Settings->getKey("settings","sp_anloginid")."^".$cartId."^".$timestamp."^".
		($_SESSION["Tax_Amount"]+$_SESSION["Payment_Amount"])."^",
		$SPFunctions->getSecureSetting("sp_antxkey"));
	$myData = $cartId;
	$postArgs = array(
		"x_login" => $_SWIFT->Settings->getKey("settings","sp_anloginid"),
		"x_version" => "3.1",
		"x_method" => "CC",
		"cartData" => $cartId,
		"sessId" => $_SWIFT->Session->GetSessionID(),
		"x_invoice_num" => $cartId,
		"x_type" => $procType,
		"x_tax" => $_SESSION["Tax_Amount"],
		"x_amount" => ($_SESSION["Tax_Amount"] + $_SESSION["Payment_Amount"]),
		"x_show_form" => "PAYMENT_FORM",
		"x_email" => $fromemail[0],
		"x_email_customer" => "YES",
		"x_fp_sequence" => $cartId,
		"x_fp_hash" => $hash,
		"x_fp_timestamp" => $t,
		"x_relay_url" => $IPNURL,
		"x_relay_response" => "TRUE",
		"x_first_name" => substr($clientName[0],0,50),
		"x_last_name" => substr($clientName[1],0,50),
		"x_company" => $_SWIFT->User->GetOrganizationName(),
		"x_customer_ip" => $_SERVER["REMOTE_ADDR"],
		"x_cust_id" => $userid,
	);

	$postArgsString = $SPFunctions->array_implode( '=', '&', $postArgs );
	foreach ($_SESSION["items"] as $itemIndex => $itemDetails) {
		$postArgsString .= "&x_line_item=".($itemIndex+1)."<|>".
			htmlspecialchars(substr($itemDetails["name"],0,31),ENT_QUOTES)."<|>".
			htmlspecialchars(substr($itemDetails["desc"],0,255),ENT_QUOTES)."<|>".
			intval($itemDetails["itemcount"])."<|>".
			sprintf("%0.2f",floatval($itemDetails["cost"]))."<|>".
			($_SESSION["Tax_Amount"] == 0 ? "N":"Y");
	}
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $host."transact.dll");
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postArgsString."&");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$response = str_ireplace("<head>","<head><base href='".$host."'/>", $response);
	
	echo $response;
} else {
	SWIFT::Error("SupportPay","No items provided.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
}

?>
