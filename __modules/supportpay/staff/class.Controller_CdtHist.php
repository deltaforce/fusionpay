<?php

class Controller_CdtHist extends Controller_staff
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function Recalc($userid) {
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$SPFunctions->checkLicense(true);
		$SPFunctions->fetchVMSales($userid);
		$SPFunctions->updateUserCredits($userid, $errmsg);
		
		$this->Router->SetAction("Main");
		return $this->Main($userid);
	}

	public function Payments($ticketid) {
		$this->View->RenderPayments($ticketid);
	}
		
	public function Main($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_chpagetitle'), 
			$SPFunctions->findStaffMenu(),4);

		if (count($_POST) == 0) {
			$SPFunctions->checkLicense();
			$SPFunctions->fetchVMSales();
		} else {
			$SPFunctions->checkLicense(true);
		}
		
		if (is_numeric($userid)) {
			$this->View->RenderGrid($userid);
		} else {
			$this->UserInterface->Error("SupportPay",$this->Language->Get('sp_nouser'));
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
