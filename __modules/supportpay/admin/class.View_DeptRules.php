<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_DeptRules.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_DeptRules extends SWIFT_View
{
	private $deptRates;
	
	public function __construct() {
		parent::__construct();
		
		global $SPFunctions;
		$this->deptRates = $SPFunctions->getPayableRates();

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}
	
	static public function _gridFields($record)
	{
		$_SWIFT = SWIFT::GetInstance();

		$deptName = $_SWIFT->Language->Get("module_".$record["departmentmodule"]);
		if (empty($record["departmentmodule"])) {
			$deptName = $_SWIFT->Language->Get("app_".$record["departmentmodule"]);
		}
		$deptName = $record["departmentmodule"];

		$record["rate"] = "<input type='text' size='6' name='drate[".$record["departmentid"].
			"]' value='".(!empty($record["minuterate"]) ? floatval($record["minuterate"]) : "1")."'/>";
		$record["minpost"] = "<input type='text' size='6' name='minpost[".$record["departmentid"].
			"]' value='".(!empty($record["mins_to_post"]) ? intval($record["mins_to_post"]) : "")."'/>";

		$record["acceptmins"] = "<input type='checkbox' name='acceptmins[".$record["departmentid"].
			"]' value='1' ".($record["acceptmins"] == 1 ? "checked='checked'":"")." />";
		$record["accepttkts"] = "<input type='checkbox' name='accepttkts[".$record["departmentid"].
			"]' value='1' ".($record["accepttkts"] == 1 ? "checked='checked'":"")." />";
		
		return $record;
	}
	
	public function RenderGrid() {		// Actual package grid
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->Load->Library("UserInterface:UserInterfaceGrid", array("sp_deptrules"));
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->SetExtendedButtons(
			array(
					array("title" => $_SWIFT->Language->Get("update"), 
						"link" => 'document.form_sp_deptrules.submit();',
						"icon" => "icon_check.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_DEFAULT,
						"id" => "grid_submit"),
					)
				);

		$deptHdr = $_SWIFT->Language->Get('depmodule');
		if (empty($deptHdr)) {
			$deptHdr = $_SWIFT->Language->Get('depapp');
		}
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("departmentid", "departmentid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("title", $_SWIFT->Language->Get("departments"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("departmentmodule", $deptHdr, 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,
				SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField('rate', $_SWIFT->Language->Get('sp_rate_mult'), 
				SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_CENTER));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField('minpost', $_SWIFT->Language->Get('sp_rate_mincdt'), 
			SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_CENTER));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("acceptmins", $_SWIFT->Language->Get("sp_accept_minutes"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("accepttkts", $_SWIFT->Language->Get("sp_accept_tickets"), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$deptList = $SPFunctions->getPayableDepts();
		$moduleName = (class_exists('SWIFT_App') ? 'app' : 'module');

		$sql = "select d.departmentid, d.title, d.department".$moduleName." departmentmodule, sd.minuterate, sd.mins_to_post, ".
			"coalesce(sd.acceptmins,1) acceptmins, coalesce(sd.accepttkts,1) accepttkts ".
			"from ".TABLE_PREFIX."departments d left join ".TABLE_PREFIX."sp_departments sd ".
			"on (sd.departmentid = d.departmentid) where d.departmentid in (".buildIN($deptList).")";
		$countsql = "select count(1) from ".TABLE_PREFIX."departments where departmentid in (".buildIN($deptList).")";
					
		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($sql." and ".
				$this->UserInterfaceGrid->BuildSQLSearch('title'),
				$countsql." and ".$this->UserInterfaceGrid->BuildSQLSearch('title'));
		}
		
		$this->UserInterfaceGrid->SetQuery($sql,$countsql);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();

		return true;
	}
};
?>
