<?php

class Controller_PayPal extends SWIFT_Controller
{
	private $_SWIFT;
	
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		$this->_SWIFT = SWIFT::GetInstance();
		
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Main() {
		if ($_POST['test_ipn'] == 1) {
			$postbackurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		} else {
			$postbackurl = 'https://www.paypal.com/cgi-bin/webscr';
		}

		$req = 'cmd=_notify-validate';
		$debugLog = $this->_SWIFT->Settings->getKey("settings","sp_debug");

		foreach ($_POST as $key => $value) {
			//	error_log($key . " => " . $value);
			$value = urlencode(stripslashes($value));
			$req .= "&$key=$value";
		}

		if ($debugLog) {
			error_log("In PayPal IPN");
		}

		// post back to PayPal system to validate
		$curl_result=$curl_err='';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $postbackurl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_HTTPHEADER,
			array("Content-Type: application/x-www-form-urlencoded", "Content-Length: " . strlen($req)));
		curl_setopt($ch, CURLOPT_HEADER , 0);  
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$curl_result = @curl_exec($ch);
		$curl_err = curl_error($ch);
		curl_close($ch);

		//are we verified? If so, let's process the IPN
		$verified=$curl_result;
		if (strpos($curl_result, "VERIFIED") !== FALSE) {   
			$status = "Valid";
			// yes valid, f.e. change payment status
		} else {
			// invalid, log error or something
			$status = "Not valid";
		}

