<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.Controller_Updates.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Updates extends Controller_admin
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}
	
	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptupdates'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		
		// Check is administrator
		
		$SPFunctions->checkLicense();

		$this->View->Render();
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
