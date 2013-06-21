<?php

/* This is the true IPN controller for Authorize.net. */

class Controller_AuthNet extends SWIFT_Controller
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
		global $SPFunctions;
		$debugLog = $this->_SWIFT->Settings->getKey("settings","sp_debug");

		if ($debugLog) {
			foreach ($_POST as $pName => $pItem) {
				if (!empty($pItem)) {
					error_log($pName . " => " . $pItem);
				}
			}
		}

		switch ($_POST["x_response_code"]) {
			case 1:	// COMPLETED
			case 4:	// PENDING
				// Approve payment $_POST["x_trans_id"];  $_POST["x_cust_id"];
				if (isset($_POST["txn_type"]) && $_POST["txn_type"] == "recurring_payment") {	// Not implemented yet!!!
					$cartID = $_POST["rp_invoice_id"];
					$cartData = $SPFunctions->retrieveCartData(null, $_POST["rp_invoice_id"], 
						SP_PROCESSOR_AUTHORIZE, $_POST["recurring_payment_id"]);
				} else {
					$cartID = $_POST["x_invoice_num"];
					$cartData = $SPFunctions->retrieveCartData(null, $cartID, SP_PROCESSOR_AUTHORIZE);
				}
				
				if (!empty($cartData)) {
					$transid = strtoupper($_POST['x_trans_id']);
					
					if ($transid != "") {
						//		error_log("UserID = " . $userid . ", TXID = " . $transid);
						$errmsg = "";
						// use AddPayment rather than clearPendingTransaction because we may be getting recurring payments through.
						// AddPayment should replace existing transactions with their new status, instead of adding an extra one.
						
						$pending = null; $deferred = null; $userid = null;
						if ($_POST["x_response_code"] == 4) {
							// A pending sale is a pending sale ...
							if (empty($_POST["auth_amount"])) {
								$pending = $_POST["x_invoice_num"];
							} else {
								// ... unless auth_amount is set, in which case it's a complete authorisation.
								$deferred = true;
							}
						}
						
						$payType = (empty($_POST["auth_amount"]) ? SP_PAYTYPE_SALE : SP_PAYTYPE_DEFERRED);

						foreach ($cartData as $lineItem) {
							$pkgid = (empty($lineItem["pkgid"]) ? null : $lineItem["pkgid"]);
							
							$errmsg = "";
							$userid = $lineItem["userid"];
							
							// TODO: A refund has x_type == 'credit'
							if ($_POST['x_type'] == 'void') {
								$SPFunctions->voidPendingTransaction($transid, SP_PROCESSOR_AUTHORIZE);
							} elseif ($_POST["x_type"] == "credit") {
								$SPFunctions->refundPayment($transid, $transid, SP_PROCESSOR_AUTHORIZE, $_POST["x_amount"]);
							} else {
								if ($_POST["x_type"] == "auth_only") {
									// Authorised, capture later.
									$deferred = true;
									$payType = SP_PAYTYPE_DEFERRED;
								}

								$SPFunctions->addPayment($errmsg, $lineItem["userid"],
									$lineItem["minutes"],$lineItem["tickets"],
									$_POST["x_amount"],
									$_POST["x_first_name"]." ".$_POST["x_last_name"],"Tx#".$transid,$pkgid,$pending,
									SP_PROCESSOR_AUTHORIZE,$transid, null, $_POST["x_tax"],
									$payType,0 /*$_POST["mc_fee"] */);
							}
							
							if ($errmsg != "") {
								$SPFunctions->paymentErrorEmail("IPN Error " . $errmsg, SP_PROCESSOR_AUTHORIZE);
							} elseif ($debugLog) {
								error_log("IPN Succeeded");
							}
						}
						
						if (is_null($pending) && !is_null($userid) && $payType == SP_PAYTYPE_SALE) {
							// Not a pending transaction, so delete the cart.
							$SPFunctions->deleteCartData($userid, $cartID, SP_PROCESSOR_AUTHORIZE);
						}
					}
				} else {
					$SPFunctions->paymentErrorEmail("Unable to decode cart #".$cartID, SP_PROCESSOR_AUTHORIZE);
				}

				break;
		}
	}
}

/*

APPROVED response to a normal sale.
[21-Jul-2012 12:35:57] In AuthNet IPN
[21-Jul-2012 11:35:57] In AuthNet callback controller
[21-Jul-2012 11:35:57] x_response_code => 1
[21-Jul-2012 11:35:57] x_response_reason_code => 1
[21-Jul-2012 11:35:57] x_response_reason_text => This transaction has been approved.
[21-Jul-2012 11:35:57] x_avs_code => P
[21-Jul-2012 11:35:57] x_auth_code => Z5H2WS
[21-Jul-2012 11:35:57] x_trans_id => 2173862439
[21-Jul-2012 11:35:57] x_method => CC
[21-Jul-2012 11:35:57] x_card_type => Visa
[21-Jul-2012 11:35:57] x_account_number => XXXX0027
[21-Jul-2012 11:35:57] x_first_name => 
[21-Jul-2012 11:35:57] x_last_name => 
[21-Jul-2012 11:35:57] x_company => 
[21-Jul-2012 11:35:57] x_address => 
[21-Jul-2012 11:35:57] x_city => 
[21-Jul-2012 11:35:57] x_state => 
[21-Jul-2012 11:35:57] x_zip => 
[21-Jul-2012 11:35:57] x_country => 
[21-Jul-2012 11:35:57] x_phone => 
[21-Jul-2012 11:35:57] x_fax => 
[21-Jul-2012 11:35:57] x_email => 
[21-Jul-2012 11:35:57] x_invoice_num => 
[21-Jul-2012 11:35:57] x_description => 
[21-Jul-2012 11:35:57] x_type => prior_auth_capture
[21-Jul-2012 11:35:57] x_cust_id => 304
[21-Jul-2012 11:35:57] x_ship_to_first_name => 
[21-Jul-2012 11:35:57] x_ship_to_last_name => 
[21-Jul-2012 11:35:57] x_ship_to_company => 
[21-Jul-2012 11:35:57] x_ship_to_address => 
[21-Jul-2012 11:35:57] x_ship_to_city => 
[21-Jul-2012 11:35:57] x_ship_to_state => 
[21-Jul-2012 11:35:57] x_ship_to_zip => 
[21-Jul-2012 11:35:57] x_ship_to_country => 
[21-Jul-2012 11:35:57] x_amount => 31.35
[21-Jul-2012 11:35:57] x_tax => 0.00
[21-Jul-2012 11:35:57] x_duty => 0.00
[21-Jul-2012 11:35:57] x_freight => 0.00
[21-Jul-2012 11:35:57] x_tax_exempt => FALSE
[21-Jul-2012 11:35:57] x_po_num => 
[21-Jul-2012 11:35:57] x_MD5_Hash => DC62D81A9174BD6E265E40E0898F9FE4
[21-Jul-2012 11:35:57] x_cvv2_resp_code => 
[21-Jul-2012 11:35:57] x_cavv_response => 
[21-Jul-2012 11:35:57] x_test_request => false
*/

?>
