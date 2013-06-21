<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.Controller_Awaiting.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Awaiting extends Controller_staff
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
		global $SPFunctions, $sp_license;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$this->Language->Load('staff_tickets');

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_awpagetitle'), 
			$SPFunctions->findStaffMenu(),1);

		$SPFunctions->checkLicense(!$SPFunctions->isInitialGrid());

		if (!$SPFunctions->checkPerms("sp_cananswertkts")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderGrid();
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
