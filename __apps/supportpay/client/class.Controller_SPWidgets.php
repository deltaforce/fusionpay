<?php
/* Don't have the version number here, any extra output messes up LoginShare. */

class DummyRegistry {
	public function UpdateKey($arg1, $arg2) {
	}
}

class Controller_SPWidgets extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('Widget:Widget');
		} else {
			SWIFT_Loader::LoadLibrary('Widget:Widget');
		}
	
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
	
	static function GetWidgetListForUser($isMyPage = false)
	{
		global $SPFunctions, $sp_license;

		$_SWIFT = SWIFT::GetInstance();
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		$_finalWidgetContainer = array();

		$_widgetCache = $_origWidgetCache = $_SWIFT->Cache->Get('widgetcache');
		if (!$_widgetCache)
		{
			throw new SWIFT_Widget_Exception(SWIFT_INVALIDDATA);
		}

		$modTitle = (class_exists('SWIFT_Module') ? 'modulename':'appname');
		if (empty($_SWIFT->User)) {
			// Not logged in. Disable all SupportPay widgets.
			foreach ($_widgetCache as $_key => &$_val) {
				if ($_val[$modTitle] == "supportpay") {
					$_val['displayinindex'] = false;
					$_val['displayinnavbar'] = false;
				}
				
				$_finalWidgetContainer[] = $_val;
			}
		} else {
			// Only show account manager buttons if the license allows, AM is enabled and we're not using
			// the inbuilt Kayako Organizations data.
			$allow_accounts = ($sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")
				&& !$_SWIFT->Settings->getKey("settings","sp_am_native"));
			$Credit = $SPFunctions->getUserCredit($_SWIFT->User->GetUserID(),true);

			foreach ($_widgetCache as $_key => &$_val)
			{
				if ($_val[$modTitle] != "supportpay") {
					//					echo $_val['modulename'] . " => " . ($_val['displayinindex'] ? "true" : "false");
					if ($isMyPage) {
						// Suppress all non-SP widgets on our own landing page.
						$_val['displayinindex'] = false;
					}
				} elseif ($isMyPage) {
					$_val['displayinindex'] = true;
					$_val['isenabled'] = true;
					switch ($_val['widgetname']) {
						case 'sp_uw_cdtsum':
							if ($Credit["discount"] < 100) {
								if ($_SWIFT->Settings->getKey('settings','sp_widgetstyle') != 0) {
									$_val['displayinindex'] = $_val['displayinnavbar'] = false;
								}
							} else {
								$_val['displayinindex'] = $_val['displayinnavbar'] = false;
							}
							break;
						case 'sp_uw_agreements':
							// Billing agreements depend on packages so don't show the widget if packages are disabled.
							$_val['displayinindex'] = $_val['displayinnavbar'] = 
								$_SWIFT->Settings->getKey("settings","sp_usepackages") && $_SWIFT->Settings->getKey("settings","sp_recurenable");

							if ($Credit["discount"] == 100) {
								// Hide the Agreements button if discount == 100, unless the
								// user has any outstanding agreements.
								$Rec = $_SWIFT->Database->QueryFetch("select count(1) cnt from ".
									TABLE_PREFIX."sp_cart_items i, ".TABLE_PREFIX."sp_cart_defs d ".
									"where i.cid = d.cid and d.userid = ".$_SWIFT->User->GetUserID()." and i.proc_txid is not null");
								if ($Rec["cnt"] == 0) {
									$_val['displayinindex'] = $_val['displayinnavbar'] = false;
								}
							}
							break;
						case 'sp_uw_viewpay':
						case 'sp_uw_viewbill':
							$_val['displayinindex'] = $_val['displayinnavbar'] = true;
							break;
						case 'sp_uw_mandep':
							$_val['displayinindex'] = $_val['displayinnavbar'] = false;
							if ($allow_accounts) {
								$Record = $_SWIFT->Database->QueryFetch("select count(1) acctmgr from ".TABLE_PREFIX.
									"sp_users where acctmgr = 1 and userid=".intval($_SWIFT->User->GetUserID()));
								$_val['displayinindex'] = $_val['displayinnavbar'] = ($Record["acctmgr"] != 0);
							}
							break;
						case 'sp_uw_manacc':
							$_val['displayinindex'] = $_val['displayinnavbar'] = false;
							if ($allow_accounts) {
								$emails = buildIN($_SWIFT->User->GetEmailList());
								$sql = "select guid,fullname from ".TABLE_PREFIX."sp_depoffers o,".
									TABLE_PREFIX."users u ".
									"WHERE u.userid = o.userid AND o.email IN (".$emails.")";
								$Record = $_SWIFT->Database->QueryFetch($sql);
								if (isset($Record["guid"])) {
									$_val['displayinindex'] = $_val['displayinnavbar'] = true;
									$_val["defaulttitle"] = str_ireplace("{ManagerName}",$Record["fullname"],$_SWIFT->Language->Get("sp_uw_manacc"));
									$_val["widgetlink"] .= "/".htmlspecialchars($Record["guid"]);
								}
							}
							break;
						case 'sp_uw_master':
							$_val['displayinindex'] = $_val['displayinnavbar'] = false;
							break;
						default:
					}
				} else {
					// Is SupportPay, is not our own index page. Accept the default setting.
				}
				
				$_val['defaulttitle'] = htmlspecialchars(SWIFT_Widget::GetLabel($_val['defaulttitle']));
				$_val['widgetlink'] = htmlspecialchars(SWIFT_Widget::GetPath($_val['widgetlink']));
				$_val['defaulticon'] = htmlspecialchars(SWIFT_Widget::GetIcon($_val['defaulticon']));
				$_val['defaultsmallicon'] = htmlspecialchars(SWIFT_Widget::GetIcon($_val['defaultsmallicon']));

				$_val['isactive'] = (count($_finalWidgetContainer) == 0);	// Default selection.
				
				$_finalWidgetContainer[] = $_val;
			}
		}
		
		// Can't just update the local cache because it's protected.
		// Must update it, then un-update the registry and SWIFT.
		$oldReg = $_SWIFT->Cache->Registry;
		$_SWIFT->Cache->Registry = new DummyRegistry;
		$_SWIFT->Cache->Update('widgetcache', $_widgetCache);
		$_SWIFT->Cache->Registry = $oldReg;
			
		SWIFT::Set('widgetcache', $_origWidgetCache);
		
		return $_finalWidgetContainer;
	}
	
	public function Main()
	{
		$this->Template->Assign('_widgetContainer', $this->GetWidgetListForUser(true));
		
		$this->UserInterface->Header('sp_uw_master');
		$this->Template->Render('homeindex');
		$this->UserInterface->Footer();
	}
	
}