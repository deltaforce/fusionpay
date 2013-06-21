<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_AffSignup.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_AffSignup extends Controller_client
{
	private $regPath;
	
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		if (class_exists('SWIFT_Module')) {
			$this->regPath = "__swift/modules/base/client/class.Controller_UserRegistration.php";
		} else {
			$this->regPath = "__swift/apps/base/client/class.Controller_UserRegistration.php";
		}
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Register() {
		global $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();
		
		require_once($this->regPath);
		$me = new Controller_UserRegistration;
		ob_start();
		$success = $me->RegisterSubmit();
		$Fullscreen = ob_get_clean();
		
		if ($success) {
			session_start();
			$Rec = $_SWIFT->Database->QueryFetch("select userid from ".TABLE_PREFIX."users u,".
				TABLE_PREFIX."useremails e where u.userid = e.linktypeid and e.linktype = 1 ".
				"and email='".$_SWIFT->Database->Escape($_POST["regemail"])."'");
			
			$SPFunctions->checkUserExists($Rec["userid"],$errmsg);
			$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set affiliate = '".
				$_SWIFT->Database->Escape(strtolower($_SESSION["affid"]))."', ".
				"aff_timestamp=".time()." WHERE userid=".$Rec["userid"]);
		} else {
			// Not successful, grab the submit form again.
			$Fullscreen = preg_replace(":/Base/UserRegistration/RegisterSubmit:","/supportpay/AffSignup/Register",$Fullscreen);
		}
		
		echo $Fullscreen;

		return $success;
	}
	
	public function Index($affid=null)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;

		session_start();
		if (!empty($affid)) {
			$_SESSION["affid"] = $affid;
		}
		
		if ($sp_license["allow_affiliate"] && !empty($_SESSION["affid"])) {
			ob_start();
			require_once($this->regPath);
			$me = new Controller_UserRegistration;
			$me->Register();
			$Fullscreen = ob_get_clean();
			
			// Snaffle any register links for ourselves.	
			$Fullscreen = preg_replace(":/Base/UserRegistration/RegisterSubmit:","/supportpay/AffSignup/Register",$Fullscreen);
			
			echo $Fullscreen;
			
			//			echo "Affiliate ID : " . $_SESSION["affid"];
			
			if (!empty($userid) && ($dotemplate == "registersuccess" || $dotemplate == "registervalidate")) {
				echo "*** Client Registered as ".$userid." - enter affiliate ID ***";
				
				$SPFunctions->checkUserExists($userid,$errmsg);
				$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set affiliate = '".$_SWIFT->Database->Escape($_SESSION["affid"])."', ".
					"aff_timestamp=".time()." WHERE userid=".$userid);
			}
		} else {
			// No affiliate code, just use the standard screen.
			require_once($this->regPath);
			$me = new Controller_UserRegistration;
			return $me->RegisterSubmit();
		}
	}	
};

?>
