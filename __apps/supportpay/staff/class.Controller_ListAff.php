<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.Controller_ListAff.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_ListAff extends Controller_staff
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function AddAff($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_aff_addnew'), 
			$SPFunctions->findStaffMenu(),3);
			
		if (!$SPFunctions->checkPerms("sp_canlistaff")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderAddAff($userid);
		}
		
		$this->UserInterface->Footer();

		return true;
	}
	
	public function DoAddAff($userid) {
		if (isset($_POST["containertaginput_sp_aff_addemail"])) {
			if (is_array($_POST["containertaginput_sp_aff_addemail"])) {
				$_SWIFT = SWIFT::GetInstance();
				
				$emails = buildIN($_POST["containertaginput_sp_aff_addemail"]);
				$guid = $_SWIFT->Database->QueryFetch("select guid from ".TABLE_PREFIX."sp_users ".
					"where userid=".intval($userid));
				if (!empty($guid)) {
					if ($_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users set affiliate = '".
						$_SWIFT->Database->Escape($guid["guid"])."' where userid in (select distinct linktypeid from ".TABLE_PREFIX.
						"useremails where linktype=1 and email in (".$emails."))"))
					{
						SWIFT::Info("SupportPay", $_SWIFT->Language->Get("sp_affs_added"));
					} else {
						SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
					}		
				} else {
					SWIFT::Error("SupportPay","Affiliate GUID is empty, can't assign.");
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
	
	static public function DelAff($delete) {
		$_SWIFT = SWIFT::GetInstance();
		$res = false;
		
		if (is_array($delete)) {
			if ($_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users set affiliate=null where userid in (".
				buildIN($delete).")"))
			{
				// Hmm - these don't get displayed...
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_affs_removed"));
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
			if ($SPFunctions->checkPerms("sp_canlistaff")) {
				if ($sp_license["allow_affiliate"]) {
					$this->View->RenderGrid($userid);
				} else {
					$this->UserInterface->Error("SupportPay",$_SWIFT->Language->Get("sp_noaffiliate"));
				}
			} else {
				$this->UserInterface->Error("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
			}
		} else {
			$this->UserInterface->Error("SupportPay",$this->Language->Get('sp_nouser'));
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
