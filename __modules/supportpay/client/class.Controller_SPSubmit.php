<?php

if (!defined('SWIFT_MODULESDIRECTORY')) {
	define('SWIFT_MODULESDIRECTORY', SWIFT_APPSDIRECTORY);
	SWIFT_Loader::LoadModel('Ticket:Ticket',APP_TICKETS);
}

include SWIFT_MODULESDIRECTORY."/tickets/client/class.Controller_Submit.php";

class Controller_SPSubmit extends Controller_Submit {
	private $SWIFT4 = null;
	
	public function __construct() {
		if (!$this->Initialize())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}
		
		$this->SWIFT4 = SWIFT::GetInstance();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		$moduleName = $SPFunctions->IsModuleRegistered("TICKETS");
		if (method_exists('SWIFT_Loader','LoadModel')) {
			$this->Load->LoadModel('Ticket:Ticket',$moduleName);
			$this->Load->LoadModel('Priority:TicketPriority',$moduleName);
		} else {
			$this->Load->LoadLibrary('Ticket:Ticket',$moduleName);
			$this->Load->LoadLibrary('Priority:TicketPriority',$moduleName);
		}

		parent::__construct();

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
		$SPUserFuncs->ResumeSession(-1);

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function Index($_departmentID = false) {
		// Fiddle with e.g. the department list here.
		global $SPUserFuncs, $SPFunctions;

		$deptList = $SPFunctions->getPayableDepts();
		$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
		if (is_object($this->SWIFT4->User)) {
			$UserCredit = $SPFunctions->getUserCredit($this->SWIFT4->User->GetUserID());
		} else {
			$UserCredit = array("minutes" => 0, "tickets" => 0, "discount" => 0);
		}

		ob_start();
		$res = parent::Index($_departmentID);
		$page = ob_get_clean();
		$deptLimits = array();
		$this->SWIFT4->Database->Query("select departmentid, mins_to_post * minuterate mins_to_post, acceptmins, accepttkts from ".TABLE_PREFIX."sp_departments");
		while ($this->SWIFT4->Database->NextRecord()) {
			$deptLimits[$this->SWIFT4->Database->Record["departmentid"]] = $this->SWIFT4->Database->Record;
		}
		
		$doPaymentRedirect = $this->SWIFT4->Settings->getKey("settings","sp_purchase_redirect");

		// This will get done by the hook anyway, but just in case...
		$page = str_replace("/Tickets/Submit","/supportpay/SPSubmit",$page);
		$page = str_ireplace("<head>","<head>".$this->Template->RenderTemplate("sp_css"),$page);
		$page = str_ireplace($this->SWIFT4->Language->Get("selectdepartmenttitle")."</", 
			$SPUserFuncs->MakeCreditHeader($this->SWIFT4->Language->Get("selectdepartmenttitle"))."</",
			$page);
	
		foreach ($deptList as $deptId) {
			$cdtMessage = "";
			
			if (!empty($deptLimits[$deptId])) {
				$effLimit = $deptLimits[$deptId]["mins_to_post"] * ((100-$UserCredit["discount"])/100);
				$deptAccept = $accept;
				if ($accept == SP_ACCEPT_BOTH) {
					$deptAccept &= (($deptLimits[$deptId]["acceptmins"] * SP_ACCEPT_MINUTES) | ($deptLimits[$deptId]["accepttkts"] * SP_ACCEPT_TICKETS));
				}

				// Don't block this department if we're redirecting to a payment page.
				if (($effLimit > $UserCredit["minutes"] || !($deptAccept & SP_ACCEPT_MINUTES)) 
					&& ($UserCredit["tickets"] == 0 || !($deptAccept & SP_ACCEPT_TICKETS))
				) {
					$cdtMessage = $this->SWIFT4->Settings->getKey("settings","sp_purchase_redirect_msg");
					if (!$doPaymentRedirect) {
						$page = preg_replace('/(id=["\']department_'.$deptId.'["\'])/','$1 disabled="disabled"',$page);
					}
				}
			}

			if (!empty($cdtMessage)) {
				$cdtMessage = "&nbsp;".$cdtMessage;
			}
			$page = preg_replace('/(label\s+for=[\'"]department_'.$deptId.'[\'"]\s*>)(.*)?</',
				'$1<img src="'.SWIFT::Get("themepathimages").'icon_creditcards.png" style="vertical-align: middle;"/>&nbsp;$2'.$cdtMessage.'<',$page);
		}

		echo $page;
		return $res;
	}

	public function RenderForm($_departmentID = false) {
		// Check the credit limits already notified in the previous page.
		// Redirect if required.

		if ($_departmentID == false) {
			$_departmentID = $_POST["departmentid"];
		}
		
		global $SPFunctions;
		$deptList = $SPFunctions->getPayableDepts();

		if (in_array($_departmentID, $deptList)) {
			$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
			if (is_object($this->SWIFT4->User)) {
				$UserCredit = $SPFunctions->getUserCredit($this->SWIFT4->User->GetUserID());
			} else {
				$UserCredit = array("minutes" => 0, "tickets" => 0, "discount" => 0);
			}

			// What's the paid limit for this department?
			$deptLimit = $this->SWIFT4->Database->QueryFetch("select departmentid, mins_to_post * minuterate mins_to_post, ".
				"acceptmins, accepttkts from ".TABLE_PREFIX."sp_departments ".
				"where departmentid = ".intval($_departmentID));
			if ($deptLimit == false) {
				$deptLimit = array(
					"mins_to_post" => 0, 
					"acceptmins" => ($accept & SP_ACCEPT_MINUTES),
					"accepttkts" => ($accept & SP_ACCEPT_TICKETS)
				);
			}

			$effLimit = $deptLimit["mins_to_post"] * ((100-$UserCredit["discount"])/100);
			$deptAccept = $accept;
			if ($accept == SP_ACCEPT_BOTH) {
				$deptAccept &= (($deptLimit["acceptmins"] * SP_ACCEPT_MINUTES) | ($deptLimit["accepttkts"] * SP_ACCEPT_TICKETS));
			}

			if (($effLimit > $UserCredit["minutes"] || !($deptAccept & SP_ACCEPT_MINUTES)) 
					&& ($UserCredit["tickets"] == 0 || !($deptAccept & SP_ACCEPT_TICKETS))
				)
			{
				// Redirect to payment page when credit is low, if it's enabled, we're taking payments and WHMCS push is off.
				$doPaymentRedirect = $this->SWIFT4->Settings->getKey("settings","sp_purchase_redirect");

				if ($doPaymentRedirect) {
					$theURL = $this->SWIFT4->Settings->getKey("settings","sp_purchase_redirect_url");
					if (empty($theURL)) {
						$theURL = SWIFT::Get('basename')."/supportpay/CdtLanding/Main";
					}
					header("Location: ".$theURL);
					exit;
				}
			}
		}
		
		ob_start();
		$res = parent::RenderForm($_departmentID);
		$page = ob_get_clean();
		$page = str_replace("/Tickets/Submit","/supportpay/SPSubmit",$page);
		echo $page;
		
		return $res;
	}
		
}
?>
