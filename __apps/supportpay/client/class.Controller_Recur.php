<?php

class Controller_Recur extends Controller_client
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

		$record["options"] = "<a href='#' title='".htmlspecialchars($_SWIFT->Language->Get("sp_delete"),ENT_QUOTES).
			"' onclick='if (confirm(\"".
			htmlspecialchars($_SWIFT->Language->Get('sp_del_agreement'))."\")) window.location=\"".
			SWIFT::Get('basename')."/supportpay/Recur/Delete/".urlencode($record["proc_txid"])."\";'".
			"><img src='".SWIFT::Get('themepathimages')."icon_trash.gif' style='vertical-align: middle; border: none;'/>".
			"&nbsp;".htmlspecialchars($_SWIFT->Language->Get("sp_delete"))."</a>";
		
		$record["created"] = "<span title='".			htmlspecialchars(date(DATE_COOKIE, $record["created"]),ENT_QUOTES)."'>".			htmlspecialchars(date(SWIFT_Date::GetCalendarDateFormat(), $record["created"]),ENT_QUOTES)."</span>";		if (!empty($record["last_paid"])) {			$record["last_paid"] = "<span title='".				htmlspecialchars(date(DATE_COOKIE, $record["last_paid"]),ENT_QUOTES)."'>".				htmlspecialchars(date(SWIFT_Date::GetCalendarDateFormat(), $record["last_paid"]),ENT_QUOTES)."</span>";		} else {			$record["last_paid"] = "";		}		$curtxt = $record["currency"];		if (!empty($sp_currencylist[$curtxt]))			$curtxt = $sp_currencylist[$curtxt]["symbol"];		else			$curtxt = "???";
		$record["recur_display"] = $record["recur_period"]." ";

		switch ($record["recur_unit"]) {
			case SP_RECUR_UNIT_WEEK:
				$record["recur_display"] .= $_SWIFT->Language->Get("week");
				break;
			case SP_RECUR_UNIT_MONTH:
				$record["recur_display"] .= $_SWIFT->Language->Get("month");
				break;
			case SP_RECUR_UNIT_YEAR:
				$record["recur_display"] .= $_SWIFT->Language->Get("year");
				break;
			default:
				$record["recur_display"] .= $_SWIFT->Language->Get("sps_unknown");
		}
		
		$record["dispcost"] = $curtxt.sprintf("%0.2f",$record["cost"]*$record["itemcount"]);
	}

	public function Index()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;

		$SPFunctions->QuitIfGuest('sp_uw_master');

		$userid = $_SWIFT->User->GetUserID();

		// Fetch this user's current total credit.
		$mins = $tkts = $_SWIFT->Language->Get("sps_unkonwn");
		$Record = $SPFunctions->getUserCredit($userid);		
		$accept = $_SWIFT->Settings->getKey("settings","sp_accept");

		if (!empty($Record)) {
			$mins = $Record["minutes"];
			$tkts = $Record["tickets"];
		}
		
		// Grid setup
		$fields = array();
		$fields[] = array("name" => "created", "title" => $_SWIFT->Language->Get("sp_created"), "width" => "");
		$fields[] = array("name" => "last_paid", "title" => $_SWIFT->Language->Get("sp_last_paid"), "width" => "");
		$fields[] = array("type" => "custom", "name" => "recur_display", "title" => $_SWIFT->Language->Get("sp_recur_period"), "width" => "");
		$fields[] = array("name" => "descr", "title" => $_SWIFT->Language->Get("sp_comments"));
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$fields[] = array("name" => "minutes", "title" => $SPFunctions->formatMTP("{Minutes}"));
		}
		if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH) {
			$fields[] = array("name" => "tickets", "title" => $SPFunctions->formatMTP("{Tickets}"));
		}
		$fields[] = array("type" => "custom", "name" => "dispcost", "title" => $_SWIFT->Language->Get("sp_cost"));
		$fields[] = array("type" => "custom", "name" => "options", "title" => $_SWIFT->Language->Get("sp_options"));
		
		$options = array();
		$options["recordsperpage"] = "6";		$options["sortby"] = "created";
		$options["sortorder"] = "desc";
		$options["callback"] = array(get_class(), "_formatGrid");
		$sql = 'SELECT d.created,i.* FROM '. TABLE_PREFIX .'sp_cart_defs d, '.TABLE_PREFIX.'sp_cart_items i '.
			'WHERE userid='.$userid.' and i.cid = d.cid and ctype='.SP_CTYPE_RECURRING;
		$countsql = 'SELECT count(1) FROM '. TABLE_PREFIX .'sp_cart_defs d, '.TABLE_PREFIX.'sp_cart_items i '.
			'WHERE userid='.$userid.' and i.cid = d.cid and ctype='.SP_CTYPE_RECURRING;
		
		$gridContents  = $SPUserFuncs->RenderListHeader($countsql,$fields,$options,$_SWIFT->Language->Get('sp_rblisttitle'));
		$gridContents .= $SPUserFuncs->RenderListContents($sql, $fields,$options);
		$gridContents .= $SPUserFuncs->RenderListFooter($countsql,$options);
		
		$this->Template->Assign("gridcontents", $gridContents);
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($SPUserFuncs->MakeCreditHeader($_SWIFT->Language->Get("sp_rblisttitle")));
		$this->Template->Render("sp_header");
		$this->Template->Render("sp_listagreements");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}

	public function Delete($proc_txid = null) {
		$_SWIFT = SWIFT::GetInstance();
		$userid = $_SWIFT->User->GetUserID();

		if (empty($proc_txid)) {
			SWIFT::Error("SupportPay","Invalid data");
		} else {
			$Rec = $_SWIFT->Database->QueryFetch("select d.cid from ".TABLE_PREFIX."sp_cart_items i, ".
				TABLE_PREFIX."sp_cart_defs d ".
				"where i.proc_txid = '".$_SWIFT->Database->Escape($proc_txid)."' and d.userid = ".$userid." and i.cid = d.cid");

			if (!empty($Rec["cid"])) {
				SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
				$PP = new SWIFT_SPPayPal();
				$cid = $_SWIFT->Database->Escape($Rec["cid"]);
				
				// Call PayPal to cancel the agreement. ManageRecurringPaymentsProfileStatus ($Rec["proc_txid"]), cancel);
				// sp_agree_cancelled
				$resArray = $PP->CancelRecurringPayment($proc_txid, 
					str_replace('{fullname}',$_SWIFT->User->GetProperty('fullname'),$_SWIFT->Language->Get("sp_agree_cancelled")));
				
				// Allow a failure where the payment is unknown, delete the cart anyway.
				if ($resArray["ACK"] == "SUCCESS" || $resArray["L_ERRORCODE0"] == 11556) {
					$_SWIFT->Database->StartTrans();
					$_SWIFT->Database->Execute("delete from ".TABLE_PREFIX."sp_cart_items where cid = '".$cid.
						"' and proc_txid = '".$proc_txid."'");
					$_SWIFT->Database->Execute("delete from ".TABLE_PREFIX."sp_cart_defs where cid = '".$cid.
						"' and userid = ".$userid." and not exists (select 1 from ".TABLE_PREFIX."sp_cart_items i ".
						" where i.cid = '".$cid."')");
					$_SWIFT->Database->CompleteTrans();
					
					SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_agreement_deleted"));
				} else {
					// TODO: PayPal's ERRORMESSAGE text here.
					SWIFT::Error("SupportPay",$resArray['L_LONGMESSAGE0']);
				}
			} else {
				SWIFT::Error("SupportPay","Unable to find the transaction ID for this agreement.");
			}
		}
		
		$this->Router->SetAction("Index");
		return $this->Index();
	}	
}
?>
