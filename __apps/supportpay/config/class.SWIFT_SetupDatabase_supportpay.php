<?php

if (!defined('SWIFT_MODULESDIRECTORY')) {
	define('SWIFT_MODULESDIRECTORY', SWIFT_APPSDIRECTORY);
}

class SWIFT_SetupDatabase_supportpay extends SWIFT_SetupDatabase
{
	// Core Constants
	const PAGE_COUNT = 1;

	public function __construct()
	{
		parent::__construct("supportpay");

		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('Widget:Widget');
		} else {
			SWIFT_Loader::LoadLibrary('Widget:Widget');
		}

		if (!class_exists("SWIFT_Cron")) {
			SWIFT_Loader::LoadLibrary('Cron:Cron');
		}

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}

	public function LoadTables()
	{		
		$upay = "userid I NOTNULL,
			txid I PRIMARY AUTO NOTNULL,
			minutes I NOTNULL,
			tickets I NOTNULL,
			rem_minutes I NOTNULL,
			rem_tickets I NOTNULL,
			cost F NOTNULL,
			currency C(3) NOTNULL,
			paidby C(128) NOTNULL,
			comments C(128) NOTNULL,
			packageid I,
			created I UNSIGNED NOTNULL,
			pending C(32),
			ticketid I,
			paytype I2,
			migrated I2,
			processor I2,
			proc_txid C(64),
			tax F NOTNULL DEFAULT '0',
			expiry I UNSIGNED,
			fee F
			";
			
		$this->AddTable('sp_user_payments', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_user_payments", $upay));
		$this->AddIndex('sp_user_payments', new SWIFT_SetupDatabaseIndex("utx", TABLE_PREFIX . "sp_user_payments", "userid, txid"));
		$this->AddIndex('sp_user_payments', new SWIFT_SetupDatabaseIndex("userid", TABLE_PREFIX . "sp_user_payments", "userid, rem_minutes, rem_tickets"));

		$this->AddTable('sp_user_payments_old', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_user_payments_old", $upay));
		$this->AddIndex('sp_user_payments_old', new SWIFT_SetupDatabaseIndex("utx_o", TABLE_PREFIX . "sp_user_payments_old", "userid, txid"));
		$this->AddIndex('sp_user_payments_old', new SWIFT_SetupDatabaseIndex("userid_o", TABLE_PREFIX . "sp_user_payments_old", "userid, rem_minutes, rem_tickets"));

		$this->AddTable('sp_users', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_users", 
				"userid I PRIMARY NOTNULL,
				guid C(40) UNIQUE NOTNULL,
				affiliate C(40),
				aff_timestamp I UNSIGNED,
				minutes I NOTNULL DEFAULT '0',
				tickets I NOTNULL DEFAULT '0',
				payerid I,
				discount F NOTNULL DEFAULT '0',
				acctmgr L NOTNULL DEFAULT '0',
				overdraft I UNSIGNED,
				last_invoice I UNSIGNED,
				whmcs_userid I UNSIGNED,
				last_credit_email I UNSIGNED,
				last_notified_minutes I DEFAULT '0',
				last_notified_tickets I DEFAULT '0'
				"));
		$this->AddIndex('sp_users', new SWIFT_SetupDatabaseIndex("sp_upayerid", TABLE_PREFIX."sp_users", "payerid"));
		$this->AddIndex('sp_users', new SWIFT_SetupDatabaseIndex("sp_uaffid", TABLE_PREFIX."sp_users", "affiliate"));

		// Used to cross-reference users which no longer exist in WHMCS but are still assigned here.
		$this->AddTable('sp_whmcs_knownusers', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_whmcs_knownusers", 
				"userid I,
				email C(40)
				"));

		$this->AddTable('sp_ticket_paid', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_ticket_paid", 
				"ticketid I PRIMARY UNSIGNED NOTNULL,
				userid I PRIMARY UNSIGNED NOTNULL,
				paytype I2 PRIMARY,
				paid_date I UNSIGNED,
				call_minutes I UNSIGNED NOTNULL,
				bill_minutes I UNSIGNED NOTNULL,
				minutes I UNSIGNED NOTNULL DEFAULT '0',
				tickets I UNSIGNED NOTNULL DEFAULT '0',
				migrated I2
				"));

		$this->AddTable('sp_packages', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_packages", 
				"pkgid I UNSIGNED NOTNULL AUTO PRIMARY,
				title C(128) NOTNULL,
				description C(256) NOTNULL,
				img_url C(256),
				pkg_commence I UNSIGNED NOTNULL,
				pkg_expire I UNSIGNED,
				duration I,
				minutes I NOTNULL,
				tickets I NOTNULL,
				price F NOTNULL,
				enabled L NOTNULL DEFAULT '1',
				startup I NOTNULL DEFAULT '0',
				migrated I2,
				recur_period I,
				recur_unit I,
				migrated_id C(16)
				"));

		$this->AddIndex('sp_packages', new SWIFT_SetupDatabaseIndex("validity", TABLE_PREFIX . "sp_packages", "pkg_commence,pkg_expire"));

		// Store the package-to-templategroup mappings
		$this->AddTable('sp_package_tgroups', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_package_tgroups", 
			"pkgid I UNSIGNED NOTNULL,
			tgroupid I UNSIGNED NOTNULL
			"));
		
		$this->AddTable('sp_depoffers', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_depoffers", 
				"userid I NOTNULL,
				guid C(16) NOTNULL PRIMARY,
				offer_made I UNSIGNED NOTNULL,
				email C(128) NOTNULL
				"));

		$this->AddTable('sp_audit', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_audit", 
				"auditid I NOTNULL PRIMARY AUTO,
				staffid I NOTNULL,
				is_user L NOTNULL DEFAULT '0',
				created I UNSIGNED NOTNULL,
				event C(256) NOTNULL
				"));

		$this->AddTable('sp_staff_paid', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_staff_paid", 
				"ticketid I NOTNULL PRIMARY,
				timetrackid I NOTNULL PRIMARY,
				staffid I NOTNULL PRIMARY,
				paid_date INT UNSIGNED,
				spid C(16) NOTNULL,
				txid C(40)
				"));

		$this->AddTable('sp_staff_payments', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_staff_payments", 
				"spid C(16) NOTNULL PRIMARY,
				email C(64) NOTNULL PRIMARY,
				currency C(3) NOTNULL,
				amount F NOTNULL,
				processor I2 NOTNULL,
				txid C(64),
				rundate I UNSIGNED NOTNULL,
				tttmin I UNSIGNED NOTNULL,
				tttmax I UNSIGNED NOTNULL
				"));		

		// This one is (was?) used for per-department staff payment rates.
		$this->AddTable('sp_deptrate', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_deptrate", 
				"departmentid I NOTNULL PRIMARY,
				minuterate F NOTNULL DEFAULT '0',
				ticketrate I NOTNULL DEFAULT '0',
				enabled L NOTNULL DEFAULT '0'
				"));		

		// This one is used for extended department-specific data.
		$this->AddTable('sp_departments', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_departments", 
			"departmentid I NOTNULL PRIMARY,
				minuterate F NOTNULL DEFAULT '1',
				mins_to_post I,
				acceptmins I NOTNULL DEFAULT '1',
				accepttkts I NOTNULL DEFAULT '1'
				"));		

		$this->AddTable('sp_reports', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_reports", 
				"repid I UNSIGNED NOTNULL AUTO PRIMARY,
				title C(128) NOTNULL,
				hash C(64),
				query C(2048) NOTNULL,
				countsql C(2048) NOTNULL 
				"));		

		$this->AddTable('sp_cart_defs', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_cart_defs", 
			"cid C(64) NOTNULL PRIMARY,
			userid I NOTNULL,
			created I UNSIGNED NOTNULL,
			ctype I NOTNULL,
			provider I NOTNULL
			"));		

		$this->AddTable('sp_cart_items', new SWIFT_SetupDatabaseTable(TABLE_PREFIX . "sp_cart_items", 
			"cid C(64) NOTNULL PRIMARY,
			 itemid I UNSIGNED PRIMARY,
			descr C(256),
			itemcount I UNSIGNED NOTNULL,
			minutes I UNSIGNED NOTNULL,
			tickets I UNSIGNED NOTNULL,
			pkgid I UNSIGNED,
			currency C(3) NOTNULL,
			cost F NOTNULL,
			tax F NOTNULL,
			last_paid I UNSIGNED,
			proc_txid C(64),
			recur_period I,
			recur_unit I
			"));		

		return true;
	}

	public function GetPageCount()
	{
		return self::PAGE_COUNT;
	}

	private function CreateWidgets() {
		$_SWIFT = SWIFT::GetInstance();
		
		$this->Load->Library('Settings:SettingsManager');
		
		$settingsPath = SWIFT_MODULESDIRECTORY.'/supportpay/config/settings.xml';
		$_statusListContainer = $this->SettingsManager->Import($settingsPath);
		
		$Rec = $_SWIFT->Database->QueryFetch("select max(displayorder)+1 as next_order from ".TABLE_PREFIX."widgets");
		$_displayOrder = $Rec["next_order"];

		/////////////////////////////////////////////////////////
		// Crons
		SWIFT_Cron::DeleteOnName(array('sp_reconciler','sp_pendingtx','sp_whmcs'));
		SWIFT_Cron::Create('sp_reconciler', 'supportpay', 'SPCron', 'Reconciler', '0', '5', '0', true);
		SWIFT_Cron::Create('sp_pendingtx', 'supportpay', 'SPCron', 'Pending', '1', '0', '0', true);
		SWIFT_Cron::Create('sp_whmcs', 'supportpay', 'SPCron', 'WHMCS', '0', '15', '0', true);

		if (method_exists("SWIFT_Widget","DeleteOnModule")) {
			SWIFT_Widget::DeleteOnModule(array("supportpay"));
		} else {
			SWIFT_Widget::DeleteOnApp(array("supportpay"));
		}
		/////////////////////////////////////////////////////////
		// Master widget
		SWIFT_Widget::Create('PHRASE:sp_uw_master', 'sp_uw_master', "supportpay", '/supportpay/SPWidgets/Main', 
			'{$themepath}../supportpay/mstr-icon.png',
			'{$themepath}../supportpay/mstr-icon-small.png',
			$_displayOrder++,
			true, true, true, true, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);
		
		// Style 0 : View Payments
		SWIFT_Widget::Create('PHRASE:sp_uw_viewpay', 'sp_uw_viewpay', "supportpay", '/supportpay/ListPayments/Index', 
			'{$themepath}../supportpay/payment-icon.png',
			'{$themepath}../supportpay/payment-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);

		// Style 0 : View Bills
		SWIFT_Widget::Create('PHRASE:sp_uw_viewbill', 'sp_uw_viewbill', "supportpay", '/supportpay/ListDebits/Index', 
			'{$themepath}../supportpay/debit-icon.png',
			'{$themepath}../supportpay/debit-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);

		// Style 1 : Credit Management
		SWIFT_Widget::Create('PHRASE:sp_uw_cdtsum', 'sp_uw_cdtsum', "supportpay", '/supportpay/CdtLanding/Main', 
			'{$themepath}../supportpay/cmgt-icon.png',
			'{$themepath}../supportpay/cmgt-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);

		SWIFT_Widget::Create('PHRASE:sp_uw_mandep', 'sp_uw_mandep', "supportpay", '/supportpay/AcctMgr/Main', 
			'{$themepath}../supportpay/acctmgr-icon.png',
			'{$themepath}../supportpay/acctmgr-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);
		
		SWIFT_Widget::Create('PHRASE:sp_uw_manacc', 'sp_uw_manacc', "supportpay", '/supportpay/DepAccept/Index', 
			'{$themepath}../supportpay/amaccept-icon.png',
			'{$themepath}../supportpay/amaccept-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);

		SWIFT_Widget::Create('PHRASE:sp_uw_agreements', 'sp_uw_agreements', "supportpay", '/supportpay/Recur/Index', 
			'{$themepath}../supportpay/recur-icon.png',
			'{$themepath}../supportpay/recur-icon-small.png',
			$_displayOrder++,
			false, false, true, false, 
			SWIFT_Widget::VISIBLE_LOGGEDIN, false);
	}
	
	public function Install($_pageIndex)
	{
//		parent::Uninstall($_pageIndex);
		define("MinVersion","4.01.204");
		
		if (version_compare(SWIFT_VERSION, MinVersion) < 0) {
			SWIFT::Error("SupportPay","SupportPay requires at least version ".MinVersion." of Fusion.");
			return false;
		}

		if (!extension_loaded('ionCube Loader')) {
			SWIFT::Error("SupportPay","SupportPay requires the ionCube loaders. ".
				"Please see the FAQ at <a style='color: blue;' target='_blank' ".
				"href='https://www.support-pay.com/support/index.php?/Knowledgebase/Article/View/7/0/ioncube-loaders'>".
				"https://www.support-pay.com/support/index.php?/Knowledgebase/Article/View/7/0/ioncube-loaders</a> .");
			return false;
		}

		$tabList = $this->GetTableContainer();
		$_ADODBDictionaryObject = $this->Database->GetADODBDictionaryObject();
		foreach ($tabList as $tabName => $tabDetails) {
			$_dropTableSQLResult = $_ADODBDictionaryObject->DropTableSQL(TABLE_PREFIX.$tabName);
			$_result = $_ADODBDictionaryObject->ExecuteSQLArray($_dropTableSQLResult);
		}

		if (parent::Install($_pageIndex)) {
			global $SPFunctions;
			// Can't use the LoadLibrary call here, the module isn't registered yet.
			include(SWIFT_MODULESDIRECTORY.'/supportpay/library/SupportPay/class.SWIFT_SPFunctions.php');
			
			$this->CreateWidgets();
			$SPFunctions->ImportReportXML(SWIFT_MODULESDIRECTORY."/supportpay/resources/reports.xml");
			
			// Customise the default value for the PayPal IPN override.
			$_SWIFT = SWIFT::GetInstance();
			$_SWIFT->Settings->UpdateKey("settings", "sp_paypalipnurl", 
				$_SWIFT->Settings->getKey("settings","general_producturl").
				SWIFT_MODULESDIRECTORY."/supportpay/client/paypal/ipn.php");

			return true;
		}
		return false;
	}

	public function Uninstall()
	{
		$_SWIFT = SWIFT::GetInstance();
		if (!class_exists("SWIFT_TemplateManager")) {
			SWIFT_Loader::LoadLibrary('Template:TemplateManager');
		}

		parent::Uninstall();
		
		// Crons
		SWIFT_Cron::DeleteOnName(array('sp_reconciler','sp_pendingtx'));

		// Widgets
		if (method_exists("SWIFT_Widget","DeleteOnModule")) {
			SWIFT_Widget::DeleteOnModule(array("supportpay"));
		}
		
		if (method_exists("SWIFT_SettingsManager","DeleteOnApp")) {
			$this->DeleteOnApp(array("supportpay"));
		} else {
			// Delete settings manually from an old-style database. Field name is "module".
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."settings where section='settings' ".
				" and vkey in (select name from ".TABLE_PREFIX."settingsfields where module = 'supportpay')");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."settings where section='settings' ".
				" and vkey in (".buildIN(array('sp_have_agreed')).")");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."settingsfields where module = 'supportpay'");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."settingsgroups where module = 'supportpay'");
		}

		// Delete templates.
		if (method_exists("SWIFT_TemplateManager","DeleteOnApp")) {
			$_SWIFT_TemplateManagerObject = new SWIFT_TemplateManager();
			$_SWIFT_TemplateManagerObject->DeleteOnApp(array("supportpay"));
		} else {
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."templatehistory ".
				"where templateid in (select templateid from ".TABLE_PREFIX."templates where substr(name,1,3) = 'sp_')");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."templatedata ".
				"where templateid in (select templateid from ".TABLE_PREFIX."templates where substr(name,1,3) = 'sp_')");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."templates where substr(name,1,3) = 'sp_'");
			$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."templatecategories where name = 'template_supportpay'");
		}
		
		// Remove the internal registry settings saying that this module has been loaded.
		if (!class_exists("SWIFT_ImportRegistry")) {
			$this->Load->Library('Import:ImportRegistry', false, false);
		}
		$_ImportRegistry = new SWIFT_ImportRegistry();
		$_ImportRegistry->DeleteSection("supportpay");

		return true;
	}

	// This function no longer exists under V4.50+ . However, V4.50 does load templates automatically so
	// it's no longer needed anyway.
	public function CompleteInstall() {
		if (parent::CompleteInstall()) {
			// Load the templates. Can't do this during install, because the module isn't registered yet.
			if (!class_exists("SWIFT_TemplateManager")) {
				SWIFT_Loader::LoadLibrary('Template:TemplateManager');
			}
			
			$templatePath = SWIFT_MODULESDIRECTORY.'/supportpay/config/templates.xml';
			$_SWIFT_TemplateManagerObject = new SWIFT_TemplateManager();
			
			return $_SWIFT_TemplateManagerObject->Merge($templatePath, false);
		}
		
		return false;
	}

	public function Upgrade()
	{	
		if (!class_exists("SWIFT_TemplateManager")) {
			SWIFT_Loader::LoadLibrary('Template:TemplateManager');
		}
		$templatePath = SWIFT_MODULESDIRECTORY.'/supportpay/config/templates.xml';
		$_SWIFT_TemplateManagerObject = new SWIFT_TemplateManager();
		$_SWIFT_TemplateManagerObject->Merge($templatePath, false);

		$res = parent::Upgrade();

		global $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		// Set default values for date and value of last credit-change email.
		$_SWIFT->Database->Execute("update ".TABLE_PREFIX."sp_users set ".
			"last_credit_email = ".time().", last_notified_minutes = minutes, last_notified_tickets = tickets ".
			"where last_credit_email is null");

		// Populate defaults for package/templategroups where they don't currently exist
		$_SWIFT->Database->Execute("insert into ".TABLE_PREFIX."sp_package_tgroups(pkgid, tgroupid) ".
			"(select p.pkgid, g.tgroupid from ".TABLE_PREFIX."sp_packages p, ".TABLE_PREFIX."templategroups g ".
			"where p.pkgid not in (select distinct pkgid from ".TABLE_PREFIX."sp_package_tgroups))"
			);

		$this->CreateWidgets();
		$SPFunctions->ImportReportXML(SWIFT_MODULESDIRECTORY."/supportpay/resources/reports.xml");

		if (SWIFT_MODULESDIRECTORY != "__modules") {
			foreach (array("sp_paypalipnurl","sp_anipnurl") as $theSetting) {
				$oldValue = $_SWIFT->Settings->getKey("settings",$theSetting);
				if (strpos($oldValue,"/__modules/") > 0) {
					$_SWIFT->Settings->UpdateKey("settings", $theSetting, 
						str_replace("/__modules/","/".SWIFT_MODULESDIRECTORY."/",$oldValue));
				}
			}
		}
		
		return $res;
	}
}
?>