		if ($status == "Valid") {
			global $SPFunctions;
			
			switch ($_POST["txn_type"]) {
				case "express_checkout":
				case "cart":
				case "web_accept":
				case "recurring_payment":
					// Regular payment IPN.
					if ($_POST["payment_status"] == "Completed" || $_POST["payment_status"] == "Pending") {
						/*
						[09-Jan-2010 15:57:22] mc_gross => 0.25
						[09-Jan-2010 15:57:22] protection_eligibility => Eligible
						[09-Jan-2010 15:57:22] address_status => confirmed
						[09-Jan-2010 15:57:22] payer_id => D7J7EXP8T2XAU
						[09-Jan-2010 15:57:22] tax => 0.00
						[09-Jan-2010 15:57:22] address_street => 1 Main Terrace
						[09-Jan-2010 15:57:22] payment_date => 07:57:16 Jan 09, 2010 PST
						[09-Jan-2010 15:57:22] payment_status => Completed
						[09-Jan-2010 15:57:22] charset => windows-1252
						[09-Jan-2010 15:57:22] address_zip => W12 4LQ
						[09-Jan-2010 15:57:22] first_name => Test
						[09-Jan-2010 15:57:22] mc_fee => 0.25
						[09-Jan-2010 15:57:22] address_country_code => GB
						[09-Jan-2010 15:57:22] address_name => Test User
						[09-Jan-2010 15:57:22] notify_version => 2.8
						[09-Jan-2010 15:57:22] custom => 
						[09-Jan-2010 15:57:22] payer_status => unverified
						[09-Jan-2010 15:57:22] address_country => United Kingdom
						[09-Jan-2010 15:57:22] address_city => Wolverhampton
						[09-Jan-2010 15:57:22] quantity => 1
						[09-Jan-2010 15:57:22] verify_sign => ARJ5zIbJfvWyjKBOrNcLT0eymoN-Awe0kD3JABjTa5Ix93bhQ6YjXICW
						[09-Jan-2010 15:57:22] payer_email => jimkei_1225468064_per@yahoo.co.uk
						[09-Jan-2010 15:57:22] txn_id => 2RD27435SH684731D
						[09-Jan-2010 15:57:22] payment_type => instant
						[09-Jan-2010 15:57:22] last_name => User
						[09-Jan-2010 15:57:22] address_state => West Midlands
						[09-Jan-2010 15:57:22] receiver_email => jimkei_1225661997_biz@yahoo.co.uk
						[09-Jan-2010 15:57:22] payment_fee => 
						[09-Jan-2010 15:57:22] receiver_id => QNB4NGKSCB5KC
						[09-Jan-2010 15:57:22] txn_type => express_checkout
						[09-Jan-2010 15:57:22] item_name => 
						[09-Jan-2010 15:57:22] mc_currency => EUR
						[09-Jan-2010 15:57:22] item_number => 
						[09-Jan-2010 15:57:22] residence_country => GB
						[09-Jan-2010 15:57:22] test_ipn => 1
						[09-Jan-2010 15:57:22] handling_amount => 0.00
						[09-Jan-2010 15:57:22] transaction_subject => 
						[09-Jan-2010 15:57:22] payment_gross => 
						[09-Jan-2010 15:57:22] shipping => 0.00
						*/
						
						if ($_POST["txn_type"] == "recurring_payment") {
							$cartID = $_POST["rp_invoice_id"];
							$cartData = $SPFunctions->retrieveCartData(null, $_POST["rp_invoice_id"], 
								SP_PROCESSOR_PAYPAL, $_POST["recurring_payment_id"]);
						} else {
							$cartID = $_POST["custom"];
							$cartData = $SPFunctions->retrieveCartData(null, $_POST["custom"], 
								SP_PROCESSOR_PAYPAL);
						}

						if (!empty($cartData)) {
							$transid = strtoupper($_POST['txn_id']);
							$userid = null;
							
							if ($transid != "") {
								//		error_log("UserID = " . $userid . ", TXID = " . $transid);
								$errmsg = "";
								// use AddPayment rather than clearPendingTransaction because we may be getting recurring payments through.
								// AddPayment should replace existing transactions with their new status, instead of adding an extra one.
								
								$pending = null;
								$payType = SP_PAYTYPE_SALE;
								if ($_POST["payment_status"] == "Pending") {
									// A pending sale is a pending sale ...
									if (empty($_POST["auth_amount"])) {
										$pending = $_POST["txn_id"];
									} else {
										// ... unless auth_amount is set, in which case it's a complete authorisation.
										$payType = SP_PAYTYPE_DEFERRED;
									}
								}
								
								foreach ($cartData as $lineItem) {
									$pkgid = (empty($lineItem["pkgid"]) ? null : $lineItem["pkgid"]);
									$userid = $lineItem["userid"];
									
									$errmsg = "";
									$SPFunctions->addPayment($errmsg, $lineItem["userid"],
										$lineItem["minutes"],$lineItem["tickets"],
										$_POST["mc_gross"],
										$_POST["first_name"]." ".$_POST["last_name"],"IPN Tx#".$_POST["txn_id"],$pkgid,$pending,
										SP_PROCESSOR_PAYPAL,$_POST["txn_id"], null, $_POST["tax"],
										$payType,(isset($_POST["mc_fee"]) ? $_POST["mc_fee"] : 0));
									
									if ($errmsg != "") {
										$SPFunctions->paymentErrorEmail("IPN Error " . $errmsg, SP_PROCESSOR_PAYPAL);
									} elseif ($debugLog) {
										error_log("IPN Succeeded");
									}
								}

								if (is_null($pending) && !is_null($userid) && $payType == SP_PAYTYPE_SALE) {
									// Not a pending transaction, so delete the cart.
									$SPFunctions->deleteCartData($userid, $cartID, SP_PROCESSOR_PAYPAL);
								}
							}
						} else {
							$SPFunctions->paymentErrorEmail("Unable to decode cart #".$cartID, SP_PROCESSOR_PAYPAL);
						}
					} elseif ($_POST["payment_status"] == "Refunded" || $_POST["payment_status"] == "Reversed") {
						$SPFunctions->refundPayment($_POST['parent_txn_id'], $_POST['txn_id'], SP_PROCESSOR_PAYPAL, $_POST['mc_gross']);
					} else {
						$SPFunctions->paymentErrorEmail("Unexpected transaction state ".$_POST["payment_status"]." for ".
							$_POST["txn_type"]." #".$_POST["txn_id"], SP_PROCESSOR_PAYPAL);
					}
					break;
					/*
				case "masspay":			
					if ($_POST["payment_status"] == "Processed" || $_POST["payment_status"] == "Completed") {
						error_log("masspay begins");
						$now = time();
						
						for ($payId = 1; isset($_POST["masspay_txn_id_".$payId]); $payId++) {
							error_log("Paying Txn " . $_POST["masspay_txn_id_".$payId]." as ".$_POST["status_".$payId]);

							if (preg_match("/([0-9]+),([\w]+)/",$_POST["unique_id_".$payId], $matches)) {
								if (!$dbCore->query("update `".TABLE_PREFIX."sp_staff_paid` set `txid`='".$dbCore->escape($_POST["masspay_txn_id_".$payId])."'".
									($_POST["status_".$payId] == "Completed" ? ", paid_date=".$now : "").
									" where `spid`='".$dbCore->escape($matches[2])."' and `staffid`=".$matches[1]))
								{
									paymentErrorEmail("Unable to set MassPay to ".$_POST["status_".$payId]." for recipient ".$_POST["receiver_email_".$payId].
										", Tx#".$_POST["txn_id"], SP_PROCESSOR_PAYPAL);
								}
							} else {
								paymentErrorEmail("Unable to read MassPay UniqueID value ".$_POST["unique_id_".$payId]." for ".
									$_POST["txn_type"]." #".$_POST["txn_id"], SP_PROCESSOR_PAYPAL);
							}
						}
					} else {
						paymentErrorEmail("Unexpected transaction state ".$_POST["payment_status"]." for ".
							$_POST["txn_type"]." #".$_POST["txn_id"], SP_PROCESSOR_PAYPAL);
					}
					break;
					*/
				case "recurring_payment_profile_created":
					// Don't need to add the profile stuff, it *has* to already exist. Just handle
					// the initial payment here.
					
					if ($_POST["initial_payment_status"] == "Completed") {
						// Then we need to add a payment.
						// $_POST["initial_payment_txn_id"]
						// $_POST["amount"]
						// $_POST["rp_invoice_id"] == cart_id
						// $_POST["recurring_payment_id"] == swsp_cart_items.proc_txid
						$Rec = $this->_SWIFT->Database->QueryFetch("select i.*,d.* ".
							" from ".TABLE_PREFIX."sp_cart_items i, ".TABLE_PREFIX."sp_cart_defs d ".
							"where i.proc_txid='".$this->_SWIFT->Database->Escape($_POST["recurring_payment_id"]).
							"' and i.cid='".$this->_SWIFT->Database->Escape($_POST["rp_invoice_id"])."' and d.cid = i.cid");
						
						if (isset($Rec["proc_txid"])) {				
							$errmsg = "";
							$pkgid = (empty($Rec["pkgid"]) ? null : $Rec["pkgid"]);

							$SPFunctions->addPayment($errmsg, $Rec["userid"],
								$Rec["minutes"],$Rec["tickets"],
								$_POST["amount"],
								$_POST["first_name"]." ".$_POST["last_name"],"Tx#".$_POST["initial_payment_txn_id"],
								$pkgid,null,
								SP_PROCESSOR_PAYPAL,$_POST["initial_payment_txn_id"], null, 0,
								SP_PAYTYPE_SALE);
							
							if ($errmsg != "") {
								$SPFunctions->paymentErrorEmail("IPN Error " . $errmsg, SP_PROCESSOR_PAYPAL);
							} else {
								$this->_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_cart_items set ".
									"last_paid=".time()." where proc_txid='".$this->_SWIFT->Database->Escape($_POST["recurring_payment_id"]).
									"' and cid='".$this->_SWIFT->Database->Escape($_POST["rp_invoice_id"])."'");
								if ($debugLog) {
									error_log("IPN Succeeded for recurring payment ".$_POST["recurring_payment_id"]);
								}
							}
						} else {
							error_log("Can't find recurring cart for agreement ".$_POST["recurring_payment_id"]);
						}	
					}
					break;
			}
		} else {
			error_log("IPN Ignored : Status = ".$status." and Payment_Status = ".$_POST['payment_status']);
		}
	}
}

/*
Recurring payment IPN:
payment_cycle=Monthly
&txn_type=recurring_payment_profile_created
&last_name=User
&initial_payment_status=Completed
&next_payment_date=03:00:00 Apr 14, 2011 PDT
&residence_country=GB
&initial_payment_amount=6.00
&rp_invoice_id=4d7e725a368161.24449405
&currency_code=GBP
&time_created=12:54:16 Mar 14, 2011 PDT
&verify_sign=AW7.cb03qQCGnshAEj0dUucBy436AGJVKaWv-kj.q33dGZKyy3w5Kstw
&period_type= Regular
&payer_status=verified
&test_ipn=1
&tax=0.00
&payer_email=jimkei_1265111117_per@yahoo.co.uk
&first_name=Test
&receiver_email=jimkei_1225661997_biz@yahoo.co.uk
&payer_id=LMMUX89UYF8YE
&product_type=1
&initial_payment_txn_id=11886694DL355093V
&shipping=0.00
&amount_per_cycle=6.00
&profile_status=Active
&charset=windows-1252
&notify_version=3.0
&amount=6.00
&outstanding_balance=0.00
&recurring_payment_id=I-RJNN83KA7YP2
&product_name=This package contains seven minutes and two tickets.

*/

?>
