<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.Controller_DeptRules.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_DeptRules extends Controller_admin
{
	public function __construct() {
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$_SWIFT = SWIFT::GetInstance();
		$_SWIFT->Language->Load('departments');
		
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function Submit() {
		if (is_array($_POST)) {
			global $SPFunctions;
			$_SWIFT = SWIFT::GetInstance();
			
			$deptList = $SPFunctions->getPayableDepts();

			$_SWIFT->Database->Query("insert into ".TABLE_PREFIX."sp_departments (departmentid, minuterate, mins_to_post) ".
				"(select departmentid,1,null from ".TABLE_PREFIX."departments d where departmentid in (".buildIN($deptList).
				") and not exists (select 1 from ".TABLE_PREFIX."sp_departments sd where sd.departmentid = d.departmentid))");
						
			foreach ($deptList as $deptId) {
				$mrate = floatval(isset($_POST["drate"][$deptId]) ? $_POST["drate"][$deptId] : 0);
				$mpost = intval(isset($_POST["minpost"][$deptId]) ? $_POST["minpost"][$deptId] : 0);
				$aMins = intval(isset($_POST["acceptmins"][$deptId]) ? $_POST["acceptmins"][$deptId] : 0);
				$aTkts = intval(isset($_POST["accepttkts"][$deptId]) ? $_POST["accepttkts"][$deptId] : 0);
				
				$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_departments ".
					"set minuterate = ".$mrate.", mins_to_post = ".$mpost.
					", acceptmins = ".$aMins.", accepttkts = ".$aTkts.
					" where departmentid = ".intval($deptId));
			}
			
			SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_depsupdated"));
		}
		
		return $this->Main();
	}
	
	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$this->Router->SetAction("Submit");
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_am_deptrules'), 1,
			$SPFunctions->findAdminBar("SupportPay"));

		$SPFunctions->checkLicense();

		$this->View->RenderGrid();
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
