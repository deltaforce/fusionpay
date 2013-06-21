<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.Controller_OrgCredit.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_OrgCredit extends Controller_staff
{
	public function __construct() {
		parent::__construct();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}
	
	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$this->Language->Load('admin_users');

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ompagetitle'), 
			$SPFunctions->findStaffMenu(),5);

		if (count($_POST) == 0) {
			$SPFunctions->checkLicense();
			$SPFunctions->fetchVMSales();
		} else {
			$SPFunctions->checkLicense(true);
		}

		if (!$SPFunctions->checkPerms("sp_cansetmgr")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} elseif (!$sp_license["allow_accounts"]) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_noacm"));
		} else {
			$this->View->RenderGrid();
		}
		
		$this->UserInterface->Footer();
		return true;
	}
	
	public function ViewMembers($orgId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$this->Language->Load('admin_users');

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ompagetitle'), 
			$SPFunctions->findStaffMenu(),5);

		$SPFunctions->checkLicense(count($_POST) == 0);

		if (!$SPFunctions->checkPerms("sp_cansetmgr")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} elseif (! $sp_license["allow_accounts"]) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_noacm"));
		} else {
			$this->View->RenderMemberGrid($orgId);
		}
		
		$this->UserInterface->Footer();
		return true;
	}
	
	public function MakeMgr($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_set_acctmgr'), 
			$SPFunctions->findStaffMenu(),5);
		
		$this->View->RenderMakeMgr($userid);

		$this->UserInterface->Footer();

		return true;
	}

	public function DoSetMgr() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		if (isset($_POST["userid"])) {
			$userid = intval($_POST["userid"]);
			$errmsg = "";
			
			$UserRecord = $_SWIFT->Database->queryFetch("select fullname, userorganizationid from ".TABLE_PREFIX."users ".
				"where userid = ".$userid);
			$orgId = $UserRecord["userorganizationid"];
			
			/* TODO: 
			** - Set this account manager
			** - Remove all others for this org
			** - Set payerid for all members of this org except the manager
			** - Transfer credit if required
			*/
			
			$_SWIFT->Database->Query("select u.userid, u.fullname from ".TABLE_PREFIX."users u,".
				TABLE_PREFIX."sp_users spu where u.userid = spu.userid and u.userorganizationid = ".$orgId.
				" and spu.acctmgr = 1",2);
			while ($_SWIFT->Database->NextRecord(2)) {
				$infomessage = "Manager status removed from ".$_SWIFT->Database->Record2["fullname"];
				$SPFunctions->addAudit($infomessage);

				if ($_POST["txcredit"]) {
					// Transfer the credit. Safe to use this now because the "payerid"
					// is NULL, we only get personal credit.
					$uCredit = $SPFunctions->getUserCredit($_SWIFT->Database->Record2["userid"]);
					$uCredit["minutes"] -= $uCredit["overdraft"];
					if ($uCredit["minutes"] > 0 || $uCredit["tickets"] > 0) {
						// TODO: Check what transaction IDs are used in addPayment, this seems to get overwritten.
						$SPFunctions->addPayment($errmsg, $_SWIFT->Database->Record2["userid"], 
							-$uCredit["minutes"], -$uCredit["tickets"], 
							0, "SupportPay", 'Organization Manager Transfer to '.$UserRecord["fullname"], null, null);

						$SPFunctions->addPayment($errmsg, $userid, $uCredit["minutes"], $uCredit["tickets"], 
							0, "SupportPay", 'Organization Manager Transfer from '.$_SWIFT->Database->Record2["fullname"], null, null);
					}
				}
			}

			$SPFunctions->checkLicense(true);
			$SPFunctions->checkUserExists($userid, $errmsg);
			$_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users ".
				"set acctmgr = 0, payerid = ".$userid." where userid in (".
				"select userid from ".TABLE_PREFIX."users where userorganizationid = ".$orgId.")");

			$_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users ".
				"set acctmgr = 1, payerid = null where userid = ".$userid);
			
			// N.B. Don't update the swusers.userrole field, which is also set for Manager.
			// This will allow the one 'Organization' account to exist for billing, but
			// multiple Managers to exist for viewing tickets.
			if ($_POST["makemgr"]) {
				if (method_exists('SWIFT_Loader','LoadModel')) {
					SWIFT_Loader::LoadModel('User:UserOrganization');
				} else {
					SWIFT_Loader::LoadLibrary('User:UserOrganization');
				}
				$_SWIFT->Database->Execute("update ".TABLE_PREFIX."users ".
					"set userrole = ".SWIFT_User::ROLE_MANAGER." where userid = ".$userid);
			}

			$infomessage = "Manager status added to ".$UserRecord["fullname"];
			$SPFunctions->addAudit($infomessage);
			
			SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_acmgr_changed"));
		} else {
			SWIFT::Error("SupportPay", "Invalid data");
		}
		
		$this->Router->SetAction("ViewMembers/".$orgId);
		return $this->ViewMembers($orgId);
	}
}

?>
