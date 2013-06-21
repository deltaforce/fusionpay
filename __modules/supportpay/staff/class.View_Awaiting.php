<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.View_Awaiting.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Awaiting extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function _gridFields($record)
	{
		global $SPFunctions, $sp_currencylist, $sp_license;
		$_SWIFT = SWIFT::GetInstance();

		$record["dateline"] = date(SWIFT_Date::GetCalendarDateFormat().' H:i',$record["dateline"]);
		
		// Link to the user
		$record["fullname"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$record["fullname"],
			'loadViewportData("/Base/User/Edit/'.$record["userid"].'");');
		
		// Link to the ticket details	
		$record["subject"] = $SPFunctions->visibleLink(null,
			$_SWIFT->Language->Get("rnticketlist"),$record["subject"],
			'loadViewportData("/Tickets/Ticket/View/'.$record["ticketid"].'");');

		$record["ticketmaskid"] = $SPFunctions->visibleLink(null,
			$_SWIFT->Language->Get("rnticketlist"),$record["ticketmaskid"],
			'loadViewportData("/Tickets/Ticket/View/'.$record["ticketid"].'");');
			
		if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			$Credit = $SPFunctions->getUserCredit($record["userid"]);
			$AllMins = $Credit["minutes"];
			$AllTkts = $Credit["tickets"];
			if ($AllMins != $record["rem_minutes"])
				$record["rem_minutes"] = $AllMins . " (".$record["rem_minutes"].")";
			else
				$AllMins = $record["rem_minutes"];
			
			if ($AllTkts != $record["rem_tickets"])
				$record["rem_tickets"] = $AllTkts . " (".$record["rem_tickets"].")";
			else
				$AllTkts = $record["rem_tickets"];
		} else {
			$AllMins = intval($record["rem_minutes"]);
			$AllTkts = intval($record["rem_tickets"]);
		}
		if ($AllMins <= 0) {
			$record["rem_minutes"] = "<span style='color: Red;'>".$record["rem_minutes"]."</span>";
		} elseif ($AllMins <= 15) {
			$record["rem_minutes"] = "<span style='color: #FFCC00;'>".$record["rem_minutes"]."</span>";
		}
		
		if ($AllTkts < 1) {
			$record["rem_tickets"] = "<span style='color: Red;'>".$record["rem_tickets"]."</span>";
		}
		
		if (floatval($record["discount"]) == 100.0) {
			$record["discount"] = "<span style='color: Green;'>".sprintf("%0.2f%%",floatval($record["discount"]))."</span>";
		} else {
			$record["discount"] = sprintf("%0.2f%%",floatval($record["discount"]));
		}
				
		return $record;
	}
		
	public function RenderGrid() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$className = str_replace("View_","",get_class($this));
		
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_"."awaiting"));
		$this->UserInterfaceGrid->SetRecordsPerPage(10);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("ticketid", "ticketid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));

		$_assigns = $_SWIFT->Staff->GetAssignedDepartments($SPFunctions->IsModuleRegistered("TICKETS"));
		$isAdmin = ($_SWIFT->Staff->IsAdmin() ? 1 : 0);
		$staffid = $_SWIFT->Staff->GetStaffID();
		
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("subject", $_SWIFT->Language->Get("f_subject"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("ticketmaskid", $_SWIFT->Language->Get("f_ticketmaskid"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("deptname", $_SWIFT->Language->Get("f_department"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("dateline", $_SWIFT->Language->Get("f_date"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", $_SWIFT->Language->Get("f_fullname"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("timebillable", $_SWIFT->Language->Get("sp_chatbillable"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("timepaid", $_SWIFT->Language->Get("sp_chatpaid"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("rem_minutes", 
				ucwords($_SWIFT->Settings->getKey("settings","sp_minutestxt").' '.$_SWIFT->Language->Get('sp_credit')),
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		}
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("rem_tickets", 
				ucwords($_SWIFT->Settings->getKey("settings","sp_ticketstxt").' '.$_SWIFT->Language->Get('sp_credit')), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		}
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("discount", $_SWIFT->Language->Get("sp_discount"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		// Go straight to tickettimetracks here because the calls aren't closed yet. ticket_paid won't necessarily have an entry.
		$_selectquery = 'select t.ticketid, t.ticketmaskid, ts.title, t.subject, t.dateline, u.fullname, u.userid, d.title as deptname, '.
			'coalesce(sum(floor(coalesce(sd.minuterate,1)*tt.timebillable/60)),0) as timebillable, '.
			'coalesce(sum(tp.minutes),0) as timepaid, coalesce(sum(tp.tickets),0) as tktspaid, '.
			'coalesce(spu.minutes,0) as rem_minutes, coalesce(spu.tickets,0) as rem_tickets, '.
			'coalesce(spu.discount,0) as discount '.
			'from '.TABLE_PREFIX.'tickets as t '.
			'left join '.TABLE_PREFIX.'sp_ticket_paid as tp on  '.
					'(t.ticketid = tp.ticketid and t.userid = tp.userid and tp.paytype = '.SP_PAYTYPE_TICKET.') '.
			'left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = t.departmentid) '.
			'left join '.TABLE_PREFIX.'tickettimetracks as tt on (tt.ticketid = t.ticketid), '.TABLE_PREFIX.'ticketstatus as ts, '.
			TABLE_PREFIX.'users as u '.
			'left join '.TABLE_PREFIX.'sp_users as spu on (spu.userid = u.userid), '.TABLE_PREFIX.'departments as d '.
			'where ts.ticketstatusid = t.ticketstatusid and (t.departmentid in ('.buildIN($_assigns).') or 1='.$isAdmin.') '.
			'and tp.paid_date is null and u.userid = t.userid and d.departmentid = t.departmentid '.
			'and t.ticketstatusid != '.intval($_SWIFT->Settings->getKey("settings","sp_statusclosed")).
			' and (t.assignstatus = 0 or t.ownerstaffid = '.$staffid.' or 1='.$isAdmin.') ';
		$_selectgroup = 'group by t.ticketid,t.ticketmaskid,ts.title,t.subject,t.dateline,u.fullname,u.userid,d.title,
			spu.minutes,spu.tickets,spu.discount';
		$_countquery = 'SELECT COUNT(distinct t.ticketid) as totalitems FROM '. TABLE_PREFIX .'tickets as t
			left join '.TABLE_PREFIX.'sp_ticket_paid as tp on 
			(t.ticketid = tp.ticketid and t.userid = tp.userid and tp.paytype = '.SP_PAYTYPE_TICKET.')
			left join '.TABLE_PREFIX.'tickettimetracks as tt on (tt.ticketid = t.ticketid)
			where t.ticketstatusid != '.intval($_SWIFT->Settings->getKey("settings","sp_statusclosed")).' and (t.departmentid in ('.buildIN($_assigns).') or 1='.$isAdmin.')
			and (t.assignstatus = 0 or t.ownerstaffid = '.$staffid.' or 1='.$isAdmin.') ';
		$_countgroup = 'group by t.ticketid';
		
		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery . 
				" AND ( ".$this->UserInterfaceGrid->BuildSQLSearch('t.subject').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('u.fullname').") ".
				$_selectgroup,
				$_countquery. 
				" AND ( ".$this->UserInterfaceGrid->BuildSQLSearch('t.subject').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('u.fullname').") ".
				$_countgroup
				);
		}
		$this->UserInterfaceGrid->SetQuery($_selectquery.$_selectgroup, $_countquery.$_countgroup);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		
		if ($SPFunctions->isInitialGrid()) {
			if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable"))
				echo "<p><em>".$_SWIFT->Language->Get("sp_accounts_on")."</em></p>";
		}
		
		return true;
	}
};
?>
