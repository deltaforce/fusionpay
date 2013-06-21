<?php
global $sp_currencylist, $SPFunctions, $sp_license;

$_SWIFT = SWIFT::GetInstance();
$isLive = $_SWIFT->Settings->getKey("supportpay","sp_paypallive");

$userid = $_SWIFT->User->GetUserID();
if (empty($userid)) {
	SWIFT::Error("SupportPay",$_SWIFT->Language->Get("perminvalid"));
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}

if (!$SPFunctions->checkPaymentMethod() || $sp_license["status"] != SP_LICENSE_GOOD || intval($sp_license["death"]) <= time()) {
	SWIFT::Error("SupportPay","We are unable to process payments at the moment. Apologies for the inconvenience. ".
		"Please contact <strong><a href='mailto:" . $_SWIFT->Settings->getKey("settings","general_returnemail") . "'>support</a></strong> for more information.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}

if(!empty($isLive) && (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")) {
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: https://' . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"]);
	exit();
}

SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
$PP = new SWIFT_SPPayPal;

SWIFT::Info("SupportPay",$SPFunctions->getPaymentWarnings());
if ( !empty($_SESSION["items"]) ) {
	$cartData = &$_SESSION["items"];
	$expDate = $_POST["expDateM"] . $_POST["expDateY"];
	
	$extras = "";
	if ($_POST["cardtype"] == "Maestro" || $_POST["cardtype"] == "Solo") {
		if (!empty($_POST["issue"]))
			$extras = "&ISSUENUMBER=".intval($_POST["issue"]);
		else
			$extras = "&STARTDATE=".$_POST["startDateM"].$_POST["startDateY"];
	}

	$cost = 0; $tax = 0; $itemIndex = 0;
	foreach ($cartData as $lineItem) {
		$lineItem["itemcount"] = intval($lineItem["itemcount"]);
		$cost += $lineItem["cost"] * $lineItem["itemcount"];
		$tax += $lineItem["tax"] * $lineItem["itemcount"];

		$extras .= "&L_NAME".$itemIndex."=".urlencode(substr($lineItem["name"],0,127)).
			"&L_DESC".$itemIndex."=".urlencode(substr($lineItem["desc"],0,127)).
			"&L_AMT".$itemIndex."=".sprintf("%0.2f",floatval($lineItem["cost"])).
			"&L_QTY".$itemIndex."=".intval($lineItem["itemcount"]).
			"&L_TAXAMT".$itemIndex."=".sprintf("%0.2f",$lineItem["tax"]);

		$itemIndex++;
	}
	$_SWIFT->Template->Assign("payamount",$cost+$tax);
	$extras .= "&CUSTOM=".$_SESSION["cart_id"];

	$resArray = $PP->DirectPayment ( $_SESSION["paymentType"], $_SESSION["Payment_Amount"], 
		$_SESSION["Tax_Amount"],
		$_POST["cardtype"], $_POST["cardno"],
		$expDate, $_POST["cvv2"], $_POST["fname"], $_POST["sname"], $_POST["street1"], $_POST["city"], $_POST["state"], $_POST["zip"], 
		$_POST["ctry"], $_SWIFT->Settings->getKey("settings","sp_currency"),$extras); 

	$resArray["ACK"] = strtoupper($resArray["ACK"]);
	$fromemail = $_SWIFT->User->GetEmailList();
	$resArray["EMAIL"] = $fromemail[0];
	
	if($resArray["ACK"] != "SUCCESS") {
		// Code 10501 == Not a Website Pro payments site.
		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		$SPUserFuncs->ShowPaymentErrorPage($resArray);
		exit;
	} else {
		// Need to pad out some fake response codes for the result page.
		// DoDirectPayment returns a simple SUCCESS or FAILURE.
		
		$deferred = ($_SESSION["paymentType"] == "Authorization");

		$resArray["PAYMENTSTATUS"] = "COMPLETED";
		$resArray["DEFERRED"] = $deferred;

		foreach ($cartData as $lineItem) {
			$pkgid = (empty($lineItem["pkgid"]) ? null : $lineItem["pkgid"]);
			
			$errmsg = "";
			$SPFunctions->addPayment($errmsg, $userid,
				$lineItem["minutes"],$lineItem["tickets"],
				($lineItem["cost"]+$lineItem["tax"]) * $lineItem["itemcount"],
				$_POST["fname"]." ".$_POST["sname"],"Tx#".$resArray["TRANSACTIONID"],$pkgid,null,
				SP_PROCESSOR_PAYPAL,$resArray["TRANSACTIONID"],	null, ($lineItem["tax"] * $lineItem["itemcount"]),
				($_SESSION["paymentType"] == "Sale" ? SP_PAYTYPE_SALE : SP_PAYTYPE_DEFERRED));

			if (!empty($errmsg)) {
				SWIFT::Error("SupportPay", $errmsg);
			}
		}
	}
} else {
	SWIFT::Error("SupportPay", "This payment has already been processed.");
	$resArray = array("PAYMENTSTATUS" => "ERROR");
	$_SWIFT->Template->Assign("transdetails","<b>Transaction ID : </b>" . $_SESSION["token"]);
}

$this->UserInterface->Header('sp_uw_master');
$SPFunctions->assignSectionTitle("Payment ".$resArray["PAYMENTSTATUS"]);
$_SWIFT->Template->Assign("paydetails",$resArray);
$_SWIFT->Template->Render("sp_header");
$_SWIFT->Template->Render("sp_dopay");
$_SWIFT->Template->Render("sp_footer");
$this->UserInterface->Footer();

?>
