<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.Controller_Settings.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Settings extends Controller_admin
{
	public function __construct()
	{
		parent::__construct();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	private function hexToStr($hex)
	{
		$string='';
		for ($i=0; $i < strlen($hex)-1; $i+=2)
		{
			$string .= chr(hexdec($hex[$i].$hex[$i+1]));
		}
		return $string;
	}

	public function TestPayPal($isSandbox, $userid="", $pass="", $key="") {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_testsettings'), 
			$SPFunctions->findAdminBar("Settings"),1);

		$this->View->RenderPayPalDlg($isSandbox, $this->hexToStr($userid), $this->hexToStr($pass), $this->hexToStr($key));
		
		$this->UserInterface->Footer();
		return true;
	}
	
	public function TestWHMCS($url="", $userid="", $pass="", $httpuser="", $httppass="") {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_testsettings'), 
			$SPFunctions->findAdminBar("Settings"),1);

		$this->View->RenderWHMCSDlg($this->hexToStr($url),$this->hexToStr($userid), $this->hexToStr($pass),
			$this->hexToStr($httpuser), $this->hexToStr($httppass));
		
		$this->UserInterface->Footer();
		return true;
	}

}
?>