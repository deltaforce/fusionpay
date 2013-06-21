<?php

class Controller_Unpaid extends Controller_staff
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function PayMinutes($tktId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		
		if (is_array($tktId)) {
			$SPFunctions->payTickets(SP_PAYTYPE_TICKET, $tktId, "minutes");
			
			SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_payment_taken"));
			return true;
		}
		
		return false;
	}
	
	static public function PayTickets($tktId) {
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($tktId)) {
			$SPFunctions->payTickets(SP_PAYTYPE_TICKET, $tktId, "tickets");
			
			SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_payment_taken"));
			return true;
		}
		
		return false;
	}

	static public function CloseUnpaid($tktId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($tktId)) {
			foreach($tktId as $iid) {
				$sql = "delete from ".TABLE_PREFIX."sp_ticket_paid where ticketid = ".$iid.
					" and paytype = ".SP_PAYTYPE_TICKET;
				if (!$_SWIFT->Database->Query($sql)) {
					SWIFT::Error("SupportPay", $_SWIFT->Database->FetchLastError());
				}
				
				$sql = "insert into ".TABLE_PREFIX."sp_ticket_paid ".
					"(ticketid,userid,paytype,paid_date,call_minutes,bill_minutes,minutes,tickets) ".
					"select t.ticketid,userid,".SP_PAYTYPE_TICKET.",".time().",ceil(sum(ttt.timebillable * coalesce(sd.minuterate,1))/60),0,0,0 ".
					"from ".TABLE_PREFIX."tickets t ".
					"left join ".TABLE_PREFIX."sp_departments sd on (sd.departmentid = t.departmentid) ".
					", ".TABLE_PREFIX."tickettimetracks ttt ".
					"where t.ticketid = ttt.ticketid and t.ticketid = ".intval($iid)." group by t.ticketid, userid";
				if (!$_SWIFT->Database->Query($sql)) {
					SWIFT::Error("SupportPay", $_SWIFT->Database->FetchLastError());
				}
			}
			
			SWIFT::Info("SupportPay", "Tickets closed unpaid.");
			return true;
		}
		
		return false;
	}

	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		// Don't call the header here. It outputs text before any MassAction gets
		// called, which cocks up PayPal headers.

		if (!$SPFunctions->checkPerms("sp_canpaytkts")) {
			$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptpagetitle'), 
				$SPFunctions->findStaffMenu(),1);
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
			$this->UserInterface->Footer();
		} else {
			$this->View->RenderGrid();
		}
		
		return true;
	}
}

?>
