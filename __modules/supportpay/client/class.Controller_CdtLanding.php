<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_CdtLanding.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_CdtLanding extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Main()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;

		$SPFunctions->QuitIfGuest('sp_uw_master');

		$mins = $tkts = "Unknown";
		if (!empty($_SWIFT->User)) {
			$Record = $SPFunctions->getUserCredit($_SWIFT->User->GetUserID(),true);
		} else {
			$Record = array("minutes" => 0, "tickets" => 0, "discount" => 0, "acctmgr" => false);
		}
		
		$this->Template->Assign("has_acctmgr",$Record["acctmgr"]);
		$this->Template->Assign("discount",$Record["discount"]);
		if (!empty($Record)) {
			$mins = $Record["minutes"];
			$tkts = $Record["tickets"];
		}
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->Template->Assign("dominutes",true);
			$this->Template->Assign("minutescdt",$mins);
		}
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->Template->Assign("dotickets",true);
			$this->Template->Assign("ticketscdt",$tkts);
		}
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($SPUserFuncs->MakeCreditHeader($_SWIFT->Language->Get("sp_ptcdtlanding")));
		$this->Template->Render("sp_header");
		$this->Template->Render("sp_cdtlanding");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
	
}