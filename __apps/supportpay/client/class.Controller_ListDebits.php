<?php

class Controller_ListDebits extends Controller_client
{
	private $deptRates;
	
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		$chatName = $SPFunctions->IsModuleRegistered("LIVECHAT");
		if (!empty($chatName)) {
			if (method_exists('SWIFT_Loader','LoadModel')) {
				SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
			} else {
				SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
			}
		}

		$this->deptRates = $SPFunctions->getPayableRates();
						
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	static public function _formatGrid(&$record) {
		global $sp_currencylist, $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();

		$record["dateline"] = "<span title='".date(SWIFT_Date::GetCalendarDateFormat(), $record["dateline"])."'>".
			date(SWIFT_Date::GetCalendarDateFormat(), $record["dateline"])."</span>";
		
		switch ($record["timepaid"]) {
			case 0: $record["paid"] = ""; break;
			case 1: $record["paid"] = $SPFunctions->formatMTP($record["timepaid"]." {minute}"); break;
			default: $record["paid"] = $SPFunctions->formatMTP($record["timepaid"]." {minutes}"); break;
		}
		if ($record["timepaid"] > 0 && $record["tktspaid"] > 0) $record["paid"] .= ", "; // ... but shouldn't happen.
		
		switch ($record["tktspaid"]) {
			case 0: $record["paid"] .= ""; break;
			case 1: $record["paid"] .= $SPFunctions->formatMTP($record["tktspaid"]." {ticket}"); break;
			default: $record["paid"] .= $SPFunctions->formatMTP($record["tktspaid"]." {tickets}"); break;
		}

		if ($record["paid"] == "")
			$record["paid"] = "0";
		
		switch ($record["tickettype"]) {
			case SP_PAYTYPE_TICKET:
				$icon = "icon_widget_submitticket_small.png";
				break;
			case SP_PAYTYPE_LIVESUPPORT:
				$icon = "icon_chatstatusbar.gif";
				break;
			default: {}
		}

		switch ($record["due"]) {
			case 0:
				$record["due"] = $_SWIFT->Language->Get("no");
				break;
			case 1:
				$record["due"] = $_SWIFT->Language->Get("yes");
				break;
			case 2:
				$record["due"] = "Not Yet";
				break;
		}
		$record["ticketstatusid"] = "<span style=\"color: ".$record["statuscolor"].";\">".$record["statustitle"]."</span>";
		
		if ($_SWIFT->User->GetUserID() == $record["userid"]) {	// Because we might be viewed by an AM
			if ($record["tickettype"] == SP_PAYTYPE_TICKET) {
				$record["subject"] = $SPFunctions->visibleLink("/Tickets/Ticket/View/".$record["ticketid"],					$record["subject"],$record["subject"]);
			} elseif ($record["tickettype"] == SP_PAYTYPE_LIVESUPPORT) {
				$record["subject"] = $SPFunctions->visibleLink("/supportpay/ShowChat/Index/".$record["ticketid"],					$record["subject"],$record["subject"]);				}
		}
		
		$record["subject"] = '<img src="'.SWIFT::Get('themepathimages').$icon.'"/>&nbsp;'.$record["subject"];
	}

	public function Index($in_userid = null, $dispmode = 1)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;

		$SPFunctions->QuitIfGuest('sp_uw_master');
		$this->Language->Load('tickets',SWIFT_LanguageEngine::TYPE_DB);
		
		$formats = $SPFunctions->GetCalendarDateFormats(false);
		$this->Template->Assign("dateFormat",$formats["cal"]);
		if (date("d") < 5) {
			$this->Template->Assign("fromDate",date($formats["html"], strtotime("-1 month", strtotime(date("01-M-Y")))));
			$this->Template->Assign("toDate",date($formats["html"], strtotime("-1 minute", strtotime(date("01-M-Y")))));
		} else {
			$this->Template->Assign("fromDate",date($formats["html"], strtotime(date("01-M-Y"))));
			$this->Template->Assign("toDate",date($formats["html"], strtotime("-1 minute", strtotime("+1 month",strtotime(date("01-M-Y"))))));
		}

