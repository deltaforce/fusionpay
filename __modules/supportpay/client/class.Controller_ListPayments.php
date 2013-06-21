<?php

class Controller_ListPayments extends Controller_client
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
	
	static public function _formatGrid(&$record) {
		global $sp_currencylist, $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();

		$record["options"] = '<a href="#" onClick="window.open(\''.SWIFT::Get('basename').'/supportpay/Invoice/Index/'.$record["txid"].
			'\',\'_blank\'); return true;" title="'. $_SWIFT->Language->Get("sp_invoice").
			'"><img src="'.SWIFT::Get("themepathimages").'icon_widget_print.gif" border="0"/></a>';
		
		$record["created"] = "<span title='".			htmlspecialchars(date(DATE_COOKIE, $record["created"]),ENT_QUOTES)."'>".			htmlspecialchars(date(SWIFT_Date::GetCalendarDateFormat(), $record["created"]),ENT_QUOTES)."</span>";		if (!empty($record["expiry"])) {			$record["expiry"] = "<span title='".				htmlspecialchars(date(DATE_COOKIE, $record["expiry"]),ENT_QUOTES)."'>".				htmlspecialchars(date(SWIFT_Date::GetCalendarDateFormat(), $record["expiry"]),ENT_QUOTES)."</span>";		} else {			$record["expiry"] = $_SWIFT->Language->Get("sp_never");		}				$curtxt = $record["currency"];		if (!empty($sp_currencylist[$curtxt]))			$curtxt = $sp_currencylist[$curtxt]["symbol"];		else			$curtxt = "???";
		$record["dispcost"] = $curtxt.sprintf("%0.2f",$record["cost"]);
		
		if (intval($record["tickets"]) < 0)
			$record["tickets"] = "<span style='color: Red;'>".$record["tickets"]."</span>";
		if (intval($record["minutes"]) < 0)
			$record["minutes"] = "<span style='color: Red;'>".$record["minutes"]."</span>";		// Flag to show pending status. Simple conversion to something more visually appealing.		$record["pending"] = $_SWIFT->Language->Get(($record["pending"] == "" ? "yes":"no"));				// Link to a ticket if this entry is a payment.		if (!empty($record["ticketid"])) {			$record["dispcost"] = null;			$record["expiry"] = null;						switch ($record["paytype"]) {				case SP_PAYTYPE_TICKET:					$record["comments"] = $SPFunctions->visibleLink(						"/Tickets/Ticket/View/".$record["ticketid"],						$record["comments"],$record["comments"]);								break;				case SP_PAYTYPE_LIVESUPPORT:					$record["comments"] = $SPFunctions->visibleLink(						"/supportpay/ShowChat/".$record["ticketid"],						$record["comments"],$record["comments"]);						break;			}		} elseif (!empty($record["processor"])) {			$procName = $SPFunctions->getProcessorName($record["processor"]);						// It's a payment. Show the processor's icon.			$record["comments"] = "<nobr><img style='vertical-align: middle; padding-right: 0.3em;' src='".				SWIFT::Get("themepath")."supportpay/pi".				$record["processor"].".png' width='16px' height='16px' title='".				htmlspecialchars($procName,ENT_QUOTES)."'/>".$record["comments"]."</nobr>";		}
	}

	public function Index($dispmode=1)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;

		$SPFunctions->QuitIfGuest('sp_uw_master');

		$formats = $SPFunctions->GetCalendarDateFormats(false);
		$this->Template->Assign("dateFormat","M dd yy");
		if (date("d") < 5) {
			$this->Template->Assign("fromDate",date($formats["html"], strtotime("-1 month", strtotime(date("01-M-Y")))));
			$this->Template->Assign("toDate",date($formats["html"], strtotime("-1 minute", strtotime(date("01-M-Y")))));
		} else {
			$this->Template->Assign("fromDate",date($formats["html"], strtotime(date("01-M-Y"))));
			$this->Template->Assign("toDate",date($formats["html"], strtotime("-1 minute", strtotime("+1 month",strtotime(date("01-M-Y"))))));
		}

		$userid = $_SWIFT->User->GetUserID();
		$SPFunctions->fetchVMSales($_SWIFT->User->GetEmailList());

		// Fetch this user's current total credit.
		$mins = $tkts = "Unknown";
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
		$fields = array();
		$fields[] = array("name" => "created", "title" => $_SWIFT->Language->Get("sp_created"), "width" => "");
		$fields[] = array("name" => "expiry", "title" => $_SWIFT->Language->Get("sp_expiry"), "width" => "");
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$fields[] = array("name" => "minutes", "title" => $SPFunctions->formatMTP("{Minutes}"));
		}
		if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH) {
			$fields[] = array("name" => "tickets", "title" => $SPFunctions->formatMTP("{Tickets}"));
		}
		$fields[] = array("type" => "custom", "name" => "dispcost", "title" => $_SWIFT->Language->Get("sp_cost"));
		$fields[] = array("name" => "paidby", "title" => $_SWIFT->Language->Get("sp_buyer"));
		$fields[] = array("name" => "comments", "title" => $_SWIFT->Language->Get("sp_comments"));
		$fields[] = array("name" => "pending", "title" => $_SWIFT->Language->Get("sp_cleared"), "align"=>"center");
		$fields[] = array("type" => "custom", "name" => "options", "title" => $_SWIFT->Language->Get("sp_print"),
			"align" => "center");
		
		if (!empty($_POST["dispmode"])) {
			if (intval($_POST["dispmode"]) != $dispmode) {
				$dispmode = intval($_POST["dispmode"]);
				unset($_POST["_gPage"]);
				$this->Router->SetArguments(array($dispmode));
			}
		}
		
		switch ($dispmode) {
			case "2":
				$where = " AND ticketid IS NULL";
				break;
			case "3":
				$where = " AND ticketid IS NOT NULL";
				break;
			default:
				$where = "";
				break;
		}
		
		$options = array();
		$options["recordsperpage"] = "6";		$options["sortby"] = "created";
		$options["sortorder"] = "desc";
		$options["callback"] = array(get_class(), "_formatGrid");		$options["massaction"][] = array( "title" => $_SWIFT->Language->Get("sp_invoice"), "callback" => "_maPrintInvoices" );

		$sql = 'SELECT * FROM '. TABLE_PREFIX .'sp_user_payments WHERE userid='.$userid.$where;
		$countsql = 'SELECT count(1) FROM '. TABLE_PREFIX .'sp_user_payments WHERE userid='.$userid.$where;
		
		$filters = $_SWIFT->Language->Get('sp_show').'&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="1"'.
			($dispmode==1?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_oall').'&nbsp;&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="2"'.
			($dispmode==2?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_opurchases').'&nbsp;&nbsp;';
		$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="3"'.
			($dispmode==3?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_opayments');

		$gridContents  = $SPUserFuncs->RenderListHeader($countsql,$fields,$options,$_SWIFT->Language->Get('sp_uclisttitle'),$filters);
		$gridContents .= $SPUserFuncs->RenderListContents($sql, $fields,$options);
		$gridContents .= $SPUserFuncs->RenderListFooter($countsql,$options);
		
		$this->Template->Assign("gridcontents", $gridContents);
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($SPUserFuncs->MakeCreditHeader($_SWIFT->Language->Get("sp_uppagetitle")));
		$this->Template->Render("sp_header");
		$this->Template->Render("sp_listpayments");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
	
}
?>
