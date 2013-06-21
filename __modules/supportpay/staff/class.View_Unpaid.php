<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Unpaid extends SWIFT_View
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
		global $SPFunctions, $sp_license;
		$_SWIFT = SWIFT::GetInstance();

		$record["dateline"] = date(SWIFT_Date::GetCalendarDateFormat().' H:i',$record["dateline"]);
		
		// Link to the user
		$record["fullname"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$record["fullname"],
			'loadViewportData("/Base/User/Edit/'.$record["userid"].'");');

		// Link to the ticket details	
		$record["subject"] = $SPFunctions->visibleLink(null,
			$_SWIFT->Language->Get("rnticketlist"),$record["subject"],
			'loadViewportData("/Tickets/Ticket/View/'.$record["ticketid"].'");');

		if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			$Credit = $SPFunctions->getUserCredit($record["userid"],true);
			$AllMins = $Credit["minutes"];
			$AllTkts = $Credit["tickets"];
			if ($AllMins != intval($record["rem_minutes"])) 
				$record["rem_minutes"] = $AllMins . " (".$record["rem_minutes"].")";
			if ($AllTkts != intval($record["rem_tickets"])) 
				$record["rem_tickets"] = $AllTkts . " (".$record["rem_tickets"].")";
		} else {
			$AllMins = intval($record["rem_minutes"]);
			$AllTkts = intval($record["rem_tickets"]);
		}
		
		if ($record["acceptmins"] != 0) {
			if ($AllMins <= 0) {
				$record["rem_minutes"] = "<span style='color: Red;'>".$record["rem_minutes"]."</span>";
			} elseif ($AllMins <= 15) {
				$record["rem_minutes"] = "<span style='color: #FFCC00;'>".$record["rem_minutes"]."</span>";
			}
		} else {
			$record["rem_minutes"] = "N/A";
		}
		
		if ($record["accepttkts"] != 0) {
			if ($AllTkts < 1) {
				$record["rem_tickets"] = "<span style='color: Red;'>".$record["rem_tickets"]."</span>";
			}
		} else {
			$record["rem_tickets"] = "N/A";
		}
		
		// Allow changing of billable time
		if (isset($record["timebillable"])) {
			$record["timebillable"] = '<input type="text" size="3" name="mtime'.$record["ticketid"].'" value="'.$record["timebillable"].'"/>
					<input type="image" title="'.$_SWIFT->Language->Get("submit").'" src="'.SWIFT::Get("themepath").'icon_enable.gif" name="ubv'.$record["ticketid"].'"/>';
		}
		
		return $record;
	}
	
	public function RenderGrid() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$className = str_replace("View_","",get_class($this));
		
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_payments"));
		$this->UserInterfaceGrid->SetRecordsPerPage(15);
		
		$_assigns = $_SWIFT->Staff->GetAssignedDepartments($SPFunctions->IsModuleRegistered("TICKETS"));
		$isAdmin = ($_SWIFT->Staff->IsAdmin() ? 1 : 0);
		$staffid = $_SWIFT->Staff->GetStaffID();
		$_payDepts = buildIN($SPFunctions->getPayableDepts());

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("ticketid", "ticketid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));

		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddMassAction(
				new SWIFT_UserInterfaceGridMassAction(
						ucwords($_SWIFT->Language->Get("sp_paywith")." ".$_SWIFT->Settings->getKey("settings","sp_minutestxt")),
						'icon_clock.png', array('Controller_'.$className, 'PayMinutes'), $this->Language->Get('actionconfirm')));
		}
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddMassAction(
				new SWIFT_UserInterfaceGridMassAction(
						ucwords($_SWIFT->Language->Get("sp_paywith")." ".$_SWIFT->Settings->getKey("settings","sp_ticketstxt")),
						'icon_ticketbilling.png', array('Controller_'.$className, 'PayTickets'), $this->Language->Get('actionconfirm')));
		}
		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction($_SWIFT->Language->Get("sp_closenow"),
					'icon_tagx.gif', array('Controller_'.$className, 'CloseUnpaid'), $this->Language->Get('actionconfirm')));

		// MassAction calls done, it's now safe to add the page header.
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_ptpagetitle'), 
			$SPFunctions->findStaffMenu(),1);
		$SPFunctions->checkLicense(!$SPFunctions->isInitialGrid());

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("dateline", "Created", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", "User", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("mins_spent", "Worked", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("mins_billable", $this->Language->Get('sp_chatbillable'), 
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
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("subject", $_SWIFT->Language->Get("subject"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		// Go straight to tickettimetracks here because the calls aren't closed yet. ticket_paid won't necessarily have an entry.
		
		// n.b. tt.timebillable and tt.timespent on the second line are already divided by 60.
		$_selectquery = 'select t.ticketid, ts.title, t.subject, t.dateline, u.fullname, u.userid, '.
			'coalesce(tp.bill_minutes,ceil(tt.mins_billable * coalesce(sd.minuterate,1))) as mins_billable, tt.mins_spent, '.
			'coalesce(tp.minutes,0) as timepaid, coalesce(sd.acceptmins,1) as acceptmins, coalesce(sd.accepttkts,1) as accepttkts,'.
			'coalesce(tp.tickets,0) as tktspaid, coalesce(upt.rem_minutes,0) as rem_minutes, '.
			'coalesce(upt.rem_tickets,0) as rem_tickets from '.TABLE_PREFIX.'tickets as t '.
			'left join '.TABLE_PREFIX.'sp_ticket_paid as tp on  '.
			'(t.ticketid = tp.ticketid and t.userid = tp.userid and tp.paytype = '.SP_PAYTYPE_TICKET.') '.
			'left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = t.departmentid) '.
			'left join (select ticketid, sum(floor(tk.timebillable/60)) as mins_billable, sum(floor(timespent/60)) as mins_spent from '.
			TABLE_PREFIX.'tickettimetracks as tk group by ticketid) as tt on (tt.ticketid = t.ticketid), '.
			TABLE_PREFIX.'ticketstatus as ts, '.
			TABLE_PREFIX.'users as u '.
			'left join (select userid, sum(up.rem_minutes) as rem_minutes,sum(up.rem_tickets) as rem_tickets '.
			'from '.TABLE_PREFIX.'sp_user_payments as up where up.pending is null group by up.userid) as upt on '.
			'(upt.userid = u.userid) where tt.ticketid = t.ticketid and ts.ticketstatusid = t.ticketstatusid '.
			'and u.userid = t.userid and (t.departmentid in ('.buildIN($_assigns).') or 1 = '.$isAdmin.') and t.departmentid in ('.$_payDepts.') '.
			'and t.ticketstatusid = '.intval($_SWIFT->Settings->getKey("settings","sp_statusclosed")).
			' and (t.ownerstaffid = '.$staffid.' or t.assignstatus = 0 or 1 = '.$isAdmin.') '.
			'and (coalesce(tp.bill_minutes,tt.mins_billable * coalesce(sd.minuterate,1)) > coalesce(tp.minutes,0) and coalesce(tp.tickets,0) = 0) ';
		$_selectgroup = 'group by t.ticketid, ts.title, t.subject, t.dateline, u.fullname, u.userid, '.
			'coalesce(tp.bill_minutes,ceil(tt.mins_billable * coalesce(sd.minuterate,1))), '.
			'tt.mins_spent, coalesce(tp.minutes,0), coalesce(tp.tickets,0), coalesce(upt.rem_minutes,0), coalesce(upt.rem_tickets,0),'.
			'coalesce(sd.acceptmins,1),coalesce(sd.accepttkts,1)';
		$_countquery = 'select count(distinct t.ticketid) as totalitems FROM '.TABLE_PREFIX.'users u, '. TABLE_PREFIX .'tickets t '.
			'left join '.TABLE_PREFIX.'sp_ticket_paid as tp on '.
			'(t.ticketid = tp.ticketid and t.userid = tp.userid and tp.paytype = '.SP_PAYTYPE_TICKET.') '.
			'left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = t.departmentid) '.
			'left join (select ticketid, sum(floor(timebillable/60)) as timebillable from '.TABLE_PREFIX.
			'tickettimetracks as tk group by ticketid) as tt on (tt.ticketid = t.ticketid) '.
			'where t.ticketstatusid = '.intval($_SWIFT->Settings->getKey("settings","sp_statusclosed")).
			' and (t.departmentid in ('.buildIN($_assigns).') or 1 = '.$isAdmin.') and t.departmentid in ('.$_payDepts.') '.
			'and (t.ownerstaffid = '.$staffid.' or t.assignstatus = 0 or 1 = '.$isAdmin.') and u.userid = t.userid '.
			'and (coalesce(tp.bill_minutes,tt.timebillable * coalesce(sd.minuterate,1)) > coalesce(tp.minutes,0) and coalesce(tp.tickets,0) = 0) ';
		$_countgroup = '';

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

//		echo $_countquery.$_countgroup;

		$this->UserInterfaceGrid->SetQuery($_selectquery.$_selectgroup, $_countquery.$_countgroup);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		
		$append = "";
		if ($SPFunctions->isInitialGrid()) {
			if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable"))
				$append = "<p><em>".$_SWIFT->Language->Get("sp_accounts_on")."</em></p>";
			$append .= "<p><em>Credit totals are shown without due payments subtracted.</em></p>";
			
			echo $append;
		}
/*
		$expRec = $_SWIFT->Database->queryFetch($_countquery.$_countgroup);
		if ($expRec === false) {
			echo "<p><em>Error fetching count: ".$_SWIFT->Database->fetchLastError()."</em></p>";
		} else {
			echo "<p><em>Expected ".$expRec["totalitems"]." records</em></p>";
		}
*/
		$this->UserInterface->Footer();

		return true;
	}
};
?>