		$userid = $_SWIFT->User->GetUserID();
		if (!is_null($in_userid)) {
			$_testUserId = intval($in_userid);			if ($_testUserId != $userid) {				$Perm = $_SWIFT->Database->QueryFetch("select fullname from ".TABLE_PREFIX."sp_users me, ".TABLE_PREFIX."sp_users dep, ".					TABLE_PREFIX."users u WHERE u.userid = dep.userid ".					" AND me.userid=".$userid." AND me.acctmgr=1 AND dep.userid=".$_testUserId.					" AND dep.payerid = me.userid");				if (isset($Perm["fullname"])) {	// I'm the account manager for this user, and the AM flag is on.					$userid = $_testUserId;					$_SWIFT->Template->Assign("OtherUser",$Perm["fullname"]);				} else {					SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_notuseracctmgr"));					$this->UserInterface->Footer();
					exit;
				}			}		}

		// Fetch this user's current total credit.
		$mins = $tkts = $_SWIFT->Language->Get("sps_unknown");
		$Record = $SPFunctions->getUserCredit($userid);
		$this->Template->Assign("has_acctmgr",$Record["acctmgr"]);
		
		$accept = $_SWIFT->Settings->getKey("settings","sp_accept");

		if (!empty($Record)) {
			$mins = $Record["minutes"];
			$tkts = $Record["tickets"];
		}
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$this->Template->Assign("dominutes",true);
			$this->Template->Assign("minutescdt",$mins);
		}
		if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH) {
			$this->Template->Assign("dotickets",true);
			$this->Template->Assign("ticketscdt",$tkts);
		}
		$this->Template->Assign("discount", $Record["discount"]);
		
		if ($_SWIFT->Settings->getKey("settings","sp_pmexpiry") > 0) {
			$this->Template->Assign("oldpaymentwarning",
				str_replace("{days}",
						intval($_SWIFT->Settings->getKey("settings","sp_pmexpiry")),
						$_SWIFT->Language->Get("sp_oldpayments")));
		} else {
			$this->Template->Assign("oldpaymentwarning","");
		}
		
		// Grid setup
		if (!empty($_POST["dispmode"])) {
			if (intval($_POST["dispmode"]) != $dispmode) {
				$dispmode = intval($_POST["dispmode"]);
				unset($_POST["_gPage"]);
			}
		}
		$this->Router->SetArguments(array($userid, $dispmode));
		
		$doDependents = ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable"));
		
		$options = array();
		$options["recordsperpage"] = "10";
		$options["sortby"] = "dateline";
		$options["sortorder"] = "desc";
		$options["idname"] = "ticketid";
		$options["callback"] = array(get_class(), "_formatGrid");		//if ($userid != $_SWIFT->User->GetUserID())
		//	$options["appendurl"] = "&userid=".$userid;

		//$fields[] = array(name => "ticketid", title => "Ticket ID", width => "");
		$fields[] = array("name" => "dateline", "title" => $_SWIFT->Language->Get('sp_created'), "width" => "");
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$fields[] = array("name" => "bill_minutes", "title" => $_SWIFT->Language->Get('sp_chatbillable'), "width" => "");
			$fields[] = array("type" => "custom", "name" => "paid", "title" => $_SWIFT->Language->Get('sp_chatpaid'), "width" => "");
		}
		if ($doDependents) {
			// Also add the ticket owner.
			$fields[] = array("name" => "fullname", "title" => $_SWIFT->Language->Get('st_fullname'), "width" => "");
		}
		$fields[] = array("name" => "due", "title" => $_SWIFT->Language->Get('sp_due'), "width" => "");
		$fields[] = array("name" => "statustitle", "title" => $_SWIFT->Language->Get('tlstatus'), "width" => "");
		$fields[] = array("name" => "subject", "title" => $_SWIFT->Language->Get('st_subject'), "width" => "");
		
		switch ($dispmode) {
			case "2":
				$where = " AND due in (1,2)";
				break;
			case "3":
				$where = " AND due = 0";
				break;
			default:
				$where = "";
				break;
		}
		
		$_payDepts = buildIN($SPFunctions->getPayableDepts());		if ($doDependents) {
			// Just my tickets, or tickets for account dependents too?
			$userClause = " in (select userid from ".TABLE_PREFIX."sp_users where userid = ".intval($userid).
				" or payerid = ".intval($userid).")";
		} else {
			$userClause = " = ".intval($userid);
		}

		$querysql = 'select * from (select u.fullname, t.tickettype, t.userid, t.ticketid, t.ticketmaskid, t.ticketstatusid, t.statustitle, '.
			't.statuscolor, t.subject, t.dateline, coalesce(tp.bill_minutes,coalesce(t.timebillable * coalesce(sd.minuterate,1),0)) as bill_minutes, '.
			'coalesce(tp.minutes,0) as timepaid,sum(tp.tickets) as tktspaid, '.
			'case when t.complete=0 then 2 else '.
			'case when sum(tp.tickets) > 0 or coalesce(tp.bill_minutes,coalesce(t.timebillable * coalesce(sd.minuterate,1),0)) = 0 '.
			'or coalesce(tp.bill_minutes,coalesce(t.timebillable * coalesce(sd.minuterate,1),0)) <= coalesce(tp.minutes,0) '.
										'then 0 else 1 end end as due '.
			'from (select 1 as tickettype, tk.userid, tk.ticketid, tk.ticketmaskid, '.
			'case when tk.ticketstatusid = '.intval($_SWIFT->Settings->getKey("settings","sp_statusclosed")).' then 1 else 0 end as complete, '.
			'tk.departmentid, tk.subject, tk.dateline, coalesce(ceil(sum(tt.timebillable)/60),0) as timebillable, '.
			'tk.ticketstatusid, coalesce(ts.title,\'Unknown\') statustitle, coalesce(ts.statuscolor,\'Black\') statuscolor '.
			'from '.TABLE_PREFIX.'tickets tk left join '.TABLE_PREFIX.'tickettimetracks tt on (tt.ticketid = tk.ticketid) '.
			'left join '.TABLE_PREFIX.'ticketstatus as ts on (ts.ticketstatusid = tk.ticketstatusid) '.
			'where userid '.$userClause.' group by tk.userid,tk.ticketid,tk.ticketmaskid,tk.ticketstatusid,ts.title,ts.statuscolor, '.
			'tk.departmentid,tk.subject,tk.dateline ';
		if (false) { // SWIFT_MODULE::IsRegistered(MODULE_LIVECHAT)) {
			$querysql .= 'union select 2 as tickettype, c.userid, c.chatobjectid as ticketid, null as ticketmaskid, '.
				'case when chatstatus = '.SWIFT_Chat::CHAT_ENDED.' then 1 else 0 end as complete, '.
				'c.departmentid, \'Live Support\' as subject, c.dateline, '.
				'ceil((staffpostactivity-dateline)/60) as timebillable,chatstatus,\'Complete\',\'Black\''.
				' from '.TABLE_PREFIX.'chatobjects c where userid ='.$userClause;
		}
		$querysql .= ') as t left join '.TABLE_PREFIX.'sp_ticket_paid as tp on 
			(t.ticketid = tp.ticketid and t.userid = tp.userid and tp.paytype = t.tickettype) '.
			' left join '.TABLE_PREFIX.'sp_departments sd on (sd.departmentid = t.departmentid) '.
			' left join '.TABLE_PREFIX.'users u on (u.userid = t.userid) '.
			' where (paid_date is not null or t.departmentid in ('.$_payDepts.')) '.
			' group by t.tickettype, t.userid, t.ticketid,t.ticketmaskid,t.ticketstatusid,t.complete,'.
			't.statustitle,t.statuscolor,t.subject,t.dateline,sd.minuterate,'.
			'coalesce(tp.bill_minutes,coalesce(t.timebillable * coalesce(sd.minuterate,1),0)),tp.bill_minutes,t.timebillable,tp.minutes) m '.
			'where 1=1 '.$where;
		
		$countsql = str_replace("select *","select count(*)",$querysql);

		$filters = $_SWIFT->Language->Get('sp_show').'&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="1"'.
			($dispmode==1?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_oall').'&nbsp;&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="2"'.
			($dispmode==2?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_ounpaid').'&nbsp;&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="3"'.
			($dispmode==3?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_opaid');

		$gridContents  = $SPUserFuncs->RenderListHeader($countsql,$fields,$options,$_SWIFT->Language->Get('sp_udlisttitle'),$filters);
		$gridContents .= $SPUserFuncs->RenderListContents($querysql,$fields,$options);
		$gridContents .= $SPUserFuncs->RenderListFooter($countsql,$options);
		
		$this->Template->Assign("gridcontents", $gridContents);
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($SPUserFuncs->MakeCreditHeader($_SWIFT->Language->Get("sp_dppagetitle")));
		$this->Template->Render("sp_header");
		$this->Template->Render("sp_listdebits");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
	
}

?>
