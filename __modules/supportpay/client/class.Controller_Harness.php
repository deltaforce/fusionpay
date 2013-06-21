<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_Harness.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Harness extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}

	public function Index()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;

		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle("Client Page Test Harness");
		$_SWIFT->Template->Render("sp_header");
		echo '<form action="'.SWIFT::Get('basename').'/supportpay/Harness/Visit" method="POST">';
		
		echo '<fieldset class="spfieldset" style="width: 90%"><legend>PayPal</legend>';
		echo '<input type="submit" name="pprevok" class="rebuttonwide2" value="Review Good"/>';
		echo '<input type="submit" name="pprevbad" class="rebuttonwide2" value="Review Fail"/>';
		echo '<input type="submit" name="pppayok" class="rebuttonwide2" value="Pay Good"/>';
		echo '<input type="submit" name="pppaybad" class="rebuttonwide2" value="Pay Fail"/>';
		echo '<input type="submit" name="pppaypending" class="rebuttonwide2" value="Pay Pending"/>';
		echo '<input type="submit" name="pppayauth" class="rebuttonwide2" value="Pay Pending"/>';
		echo '</fieldset>';
		
		echo '</form>';
		$_SWIFT->Template->Render("sp_footer");
		$this->UserInterface->Footer();
		
		$SPUserFuncs->StoreSession();
	}
	
	public function Visit() {
		global $SPUserFuncs,$SPFunctions;
		$_SWIFT = SWIFT::GetInstance();

		$arg = array_keys($_POST);
		$url = "";

		$itemList = array();
		$itemList[] = array ( "name" => $_SWIFT->Settings->getKey("settings","general_companyname")." ".
			"Item One","desc" => "", "rowcost" => 13.26, "tax" => 0.07, "itemtype" => "minutes",
			"minutes" => 13, "tickets" => 0, "pkgid" => null, "itemcount" => 13, "cost" => 0.95);
		$itemList[] = array ( "name" => $_SWIFT->Settings->getKey("settings","general_companyname")." ".
			"Item Two","desc" => "", "rowcost" => 64.2, "tax" => .71, "itemtype" => "minutes",
			"minutes" => 0, "tickets" => 6, "pkgid" => null, "itemcount" => 6, "cost" => 9.99);
				
		switch ($arg[0]) {
			case "pprevok":	// PayPal payment review
				session_start();
				$SPUserFuncs->StoreSession();
				
				$_SESSION["cart_id"] = $SPFunctions->encodeCartData($_SWIFT->User->GetUserID(),
					$itemList,SP_PROCESSOR_PAYPAL);
				
				$_SESSION['Payment_Amount'] = 34.68;
				$_SESSION['currencyCodeType'] = "GBP";

				$url = SWIFT::Get('basename')."/supportpay/ReviewOrder/Index/".htmlspecialchars(session_id())."&token=harnessgood";
				break;
			case "pprevbad":	// PayPal payment review
				session_start();
				$SPUserFuncs->StoreSession();

				$_SESSION["cart_id"] = $SPFunctions->encodeCartData($_SWIFT->User->GetUserID(),
					$itemList,SP_PROCESSOR_PAYPAL);

				$resArray = array("ACK" => "FAILURE");
				$resArray = array("EMAIL" => "bob@home.com");
				$resArray = array("FIRSTNAME" => "Bob");
				$resArray = array("LASTNAME" => "Hope");
				$url = SWIFT::Get('basename')."/supportpay/ReviewOrder/Index/".htmlspecialchars(session_id())."&token=harnessbad";
				break;
			case "pppayok":	// PayPal make payment
				session_start();
				$SPUserFuncs->StoreSession();

				$_SESSION["cart_id"] = $SPFunctions->encodeCartData($_SWIFT->User->GetUserID(),
					$itemList,SP_PROCESSOR_PAYPAL);

				$_SESSION['token'] = "harnessgood";
				$_SESSION['Payment_Amount'] = 56.78;
				$_SESSION['Tax_Amount'] = 2.46;
				$_SESSION['currencyCodeType'] = "GBP";
				$_SESSION['paymentType'] = 'Sale';
				$_SESSION['payer_id'] = 'payer@donations.net';
				$url = SWIFT::Get('basename')."/supportpay/MakeOrder/Index/".htmlspecialchars(session_id())."/";
				break;

			case "pppaybad":	// PayPal make payment - bad
				session_start();
				$SPUserFuncs->StoreSession();
				$_SESSION['token'] = "harnessbad";

				$_SESSION["cart_id"] = $SPFunctions->encodeCartData($_SWIFT->User->GetUserID(),
					$itemList,SP_PROCESSOR_PAYPAL);

				$_SESSION['Payment_Amount'] = 56.78;
				$_SESSION['Tax_Amount'] = 2.46;
				$_SESSION['currencyCodeType'] = "GBP";
				$_SESSION['paymentType'] = 'Sale';
				$_SESSION['payer_id'] = 'payer@donations.net';
				$url = SWIFT::Get('basename')."/supportpay/MakeOrder/Index/".htmlspecialchars(session_id());
				break;

			case "pppaypending":	// PayPal make payment - pending
				session_start();
				$SPUserFuncs->StoreSession();
				$_SESSION['token'] = "harnesspending";
				$_SESSION['buyMinutes'] = 12;
				$_SESSION['buyTickets'] = 34;
				$_SESSION['Payment_Amount'] = 56.78;
				$_SESSION['Tax_Amount'] = 2.46;
				$_SESSION['currencyCodeType'] = "GBP";
				$_SESSION['paymentType'] = 'Sale';
				$_SESSION['payer_id'] = 'payer@donations.net';
				$url = SWIFT::Get('basename')."/supportpay/MakeOrder/Index/".htmlspecialchars(session_id())."/";
				break;
			case "pppayauth":	// PayPal make payment - authorised
				session_start();
				$SPUserFuncs->StoreSession();
				$_SESSION['token'] = "harnessauth";

				$_SESSION["cart_id"] = $SPFunctions->encodeCartData($_SWIFT->User->GetUserID(),
					$itemList,SP_PROCESSOR_PAYPAL);

				$_SESSION['Payment_Amount'] = 56.78;
				$_SESSION['Tax_Amount'] = 2.46;
				$_SESSION['currencyCodeType'] = "GBP";
				$_SESSION['paymentType'] = 'Sale';
				$_SESSION['payer_id'] = 'payer@donations.net';
				$url = SWIFT::Get('basename')."/supportpay/MakeOrder/Index/".htmlspecialchars(session_id());
				break;
		}
		
		if (!empty($url)) {
			header("Location: ".$url);
		}
	}
}

?>
