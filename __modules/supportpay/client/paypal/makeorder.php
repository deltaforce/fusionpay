<?php
/*==================================================================
 PayPal Express Checkout Call
 ===================================================================
*/

SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
$PP = new SWIFT_SPPayPal;

SWIFT::Info("SupportPay",$SPFunctions->getPaymentWarnings());
if ( !empty($cartData) ) {
	// Complication: cartData is now an array of records, not a record.
	
	$cost = 0; $tax = 0;
	foreach ($cartData as $lineItem) {
		$lineItem["itemcount"] = intval($lineItem["itemcount"]);
		$cost += $lineItem["cost"] * $lineItem["itemcount"];
		$tax += $lineItem["tax"] * $lineItem["itemcount"];
	}
	
	$this->SWIFT4->Template->Assign("payamount",$cost+$tax);
	/*
	** Calls the DoExpressCheckoutPayment API call
	*/
	
	$custom = $_SESSION["cart_id"];
	
	$realPay = false;
	if ($_SESSION["token"] == "harnessgood") {
		$resArray = array("ACK" => "SUCCESS", "PAYMENTSTATUS" => "COMPLETED",
			"TRANSACTIONID" => "TransID","EMAIL" => "harness@home.com");
	} elseif ($_SESSION["token"] == "harnessbad") {
		$resArray = array("ACK" => "SUCCESS", "PAYMENTSTATUS" => "REFUSED",
			"TRANSACTIONID" => "TransID","EMAIL" => "harness@home.com");
	} elseif ($_SESSION["token"] == "harnesspending") {
		$resArray = array("ACK" => "SUCCESS", "PAYMENTSTATUS" => "PENDING",
			"TRANSACTIONID" => "TransID","EMAIL" => "harness@home.com");
	} elseif ($_SESSION["token"] == "harnessauth") {
		$resArray = array("ACK" => "SUCCESS", "PAYMENTSTATUS" => "PENDING",
			"PENDINGREASON" => 'authorization',
			"TRANSACTIONID" => "TransID","EMAIL" => "harness@home.com");
	} else {
		$realPay = true;
		
		// Count the number of recurring packages.
		$recurItems = 0;
		foreach ($_SESSION["items"] as $item) {
			$recurItems += (!empty($item["recur_period"]) ? 1 : 0);
		}

		if ($recurItems != count($_SESSION["items"])) {
			$resArray = $PP->ConfirmPayment ($cost+$tax, $custom);
		} else {
			// It's a recurring payment with no oher items. Simulate a success.
			$resArray["ACK"] = "SUCCESS";			
		}

		if ($resArray["ACK"] == "SUCCESS" && $recurItems > 0) {
			// Also need to set up a billing agreement.
			$recurIndex = 0;
			$cid = $this->SWIFT4->Database->Escape($custom);
			
			foreach ($_SESSION["items"] as $itemIndex => &$lineItem) {
				if (!is_null($item["recur_period"])) {
					$recurIndex++;

					$recurArray = $PP->ConfirmBillingAgreement($_SESSION["cart_id"],$_SESSION["TOKEN"],substr($lineItem["desc"],0,127),
						$lineItem["recur_unit"],$lineItem["recur_period"],
						$lineItem["cost"] * $lineItem["itemcount"],$lineItem["tax"] * $lineItem["itemcount"],$_SESSION['currencyCodeType']);

					if ($recurArray["ACK"] == "SUCCESS") {
						// It's OK. Store the profile ID.
						$this->SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_cart_items set ".
							"proc_txid='".$this->SWIFT4->Database->Escape($recurArray["PROFILEID"])."' ".
							"where cid='".$cid."' and itemid=".$itemIndex);

						$this->SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_cart_defs ".
							"set ctype=".SP_CTYPE_RECURRING." where cid='".$cid."'");
					} else {
						// TODO: Add standard PayPal error screen.
					}
					// Looking for PROFILEID to store, and
					// STATUS == ActiveProfile | PendingProfile
					
					if (!isset($resArray["PAYMENTSTATUS"])) {
						$resArray = $recurArray;
					}
				}
			}
		}
	}
	
	if (isset($resArray["PAYMENTSTATUS"])) {
		$resArray["PAYMENTSTATUS"] = strtoupper($resArray["PAYMENTSTATUS"]); 
	}
	
	$this->SWIFT4->Template->Assign("paydetails",$resArray);
	
	if ($resArray["ACK"] == "SUCCESS")	{
		// authorized payments are returned as 'pending' but we want to treat them as completed.
		$pending = null; $deferred = null;
		if (isset($resArray["TRANSACTIONID"])) { // Could be a recurring payment setup, no transaction ID in this case.
			if ($resArray["PAYMENTSTATUS"] == "PENDING") {
				if ($resArray["PENDINGREASON"] != "authorization") {
					$pending = $resArray["TRANSACTIONID"];
				} else {
					$deferred = true;
				}
			}
		}
		
		$errmsg = "";
//		error_log("PayPal add payment. Status = ".$resArray["PAYMENTSTATUS"]);
		
		// Don't add a transaction if this is a test-harness operation.
		if (isset($resArray["TRANSACTIONID"])) {
			if ($realPay && ($resArray["PAYMENTSTATUS"] == "COMPLETED" || $resArray["PAYMENTSTATUS"] == "PENDING")) {
				foreach ($cartData as $lineItem) {
					if (empty($lineItem["recur_period"])) {
						
						/*
						For authorisation payments, 
							ACK => SUCCESS
							PAYMENTSTATUS => PENDING
							PAYMENTTYPE => instant
							PENDINGREASON => authorization
						*/

						$pkgid = (empty($lineItem["pkgid"]) ? null : $lineItem["pkgid"]);
						$SPFunctions->addPayment($errmsg, $this->SWIFT4->User->GetUserID(),
							$lineItem["minutes"],$lineItem["tickets"],
							($lineItem["cost"]+$lineItem["tax"]) * $lineItem["itemcount"],
							$_SESSION["PayerName"],"Tx#".$resArray["TRANSACTIONID"],$pkgid,$pending,SP_PROCESSOR_PAYPAL,
							$resArray["TRANSACTIONID"],
							null, ($lineItem["tax"] * $lineItem["itemcount"]),
							($_SESSION["paymentType"] == "Sale" ? SP_PAYTYPE_SALE : SP_PAYTYPE_DEFERRED));
						if (!empty($errmsg)) {
							SWIFT::Error("SupportPay", $errmsg);
						}
					}
				}
			}
		}
		
		// Retrieve the transaction details, including all payer information.
		if ($realPay) {
			if (!empty($resArray["PROFILEID"])) {
				$resArray = $PP->GetProfileDetails ( $resArray["PROFILEID"] );
			} elseif (!empty($resArray["TRANSACTIONID"])) {
				$resArray = $PP->GetTransactionDetails ( $_SESSION["paymentType"], $resArray["TRANSACTIONID"] );
			}
		} else {
			$resArray = array("EMAIL" => "test@home.com",
				"PAYERID" => "12345678", "TAXAMT" => "0.00",
				"ACK" => "Success", "FIRSTNAME" => "Test",
				"LASTNAME" => "User", "TRANSACTIONID" => "Harness trans ID",
				"CURRENCYCODE" => "GBP",
				);
			if ($_SESSION["token"] == "harnesspending" || $_SESSION["token"] == "harnessauth") {
				$resArray["PAYMENTSTATUS"] = "PENDING";
			} elseif ($_SESSION["token"] == "harnessbad") {
				$resArray["PAYMENTSTATUS"] = "REFUSED";
			} else {
				$resArray["PAYMENTSTATUS"] = "COMPLETED";
			}
		}
		$resArray["ACK"] = strtoupper($resArray["ACK"]);
		$resArray["DEFERRED"] = $deferred;
		
		if (isset($resArray["TRANSACTIONID"])) {
			$resArray["PAYMENTSTATUS"] = strtoupper($resArray["PAYMENTSTATUS"]);
			if ($deferred && $resArray["PAYMENTSTATUS"] == "PENDING") {
				// Then it's actually been accepted. Change the status and add a flag to the template.
				$resArray["PAYMENTSTATUS"] = "COMPLETED";
			}
			$this->SWIFT4->Template->Assign("transdetails","<b>Transaction ID : </b>" . $resArray["TRANSACTIONID"]);
		} elseif (isset($resArray["PROFILEID"])) {
			$this->SWIFT4->Template->Assign("transdetails","<b>Profile ID : </b>" . $resArray["PROFILEID"]);
			$resArray["PAYMENTSTATUS"] = "PENDING";
		} else {
			$this->SWIFT4->Template->Assign("transdetails","<b>Transaction ID : </b>" . "Unknown");
		}

		$this->SWIFT4->Template->Assign("paydetails",$resArray);
		
		// If pending:
		// $pendingReason	= $resArray["PENDINGREASON"];  
		// $reasonCode		= $resArray["REASONCODE"];   
	} else {
		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		$errormessage = $SPUserFuncs->GenPaymentErrorPage($resArray);
		SWIFT::Error("SupportPay",$errormessage);
		$this->SWIFT4->UserInterface->Header('sp_payerror_title');
		$this->SWIFT4->UserInterface->Footer();
		exit;
	}
} else {
	SWIFT::Error("SupportPay", "This payment has already been processed.");
	$resArray = array("PAYMENTSTATUS" => "ERROR");
	$this->SWIFT4->Template->Assign("transdetails","<b>Transaction ID : </b>" . $_SESSION["token"]);
}	

$this->UserInterface->Header('sp_uw_master');
$SPFunctions->assignSectionTitle("Payment ".$resArray["PAYMENTSTATUS"]);
$this->SWIFT4->Template->Render("sp_header");
$this->SWIFT4->Template->Render("sp_dopay");
$this->SWIFT4->Template->Render("sp_footer");
$this->UserInterface->Footer();
?>