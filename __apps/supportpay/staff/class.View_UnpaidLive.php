<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_UnpaidLive extends SWIFT_View
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
		$deptNames = $_SWIFT->Cache->Get('departmentcache');

		$record["dateline"] = date(SWIFT_Date::GetCalendarDateFormat().' H:i',$record["dateline"]);
		$record["subject"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("chconversation"),$record["subject"],
			'UICreateWindow("'.SWIFT::Get('basename').'/supportpay/UnpaidLive/ViewChat/'.$record["chatobjectid"].
			'", "lsconv", "SupportPay", "'.$_SWIFT->Language->Get('loadingwindow') .'", 600, 360, true, this);');

		$record["chatduration"] = date('H:i:s',$record["chatduration"]);

		if (isset($deptNames[$record["departmentid"]])) {
			$record["departmentid"] = $deptNames[$record["departmentid"]]["title"];
		}

		$billTime = $record["bill_minutes"];
		// Are we allowing changes to billable minutes?
		if (!$_SWIFT->Settings->getKey("settings","sp_autobilllive")) {
			$record["bill_minutes"] = '<input type="text" size="3" name="mtime'.$record["chatobjectid"].'" value="'.$billTime.'"/>
			<input type="image" title="'.$_SWIFT->Language->Get("submit").'" src="'.
				SWIFT::Get("themepathimages").'icon_addplus2.gif" name="ubv'.$record["chatobjectid"].'" onclick="document.form_UnpaidLive_lspayments.submit()"/>';
		} else {
			$record["bill_minutes"] = date('H:i:s',$billTime*60);
		}
		
		// Link to the user
		$record["userfullname"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$record["userfullname"],
			'loadViewportData("/Base/User/Edit/'.$record["userid"].'");');

		if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			$Credit = $SPFunctions->getUserCredit($record["userid"],true);
			$AllMins = $Credit["minutes"];
			$AllTkts = $Credit["tickets"];
			if ($AllMins != intval($record["rem_minutes"])) $record["rem_minutes"] = $AllMins . " (".$record["rem_minutes"].")";
			if ($AllTkts != intval($record["rem_tickets"])) $record["rem_tickets"] = $AllTkts . " (".$record["rem_tickets"].")";
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
		
		return $record;
	}
	
	public function RenderGrid() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$chatName = $SPFunctions->IsModuleRegistered("LIVECHAT");
		if (!empty($chatName)) {
			if (method_exists('SWIFT_Loader','LoadModel')) {
				SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
			} else {
				SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
			}
		} else {
			return;
		}

		$className = str_replace("View_","",get_class($this));
		
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_"."lspayments"));
		$this->UserInterfaceGrid->SetRecordsPerPage(15);
		
		$_assigns = $_SWIFT->Staff->GetAssignedDepartments($chatName);
		$isAdmin = ($_SWIFT->Staff->IsAdmin() ? 1 : 0);
		$staffid = $_SWIFT->Staff->GetStaffID();
		$_payDepts = buildIN($SPFunctions->getPayableDepts());
		$SPFunctions->matchChatUsers($errmsg);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("chatobjectid", "chatobjectid", SWIFT_UserInterfaceGridField::TYPE_ID));
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

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
					$this->Language->Get('sp_changedept'), 
					'icon_department.gif',
					array($this->Controller, 'ChangeDept'), $this->Language->Get('actionconfirm'),
					array($this->Language->Get('sp_changedepart'),		// Title
						400,					// Width
						200,					// Height
						array($this->Controller, 'ShowDeptDlg'))));
		
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("dateline", $this->Language->Get('csdateline'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("subject", $this->Language->Get('cssubject'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("departmentid", $this->Language->Get('csdepartment'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("userfullname", $this->Language->Get('userfullname'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("chatduration", $this->Language->Get('cvduration'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("bill_minutes", $this->Language->Get('sp_chatbillable'), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", $_SWIFT->Language->Get("sp_chatpaid"), 
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
		
		// n.b. tt.timebillable and tt.timespent on the second line are already divided by 60.
		$_selectquery = 'SELECT (lastpostactivity-c.dateline) as chatduration, c.subject, c.departmentid, '.
			'coalesce(tp.bill_minutes,ceil((coalesce(sd.minuterate,1)*(staffpostactivity-dateline))/60)) as bill_minutes, '.
			'c.*,coalesce(tp.minutes,0) as minutes,coalesce(tp.tickets,0) as tickets, '.
			'u.minutes as rem_minutes, u.tickets as rem_tickets FROM '.TABLE_PREFIX.'chatobjects as c '.
			'left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = c.departmentid) '.
			'left join '.TABLE_PREFIX.'sp_ticket_paid as tp on (tp.paytype = '.SP_PAYTYPE_LIVESUPPORT.
			' AND tp.ticketid = c.chatobjectid and c.userid = tp.userid), '.
			TABLE_PREFIX.'sp_users as u '.
			'WHERE chatstatus = '.SWIFT_Chat::CHAT_ENDED.' and chattype = '.SWIFT_Chat::CHATTYPE_CLIENT.' and staffpostactivity > dateline '.
			'and coalesce(tp.bill_minutes,ceil((coalesce(sd.minuterate,1)*(staffpostactivity-dateline))/60)) > coalesce(tp.minutes,0) and coalesce(tp.tickets,0) = 0 '.
			'and (c.departmentid in ('.buildIN($_assigns).') or 1 = '.$isAdmin.') and c.departmentid in ('.$_payDepts.') '.
			'and (c.staffid = '.$staffid.' or 1 = '.$isAdmin.') and u.userid = c.userid and c.userid != 0';
		$_countquery = 'select count(distinct c.chatobjectid) as totalitems '.
			'FROM '.TABLE_PREFIX.'chatobjects as c '.
			'left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = c.departmentid) '.
			'left join '.TABLE_PREFIX.'sp_ticket_paid as tp on (tp.paytype = '.SP_PAYTYPE_LIVESUPPORT.' '.
			'AND tp.ticketid = c.chatobjectid and c.userid = tp.userid) '.
			'WHERE chatstatus = '.SWIFT_Chat::CHAT_ENDED.' and chattype = '.SWIFT_Chat::CHATTYPE_CLIENT.' and staffpostactivity > dateline '.
			'and coalesce(tp.bill_minutes,ceil((coalesce(sd.minuterate,1)*(staffpostactivity-dateline)/60))) > coalesce(tp.minutes,0) and coalesce(tp.tickets,0) = 0 '.
			'and (c.departmentid in ('.buildIN($_assigns).') or 1 = '.$isAdmin.') and c.departmentid in ('.$_payDepts.') '.
			'and (c.staffid = '.$staffid.' or 1 = '.$isAdmin.') and c.userid != 0';

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery . 
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('userfullname'),
				$_countquery. 
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('userfullname')
				);
		}

		$this->UserInterfaceGrid->SetQuery($_selectquery, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		
		if ($SPFunctions->isInitialGrid()) {
			$append = "";
			if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable"))
				$append = "<p><em>".$_SWIFT->Language->Get("sp_accounts_on")."</em></p>";
			$append .= "<p><em>Credit totals are shown without due payments subtracted.</em></p>";
			
			echo $append;
		}
		
		return true;
	}
	
	public function RenderDepartmentDlg() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, $_POST['_gridURL'], SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_changedepart'), 'icon_form.gif', 'general', true);

		if (isset($_POST["itemid"]) && is_array($_POST["itemid"])) {
			$deptNames = $_SWIFT->Cache->Get('departmentcache');
			$payableDepts = $SPFunctions->getPayableDepts();
			$moduleName = (class_exists('SWIFT_App') ? 'app' : 'module');
			$chatName = $SPFunctions->IsModuleRegistered("LIVECHAT");

			// Option title is escaped, so can't use the real currency symbol.
//			$cSymbol = " (".$sp_currencylist[$_SWIFT->Settings->getKey("settings","sp_currency")]["symbol"] . ")";
			$cSymbol = " ($)";
			
			$_optionsContainer = array();
			foreach ($deptNames as $deptId => $deptDetails) {
				if ($deptDetails["department".$moduleName] == $chatName) {
					$_optionsContainer[] = array(
						"title" => $deptDetails["title"] . (in_array($deptId, $payableDepts) ? $cSymbol : ""), 
						"value" => $deptId, 
						//		"selected" => (($Package["migrated"].'/'.$Package["migrated_id"]) == $ddId)
						);
				}
			}
			$_TabObject->Select("departmentid",$_SWIFT->Language->Get("csdepartment"), '',	$_optionsContainer);
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_noitemsselected'));
		}
		
		$this->UserInterface->End();
		return true;
	}

	public function RenderChat($_chatObjectID) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$chatName = $SPFunctions->IsModuleRegistered("LIVECHAT");
		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
		} else {
			SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
		}
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Main', SWIFT_UserInterface::MODE_INSERT, 
			true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('chathistory'), 'icon_form.gif', 'general', true);

		$_SWIFT_ChatObject = new SWIFT_Chat($_chatObjectID);

		$_chatDataArray = $_SWIFT_ChatObject->GetConversationArray();

		$_conversationHTML = '';
		foreach ($_chatDataArray as $_key => $_val)
		{
			if ($_val['type'] != SWIFT_ChatQueue::MESSAGE_SYSTEM && $_val['type'] != SWIFT_ChatQueue::MESSAGE_STAFF && $_val['type'] != SWIFT_ChatQueue::MESSAGE_CLIENT)
			{
				continue;
			}

			$_conversationHTML .= '<div class="chathistorymessage">';
			if ($this->Settings->Get('livechat_timestamps') == true)
			{
				$_conversationHTML .= '<span class="chathistorytimestamp">' . $_val['timestamp'] . ' </span>';
			}

			// Process the message 
			if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_CLIENT)
			{
				$_cssClass = 'chathistoryblue';
			} else if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_STAFF) {
				$_cssClass = 'chathistoryred';
			} else {
				$_cssClass = 'chathistorygreen';
			}

			if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_SYSTEM)
			{
				$_conversationHTML .= '<span class="' . $_cssClass . '">' . strip_tags($_val['messagehtml']) . '</span>';
			} else if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_STAFF || $_val['type'] == SWIFT_ChatQueue::MESSAGE_CLIENT) {
				$_conversationHTML .= '<span class="' . $_cssClass . '">' . htmlspecialchars($_val['name']) . ':</span> ' . $_val['messagehtml'];
			}

			$_conversationHTML .= '</div>';
		}

		$_columnContainer = array();
		$_columnContainer[] = array('value' => $_conversationHTML, 'align' => 'left', 'valign' => 'top', 'class' => 'gridrow2', 'colspan' => '4');
		$_TabObject->Row($_columnContainer);
		
		$this->UserInterface->End();
		return true;
	}

};
?>
