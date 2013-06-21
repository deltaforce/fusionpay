<?php

/* PayPal API Method */

global $sp_currencylist, $SPFunctions, $sp_license;
$_SWIFT = SWIFT::GetInstance();

$userid = $_SWIFT->User->GetUserID();
if (empty($userid)) {
	SWIFT::Error("SupportPay",$_SWIFT->Language->Get("perminvalid"));
	$_SWIFT->Template->Render("header");
	$_SWIFT->Template->Render("footer");
	exit;
}

SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
$PP = new SWIFT_SPPayPal;

if (!$SPFunctions->checkPaymentMethod() || $sp_license["status"] != SP_LICENSE_GOOD || intval($sp_license["death"]) <= time()) {
	SWIFT::Error("SupportPay","We are unable to process payments at the moment. Apologies for the inconvenience. ".
			"Please contact <strong><a href='mailto:" . $_SWIFT->Settings->getKey("settings","general_returnemail") . "'>support</a></strong> for more information.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}

// Need list of items in $_SESSION
if (!empty($_SESSION["items"]) && is_array($_SESSION["items"]) && !empty($_SESSION["paymentType"])) {
	$itemDesc = "";
	$recurIndex = 0;
	
	foreach ($_SESSION["items"] as $itemIndex => $itemDetails) {
		$itemDesc .= "&L_NAME".$itemIndex."=".urlencode(substr($itemDetails["name"],0,127)).
//			"&L_NUMBER".$itemIndex."=".$itemDetails["itemcount"].
			"&L_DESC".$itemIndex."=".urlencode(substr($itemDetails["desc"],0,127)).
			"&L_AMT".$itemIndex."=".sprintf("%0.2f",floatval($itemDetails["cost"])).
			"&L_QTY".$itemIndex."=".intval($itemDetails["itemcount"]).
			"&L_TAXAMT".$itemIndex."=".sprintf("%0.2f",$itemDetails["tax"]);
			
		if (!empty($itemDetails["recur_period"])) {
			$itemDesc .= "&L_BILLINGTYPE".$recurIndex."=RecurringPayments";
			$itemDesc .= "&L_BILLINGAGREEMENTDESCRIPTION".$recurIndex."=".urlencode(substr($itemDetails["desc"],0,127));
			$recurIndex++;
		}
	}
	
	if ($itemDesc != "" && floatval($_SESSION["Payment_Amount"]) >= $_SWIFT->Settings->getKey("settings","sp_minsale")) {
		$returnURL = SWIFT::Get('basename').'/supportpay/ReviewOrder/Index/'.htmlspecialchars(session_id());
		$cancelURL = SWIFT::Get('basename').'/supportpay/PurchasePage/Cancel/'.htmlspecialchars(session_id());

		$_SESSION['currencyCodeType'] = $_SWIFT->Settings->getKey("settings","sp_currency");
		
		// Do we use our card payment or PayPal's if it's a Credit Card sale?
		if ($_SESSION["paytype"] == "PayPal" || !($_SWIFT->Settings->getKey("settings","sp_paypalwpp") == "WPP" && $sp_license["allow_wpp"])) {
			// Paypal's. Build and submit the request.

			$custom = $_SESSION["cart_id"];
			$custom .= "&ALLOWNOTE=0&LOCALECODE=".$_SWIFT->Settings->getKey("settings","sp_paypallocale");
			$custom .= "&ITEMAMT=".sprintf("%0.2f",floatval($_SESSION["Payment_Amount"]));
			$custom .= "&TAXAMT=".sprintf("%0.2f",floatval($_SESSION["Tax_Amount"]));
			
			if ($_SWIFT->Settings->getKey("settings","sp_paypalimgurl") != "") {
				$custom .= "&HDRIMG=".urlencode($_SWIFT->Settings->getKey("settings","sp_paypalimgurl"));
			}
			if ($_SWIFT->Settings->getKey("settings","sp_paypalbgcolor") != "") {
				$custom .= "&HDRBACKCOLOR=".urlencode(str_replace("#","",$_SWIFT->Settings->getKey("settings","sp_paypalbgcolor")));
			}
			if ($_SWIFT->Settings->getKey("settings","sp_paypalformcolor") != "") {
				$custom .= "&PAYFLOWCOLOR=".urlencode(str_replace("#","",$_SWIFT->Settings->getKey("settings","sp_paypalformcolor")));
			}
			$custom .= $itemDesc;
			
			$custom .= "&SOLUTIONTYPE=Sole&LANDINGPAGE=".($_SESSION["paytype"] == "PayPal" ? "Login":"Billing");
	
			$resArray = $PP->CallShortcutExpressCheckout(floatval($_SESSION["Payment_Amount"]) + floatval($_SESSION["Tax_Amount"]),
				$_SESSION['currencyCodeType'], $_SESSION['paymentType'], $returnURL,
				$cancelURL, $custom);
			$ack = strtoupper($resArray["ACK"]);

			if($ack=="SUCCESS")	{
				$token = urldecode($resArray["TOKEN"]);
				$_SESSION['reshash']=$token;
				$PP->RedirectToPayPal ( $token );
			} else {
				// Display a user friendly Error on the page using any of the following error information returned by PayPal
				global $SPUserFuncs;
				SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
				$SPUserFuncs->ShowPaymentErrorPage($resArray);

				exit;
			}
		} elseif ($_SESSION["paytype"] == "Card") {
			header("Location: ".SWIFT::Get('basename')."/supportpay/CardGather/Index");
			exit;
		} else {
			SWIFT::Error("SupportPay","Unknown payment option \"".$_SESSION["paytype"]."\"");
		}
	}
} else {
	SWIFT::Error("SupportPay","No items provided.");
	$_SWIFT->UserInterface->Header('sp_payerror_title');
	$_SWIFT->UserInterface->Footer();
	exit;
}
