<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.Controller_Audit.php $, $Change: 3325 $, $DateTime: 2013/01/08 20:49:47 $ -->
<?php

class Controller_Audit extends Controller_admin
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

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptaudit'), 1,
			$SPFunctions->findAdminBar("SupportPay"));

		$SPFunctions->checkLicense();

		$this->View->RenderGrid();
		
		$this->UserInterface->Footer();

		return true;
	}
}

?>
