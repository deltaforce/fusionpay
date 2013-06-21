<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.View_OrgCredit.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_OrgCredit extends SWIFT_View
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

	static public function _gridFields($record)
	{
		global $sp_license, $SPFunctions, $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();
		
		$record["organizationname"] = $SPFunctions->visibleLink(null,
			$_SWIFT->Language->Get("sp_org_members"),$record["organizationname"],
			'loadViewportData("/SupportPay/OrgCredit/ViewMembers/'.$record["userorganizationid"].'");');

		if ($record["managers"] != 1) {
			$record["managers"] = "<span style='color: red;'>".$record["managers"]." ".
				$_SWIFT->Language->Get("sp_org_owners")."</span>";
		} else if ($record["mgrid"]) {
			$mgrName = $_SWIFT->Database->queryFetch("select fullname from ".TABLE_PREFIX."users ".
				"where userid = ".intval($record["mgrid"]));
			if (!empty($mgrName["fullname"])) {
				$record["managers"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$mgrName["fullname"],
					'loadViewportData("/Base/User/Edit/'.$record["mgrid"].'");');
			}
		}
		
		$record["options"] = "";
		
		if ($SPFunctions->checkPerms("sp_canchangecredit")) {
			if ($record["mgrid"] != 0) {
				$record["options"] .= "<a href='javascript: void(0);' onclick='loadViewportData(\"/supportpay/UserCredit/AddCredit/".$record["mgrid"]."/".
					"View_OrgCredit\");' title='".$_SWIFT->Language->Get("sp_addcredit").
					"'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
					"src='".SWIFT::Get('swiftpath').SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_addcredit.png'/>".
					$_SWIFT->Language->Get("sp_addcredit")."</a>&nbsp;";
			}
		}

		if (intval($record["mgrid"]) > 0) {
			$record["minutes"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sp_chpagetitle"),$record["minutes"],
				'loadViewportData("/supportpay/CdtHist/Main/'.$record["mgrid"].'");');

			$record["tickets"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("sp_chpagetitle"),$record["tickets"],
				'loadViewportData("/supportpay/CdtHist/Main/'.$record["mgrid"].'");');
		}

		return $record;
	}

	public function RenderGrid() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;

		$_SWIFT->Language->Load('staff_users');
		
		$className = str_replace("View_","",get_class($this));
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_sporgs"));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("userorganizationid", "userorganizationid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("organizationname", $_SWIFT->Language->Get("userorganization"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("members", $_SWIFT->Language->Get("sp_org_nummembers"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("managers", $_SWIFT->Language->Get("sp_org_owner"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
	
		$accept = $_SWIFT->Settings->getKey("settings","sp_accept");
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_minutestxt")."<br/> ".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_ticketstxt")."<br/> ".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}

		if ($_SWIFT->Settings->getKey("settings","sp_odenable") && $SPFunctions->checkPerms("sp_cansetoverdraft"))
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

		if ($SPFunctions->checkPerms("sp_canchangecredit")) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("options", $_SWIFT->Language->Get("options"), 
				SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_CENTER));
		}

		$_selectquery = 'SELECT uo.userorganizationid, uo.organizationname, count(u.userid) as members, coalesce(sum(acctmgr),0) managers, '.
			'sum(coalesce((spu.minutes * spu.acctmgr),0)) minutes, sum(coalesce((spu.tickets * spu.acctmgr), 0)) tickets, '.
			'max(coalesce((spu.userid * spu.acctmgr),0)) mgrid '.
			'from '.TABLE_PREFIX.'userorganizations uo left join '.TABLE_PREFIX.'users u on (u.userorganizationid = uo.userorganizationid) '.
			'left join '.TABLE_PREFIX.'sp_users spu on (u.userid = spu.userid)';
		$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'userorganizations';
		$_group = ' group by uo.userorganizationid, uo.organizationname';

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery.
				" WHERE ".$this->UserInterfaceGrid->BuildSQLSearch('uo.organizationname').
				$_group,
				$_countquery. 
				" WHERE ".$this->UserInterfaceGrid->BuildSQLSearch('uo.organizationname')
			);
		}

		$this->UserInterfaceGrid->SetQuery($_selectquery.$_group, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}
	
	static public function _gridMemberFields($record)
	{
		global $sp_license, $SPFunctions, $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();
		
		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('User:UserGroup');
		} else {
			SWIFT_Loader::LoadLibrary('User:UserGroup');
		}

		$record["fullname"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get("edituser"),$record["fullname"],
			'loadViewportData("/Base/User/Edit/'.$record["userid"].'");');

		if ($record["grouptype"] == SWIFT_UserGroup::TYPE_GUEST) {
			$record["title"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_visitorgroup.gif" border="0" /> '.htmlspecialchars($record["title"]);
		} else if ($record["grouptype"] == SWIFT_UserGroup::TYPE_REGISTERED) {
			$record["title"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_usergroup.gif" border="0" /> '.htmlspecialchars($record["title"]);
		}

		$isMgr = $record["is_mgr"];
		$record["is_mgr"] = ucwords($_SWIFT->Language->Get((intval($record["is_mgr"]) != 0 ? "yes":"no")));

		// Don't offer to change the manager when this user is already the manager.
		// Unless there is more than one manager, in which case we need to be able to
		// choose one.
		if ($record["mc"] > 1 || !$isMgr) {
			$record["is_mgr"] = "<a href='javascript: void(0);' onclick=\"".
				"UICreateWindow('".SWIFT::Get('basename')."/supportpay/OrgCredit/MakeMgr/".$record["userid"].
				"', 'makemgr', 'SupportPay', '".$_SWIFT->Language->Get('loadingwindow') ."', 760, 300, true, this);\"".
				" title='".$_SWIFT->Language->Get("sp_set_acctmgr")."'><img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
				"src='".SWIFT::Get("swiftpath").SWIFT_MODULESDIRECTORY."/supportpay/resources/icon_makemgr.png'/>".$record["is_mgr"]."</a>";
		}
		
		return $record;
	}

	public function RenderMemberGrid($orgId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;

		$className = str_replace("View_","",get_class($this));
		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_sporgmembers"));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("userid", "userid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridMemberFields"));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", ucwords($_SWIFT->Language->Get("fullname")), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("title", ucwords($_SWIFT->Language->Get("usergrouptitle")), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
	
		$accept = $_SWIFT->Settings->getKey("settings","sp_accept");
		if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_minutestxt")."<br/> ".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}
		
		if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH) {
			$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", 
				ucwords(strtolower($_SWIFT->Settings->getKey("settings","sp_ticketstxt")."<br/> ".$_SWIFT->Language->Get("sp_credit"))), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		}

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("is_mgr", $_SWIFT->Language->Get("sp_is_org_acct"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$_selectquery = 'SELECT u.userid, u.fullname, coalesce(spu.acctmgr,0) is_mgr, ug.grouptype, ug.title,'.
			'coalesce(spu.minutes, 0) minutes, coalesce(spu.tickets, 0) tickets, uc.mc '.
			'from '.TABLE_PREFIX.'users u '.
			'left join '.TABLE_PREFIX.'sp_users spu on (u.userid = spu.userid) '.
			'LEFT JOIN '.TABLE_PREFIX.'usergroups AS ug ON (u.usergroupid = ug.usergroupid), '.
			'(select count(1) mc from swusers u2 left join swsp_users spu2 on (u2.userid = spu2.userid) '.
			'where u2.userorganizationid = '.intval($orgId).' and COALESCE( spu2.acctmgr, 0 ) = 1) uc '.
			'where u.userorganizationid = '.intval($orgId)
			;
		$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'users u where u.userorganizationid = '.intval($orgId);

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery.
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('u.fullname'),
				$_countquery. 
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('u.fullname')
				);
		}

		$this->UserInterfaceGrid->SetQuery($_selectquery, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}

	public function RenderMakeMgr($userid) {
		global $SPFunctions;

		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, '/supportpay/OrgCredit/DoSetMgr', SWIFT_UserInterface::MODE_INSERT, 
			true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_set_acctmgr'), 'icon_form.gif', 'general', true);
		$_TabObject->Hidden("userid", $userid);
		
		$_TabObject->YesNo('txcredit', $this->Language->Get('sp_tx_credit'), "", false);
		$_TabObject->YesNo('makemgr', $this->Language->Get('sp_org_makemanager'), "", false);

		$this->UserInterface->End();
	}

};
?>

