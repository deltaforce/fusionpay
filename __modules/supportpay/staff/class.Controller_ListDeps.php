<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.Controller_ListDeps.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_ListDeps extends Controller_staff
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function AddDep($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_adddep'), 
			$SPFunctions->findStaffMenu(),3);
		
		if (!$SPFunctions->checkPerms("sp_cansetmgr")) {
			SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderAddDep($userid);
		}
		
		$this->UserInterface->Footer();

		return true;
	}
	
	public function DoAddDep($userid) {
		if (isset($_POST["containertaginput_sp_dep_addemail"])) {
			if (is_array($_POST["containertaginput_sp_dep_addemail"])) {
				$_SWIFT = SWIFT::GetInstance();
				
				$emails = buildIN($_POST["containertaginput_sp_dep_addemail"]);
				if ($_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users set payerid = ".
					$userid." where userid in (select distinct linktypeid from ".TABLE_PREFIX.
					"useremails where linktype=1 and email in (".$emails."))".
					" and userid != ".$userid))
				{
					SWIFT::Info("SupportPay", $_SWIFT->Language->Get("sp_deps_added"));
				} else {
					SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
				}		
			} else {
				SWIFT::Error("SupportPay","Invalid Parameters");
			}
		} else {
			SWIFT::Error("SupportPay","Invalid Parameters");
		}
		
		$this->Router->SetAction("Main");
		return $this->Main($userid);
	}
	
	static public function DelDep($delete) {
		$_SWIFT = SWIFT::GetInstance();
		$res = false;
		
		if (is_array($delete)) {
			if ($_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users set payerid=null where userid in (".
				buildIN($delete).")"))
			{
				// Hmm - these don't get displayed...
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_am_depremoved_staff"));
			} else {
				SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
			}
		} else {
			SWIFT::Error("SupportPay","Invalid parameters.");
		}
		
		return $res;
	}
	
	public function Main($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_alpagetitle'), 
			$SPFunctions->findStaffMenu(),4);

		$SPFunctions->checkLicense(!$SPFunctions->isInitialGrid());
		
		if (is_numeric($userid)) {
			if ($SPFunctions->checkPerms("sp_cansetmgr")) {
				if ($sp_license["allow_accounts"]) {
					$this->View->RenderGrid($userid);
				} else {
					$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_noacm"));
				}
			} else {
				$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
			}
		} else {
			$this->UserInterface->DisplayError("SupportPay",$this->Language->Get('sp_nouser'));
		}

		$this->UserInterface->Footer();
		return true;
	}
}

?>
