<?php

class Controller_UnpaidLive extends Controller_staff
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function ShowDeptDlg() {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_changedepart'), 
			$SPFunctions->findStaffMenu(),4);

		$this->View->RenderDepartmentDlg();
		
		$this->UserInterface->Footer();
		return true;
	}
	
	public function ChangeDept() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$errormessage = "";
		$infomessage = "";
		$newDept = intval($_POST["departmentid"]);
		$deptNames = $_SWIFT->Cache->Get('departmentcache');

		if (in_array($newDept,array_keys($deptNames))) {
			$_SWIFT->Database->Query("update ".TABLE_PREFIX."chatobjects set departmentid = ".$newDept.
				" where chatobjectid in (".buildIN($_POST["itemid"]).")");
			
			SWIFT::Info("SupportPay",$this->Language->Get('sp_chatsupdated'));
		} else {
			SWIFT::Error("SupportPay","Not a valid department.");
			$errormessage = "Not a valid department";
		}
		
		return empty($errormessage);
	}

	static public function PayMinutes($tktId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		
		if (is_array($tktId)) {
			$SPFunctions->payTickets(SP_PAYTYPE_LIVESUPPORT, $tktId, "minutes");
				
			return true;
		}
		
		return false;
	}
	
	static public function PayTickets($tktId) {
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($tktId)) {
			$SPFunctions->payTickets(SP_PAYTYPE_LIVESUPPORT, $tktId, "tickets");
			return true;
		}
		
		return false;
	}

	static public function CloseUnpaid($tktId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($tktId)) {
			foreach($tktId as $iid) {
				$sql = "delete from ".TABLE_PREFIX."sp_ticket_paid where ticketid = ".$iid.
					" and paytype = ".SP_PAYTYPE_LIVESUPPORT;
				if (!$_SWIFT->Database->Query($sql)) {
					SWIFT::Error("SupportPay", $_SWIFT->Database->FetchLastError());
				}
				
				$sql = "insert into ".TABLE_PREFIX."sp_ticket_paid ".
					"(ticketid,userid,paytype,paid_date,call_minutes,bill_minutes,minutes,tickets) ".
					"select chatobjectid,userid,".SP_PAYTYPE_LIVESUPPORT.",".time().
					",ceil((coalesce(sd.minuterate,1) * (staffpostactivity-dateline))/60),0,0,0 ".
					"from ".TABLE_PREFIX."chatobjects c left join ".TABLE_PREFIX."sp_departments sd ".
					"on (sd.departmentid = c.departmentid) where chatobjectid = ".intval($iid);
				if (!$_SWIFT->Database->Query($sql)) {
					SWIFT::Error("SupportPay", $_SWIFT->Database->FetchLastError());
				}
			}
			
			SWIFT::Info("SupportPay", "Live Support sessions closed unpaid.");
			return true;
		}
		
		return false;
	}

	public function ViewChat($chatId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$this->Language->Load('chathistory');
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('chconversation'), 
			$SPFunctions->findStaffMenu(),3);
			
		if ($_SWIFT->Staff->GetPermission('staff_lscanviewchat') == '0') {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderChat($chatId);
		}
		
		$this->UserInterface->Footer();

		return true;
	}
	
	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$this->Language->Load('chathistory');
		$this->Language->Load('staff_livechat');
		
		if (isset($_POST)) {
			foreach ($_POST as $pTitle => $pValue) {
				if (substr($pTitle,0,5) == 'mtime') {
					$chatId = intval(substr($pTitle,5));
					$chatMins = intval($pValue);
					
					// Add a ticket_paid stub if it's not there already.
					$SPFunctions->payTickets(SP_PAYTYPE_LIVESUPPORT,array($chatId),"any",0,true);
					
					if ($_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_ticket_paid set bill_minutes = ".$chatMins.
						" where ticketid = ".$chatId." and paytype = " . SP_PAYTYPE_LIVESUPPORT))
					{
						SWIFT::Info("SupportPay",$_SWIFT->Language->Get('sp_livesup_updated'));
					} else {
						SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
					}
				}
			}
		}
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptpagetitle'), 
			$SPFunctions->findStaffMenu(),3);

		$SPFunctions->checkLicense(!$SPFunctions->isInitialGrid());

		if (!$SPFunctions->checkPerms("sp_canpaylive")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			if (!$SPFunctions->IsModuleRegistered("LIVECHAT")) {
				$this->UserInterface->DisplayError("SupportPay","You do not have Live Support installed.");
			} else {
				$this->View->RenderGrid();
			}
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
