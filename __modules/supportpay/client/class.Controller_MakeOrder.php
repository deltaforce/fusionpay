<?php

class Controller_MakeOrder extends Controller_client
{
	private $SWIFT4;
	
	public function __construct()
	{
		$this->SWIFT4 = SWIFT::GetInstance();
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Index($cartID = null)
	{
		global $SPFunctions;
		
		session_start();

		$userid = $this->SWIFT4->User->GetUserID();
		$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
		$cartData = null;
		
		// Must prefer session cart_id, old template returns username as parameter instead.
		if (isset($_SESSION["cart_id"])) {
			$cartID = $_SESSION["cart_id"];
		}
			
		if (!empty($cartID)) {
			$cartData = $SPFunctions->retrieveCartData($userid, $cartID, $gateway);
		}
		
		if (!empty($cartData)) {
			if ($gateway == SP_PROCESSOR_PAYPAL) {
				include SWIFT_MODULESDIRECTORY."/supportpay/client/paypal/makeorder.php";
			} elseif ($gateway == SP_PROCESSOR_WORLDPAY) {
				include SWIFT_MODULESDIRECTORY."/supportpay/client/worldpay/complete.php";	
			} elseif ($gateway == SP_PROCESSOR_AUTHORIZE) {
				include SWIFT_MODULESDIRECTORY."/supportpay/client/authnet/complete.php";
			}

			// Don't delete the cart here, it may well be needed by IPN.
			
			if (isset($_SESSION["cart_id"])) {
				unset($_SESSION["cart_id"]);
			}
		} else {
			SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_no_purchase_found"));
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
		}
		return true;
	}
};

?>
