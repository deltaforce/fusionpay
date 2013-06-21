<?php

// See http://www.authorize.net/files/ErrorGenerationGuide.pdf
// N.B. MUST set the "Silent Post URL" to be able to handle deferred, reversed, pre-auth and recurring payments.

// Calculate the overall cost.
$cost = 0; $tax = 0;
foreach ($cartData as &$lineItem) {
	$cost += $lineItem["cost"] * $lineItem["itemcount"];
	$tax += $lineItem["tax"] * $lineItem["itemcount"];
}
$this->SWIFT4->Template->Assign("payamount",$cost+$tax);

// No support for recurring payments in Authorize.net .

// Translate the POST into something that looks like PayPal's, for the template.
$SPFunctions->mapAuthorizeNetIPN($_POST);
$this->SWIFT4->Template->Assign("paydetails",$_POST);

// Add payment details where necessary.
$pending = null;
switch ($_POST["x_response_code"]) {
	case 4:	// PENDING
		$pending = $_POST["x_trans_id"];
		// Mark this cart as pending.
		$sql = "update ".TABLE_PREFIX."sp_cart_defs set ctype=".SP_CTYPE_PENDING.
			" where cid='".$this->SWIFT4->Database->Escape($_POST["x_invoice_num"])."'";
		$this->SWIFT4->Database->Execute($sql);
		// NO break
	case 1:	// COMPLETED
		foreach ($cartData as &$lineItem) {
			if (empty($lineItem["recur_period"])) {
				
				$pkgid = (empty($lineItem["pkgid"]) ? null : $lineItem["pkgid"]);
				
				$SPFunctions->addPayment($errmsg, $this->SWIFT4->User->GetUserID(),
					$lineItem["minutes"],$lineItem["tickets"],
					($lineItem["cost"]+$lineItem["tax"]) * $lineItem["itemcount"],
					$_POST["x_first_name"]." ".$_POST["x_last_name"],
					"Tx#".$_POST["x_trans_id"],
					$pkgid,$pending,SP_PROCESSOR_AUTHORIZE,
					$_POST["x_trans_id"],
					null, ($lineItem["tax"] * $lineItem["itemcount"]),
					SP_PAYTYPE_SALE);

				if (!empty($errmsg)) {
					SWIFT::Error("SupportPay", $errmsg);
				}
			}
		}

		break;
	case 2:	// REFUSED
		// Nothing to do, just display the results.
		break;
	case 3:	// ERROR
		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
		// TODO: Check message "x_response_reason_text"
		$errormessage = $SPUserFuncs->GenPaymentErrorPage($_POST);
		SWIFT::Error("SupportPay",$errormessage);
		$this->SWIFT4->UserInterface->Header('sp_payerror_title');
		$this->SWIFT4->UserInterface->Footer();
		exit;
}

$this->SWIFT4->Template->Assign("transdetails","<b>Transaction ID : </b>" . 
	$_POST["x_trans_id"]);

$this->UserInterface->Header('sp_uw_master');
$SPFunctions->assignSectionTitle("Payment ".$_POST["PAYMENTSTATUS"]);
$this->SWIFT4->Template->Render("sp_header");
$this->SWIFT4->Template->Render("sp_dopay");
$this->SWIFT4->Template->Render("sp_footer");
$this->UserInterface->Footer();

?>
