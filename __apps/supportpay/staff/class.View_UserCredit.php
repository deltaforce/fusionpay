<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.View_UserCredit.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_UserCredit extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		$this->Language->Load('default',SWIFT_LanguageEngine::TYPE_DB);
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function _billingGridFields($record)
	{
		global $sp_license, $SPFunctions, $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();
		
		$record["created"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["created"]);
		if (!empty($record["last_paid"])) {
			$record["last_paid"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["last_paid"]);
		}

		$record["cost"] = sprintf("%s%0.2f",$sp_currencylist[$record["currency"]]["symbol"],$record["cost"]);

		$recurUnit = "";
		switch ($record["recur_unit"]) {
			case SP_RECUR_UNIT_WEEK:
				$recurUnit = $_SWIFT->Language->Get("week");
				break;
			case SP_RECUR_UNIT_MONTH:
				$recurUnit = $_SWIFT->Language->Get("month");
				break;
			case SP_RECUR_UNIT_YEAR:
				$recurUnit = $_SWIFT->Language->Get("year");
				break;
		}
				
		$record["frequency"] = sprintf("%d %s", $record["recur_period"], $recurUnit);

		$procName = $SPFunctions->getProcessorName($record["provider"]);
		$record["proc_txid"] = "<nobr><img style='vertical-align: middle; padding-right: 0.3em;' src='".			SWIFT::Get("themepath")."supportpay/pi".			$record["provider"].".png' width='16px' height='16px' title='".			htmlspecialchars($procName,ENT_QUOTES)."'/>".$record["proc_txid"]."</nobr>";		
		return $record;
	}
		
	// Callback for grid display
	static public function _gridFields($record)
	{
		global $sp_license, $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();

		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('User:UserGroup');
		} else {
			SWIFT_Loader::LoadLibrary('User:UserGroup');
		}
		
		if (!is_array($record)) return $record;
		
		// Link to the user
		$record["fullname"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$record["fullname"],
			'loadViewportData("/Base/User/Edit/'.$record["userid"].'");');
		if ($record["grouptype"] == SWIFT_UserGroup::TYPE_GUEST) {
			$record["title"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_visitorgroup.gif" border="0" /> '.htmlspecialchars($record["title"]);
		} else if ($record["grouptype"] == SWIFT_UserGroup::TYPE_REGISTERED) {
			$record["title"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_usergroup.gif" border="0" /> '.htmlspecialchars($record["title"]);
		}
		
		if (empty($record["minutes"])) $record["minutes"] = 0;
		if (empty($record["tickets"])) $record["tickets"] = 0;

		$Credit = $SPFunctions->getUserCredit($record["userid"]);
		
		if ($_SWIFT->Settings->getKey("settings","sp_odenable")) {
			// To make the total shown here the *actual* total rather than the *available* total,
			// subtract any overdraft again. OD is shown separately on the line.
			$Credit["minutes"] -= $Credit["overdraft"];
		}
		$AllMins = $Credit["minutes"];
		$AllTkts = $Credit["tickets"];
		
		if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {			
			if ($AllMins != intval($record["minutes"])) $record["minutes"] = $AllMins . " (".$record["minutes"].")";
			if ($AllTkts != intval($record["tickets"])) $record["tickets"] = $AllTkts . " (".$record["tickets"].")";
		}
		
		if ($AllMins <= 0) {
			$record["minutes"] = "<span style='color: Red;'>".$AllMins."</span>";
		} elseif ($AllMins <= 15) {
			$record["minutes"] = "<span style='color: #FFCC00;'>".$AllMins."</span>";
		}
		
		if ($AllTkts < 1) {
			$record["tickets"] = "<span style='color: Red;'>".$AllTkts."</span>";
		}
		
		// Links for the credit values to the user transaction history
		$record["minutes"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sp_chpagetitle"),$record["minutes"],
			'loadViewportData("/supportpay/CdtHist/Main/'.$record["userid"].'");');

		$record["tickets"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sp_chpagetitle"),$record["tickets"],
			'loadViewportData("/supportpay/CdtHist/Main/'.$record["userid"].'");');
		
		if ($_SWIFT->Settings->getKey("settings","sp_affiliate") && $sp_license["allow_affiliate"] && $SPFunctions->checkPerms("sp_canlistaff")) {
			$record["affiliates"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sps_affiliate"),$record["affiliates"],
				'loadViewportData("/supportpay/ListAff/Main/'.$record["userid"].'");');
		}
		
		$record["whmcs_userid"] = (!empty($record["whmcs_userid"]) ? "Yes":"No");
		
		$record["discount"] = sprintf("%0.2f%%",$record["discount"]);
		if ($SPFunctions->checkPerms("sp_canchangecredit")) {
			$record["options"] = "<a href='javascript: void(0);' onclick='loadViewportData(\"/supportpay/UserCredit/AddCredit/".$record["userid"]."\");' title='".$_SWIFT->Language->Get("sp_addcredit").
				"'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
				"src='".SWIFT::Get('swiftpath').SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_addcredit.png'/></a>&nbsp;";
		}

		$isMgr = ($record["acctmgr"] != 0 ? 1 : 0);
		$record["acctmgr"] = ($record["acctmgr"] == 0 ? $_SWIFT->Language->Get("no") : $_SWIFT->Language->Get("yes") .
			" (".intval($record["numaccts"]).")");

		if ($SPFunctions->checkPerms("sp_cansetmgr") && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			$record["acctmgr"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sp_acmgr_hover"),$record["acctmgr"],
				'loadViewportData("/supportpay/ListDeps/Main/'.$record["userid"].'");');

			if (!is_null($record["payerid"])) {
				$record["amname"] .= " <a href='javascript: void(0);' onclick='loadViewportData(\"/supportpay/UserCredit/RemMyMgr/".$record["userid"]."\");'".
					" title='".htmlspecialchars($_SWIFT->Language->Get("sp_ptremacctmgr"),ENT_QUOTES).
					"'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
					"src='".SWIFT::Get("themepathimages")."icon_delete.gif'/></a>";
			}
		}
		
		if ($_SWIFT->Settings->getKey("settings","sp_odenable")) {
			$record["options"] .= "<a href='javascript: void(0);' onclick=\"".
				"UICreateWindow('".SWIFT::Get('basename')."/supportpay/UserCredit/ShowInvoice/".$record["userid"].
				"', 'showinv', 'SupportPay', '".$_SWIFT->Language->Get('loadingwindow') ."', 760, 500, true, this);\"".
				" title='".$_SWIFT->Language->Get("sp_show_invoice")."'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
				"src='".SWIFT::Get("swiftpath").SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_setoverdraft.png'/></a>";
			
			if (empty($record["overdraft"])) {
				$record["overdraft"] = "";
			}
		}

		$record["options"] .= "<a href='javascript: void(0);' onclick=\"".
			"loadViewportData('".SWIFT::Get('basename')."/supportpay/UserCredit/ShowBilling/".$record["userid"]."');\"".
			" title='".$_SWIFT->Language->Get("sp_rblisttitle")."'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
			"src='".SWIFT::Get("swiftpath").SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_showbilling.png'/></a>";

		return $record;
	}

	public function RenderInvoiceBorder($userid, $fromdate, $todate) {
		global $SPFunctions;

		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, '/supportpay/UserCredit/SendInvoice', 
			SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_show_invoice'), 'icon_form.gif', 'general', true);
		$_TabObject->Hidden("userid", $userid);
		
		// window_showinv to refresh.
		$_TabObject->AppendHTML('<script type="text/javascript">var _AjaxRequest = false; '.
			'function refreshPage() {_AjaxRequest = $.get("'.
			SWIFT::Get('basename').'/supportpay/UserCredit/ShowInvoice/'.$userid.'/"+'.
			'encodeURIComponent(document.UserCredit_RenderInvoiceBorderform.startdate.value)+"/"+'.
			'encodeURIComponent(document.UserCredit_RenderInvoiceBorderform.enddate.value)'.
			', function(responseText) {'.
			'$("#window_showinv").html(responseText); reParseDoc();});}</script>');

		$_TabObject->Date("startdate",$this->Language->Get("sp_from_date"),"",date(SWIFT_Date::GetCalendarDateFormat(), $fromdate),0,false,true);
		$_TabObject->Date("enddate",$this->Language->Get("sp_to_date"),"",date(SWIFT_Date::GetCalendarDateFormat(), $todate),0,false,true);
		$_TabObject->DefaultRow("Refresh",'<input name="refresh" class="rebutton" onmouseover="javascript: this.className=\'rebuttonblue\';" '.
			'onmouseout="javascript: this.className=\'rebutton\';" onclick="javascript: refreshPage();" '.
			'onfocus="blur();" type="button" value="'.$this->Language->Get("buttonupdate").'"/>');
		$_TabObject->RowHTML("<tr><td colspan='2'><iframe id='invoice_iframe' style='border: 2px inset #333333; width: 96%; margin: 2%; height: 340px;' ".
			"src='".SWIFT::Get('basename')."/supportpay/UserCredit/PureInvoice/".$userid."/0/".time()."' /></td></tr>");
		$_TabObject->Overflow(380);
		
		// Fudge the buttons: "save" => "send", "cancel" => "close"
		$this->UserInterface->Language->_phraseCache["save"] = $this->Language->Get("buttonsend");
		$this->UserInterface->Language->_phraseCache["cancel"] = $this->Language->Get("close");
		$this->UserInterface->End();
	}
		
	public function RenderInvoice($userid, $startTime, $endTime) {
		global $SPFunctions;

		$Rec = $this->Database->QueryFetch("select last_invoice from ".TABLE_PREFIX."sp_users ".
			"where userid = ".$userid);
		if (is_array($Rec)) {
			$inv_html = $SPFunctions->genAccountInvoice($userid,$startTime,$endTime);
			if (empty($inv_html)) {
				$this->UserInterface->Error("SupportPay","Unable to generate invoice!");
			} else {
				echo $inv_html;
			}
		} else {
			$this->UserInterface->Error("SupportPay","Unable to find last-invoiced time!");
		}
	}
	
	public function RenderBillingGrid($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$_SWIFT->Language->Load('livesupport');
		
		$className = str_replace("View_","",get_class($this));
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_spagreements"));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("proc_txid", "proc_txid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_billingGridFields"));

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
					$this->Language->Get('delete'),
					'icon_delete.gif', array($this->Controller, 'DelAgreement'), $this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->SetExtendedButtons(
			array(
					array("title" => $_SWIFT->Language->Get("refresh"), 
						"link" => 'loadViewportData(\'/supportpay/UserCredit/ShowBilling/'.$userid.'\');',
						"icon" => "icon_regenerate.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_DEFAULT,
						"id" => "grid_refresh"),
					)
				);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("created", $_SWIFT->Language->Get("sp_created"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("last_paid", $_SWIFT->Language->Get("sp_last_paid"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("proc_txid", $_SWIFT->Language->Get("sp_agree_id"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("descr", $_SWIFT->Language->Get("sp_pkg_descr"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("frequency", $_SWIFT->Language->Get("sp_recur_period"), 
			SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", $SPFunctions->FormatMTP("{Minutes}"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", $SPFunctions->FormatMTP("{Tickets}"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("cost", $_SWIFT->Language->Get("sp_cost"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$_selectquery = 'SELECT d.created,d.provider,i.* FROM '. TABLE_PREFIX .'sp_cart_defs d, '.TABLE_PREFIX.'sp_cart_items i '.
			'WHERE userid='.$userid.' and i.cid = d.cid and ctype='.SP_CTYPE_RECURRING;
		$_countquery = 'SELECT count(1) FROM '. TABLE_PREFIX .'sp_cart_defs d, '.TABLE_PREFIX.'sp_cart_items i '.
			'WHERE userid='.$userid.' and i.cid = d.cid and ctype='.SP_CTYPE_RECURRING;

		$this->UserInterfaceGrid->SetQuery($_selectquery, $_countquery);
		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}
	
	public function RenderGrid() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;

		$className = str_replace("View_","",get_class($this));
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_spusers"));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("userid", "userid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
				$this->Language->Get('sp_recalc_credit'),
				'icon_regenerate.gif', array($this->Controller, 'Recalc'), $this->Language->Get('actionconfirm')));

		if ($SPFunctions->checkPerms("sp_cansetdscnt")) {
			$this->UserInterfaceGrid->AddMassAction(
				new SWIFT_UserInterfaceGridMassAction(
						$this->Language->Get('sp_setdscnt'), 
						"../../../../".SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_setdiscount.png",
						array($this->Controller, 'SetDscnt'), $this->Language->Get('actionconfirm'),
						array($this->Language->Get('sp_setdscnt'),		// Title
							400,					// Width
							200,					// Height
							array($this->Controller, 'GetDscnt'))));
		}

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("email", ucwords($_SWIFT->Language->Get("email")), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_ASC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", ucwords($_SWIFT->Language->Get("fullname")), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("title", ucwords($_SWIFT->Language->Get("usergrouptitle")), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($_SWIFT->Settings->getKey("settings","sp_odenable") &&
			$SPFunctions->checkPerms("sp_cansetoverdraft"))
		{
			$this->UserInterfaceGrid->AddMassAction(
				new SWIFT_UserInterfaceGridMassAction(
						$this->Language->Get('sp_overdraft'), 
						"../../../../".SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_setoverdraft.png",
						array($this->Controller, 'SetOverdraft'), $this->Language->Get('actionconfirm'),
						array($this->Language->Get('sp_overdraft'),		// Title
							400,					// Width
							200,					// Height
							array($this->Controller, 'GetOverdraft'))));

			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("overdraft", $_SWIFT->Language->Get("sp_overdraft"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}

		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH)
		{
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_minutestxt")."<br/>".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_ticketstxt")."<br/>".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		if ($_SWIFT->Settings->getKey("settings","sp_affiliate") && $sp_license["allow_affiliate"]) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("affiliates", $_SWIFT->Language->Get("sp_affiliates"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		if ($sp_license["allow_whmcs"]) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("whmcs_userid", $_SWIFT->Language->Get("sp_is_whmcs"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("discount", $_SWIFT->Language->Get("sp_discount"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable"))
		{
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("acctmgr", $_SWIFT->Language->Get("sp_acctmgr"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("amname", $_SWIFT->Language->Get("sp_amname"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

			// Disable the "Set Mgr" button if we're in Organization mode.
			if ($SPFunctions->checkPerms("sp_cansetmgr") && !$_SWIFT->Settings->getKey("settings","sp_am_native")) {
				$this->UserInterfaceGrid->AddMassAction(
					new SWIFT_UserInterfaceGridMassAction(
							$this->Language->Get('sp_set_acctmgr'), 
							"icon_admin.gif",
							array($this->Controller, 'SetMgr'), $this->Language->Get('actionconfirm'),
							array($this->Language->Get('sp_set_acctmgr'),		// Title
								400,					// Width
								200,					// Height
								array($this->Controller, 'GetMgr'))));
			}
		}
		
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("options", $_SWIFT->Language->Get("options"), 
			SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_CENTER));

		$_selectquery = 'SELECT users.userid, users.dateline, users.fullname, usergroups.grouptype, usergroups.title, '.
			'useremails.email,count(pua.affiliate) as affiliates,pu.discount,pu.minutes,pu.tickets,pu.overdraft,pu.acctmgr, '.
			'pu.whmcs_userid, '.
			' coalesce(count(du.userid),0) as numaccts,amu.fullname as amname,pu.payerid FROM '. TABLE_PREFIX .'users AS users '.
			'LEFT JOIN '.TABLE_PREFIX.'usergroups AS usergroups ON (users.usergroupid = usergroups.usergroupid) '.
			'LEFT JOIN '.TABLE_PREFIX.'useremails AS useremails ON (users.userid = useremails.linktypeid and linktype = 1) '.
			'LEFT JOIN '.TABLE_PREFIX.'sp_users AS pu ON (users.userid = pu.userid) '.
			'LEFT JOIN '.TABLE_PREFIX.'sp_users AS pua ON (pua.affiliate = pu.guid) '.
			'LEFT JOIN '.TABLE_PREFIX.'sp_users AS du ON (du.payerid = pu.userid) '.
			'LEFT JOIN '.TABLE_PREFIX.'users AS amu ON (pu.payerid = amu.userid) ';
		$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'users users '.
			'left join '.TABLE_PREFIX.'useremails as useremails on (users.userid = useremails.linktypeid and linktype = 1)';
		$_queryend = ' group by users.userid,users.dateline,users.fullname,usergroups.grouptype,usergroups.title,'.
			'useremails.email,pu.discount,pu.minutes,pu.tickets,pu.overdraft,pu.acctmgr,pu.whmcs_userid,amu.fullname,pu.payerid';

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery . 
				" WHERE (".$this->UserInterfaceGrid->BuildSQLSearch('users.fullname').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('useremails.email').")".
				$_queryend,
				$_countquery. 
				" WHERE (".$this->UserInterfaceGrid->BuildSQLSearch('users.fullname').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('useremails.email').")");
		}

		$this->UserInterfaceGrid->SetQuery($_selectquery.$_queryend, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}

	public function RenderDiscountDlg() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, $_POST['_gridURL'], SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_setdscnt'), 'icon_form.gif', 'general', true);

		if (isset($_POST["itemid"]) && is_array($_POST["itemid"])) {
			$Record = $_SWIFT->Database->QueryFetch("SELECT coalesce(spu.discount,0) as discount FROM ".TABLE_PREFIX."users u ".
				"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".intval($_POST["itemid"][0]));

			$_TabObject->Text("discount",$_SWIFT->Language->Get("sp_discount")." ".$_SWIFT->Settings->getKey("settings","sp_discounttxt"),"",
				$Record["discount"]);
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_noitemsselected'));
		}
		
		$this->UserInterface->End();
		return true;
	}

	public function RenderOverdraftDlg() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, $_POST['_gridURL'], SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_overdraft'), 'icon_form.gif', 'general', true);

		if (isset($_POST["itemid"]) && is_array($_POST["itemid"])) {
			$Record = $_SWIFT->Database->QueryFetch("SELECT coalesce(spu.overdraft,0) as overdraft FROM ".TABLE_PREFIX."users u ".
				"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".intval($_POST["itemid"][0]));

			$_TabObject->Number("overdraft",$_SWIFT->Language->Get("sp_overdraft"),"",
				$Record["overdraft"]);
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_noitemsselected'));
		}
		
		$this->UserInterface->End();
		return true;
	}

	public function RenderManagerDlg() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, $_POST['_gridURL'], SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_set_acctmgr'), 'icon_form.gif', 'general', true);

		if (isset($_POST["itemid"]) && is_array($_POST["itemid"])) {
			$Record = $_SWIFT->Database->QueryFetch("SELECT coalesce(spu.acctmgr,0) as acctmgr FROM ".TABLE_PREFIX."users u ".
				"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".intval($_POST["itemid"][0]));

			$_TabObject->YesNo("ismgr",$_SWIFT->Language->Get("sp_is_acctmgr"),$_SWIFT->Language->Get("d_sp_is_acctmgr"),
				!$Record["acctmgr"]);
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_noitemsselected'));
		}
		
		$this->UserInterface->End();
		return true;
	}
	
	public function RenderCreditForm($CreditInfo, $errormessage = "", $returnClass = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_addcredit'),
			$SPFunctions->findStaffMenu(),4);
		
		if (is_null($returnClass)) {
			$returnClass = get_class($this);
		}
		$className = str_replace("View_","",$returnClass);
		$myClassName = str_replace("View_","",get_class($this));

		$this->UserInterface->Start($className, '/supportpay/'.$myClassName.'/DoAddCredit', SWIFT_UserInterface::MODE_INSERT, 
			false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_addcredit'), 'icon_form.gif', 'general', true);

		$_TabObject->Info("SupportPay",$SPFunctions->getPaymentWarnings());
		$_TabObject->Error("SupportPay",$SPFunctions->checkLicense());
		$_TabObject->Error("SupportPay",$errormessage);
		$_TabObject->Hidden("returnClass", $className);

		if (is_numeric($CreditInfo["userid"])) {			
			$username="";
			// Look up username
			$Record = $_SWIFT->Database->QueryFetch("SELECT u.fullname,coalesce(spu.discount) as discount FROM ".TABLE_PREFIX."users u ".
				"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".$CreditInfo["userid"]);
			$username = $Record["fullname"];
			if (!empty($username)) {
				$_TabObject->Title($_SWIFT->Language->Get('sp_addcredit'), "doublearrows.gif");
				$_TabObject->Hidden("userid", $CreditInfo["userid"]);

				$_useMins = ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH);
				$_useTkts = ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH);
				if ($_useMins) {
					$_TabObject->Text("addmins",$_SWIFT->Language->Get("sp_add")." ".$_SWIFT->Settings->getKey("settings","sp_minutestxt"),"",
						($CreditInfo["addmins"] != "" ? $CreditInfo["addmins"] : "0"));
				}
				if ($_useTkts) {
					$_TabObject->Text("addtkts",$_SWIFT->Language->Get("sp_add")." ".$_SWIFT->Settings->getKey("settings","sp_ticketstxt"),"",
						($CreditInfo["addtkts"] != "" ? $CreditInfo["addtkts"] : "0"));
				}
				
				$jscript = "var pkg = new Array();\n";
				
				if ($_SWIFT->Settings->getKey("settings","sp_usepackages")) {
					$selopts = array();
					$selopts[] = array("title" => "None", "value" => "");
					
					$_SWIFT->Database->Query("select pkgid,enabled,title,minutes,tickets from ".TABLE_PREFIX.
						"sp_packages WHERE pkg_commence <= ".time().
						" AND (pkg_expire <= ".time()." or pkg_expire is null) ORDER BY title ASC");
					while ($_SWIFT->Database->NextRecord()) {
						$selopts[] = array(
							"title" => $_SWIFT->Database->Record["title"] . ($_SWIFT->Database->Record["enabled"] ? "" : (" ".$_SWIFT->Language->Get("sp_pkg_adddisabled"))),
							"value" => $_SWIFT->Database->Record["pkgid"]
						);
						$jscript .= "pkg[".$_SWIFT->Database->Record["pkgid"]."]=[]; ";
						if ($_useMins) $jscript .= "pkg[".$_SWIFT->Database->Record["pkgid"]."]['mins']=".intval($_SWIFT->Database->Record["minutes"]).";\n";
						if ($_useTkts) $jscript .= "pkg[".$_SWIFT->Database->Record["pkgid"]."]['tkts']=".intval($_SWIFT->Database->Record["tickets"]).";\n";
					}
					$_TabObject->Select("addpkgid", "Add Package", "Add this package to the user's credit", 
						$selopts, $_onChange = 'updateValues(document.UserCreditform.addpkgid.value);');
				}
				
				$_TabObject->Number("price",$_SWIFT->Language->Get("sp_pkg_price")." (".$sp_currencylist[$_SWIFT->Settings->getKey("settings","sp_currency")]["symbol"].")",
					$_SWIFT->Language->Get("sp_ac_priced"),
					($CreditInfo["price"] != "" ? $CreditInfo["price"] : "0.00"));
				
				$_TabObject->Text("comment",$_SWIFT->Language->Get("sp_comment"),$_SWIFT->Language->Get("sp_commentd"),$CreditInfo["comment"]);

				$this->UserInterface->Toolbar->AddButton($this->Language->Get("update"), "icon_check.gif");
				$this->UserInterface->Toolbar->AddButton($this->Language->Get('back'), 'icon_back.gif', '/supportpay/'.$className.'/Main', SWIFT_UserInterfaceToolbar::LINK_VIEWPORT);

				// Now show available credits in case it's a deduction we want to take from a specific one.
				/*
				$_TabObject->EndContainer();
				$_TabObject->StartContainer("CreditLines");
				$_TabObject->RowHTML("<p>".$_SWIFT->Language->Get("sp_specificcdt")."</p>");
				$userCredit = $SPFunctions->getUserCredit($CreditInfo["userid"]);
				
				$_TabObject->Title($_SWIFT->Language->Get("sp_creditline"), "doublearrows.gif", 4);
				$_TabObject->Row(array(
					array("value" => "Credit Line", "class" => "tabletitlerowtitle"),
					array("value" => $SPFunctions->formatMTP("{Minutes}"), "class" => "tabletitlerowtitle"),
					array("value" => $SPFunctions->formatMTP("{Tickets}"), "class" => "tabletitlerowtitle"),
					array("value" => "Use", "class" => "tabletitlerowtitle"),
					));
				$_TabObject->Row(array(
					array("value" => "Any Available"),
					array("value" => $userCredit["minutes"]),
					array("value" => $userCredit["tickets"]),
					array("value" => "<input type='radio' name='deductTxid' value='' checked='checked'/>"),
					));
				$_SWIFT->Database->Query("SELECT txid,comments,rem_minutes,rem_tickets FROM ".TABLE_PREFIX."sp_user_payments".
					" WHERE userid=".$CreditInfo["userid"]." AND (rem_minutes > 0 OR rem_tickets > 0) AND pending IS NULL order by txid desc");
				while ($_SWIFT->Database->NextRecord()) {
					$_TabObject->Row(array(
						array("value" => $_SWIFT->Database->Record["comments"]),
						array("value" => $_SWIFT->Database->Record["rem_minutes"]),
						array("value" => $_SWIFT->Database->Record["rem_tickets"]),
						array("value" => "<input type='radio' name='deductTxid' value='".$_SWIFT->Database->Record["txid"]."'/>"),
						));
				}
*/
				echo "<script type='text/javascript'>".$jscript."\nfunction updateValues(id) {\n";
				echo "if (id != '') {\n";
				if ($_useMins) echo "document.UserCreditform.addmins.value = pkg[id].mins;\n";
				if ($_useTkts) echo "document.UserCreditform.addtkts.value = pkg[id].tkts;\n";
				echo "}}\n</script>";
			} else {
				$_TabObject->Error("SupportPay",$_SWIFT->Language->Get("sp_nouser"));
			}
		} else {
			$_TabObject->Error("SupportPay",$_SWIFT->Language->Get("sp_nouser"));
		}
		
		///////////////
//		$_TabObject->EndContainer();
		$this->UserInterface->End();
		$this->UserInterface->Footer();
		return true;
	}
};
?>
