<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.View_ListDeps.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_ListDeps extends SWIFT_View
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
		global $SPFunctions, $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();

		$record["fullname"] = $SPFunctions->visibleLink(null,
			$_SWIFT->Language->Get("sp_deplist"),$record["fullname"],
				'loadViewportData("/supportpay/ListDeps/Main/'.$record["userid"].'");');

		return $record;
	}
	
	public function RenderGrid($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$className = str_replace("View_","",get_class($this));

		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_"."depusers"));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("userid", "userid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));
		
		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
					ucwords($_SWIFT->Language->Get("delete")), 'icon_delete.gif', 
					array('Controller_'.$className, 'DelDep'), 
					$this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->SetExtendedButtons(
			array(
					array("title" => $_SWIFT->Language->Get("sp_adddep"), 
						"link" => "UICreateWindow('".SWIFT::Get('basename')."/supportpay/".$className."/AddDep/".$userid.
						"', 'adddep', 'SupportPay', '".$_SWIFT->Language->Get('loadingwindow') ."', 600, 360, true, this);",
						"icon" => "icon_addplus2.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_DEFAULT,
						"id" => "btn_ext1"),
					)
				);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", $_SWIFT->Language->Get("fullname"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("email", $_SWIFT->Language->Get("email"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT),true);

		$userid = intval($userid);	// Just in case.
		
		$Record = $_SWIFT->Database->QueryFetch("SELECT fullname FROM ".TABLE_PREFIX."users as u, ".
			TABLE_PREFIX."sp_users as pu ".
			"WHERE u.userid = ".$userid." AND pu.userid = u.userid");
		$myName = $Record["fullname"];
		if ($SPFunctions->isInitialGrid()) {
			echo "<h2>Dependents of ".$myName."</h2>";
		}
		
		$_selectquery = "SELECT users.userid,users.fullname, ue.email FROM ".TABLE_PREFIX."users AS users ".
			"left join ".TABLE_PREFIX."useremails ue on (ue.linktype = 1 and ue.linktypeid = users.userid), ".
			TABLE_PREFIX."sp_users AS pua WHERE pua.payerid = ".$userid." AND users.userid = pua.userid";
		$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'users AS users, '.
			TABLE_PREFIX."sp_users AS pua WHERE pua.payerid = ".$userid.
			" AND users.userid = pua.userid";
		
		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery . 
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('fullname'),
				$_countquery. 
				" AND ".$this->UserInterfaceGrid->BuildSQLSearch('comments')
				);
		}
		$this->UserInterfaceGrid->SetQuery($_selectquery, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}
	
	public function RenderAddDep($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$this->Language->Load('staff_tickets');
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/DoAddDep/'.$userid, SWIFT_UserInterface::MODE_INSERT, 
			true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_adddep'), 'icon_form.gif', 'general', true);
		$_TabObject->TextMultipleAutoComplete('sp_dep_addemail', $this->Language->Get('user'), '', '/Tickets/Ajax/SearchEmail', 
			array(), 'icon_email.gif');
		$this->UserInterface->End();
		return true;
	}
};
?>
