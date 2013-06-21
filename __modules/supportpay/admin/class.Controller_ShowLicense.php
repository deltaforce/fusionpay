<?php

class Controller_ShowLicense extends Controller_admin
{
	private $_SWIFT;
	
	public function __construct() {
		parent::__construct();
		
		$this->_SWIFT = SWIFT::GetInstance();
		
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}
	
	public function Check() {		
		$this->Router->SetAction("Main");
		return $this->Main($_POST["license"]);
	}
		
	public function Update() {
		if (!empty($_POST["license"])) {
			$this->_SWIFT->Settings->UpdateKey('settings', 'sp_license', $_POST["license"]);
			SWIFT::Info("SupportPay",$this->_SWIFT->Language->Get('sp_licenseupdated'));
		} else {
			SWIFT::Error("SupportPay", $this->_SWIFT->Language->Get('sp_nolicensegiven'));
		}
		
		$this->Router->SetAction("Main");
		return $this->Main();
	}
	
	public function Agree() {
		if ($_POST["agreed"]) {
			$this->_SWIFT->Settings->UpdateKey('settings', 'sp_have_agreed', true);
		} else {
			SWIFT::Error("SupportPay",$this->_SWIFT->Language->Get('sp_agree_details'));
		}

		$this->Router->SetAction("Main");
		return $this->Main();		
	}
	
	public function Main($testLicense = null) {
//		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptlicmaint'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
			
		// Check is administrator
		
		if ($this->_SWIFT->Settings->getKey("settings","sp_have_agreed")) {
			$SPFunctions->checkLicense();

			$this->View->Render($testLicense);
		} else {
			// Haven't agreed to the license terms yet.
			$this->View->RenderAgreement();
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
