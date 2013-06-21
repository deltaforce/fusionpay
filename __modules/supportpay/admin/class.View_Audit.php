<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_Audit.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Audit extends SWIFT_View
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
		$_SWIFT = SWIFT::GetInstance();
		$dateFormat = SWIFT_Date::GetCalendarDateFormat();

		$record["fullname"] = "<img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
			"src='".SWIFT::Get("themepathimages").($record["is_user"] ? "icon_user.gif" : "icon_staffuser.gif")."'>" . $record["fullname"];
			
		$record["created"] = date(SWIFT_Date::GetCalendarDateFormat(), $record["created"]);
		
		return $record;
	}
	
	public function RenderGrid() {		// Actual package grid
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->Load->Library("UserInterface:UserInterfaceGrid", array("sp_audittrail"));
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("auditid", "auditid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("fullname", $_SWIFT->Language->Get("username"), SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("created", $_SWIFT->Language->Get("date"), SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("event", $_SWIFT->Language->Get("sp_adtevent"), SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery(
				'SELECT a.*,coalesce(s.fullname,u.fullname) as fullname,a.is_user FROM '.TABLE_PREFIX.'sp_audit a '.
				'LEFT JOIN '.TABLE_PREFIX.'staff s ON (s.staffid = a.staffid AND a.is_user=0) '.
				'LEFT JOIN '.TABLE_PREFIX.'users u ON (u.userid = a.staffid AND a.is_user=1)'.
				' WHERE ('.$this->UserInterfaceGrid->BuildSQLSearch('coalesce(s.fullname,u.fullname)').
				'  OR '.$this->UserInterfaceGrid->BuildSQLSearch('event').')',
				'SELECT count(1) totalitems FROM '.TABLE_PREFIX.'sp_audit WHERE '.
				$this->UserInterfaceGrid->BuildSQLSearch('coalesce(s.fullname,u.fullname)').
				' OR '.$this->UserInterfaceGrid->BuildSQLSearch('event'));
		}
		$this->UserInterfaceGrid->SetQuery(
			'SELECT a.*,coalesce(s.fullname,u.fullname) as fullname,a.is_user FROM '.TABLE_PREFIX.'sp_audit a '.
			'LEFT JOIN '.TABLE_PREFIX.'staff s ON (s.staffid = a.staffid AND a.is_user=0) '.
			'LEFT JOIN '.TABLE_PREFIX.'users u ON (u.userid = a.staffid AND a.is_user=1)', 
			'SELECT COUNT(1) totalitems FROM '.TABLE_PREFIX.'sp_audit');

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
//		$this->UserInterface->End();

		return true;
	}
};
?>
