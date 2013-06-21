<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_ShowAffiliate.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_ShowAffiliate extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Index()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$wantContents = false;

		if (!$sp_license["allow_affiliate"]) {
			SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_noaffiliate"));
		} else {
			$errmsg = "";
			$SPFunctions->checkUserExists(intval($_SWIFT->User->GetUserID()), $errmsg);
			if ($errmsg != "") {
				SWIFT::Error("SupportPay",$errmsg);
			} else {
				$Record = $_SWIFT->Database->QueryFetch("select guid FROM ".TABLE_PREFIX."sp_users ".					"WHERE userid=".intval($_SWIFT->User->GetUserID()));
				if (!empty($Record)) {
					$AffCode = "<div style='display: inline-block; text-align: center; font-size: small; margin: 0.3em;'>\n".
						"<a title='".str_replace("'","&apos;",($_SWIFT->Settings->getKey("settings","sp_affmsg"))).
						"'\n   href='".SWIFT::Get('basename').
						"/supportpay/Register/".strtoupper($Record["guid"])."' />\n".
						"<img border='0'\n     src='".
						SWIFT::Get("themepath")."supportpay/affimg.png'\n".
						"     alt='Kayako SupportPay module Affiliate System'/><br/>".
						htmlspecialchars($_SWIFT->Settings->getKey("settings","sp_affmsg"))."\n</a></div>";
					$this->Template->Assign("affcode",htmlspecialchars($AffCode));
					$this->Template->Assign("affraw",$AffCode);
				}
			}
			$wantContents = true;
		}

		$this->UserInterface->Header('home');
		$SPFunctions->assignSectionTitle($_SWIFT->Language->Get("sps_affiliate"));
		$_SWIFT->Template->Render("sp_header");
		if ($wantContents) $this->Template->Render("sp_affiliate");
		$_SWIFT->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}	
}