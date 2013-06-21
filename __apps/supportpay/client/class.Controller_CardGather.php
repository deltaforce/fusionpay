<?php

class Controller_CardGather extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Submit() {
		if (true) {
//			session_start();
			include "paypal/cardpay.php";
			
			return true;
		}
		
		return $this->Index();
	}
	
	public function Index()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;
		$done = false;

		$prodUrl = $_SWIFT->Settings->getKey("settings","general_producturl");
		$isLive = $_SWIFT->Settings->getKey("settings","sp_paypallive");
		if (empty($isLive) && $prodUrl == "http://127.0.0.1/kayako_v4/")
		{
			$_POST["cardno"] = "4797603808385108";
			$_POST["cardtype"] = "Visa";
			$_POST["expdate"] = "022015";
			$_POST["cvv2"] = "111";
			$_POST["fname"] = "Test";
			$_POST["sname"] = "User";
			$_POST["street1"] = "1 Main Terrace";
			$_POST["street2"] = "";
			$_POST["city"] = "Wolverhampton";
			$_POST["state"] = "West Midlands";
			$_POST["zip"] = "W12 4LQ";
			$_POST["ctry"] = "GB";
			$_POST["issue"] = "";
		}

		if (empty($_POST)) {
			// Fill out an initial empty array to keep the template happy.
			$_POST = array(
				"cardno" => "", "cardtype" => "Visa", "expdate" => "", "cvv2" => "", "fname" => "",
				"sname" => "", "street1" => "", "street2" => "", "city" => "", "state" => "",
				"zip" => "", "ctry" => "", "issue" => ""
				);
		}
		
		$goodCards = array ( "Visa","MasterCard" );
		if ($_SWIFT->Settings->getKey("settings","sp_currency") == "GBP") {
			$goodCards[] = "Maestro";
			$goodCards[] = "Solo";
		}
		if ($_SWIFT->Settings->getKey("settings","sp_currency") != "CAD") {
			// For Canada, only MasterCard and Visa are accepted.
			$goodCards[] = "Discover";
			$goodCards[] = "Amex";
		}
		$cardList = "";
		foreach ($goodCards as $card) {
			$cardList .= "<option value='".$card."'>".$card."</option>";
		}
		
		if (empty($_POST["ctry"])) $_POST["ctry"] = $_SWIFT->Settings->getKey("settings","sp_paypallocale");
		if (empty($_POST["cardtype"])) $_POST["cardtype"] = "Visa";	// Always available.
		$ctryList = "";
		$ctryXML = simplexml_load_file(SWIFT_MODULESDIRECTORY."/supportpay/resources/iso_3166.xml");
		foreach ($ctryXML->children() as $child) {
			$ccode = strtoupper($child->{"ISO_3166-1_Alpha-2_Code_element"});
			$ctryList .= "<option value='".$ccode."'".
				($_POST["ctry"] == $ccode ? "SELECTED":"").">".ucwords(strtolower($child->{"ISO_3166-1_Country_name"}))."</option>\n";
		}
		$monthList = "";
		for ($ii=1; $ii <= 12; $ii++) {
			$cm2 = sprintf("%02d",$ii);
			$monthList .= "<option value='".$cm2."' ".(isset($_POST["expdate"]) ? ($cm2 == substr($_POST["expdate"],0,2) ? "selected='selected'":"") : "")." />".$cm2."</option>\n";
		}
		$yearList = "";

		for ($ii=date("Y"); $ii <= date("Y")+10; $ii++) {
			$cm2 = sprintf("%04d",$ii);
			$yearList .= "<option value='".$cm2."' ".(isset($_POST["expdate"]) ? ($cm2 == substr($_POST["expdate"],2,4) ? "selected='selected'":"") : "")." />".$cm2."</option>\n";
		}
		
		$cardFields = array();
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_cardtype")."</th><td><select onchange='changeFields(this.options[this.selectedIndex].value);' id='cardtype' name='cardtype' value='".$_POST["cardtype"]."'>".$cardList."</td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_cardno")."</th><td><input type='text' id='cardno' name='cardno' maxlength='16' value='".$_POST["cardno"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_cvv")."</th><td><input type='text' id='cvv2' name='cvv2' maxlength='4' value='".$_POST["cvv2"]."'/></td></tr>";
		$cardFields[] = "<tr id='startDate'><th>".$_SWIFT->Language->Get("sp_cc_startdate")."</th><td><select name='startDateM'>".$monthList."</select>".
			"<select name='startDateY'>".$yearList."</select></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_enddate")."</th><td><select id='expDateM' name='expDateM'>".$monthList."</select>".
			"<select id='expDateY' name='expDateY'>".$yearList."</select></td></tr>";
		$cardFields[] = "<tr id='issue'><th>".$_SWIFT->Language->Get("sp_cc_issue")."</th><td><input type='text' name='issue' maxlength='4' value='".$_POST["issue"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_forename")."</th><td><input type='text' id='fname' name='fname' maxlength='25' value='".$_POST["fname"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_surname")."</th><td><input type='text' id='sname' name='sname' maxlength='25' value='".$_POST["sname"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_street1")."</th><td><input type='text' id='street1' name='street1' maxlength='100' value='".$_POST["street1"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_street2")."</th><td><input type='text' id='street2' name='street2' maxlength='100' value='".$_POST["street2"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_city")."</th><td><input type='text' id='city' name='city' maxlength='40' value='".$_POST["city"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_state")."</th><td><input type='text' id='state' name='state' maxlength='40' value='".$_POST["state"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_zip")."</th><td><input type='text' id='zip' name='zip' maxlength='20' value='".$_POST["zip"]."'/></td></tr>";
		$cardFields[] = "<tr><th>".$_SWIFT->Language->Get("sp_cc_country")."</th><td><select id='ctry' name='ctry' value='".$_POST["ctry"]."'>".$ctryList."</td></tr>";
		$cardFields[] = "<script type='text/javascript'>function changeFields(ct) {".
			"var showIssue = (ct == 'Maestro' || ct == 'Solo') ? '':'none';".
			"document.getElementById('startDate').style.display=showIssue; ".
			"document.getElementById('issue').style.display=showIssue;".
			"}".
			"changeFields('".$_POST["cardtype"]."');</script>";
		$_SWIFT->Template->Assign("cardFields",$cardFields);	
		
		$done = true;
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($_SWIFT->Language->Get("sp_paydetails"));
		$this->Template->Render("sp_header");
		if ($done) $_SWIFT->Template->Render("sp_cardgather");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
}

?>
