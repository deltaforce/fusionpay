<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_Updates.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Updates extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function Render()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Main', SWIFT_UserInterface::MODE_INSERT, false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_ptupdates'), 'icon_form.gif', 'general', true);

		$_TabObject->Info("SupportPay",$SPFunctions->getPaymentWarnings());
		$_TabObject->Error("SupportPay",$SPFunctions->checkLicense());

		$results = array();
		$SPFunctions->readLicense($_SWIFT->Settings->getKey("settings","sp_license"), $results);

		if ($results["status"] == SP_LICENSE_GOOD) {
			@$updateinfo = simplexml_load_file("http://updates.jimkeir.co.uk/SupportPay-v4-1.xml");
			if (!empty($updateinfo)) {
				$newversion = $updateinfo->attributes()->version;
				$newurl = $updateinfo->attributes()->url;
				$newnotes = $updateinfo;
				if (class_exists('SWIFT_Module')) {
					$moduleObject = &$this->Module;
				} else {
					$moduleObject = &$this->App;
				}
				
				$_TabObject->Title($_SWIFT->Language->Get("sp_updateinfo"));//,"50%");
				$_TabObject->Row(array( array("value" => "Current DB Version", "class"=>"tabletitle"), array("value" => $_SWIFT->Settings->getKey("settings","sp_version"))));
				$_TabObject->Row(array( array("value" => "Current Software Version", "class"=>"tabletitle"), array("value" => $moduleObject->GetInstalledVersion("supportpay"))));
				$_TabObject->Row(array( array("value" => "New Software Version", "class"=>"tabletitle"), array("value" => $newversion )));
				$_TabObject->Row(array( array("value" => "Notes", "class"=>"tabletitle"), array("value" => "<pre style='text-align: left;'>".$newnotes."</pre>")));
				$_TabObject->Row(array( array("value" => "Download", "class"=>"tabletitle"), array("value" => "<a target='_blank' href='".htmlspecialchars($newurl,ENT_QUOTES)."'>".$newurl."</a>")));
			} else {
				$_TabObject->Error("SupportPay",$_SWIFT->Language->Get("sp_updateerror"));
			}
		} else {
			$_TabObject->Error("SupportPay",$_SWIFT->Language->Get("sp_updateinvalid"));
		}

		$this->UserInterface->End();
		return true;
	}
	
};
?>
