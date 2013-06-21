<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_Invoice.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Invoice extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('CustomField:CustomFieldGroup');
		} else {
			SWIFT_Loader::LoadLibrary('CustomField:CustomFieldGroup');
		}
		
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	private function Render($sql) {
		global $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();
		
		$userid = $_SWIFT->User->GetUserID();
/*		if (empty($userid)) {
			showNotLoggedIn();	// Never returns.
		}
*/		
		$_SWIFT->Database->Query($sql);
		
		$invoices=array();
		$_total = array();
		$_icount = 1;
		while ($_SWIFT->Database->NextRecord()) {
			$curtxt = $_SWIFT->Database->Record["currency"];
			if (!empty($sp_currencylist[$curtxt]))
				$curtxt = $sp_currencylist[$curtxt]["symbol"];
			else
				$curtxt = "???";
			
			$invoices[$_icount++] = array( 
					"txid" => $_SWIFT->Database->Record["txid"],
					"minutes" => $_SWIFT->Database->Record["minutes"],
					"tickets" => $_SWIFT->Database->Record["tickets"],
					"cost" => $curtxt.sprintf("%0.2f",$_SWIFT->Database->Record["cost"]),
					"paidby" => $_SWIFT->Database->Record["paidby"],
					"comments" => $_SWIFT->Database->Record["comments"],
					"created" => date(SWIFT_Date::GetCalendarDateFormat(),$_SWIFT->Database->Record["created"]),
					);
			
			if (!isset($_total[$_SWIFT->Database->Record["currency"]])) {
				$_total[$_SWIFT->Database->Record["currency"]] = array("value" => 0);
			}
			
			$_total[$_SWIFT->Database->Record["currency"]]["value"] += $_SWIFT->Database->Record["cost"];
			$_total[$_SWIFT->Database->Record["currency"]]["symbol"] = $curtxt;
		}
		foreach ($_total as $key => $item) {
			$_total[$key] = $item["symbol"].sprintf("%0.2f",$item["value"]);
		}
		$_customFields = array();
		$_SWIFT->Database->Query("SELECT cf.title,fieldvalue ".
			"FROM ".TABLE_PREFIX."customfieldvalues as cfv, ".TABLE_PREFIX."customfields as cf, ".TABLE_PREFIX.
			"customfieldgroups as cfg ".
			"WHERE cf.customfieldgroupid = cfg.customfieldgroupid AND cf.customfieldid = cfv.customfieldid".
			"  AND typeid = ".$_SWIFT->User->GetUserID()." AND cfg.grouptype = ".SWIFT_CustomFieldGroup::GROUP_USER.
				" ORDER BY cfg.displayorder,cf.displayorder");
		while ($_SWIFT->Database->NextRecord()) {
			$_customFields[$_SWIFT->Database->Record["title"]] = $_SWIFT->Database->Record["fieldvalue"];
		}
		
		$fromemail = $_SWIFT->User->GetEmailList();
		$fromemail = $fromemail[0];

		$_SWIFT->Template->Assign("headerImage",$_SWIFT->Template->RetrieveHeaderImage(SWIFT_TemplateEngine::HEADERIMAGE_SUPPORTCENTER));
		$_SWIFT->Template->Assign("invoicelist", $invoices);
		$_SWIFT->Template->Assign("total", $_total);
		$_SWIFT->Template->Assign("username", $_SWIFT->User->GetProperty("fullname"));
		$_SWIFT->Template->Assign("primaryemail",$fromemail);
		$_SWIFT->Template->Assign("customfields", $_customFields);
		$_SWIFT->Template->Assign("companyname", $_SWIFT->Settings->getKey("settings","general_companyname"));
		$_SWIFT->Template->Assign("dateofinvoice", date("l jS F, Y",time()));
		$_SWIFT->Template->Assign("dotickets", true);
		$_SWIFT->Template->Assign("dominutes", true);
		$_SWIFT->Template->Assign("footer",$_SWIFT->Settings->getKey("settings","sp_invoicefooter"));
		$_SWIFT->Template->Render("sp_invoice");
	}
	
	public function DateRange() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;
		$wantContents = false;
		
		if (!empty($_POST["fromdate"]) && !empty($_POST["todate"])) {
			$fromdate = strtotime($_POST["fromdate"]);
			$todate = strtotime($_POST["todate"]);
			
			if ($fromdate == null)
				$fromdate = 0;
			if ($todate == null)
				$todate = 2145916800;
				
			$this->Render('SELECT * FROM '. TABLE_PREFIX .'sp_user_payments '.
					' WHERE userid='.intval($_SWIFT->User->GetUserID()).' AND created BETWEEN '.intval($fromdate).
					' AND '.intval($todate).' ORDER BY txid ASC');
		} else {
			SWIFT::Error("SupportPay",$$_SWIFT->Language->Get("sp_stmterror"));
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
		}
	}
	
	public function Index($printMe=null)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;
		
		if (is_numeric($printMe)) {
			$userid = $_SWIFT->User->GetUserID();
			$this->Render('SELECT * FROM '. TABLE_PREFIX .'sp_user_payments '.
				' WHERE userid='.intval($userid).' AND txid IN ('.buildIN(array($printMe)).') ORDER BY txid ASC');
		} else {
			SWIFT::Error("SupportPay",$$_SWIFT->Language->Get("sp_stmterror"));
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
		}
	}
}

?>
