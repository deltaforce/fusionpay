<?php

if (!defined('SWIFT_MODULESDIRECTORY')) {
	define('SWIFT_MODULESDIRECTORY', SWIFT_APPSDIRECTORY);
}
if (!defined('APP_SP')) {
	define('APP_SP',"supportpay");
}

require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

class SWIFT_SPFunctions {
private $SWIFT4;

private static $ivBase = "apsetyvaw78a7-9q8xm34507nasiufajhr[q079_(*YP{(*un [87t-89a76-b";

function __construct() {
	$this->SWIFT4 = SWIFT::GetInstance();
}

public function sendOneAccountInvoice($userID, $from, $to, $update = false) {
	$res = false;
	$emailhtml = $this->genAccountInvoice($userID, $from, $to);
	$fromemail = "";

	if (!empty($emailhtml)) {
		// Email the results to the user
		SWIFT_Loader::LoadLibrary('Mail:Mail');
		$mailObj = new SWIFT_Mail();
		
		$Record = $this->SWIFT4->Database->queryFetch("select email from ".TABLE_PREFIX."useremails ".
			"where e.linktypeid = ".intval($userID)." and e.linktype = 1");
		$userEmail = $Record["email"];
		if (!empty($userEmail)) {
			$mailObj->SetToField($userEmail);
			$mailObj->SetFromField($fromEmail, $fromEmail);
			$mailObj->SetSubjectField($this->SWIFT4->Settings->getKey("settings","sp_invoicesubject"));
			$mailObj->SetDataHTML($emailhtml);
			if (!$mailObj->SendMail(true)) {
				$res = false;
				// TODO: Should probably have some kind of notification here too.
				error_log("Unable to send invoice mail to ".$userEmail);
				SWIFT::Error("Unable to send invoice mail to ".$userEmail);
			} else {
				$res = true;
				// It was sent OK. Mark this user as processed.
				if ($update) {
					$this->SWIFT4->Database->Query("update ".TABLE_PREFIX."sp_users set last_invoice = ".$now.
						" where userid = ".$userID);
				}
			}
		} else {
			error_log("No email address found for user ".$userID);
		}
	} else {
		$res = false;
		SWIFT::Error("Unable to render template for account invoices - not sent.");
		error_log("Unable to render template for account invoices - not sent.");
		break;	// Not much point it continuing if the template is broken.
	}
	
	return $res;
}

function showTestPayPalButton($ForSandbox) {
	if ($ForSandbox) {
		$sb = "sb";
	} else {
		$sb = "";
	}
	
	echo '<script type="text/javascript">function encodeToHex(str){
    var r="";
    var e=str.length;
    var c=0;
    var h;
    while(c<e){
        h=str.charCodeAt(c++).toString(16);
        while(h.length<2) h="0"+h;
        r+=h;
    }
    return r;
}</script>';

	echo '<input type="text" class="swifttext" name="sp_paypal'.$sb.'userid" id="sp_paypal'.$sb.'userid" '.
		'value="'.$this->SWIFT4->Settings->Get("sp_paypal".$sb."userid").'" size="30" autocomplete="OFF">';
	
	echo "<div class='rebuttonwide2' style='text-align: center;' onclick=\"UICreateWindow('".
		SWIFT::Get('basename')."/supportpay/Settings/TestPayPal/".intval($ForSandbox)."/'".
		"+encodeToHex(document.Controller_Settingsform.sp_paypal".$sb."userid.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_paypal".$sb."passwd.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_paypal".$sb."sign.value)".
		", 'testpaypal', ".
		"'Test PayPal', '".$this->SWIFT4->Language->Get('loadingwindow')."', 500, 200, true, this);\">Test</div>";
}

function showTestWHMCSButton() {
	echo '<input type="text" class="swifttext" name="sp_whmcs_api_baseURL" id="sp_whmcs_api_baseURL" '.
		'value="'.$this->SWIFT4->Settings->Get("sp_whmcs_api_baseURL").'" size="30" autocomplete="OFF">';

	echo "<div class='rebuttonwide2' style='text-align: center;' onclick=\"UICreateWindow('".
		SWIFT::Get('basename')."/supportpay/Settings/TestWHMCS/'".
		"+encodeToHex(document.Controller_Settingsform.sp_whmcs_api_baseURL.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_whmcs_api_userid.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_whmcs_api_pass.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_whmcs_web_userid.value)+'/'".
		"+encodeToHex(document.Controller_Settingsform.sp_whmcs_web_pass.value)".
		", 'testwhmcs', ".
		"'Test WHMCS', '".$this->SWIFT4->Language->Get('loadingwindow')."', 600, 400, true, this);\">Test</div>";
}

public function sendAccountInvoices($user = null, $update = true) {
	/* Loop through all accounts which use the overdraft facility and
	** email them, even if no tickets were used.
	*/
	$res = true;
	
	if (!$this->SWIFT4->Settings->getKey("settings","sp_odenable")) {
		return false;
	}
	
	$fromEmail = $this->SWIFT4->Settings->getKey("settings","sp_invoicesender");
	if (empty($fromEmail)) {
		error_log("Invoice Sender e-mail address is empty");
		return false;
	}
	
	//		if (empty($this->SWIFT4->Load)) {
	//			$this->SWIFT4->Load = new SWIFT_Loader($this->SWIFT4->Cookie);
	//		}

	$now = time();
	$userList = array();
	$this->SWIFT4->Database->Query("select su.userid, su.last_invoice, e.email from ".
		TABLE_PREFIX."sp_users su, ".TABLE_PREFIX."users u, ".TABLE_PREFIX."useremails e ".
		" where u.userid = su.userid and e.linktypeid = su.userid and e.linktype = 1".
		(!empty($user) ? " and su.userid = ".intval($user) : "").
		" and e.useremailid = (select min(e2.useremailid) from ".TABLE_PREFIX."useremails e2 where e2.linktypeid = su.userid) ".
		"and su.overdraft is not null and su.last_invoice < ".strtotime("-1 month", $now));
	
	// Build and store an array of userids so we can update them later without worrying
	// about overlapping queries.
	while ($this->SWIFT4->Database->NextRecord()) {
		$userList[$this->SWIFT4->Database->Record["userid"]] = $this->SWIFT4->Database->Record;
	}
	
	foreach ($userList as $userID => &$userDetail) {
		$res &= $this->sendOneAccountInvoice($userID,$userDetail["last_invoice"], $now, $update);
	}
	
	return $res;
}

function genAccountInvoice($userid, $startTime, $endTime) {
	// Fetch all transactions for this user
	global $sp_currencylist;
	
	$transactions = array();
	$paid_total = array();
	$tax_total = array();
	$unpaid_minutes = 0;
	$unpaid_count = 0;
	$min_total = 0;
	$tkt_total = 0;
	
	$iUser = $this->SWIFT4->Database->QueryFetch("select u.fullname, coalesce(tg.tgroupid,1) as templategroupid ".
		"from ".TABLE_PREFIX."users u left join ".TABLE_PREFIX."templategroups tg ".
		" on (tg.isenabled = 1 and tg.regusergroupid = u.usergroupid) ",
		" where userid = ".$userid);
	
	$chatName = $this->IsModuleRegistered("LIVECHAT");
	if (!empty($chatName)) {
		$sql = "select up.minutes,up.tickets,up.cost,up.currency,up.comments,up.created,".
			"up.pending,up.ticketid,up.paytype,coalesce(t.subject, c.subject) title,up.processor,up.tax ".
			"from ".TABLE_PREFIX."sp_user_payments up ".
			"left join ".TABLE_PREFIX."tickets t on (t.ticketid = up.ticketid and up.paytype = ".SP_PAYTYPE_TICKET.") ".
			"left join ".TABLE_PREFIX."chatobjects c on (c.chatobjectid = up.ticketid and up.paytype = ".SP_PAYTYPE_LIVESUPPORT.") ".
			"where up.userid = ".$userid." and up.created >= ".intval($startTime)." and up.created < ".$endTime." order by created asc";
	} else {
		$sql = "select up.minutes,up.tickets,up.cost,up.currency,up.comments,up.created,".
			"up.pending,up.ticketid,up.paytype,t.subject title,up.processor,up.tax ".
			"from ".TABLE_PREFIX."sp_user_payments up ".
			"left join ".TABLE_PREFIX."tickets t on (t.ticketid = up.ticketid and up.paytype = ".SP_PAYTYPE_TICKET.") ".
			"where up.userid = ".$userid." and up.created >= ".intval($startTime)." and up.created < ".$endTime." order by created asc";
	}

	$this->SWIFT4->Database->Query($sql, 2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		$thisRec = &$this->SWIFT4->Database->Record2;
		
		switch ($thisRec["processor"]) {
			case SP_PROCESSOR_NONE:	// 0
				$thisRec["processor"] = $this->SWIFT4->Language->Get("sps_none");
				break;
			case SP_PROCESSOR_PAYPAL:	// 1
				$thisRec["processor"] = $this->SWIFT4->Language->Get("sps_paypal");
				break;
			case SP_PROCESSOR_WORLDPAY: // 2
				$thisRec["processor"] = $this->SWIFT4->Language->Get("sps_worldpay");
				break;
			case SP_PROCESSOR_AUTHORIZE: // 5
				$thisRec["processor"] = $this->SWIFT4->Language->Get("sps_authorizenet");
				break;
			default:
				$thisRec["processor"] = $this->SWIFT4->Language->Get("sps_unknown");
		}

		$thisRec["currency"] = $sp_currencylist[$thisRec["currency"]]["symbol"];
		$thisRec["created"] = date(SWIFT_Date::GetCalendarDateFormat(),	$thisRec["created"]);
		$thisRec["cost"] = sprintf("%0.2f", $thisRec["cost"]);
		$thisRec["tax"] = sprintf("%0.2f", $thisRec["tax"]);
		if (empty($thisRec["pending"])) {
			$min_total += $thisRec["minutes"];
			$tkt_total += $thisRec["tickets"];
		}
		
		$transactions[] = $this->SWIFT4->Database->Record2;
		
		$currency = $thisRec["currency"];
		if (empty($tax_total[$currency])) {
			$tax_total[$currency] = 0;
		}
		if (empty($paid_total[$currency])) {
			$paid_total[$currency] = 0;
		}
		$tax_total[$currency] += $thisRec["tax"];
		$paid_total[$currency] += $thisRec["cost"];
	}
	
	// Just query sp_ticket_paid directly here.
	$unpaid = array();
	$this->SWIFT4->Database->Query("select * from ".TABLE_PREFIX."sp_ticket_paid tp ".
		"where userid = ".$userid." and (minutes < bill_minutes or tickets = 0)",2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		$unpaid[] = $this->SWIFT4->Database->Record2;
		$unpaid_count++;
		$unpaid_minutes += ($this->SWIFT4->Database->Record2["bill_minutes"] - 
			$this->SWIFT4->Database->Record2["minutes"]);
	}

	if (empty($paid_total)) {
		$currency = $this->SWIFT4->Settings->getKey("settings","sp_currency");
		$paid_total[$currency] = "0.00";
		$tax_total[$currency] = "0.00";
	} else {
		foreach ($tax_total as $currency => &$total) {
			$total = sprintf("%0.2f", $total);
		}
		foreach ($paid_total as $currency => &$total) {
			$total = sprintf("%0.2f", $total);
		}
	}
	
	if (empty($transactions)) {
		$transactions[] = array("minutes" => "", "tickets" => "", "cost" => "", "tax" => "",
			"created" => "", "comments" => "<em>No transactions during the invoice period.",
			"pending" => null, "currency" => ""
			);
	}
	
	// Process the template (bearing in mind this is from the cron)
	$Credit = $this->getUserCredit($userid);
	$Credit["minutes"] -= $Credit["overdraft"];
	
	$this->SWIFT4->Template->Assign("transactions", $transactions);
	$this->SWIFT4->Template->Assign("pay_totals", $paid_total);
	$this->SWIFT4->Template->Assign("tax_totals", $tax_total);
	$this->SWIFT4->Template->Assign("min_total", $min_total);
	$this->SWIFT4->Template->Assign("tkt_total", $tkt_total);
	$this->SWIFT4->Template->Assign("min_current", $Credit["minutes"]);
	$this->SWIFT4->Template->Assign("tkt_current", $Credit["tickets"]);
	$this->SWIFT4->Template->Assign("unpaid_minutes", $unpaid_minutes);
	$this->SWIFT4->Template->Assign("unpaid_count", $unpaid_count);
	$this->SWIFT4->Template->Assign("unpaid", $unpaid);
	$this->SWIFT4->Template->Assign("companyname",$this->SWIFT4->Settings->getKey("settings","general_companyname"));
	$this->SWIFT4->Template->Assign("footer",$this->SWIFT4->Settings->getKey("settings","sp_invoicefooter"));
	
	$this->SWIFT4->Template->Assign("username", $iUser["fullname"]);
	//		$this->SWIFT4->Template->Assign("primaryemail", $iUser["email"]);
	$this->SWIFT4->Template->Assign("dateofinvoice", date(SWIFT_Date::GetCalendarDateFormat(),time()));
	$this->SWIFT4->Template->Assign("startdate", date(SWIFT_Date::GetCalendarDateFormat(),$startTime));
	$this->SWIFT4->Template->Assign("enddate", date(SWIFT_Date::GetCalendarDateFormat(),$endTime));
	$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
	$this->SWIFT4->Template->Assign("dominutes",($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH));
	$this->SWIFT4->Template->Assign("dotickets",($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH));

	// TODO: Assign "customfields"
	$this->SWIFT4->Template->SetTemplateGroupID($iUser["templategroupid"]);
	$this->SWIFT4->Template->Assign("headerImage",$this->SWIFT4->Template->RetrieveHeaderImage(SWIFT_TemplateEngine::HEADERIMAGE_SUPPORTCENTER));
	return $this->SWIFT4->Template->Get("sp_email_invoice", SWIFT_TemplateEngine::TYPE_DB);
}

function nvl(&$var, $default = "") {
	return (!empty($var)) ? $var : $default;
}

// Completely cancel a pre-authorised transaction.
function authTransVoid($transid, $userid) {
	$resArray = array();
	
	$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
	$gwPath = null;
	
	switch ($gateway) {
		case SP_PROCESSOR_WORLDPAY:
			break;
		case SP_PROCESSOR_PAYPAL:
			SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
			$PP = new SWIFT_SPPayPal;
			
			$resArray = $PP->DoVoid($transid);
			break;
		case SP_PROCESSOR_AUTHORIZE:
			break;
	}

	if (isset($resArray["ACK"]) && strtoupper($resArray["ACK"]) == "SUCCESS") {
		$this->SWIFT4->Database->Query("delete from ".TABLE_PREFIX."sp_user_payments where paytype = ".SP_PAYTYPE_DEFERRED.
			" and userid = ".intval($userid)." and proc_txid = '".$this->SWIFT4->Database->Escape($transid)."'");
	} else {
		// TODO: Send error email saying unable to void a transaction.
	}
}

// Complete a pre-authorised transaction.
function authTransComplete($transid, $amount, $taxamt, $currency, $minutes) {
	$resArray = array();

	$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
	$gwPath = null;
	if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
		error_log("Claiming authorised payment Tx#".$transid." for ".$currency." ".
			$amount.", ".$minutes." mins.");
	}
	
	switch ($gateway) {
		case SP_PROCESSOR_WORLDPAY:
			break;
		case SP_PROCESSOR_PAYPAL:
			SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
			$PP = new SWIFT_SPPayPal;
			$resArray = $PP->DoCapture($transid, $amount, $currency);
			break;
		case SP_PROCESSOR_AUTHORIZE:
			SWIFT_Loader::LoadLibrary('SupportPay:SPAuthNet', "supportpay");
			$AP = new SWIFT_SPAuthNet;
			$resArray = $AP->DoCapture($transid, $amount, $currency);
			$AP = null;
			break;
	}

	// Don't filter on PayType - Authorize.net sends the IPN *before* returning from the DoCapture
	// call, so the paytype may already have been changed. Minutes, RemMinutes etc. still need
	// to be updated so that the correct credit level is set.
	
	if (isset($resArray["ACK"]) && strtoupper($resArray["ACK"]) == "SUCCESS") {
		// TODO: Check AMT, FEEAMT and TAXAMT .
		$Rec = $this->SWIFT4->Database->queryFetch("select minutes, rem_minutes, paytype from ".TABLE_PREFIX."sp_user_payments ".
			" where proc_txid = '".$this->SWIFT4->Database->Escape($transid)."'");
		error_log("In AuthTrans: Current of ".$transid." mins=".$Rec["minutes"].", rem_mins=".$Rec["rem_minutes"].", paytype=".$Rec["paytype"]);

		$sql = "update ".TABLE_PREFIX."sp_user_payments set minutes = ".
			intval($minutes).", rem_minutes=".intval($minutes).", paytype=null, proc_txid='".
			$this->SWIFT4->Database->Escape($resArray["TRANSACTIONID"]).
			"', cost=".$amount.", tax=".$taxamt.", expiry=null, fee=".
			(!isset($resArray["FEEAMT"]) ? "null" : $resArray["FEEAMT"]).
			" where proc_txid = '".$this->SWIFT4->Database->Escape($transid)."'";

		if (!$this->SWIFT4->Database->Execute($sql)) {
			$this->SPErrorLog("Failed to update Authorised Payment", "Tx# ".$resArray["TRANSACTIONID"]);
			error_log($sql);
		}
	} else {
		// TODO: Send error email saying unable to capture a transaction.
		error_log("Capture failed for Tx#".$transid);
		error_log(print_r($resArray, true));
	}
	
	return $resArray;
}

function writeSetting($_settingKey, $_settingValue) {
	if (is_array($_settingValue)) {
		$_serializedContainer = serialize($_settingValue);
		$this->SWIFT4->Settings->UpdateKey('settings', $_settingKey, 'SERIALIZED:' . $_serializedContainer);
	} else {
		$this->SWIFT4->Settings->UpdateKey('settings', $_settingKey, $_settingValue);
	}
}

function SP_ErrorBox($error) {
	$this->SWIFT4->UserInterface->Error("SupportPay",$error);
}

function getSwiftURL($modName, $funcName) {
	switch (intval(SWIFT_VERSION)) {
		case 3:
			return "_m=" . htmlspecialchars($modName) . "&_a=" . htmlspecialchars($funcName);
			break;
		case 4:
			return "/" . htmlspecialchars($modName)."/".htmlspecialchars($funcName)."/Main";
			break;
	}
}

// Just enough of the payload to display the template.
function mapAuthorizeNetIPN(&$postDetails) {
	
	switch ($postDetails["x_response_code"]) {
		case 1: $postDetails["PAYMENTSTATUS"] = "COMPLETED"; break;
		case 2: $postDetails["PAYMENTSTATUS"] = "REFUSED"; break;
		case 3: $postDetails["PAYMENTSTATUS"] = "ERROR"; break;
		case 4: $postDetails["PAYMENTSTATUS"] = "PENDING"; break;
	}

	$postDetails["EMAIL"] = $postDetails["x_email"];
	$postDetails["DEFERRED"] = ($postDetails["x_type"] == "auth_only" ? 1 : 0);
}

function mapWorldPayIPN(&$postDetails) {
	// Must be 'REFUSED' rather than 'DECLINED' - is matched in template do_pay.
	$postDetails["PAYMENTSTATUS"] = ($postDetails["transStatus"] == "Y" ? "COMPLETED":"REFUSED");
	$postDetails["EMAIL"] = $postDetails["email"];
}

function roundDown($value) {
	return floor($value*100)/100;
}

function getCardProcList($activegw) {
	global $sp_gateways;
	
	$res = "";
	
	foreach ($sp_gateways as $gwid => $gwtext) {
		$res .= "<option value='".$gwid."'".
			($activegw == $gwid ? " selected='selected'":"").">".htmlspecialchars($gwtext)."</option>\n";
	}
	
	return $res;	
}

public function getWHMCSPushModes($activeMode) {
	global $sp_WHMCS_Modes;
	
	$res = "";	
	foreach ($sp_WHMCS_Modes as $modeid => $modeText) {
		$res .= "<option value='".$modeid."'".
			($activeMode == $modeid ? " selected='selected'":"").">".htmlspecialchars($modeText)."</option>\n";
	}
	
	return $res;	
}

public function getWHMCSDate($active) {
	global $sp_WHMCS_Dates;
	
	$res = "";	
	foreach ($sp_WHMCS_Dates as $modeid => $modeDetails) {
		$res .= "<option value='".$modeid."'".
			($active == $modeid ? " selected='selected'":"").">".htmlspecialchars($modeDetails["name"])."</option>\n";
	}
	
	return $res;	
}

public function getUserGroupList($activeGroup) {
	$res = "";

	$this->SWIFT4->Database->Query("select usergroupid, title from ".TABLE_PREFIX."usergroups where grouptype=1 order by title",2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		$res .= "<option value='".$this->SWIFT4->Database->Record2["usergroupid"]."'".
			($activeGroup == $this->SWIFT4->Database->Record2["usergroupid"] ? " selected='selected'":"").">".
			htmlspecialchars($this->SWIFT4->Database->Record2["title"])."</option>\n";
	}
	
	return $res;	
}

function getPayableDepts() {
	$res = $this->SWIFT4->Settings->getKey("settings","sp_chargedepts");
	if (!is_array($res)) $res = array();
	return $res;
}

function getDeptAccept($deptId) {
	$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
	$Rec = $this->SWIFT4->Database->QueryFetch("select acceptmins, accepttkts from ".TABLE_PREFIX."sp_departments ".
		"where departmentid = ".intval($deptId));
	if (!empty($Rec)) {
		$accept &= ($Rec["acceptmins"] ? -1 : ~ SP_ACCEPT_MINUTES);
		$accept &= ($Rec["accepttkts"] ? -1 : ~ SP_ACCEPT_TICKETS);
	}
	
	return $accept;
}

function getPayableRates() {
	$deptRates = array();
	
	$this->SWIFT4->Database->Query("select d.departmentid, coalesce(minuterate,1) as minuterate ".
		"from ".TABLE_PREFIX."departments d ".
		"left join ".TABLE_PREFIX."sp_departments sd on (sd.departmentid = d.departmentid)");
	while ($this->SWIFT4->Database->NextRecord()) {
		$deptRates[$this->SWIFT4->Database->Record["departmentid"]] = $this->SWIFT4->Database->Record["minuterate"];
	}

	return $deptRates;
}

// Allow the admin to choose which status code corresponds with "Closed".
function genStatusSelect() {
	$this->SWIFT4->Database->Query("select ticketstatusid,title from ".TABLE_PREFIX."ticketstatus order by displayorder",2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		echo "<option value='".$this->SWIFT4->Database->Record2["ticketstatusid"]."'".
			(($this->SWIFT4->Database->Record2["ticketstatusid"] == $this->SWIFT4->Settings->getKey("settings","sp_statusclosed")) ? " selected='selected'":"").">".
			htmlspecialchars($this->SWIFT4->Database->Record2["title"]." (".$this->SWIFT4->Database->Record2["ticketstatusid"].")")."</option>\n";
	}
}

function genDeptSelect() {
	$deptList = $this->SWIFT4->Settings->getKey("settings","sp_chargedepts");
	if (!is_array($deptList)) {
		$deptList = array();
	}
	
	$allDepts = $this->SWIFT4->Cache->Get('departmentcache');
	$moduleName = (class_exists('SWIFT_Module') ? 'module' : 'app');
	
	foreach ($allDepts as &$thisDept) {
		echo "<option value='".$thisDept["departmentid"]."'".
			(in_array($thisDept["departmentid"],$deptList) ? " selected='selected'":"").">".
			htmlspecialchars($thisDept["title"]." (".$thisDept["department".$moduleName].")")."</option>\n";
	}
}

function customerCanPay($userid, $departmentid) {
	// Chech if this customer has enough credit, taking into account
	// discounts, manager etc.
	
	$Credit = $this->getUserCredit($userid);
	$accept = $this->getDeptAccept($departmentid);

	$reqMinutes = $this->SWIFT4->Database->QueryFetch("select coalesce(spd.minuterate, 1) minuterate, ".
		"coalesce(spd.mins_to_post, 0) mins_to_post, d.title ".
		"from ".TABLE_PREFIX."departments d left join ".TABLE_PREFIX."sp_departments spd using (departmentid) ".
		"where departmentid = ".$departmentid, 2);

	$reqMinutes["mins_to_post"] *= $reqMinutes["minuterate"];
	$reqMinutes["mins_to_post"] *= (100 - $Credit["discount"]) / 100;
	$reqMinutes["mins_to_post"] = round($reqMinutes["mins_to_post"] + 0.5, 0);		// 0.5 to round up
	
	// Quick paranoia check.
	if ((($accept & SP_ACCEPT_TICKETS) && $Credit["tickets"] >= 1) ||
		($accept & SP_ACCEPT_MINUTES) && $Credit["minutes"] >= $reqMinutes["mins_to_post"]) 
	{
		return true;
	}

	return false;
}

// Generate a single-select dropdown with departments.
function genTicketStatusSelect($whichSetting = "sp_gk_fromstatus") {
	$status = $this->SWIFT4->Settings->getKey("settings", $whichSetting);
	
	$this->SWIFT4->Database->Query("select ticketstatusid,title from ".TABLE_PREFIX.
		"ticketstatus order by displayorder",2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		echo "<option value='".$this->SWIFT4->Database->Record2["ticketstatusid"]."'".
			(($this->SWIFT4->Database->Record2["ticketstatusid"] == $status) ? " selected='selected'":"").">".
			htmlspecialchars($this->SWIFT4->Database->Record2["title"])."</option>\n";
	}
}

public function checkPerms($permName) {
	$res = false;

	$deptID = $this->SWIFT4->Staff->GetProperty('staffgroupid');
	$permValue = $this->SWIFT4->Settings->getKey("settings",$permName);
	if (is_array($permValue)) {
		$res = in_array($deptID, $permValue);
	}
	
	return $res;
}

function genRandomPassword($length) {
	$chars = "234567890abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
	$i = 0;
	$password = "";
	while ($i <= $length) {
		$password .= $chars{mt_rand(0,strlen($chars))};
		$i++;
	}
	return $password;
}

function showStatus() {
	global $sp_license, $sp_licensetxt;
	
		$errmsg = $this->checkLicense(false);

		if (!empty($errmsg)) {
			echo "<div style='border: 2px solid red; text-align: center; font-weight: bold; font-size: 125%; background-color: white;'>".
				"<p style='font-size: 125%;'>Your SupportPay license has a problem:</p><p>".$errmsg."</p></div>";
		}
	}

// Expects an array of email addresses to check for.
function fetchVMSales($emails = array()) {
	global $_DB, $sp_license;
	
	$errmsg = "";

	$vmName = $this->SWIFT4->Settings->getKey("settings","sp_vm_dbname");
	$vmUserName = $this->SWIFT4->Settings->getKey("settings","sp_vm_username");
	$vmPassword = $this->SWIFT4->Settings->getKey("settings","sp_vm_password");
	$vmVendorId = $this->SWIFT4->Settings->getKey("settings","sp_vm_vendorid");
	$vmHost = $this->SWIFT4->Settings->getKey("settings","sp_vm_dbhost");
	
	if ((!empty($vmName)) && (!empty($vmUserName)) && 
		(!empty($vmPassword)) && (!empty($vmVendorId)) &&
		(!empty($vmHost)))
	{
		if ($sp_license["status"] != SP_LICENSE_GOOD) {
			return;
		}
		
		// Try to make a connection.
		$db = @mysql_connect($vmHost,$vmUserName,$this->getSecureSetting("sp_vm_password"), true);
		if ($db) {
			mysql_select_db ($vmName);
			
			$in = "";
			if (count($emails) > 0) $in = "and u.email in (".buildIN($emails).")";
			
			$sql = "select o.order_id,o.order_number, i.product_id, i.product_quantity, i.product_final_price, i.order_item_currency, i.order_item_sku,
						u.name, u.email, u.username, u.password
					 from jos_vm_orders o,jos_users u,jos_vm_order_item i, jos_vm_product p
					where o.order_status = 'C' and u.id = o.user_id and i.order_id = o.order_id
					and p.product_id = i.product_id ".$in."
					and i.product_id in (select product_id from jos_vm_product_mf_xref where manufacturer_id = ".intval($this->SWIFT4->Settings->getKey("settings","sp_vm_vendorid")).")";
			
			$qry = @mysql_query($sql,$db);
			if ($qry) {
				while ($row = mysql_fetch_array($qry, MYSQL_ASSOC)) {
					if (preg_match("/(\d+)/", $row["order_item_sku"], $items)) {
						$pkgid = intval($items[1]);
						if ($pkgid > 0) {
							// Look up the package.
							$Pkg = $this->SWIFT4->Database->QueryFetch("select minutes,tickets from ".TABLE_PREFIX."sp_packages where pkgid = ".$pkgid);
							if (isset($Pkg["minutes"])) {

								// Need to insert a sale. First, look up the owning user.
								$sql = "select linktypeid as userid from ".TABLE_PREFIX."useremails where email = '".
									$this->SWIFT4->Database->Escape($row["email"])."'";
								$Rec = $this->SWIFT4->Database->QueryFetch($sql);
								
								if (!isset($Rec["userid"])) {
									// User doesn't exist. Must create.
									if (method_exists('SWIFT_Loader','LoadModel')) {
										SWIFT_Loader::LoadModel('User:User');
									} else {
										SWIFT_Loader::LoadLibrary('User:User');
									}
									error_log("User doesn't exist. Need to create.");
									
									$userGroupRec = $this->SWIFT4->Database->QueryFetch("select min(usergroupid) usergroupid from ".TABLE_PREFIX.
										"usergroups where grouptype = 1");

									$_SWIFT_UserObject = SWIFT_User::Create($userGroupRec["usergroupid"], false, SWIFT_User::SALUTATION_NONE, $row["name"], '', '', true,
										false, array($row["email"]), false,$this->SWIFT4->Language->GetLanguageID(), false, false, false, false, false, true, true);
									
									$Rec["userid"] = $_SWIFT_UserObject->GetUserID();
								}
								
								// Create the SupportPay records if necessary.
								$this->checkUserExists($Rec["userid"],$errmsg);
								
								$txid = $this->addPayment($errmsg, $Rec["userid"], $Pkg["minutes"], $Pkg["tickets"], $row["product_quantity"] * $row["product_final_price"],
									$row["name"],"From VirtueMart",
									$pkgid,null,null,null,null);
								if ($txid) {
									// Entered OK. Mark the VirtueMart sale as 'Shipped'.
									error_log("update jos_vm_orders set order_status = 'S' where order_id = ".$row["order_id"]);
									mysql_query("update jos_vm_orders set order_status = 'S' where order_id = ".$row["order_id"],$db);
									mysql_query("update jos_vm_order_item set order_status = 'S' where order_id = ".$row["order_id"],$db);
									mysql_query("insert into jos_vm_order_history (order_id, order_status_code, date_added, customer_notified, comments)
											values (".$row["order_id"].",'S',".time().",1,'Accepted by SupportPay')",$db);
								} else {
									$errmsg = "Unable to add order from VirtueMart : ".$errmsg;
								}
							} else {
								$errmsg = "Package '".$pkgid."' doesn't exist.";
							}
						} else {
							$errmsg = "Unable to interpret VirtueMart SKU '".$items[1]."'";
						}
					}
				}
				mysql_free_result($qry);
			} else {
				$errmsg = "Failed to get VirtueMart data : " . mysql_error();
			}

			mysql_close($db);
			
			// Reinstate the most-current link to work around a Kayako bug
			// $db = mysql_connect($_DB["hostname"].":".$_DB["port"],$_DB["username"],$_DB["password"], false);
			//				mysql_close($db);
		} else {
			$errmsg = "Failed to get VirtueMart connection : " . mysql_error();
		}
	}
	
	if ($errmsg != "") {
		if (count($emails) == 0) {
			$this->SP_ErrorBox($errmsg);
		} else {
			error_log($errmsg);
		}
	}
}

function genSGSelect($permName) {
	$deptList = $this->SWIFT4->Settings->getKey("settings",$permName);
	if (!is_array($deptList)) {
		$this->writeSetting($permName,array());
		$deptList = array();
	}
	
	$this->SWIFT4->Database->Query("select staffgroupid,title from ".TABLE_PREFIX."staffgroup order by title",2);
	while ($this->SWIFT4->Database->NextRecord(2)) {
		echo "<option value='".$this->SWIFT4->Database->Record2["staffgroupid"]."'".
			(in_array($this->SWIFT4->Database->Record2["staffgroupid"],$deptList) ? " selected='selected'":"").">".
			htmlspecialchars($this->SWIFT4->Database->Record2["title"])."</option>\n";
	}
}

function genWidgetSelect() {
	$styles = array($this->SWIFT4->Language->Get("sp_ws_separate"),$this->SWIFT4->Language->Get("sp_ws_combined"));

	foreach ($styles as $sid => $sname) {
		echo "<option value='".$sid."'".
			($sid == $this->SWIFT4->Settings->getKey("settings","sp_widgetstyle") ? " selected='selected'":"").">".
			htmlspecialchars($sname)."</option>\n";
	}
}

function visibleLink($url, $title, $text, $onClick = null) {
	$res = "<a href='".($url == null ? "javascript: void(0);" : (SWIFT::Get('basename') . $url))."'".
		(isset($onClick) ? " onclick='".$onClick."'":"")." title='".$title."'>".
		$text." <img style='vertical-align: middle; padding-right: 0.3em; border: none;' src='";
	if (is_object($this->SWIFT4->User)) {
		$res .= SWIFT::Get('themepathimages')."doublearrowsnav.gif";
	} else {
		$res .= SWIFT::Get('themepathimages')."icon_newwindow_gray.png";
	}
	$res .= "' /></a>";
	
	return $res;
}

function formatMTP($text) {
	// Singulars, lowercase
	$text = str_replace("{minute}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_minutetxt")),$text);
	$text = str_replace("{ticket}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_tickettxt")),$text);
	$text = str_replace("{package}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_packagetxt")),$text);

	// Plurals, lowercase
	$text = str_replace("{minutes}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_minutestxt")),$text);
	$text = str_replace("{tickets}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_ticketstxt")),$text);
	$text = str_replace("{packages}",strtolower($this->SWIFT4->Settings->getKey("settings","sp_packagestxt")),$text);

	// Singulars, capitalised
	$text = str_replace("{Minute}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_minutetxt"))),$text);
	$text = str_replace("{Ticket}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_tickettxt"))),$text);
	$text = str_replace("{Package}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_packagetxt"))),$text);
	
	// Plurals, capitalised
	$text = str_replace("{Minutes}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_minutestxt"))),$text);
	$text = str_replace("{Tickets}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_ticketstxt"))),$text);
	$text = str_replace("{Packages}",ucfirst(strtolower($this->SWIFT4->Settings->getKey("settings","sp_packagestxt"))),$text);
	
	return $text;
}

function findStaffMenu() {
	$staffMenu = SWIFT::Get('staffmenu');
	
	foreach ($staffMenu as $baridx => $bar) {
		if ($bar[0] == "SupportPay") {
			return $baridx;
		}
	}
	
	return -1;
}

function findAdminBar($barTitle) {
	$adminBar = SWIFT::Get('adminbar');

	foreach ($adminBar as $baridx => $bar) {
		if ($bar[0] == $barTitle) {
			return $baridx;
		}
	}
	
	return -1;
}

function getUserCredit($userid,$nopending = false)
{
	global $sp_license;

	if ($sp_license["status"] == SP_LICENSE_NONE) {
		$this->readLicense($this->SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
	}
	
	$this->checkUserExists($userid, $errmsg);

	// Get current stored credit-level information.
	if ($sp_license["allow_accounts"] && $this->SWIFT4->Settings->getKey("settings","sp_amenable")) {
		$sql = "SELECT me.discount,coalesce(am.minutes,0)+coalesce(me.minutes,0) as minutes, ".
			" coalesce(am.tickets,0)+coalesce(me.tickets,0) as tickets,coalesce(am.acctmgr,0) as acctmgr, ".
			" coalesce(me.overdraft, am.overdraft) as overdraft ".
			" from ".TABLE_PREFIX."sp_users as me LEFT JOIN ".TABLE_PREFIX."sp_users as am ".
			"ON (am.userid = me.payerid AND am.acctmgr=1 and am.userid != me.userid) WHERE me.userid=".intval($userid);
		$Record = $this->SWIFT4->Database->QueryFetch($sql);
	} else { 
		$Record = $this->SWIFT4->Database->QueryFetch("SELECT minutes,tickets,discount,0 as acctmgr, overdraft ".
			"FROM ".TABLE_PREFIX."sp_users WHERE userid = ".intval($userid));
	}
	
	if (empty($Record["minutes"])) $Record["minutes"] = 0;
	if (empty($Record["tickets"])) $Record["tickets"] = 0;
	if (empty($Record["discount"])) $Record["discount"] = 0;
	if (empty($Record["overdraft"])) $Record["overdraft"] = 0;
	
	if ($this->SWIFT4->Settings->getKey("settings","sp_odenable")) {
		$Record["minutes"] += $Record["overdraft"];
	} else {
		$Record["overdraft"] = 0;
	}

	// Now subtract outstanding payments - closed, billable tickets which need paid.
	if (!$nopending) {
		$_payDepts = buildIN($this->getPayableDepts());

		if ($sp_license["allow_accounts"] && $this->SWIFT4->Settings->getKey("settings","sp_amenable")) {
			// Just my tickets, or tickets for account dependents too?
			$userClause = " in (select userid from ".TABLE_PREFIX."sp_users where userid = ".intval($userid).
				" or payerid = ".intval($userid).")";
		} else {
			$userClause = " = ".intval($userid);
		}

		// Tickets
		if (!$this->SWIFT4->Settings->getKey("settings","sp_billimmediately")) {
			$closedClause = " AND t.ticketstatusid = ".intval($this->SWIFT4->Settings->getKey("settings","sp_statusclosed"))." ";
		} else {
			$closedClause = "";
		}
			
		$Debit = $this->SWIFT4->Database->QueryFetch("SELECT sum((1.0*tp.bill_minutes)-(1.0*tp.minutes)) as timebillable FROM ".TABLE_PREFIX."tickets as t,
					".TABLE_PREFIX."sp_ticket_paid as tp
					WHERE tp.ticketid = t.ticketid AND tp.userid = t.userid AND tp.paytype = ".SP_PAYTYPE_TICKET."
					".$closedClause." and t.userid ".$userClause."
					and t.departmentid in (".$_payDepts.") and tp.tickets = 0 and (1.0*tp.bill_minutes)-(1.0*tp.minutes) > 0");
		if (!empty($Debit)) {
			$Record["minutes"] -= $Debit["timebillable"];
		}
		
		// Live support
		$chatName = $this->IsModuleRegistered("LIVECHAT");
		if (!empty($chatName)) {
			if (method_exists('SWIFT_Loader','LoadModel')) {
				SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
			} else {
				SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
			}
			$Debit = $this->SWIFT4->Database->QueryFetch("SELECT sum(tp.bill_minutes-tp.minutes) as timebillable FROM ".TABLE_PREFIX."chatobjects as c,
					".TABLE_PREFIX."sp_ticket_paid as tp
					WHERE tp.ticketid = c.chatobjectid AND tp.userid = c.userid AND tp.paytype = ".SP_PAYTYPE_LIVESUPPORT."
					AND c.chatstatus = ".SWIFT_Chat::CHAT_ENDED." and chattype = ".SWIFT_Chat::CHATTYPE_CLIENT.
					" and c.userid ".$userClause."
					and c.departmentid in (".$_payDepts.") and tp.tickets = 0 and tp.bill_minutes-tp.minutes > 0");
			if (!empty($Debit)) {
				$Record["minutes"] -= $Debit["timebillable"];
			}
		}
	}
	return $Record;
}

function readLicense($key, &$results) {
	$results["staff"] = 999999;
	$results["site"] = $_SERVER["HTTP_HOST"];
	$results["sitelist"] = array($_SERVER["HTTP_HOST"]);
	$results["type"] = "Free";
	$results["start"] = 0;
	$results["allow_accounts"] = true;
	$results["allow_affiliate"] = true;
	$results["allow_wpp"] = true;
	$results["allow_whmcs"] = true;
	$results["allow_nobranding"] = true;
	$results["status"] = SP_LICENSE_GOOD;
	$results["death"] = $results["expiry"] = 2147483647;
}

function checkLicense($silent=false) {
	global $sp_license;
		$this->readLicense("",$sp_license);
		
	return "";
}

// Show a pop-up window to choose a user. The supplied one (in staff) doesn't work.
function chooseUser($formname, $ctrlid, $ctrlname) {
	echo "onClick=\"javascript:popupInfoWindow('".SWIFT::Get('swiftpath')."staff/index.php?" . getSwiftURL('supportpay','userlookup') . "&".
		"fname=$formname&cid=$ctrlid&nid=$ctrlname&sessionid=".$this->SWIFT4["session"]["sessionid"]."');\"";
}

// Used for affiliate IDs and Staff Payments
function gen_guid($table = null, $column = null) {
	do {
		$uuid = substr(md5(uniqid(mt_rand(), true)),0,6);
		if (isset($table) && isset($column)) {		
			$Rec = $this->SWIFT4->Database->QueryFetch("select 1 ex from ".$this->SWIFT4->Database->Escape(TABLE_PREFIX.$table).
				" WHERE ".$this->SWIFT4->Database->Escape($column)." = '".$this->SWIFT4->Database->Escape($uuid)."'");
			if (empty($Rec["ex"])) return $uuid;
			else error_log($uuid . " is a duplicate! Generating another.");
		} else {
			return $uuid;
		}
	} while (true);
}

// Check that this user's details exist in swsp_user_id and add them if not.
public function checkUserExists($userid, &$errmsg, $addPackages = true)	{
	global $sp_license;
	
	$result = false;
	$errmsg = "";
	$usergroupid = null;

	// Check that this is a valid user.	
	$Record = $this->SWIFT4->Database->QueryFetch("SELECT usergroupid from ".TABLE_PREFIX."users where userid=".
		intval($userid));
	if (empty($Record)) {
		$errmsg="No such user.";
		return false;
	}
	
	$usergroupid = $Record["usergroupid"];
	
	// Now check to see that this user exists in our extended user attributes table.
	$Record = $this->SWIFT4->Database->QueryFetch("SELECT 1 from ".TABLE_PREFIX."sp_users WHERE userid=".
		intval($userid));

	if (empty($Record)) {
		$payerid = 'NULL';
		$overdraft = 'NULL';
		
		if ($this->SWIFT4->Settings->Get('sp_odenable')) {
			$overdraft = intval($this->SWIFT4->Settings->Get('sp_oddefault'));
		}
		
		if ($sp_license["allow_accounts"] && $this->SWIFT4->Settings->getKey("settings","sp_am_native")) {
			// Then we're using the Kayako Organizations system. If this user has an organization set,
			// get any manager.
			$Record = $this->SWIFT4->Database->queryFetch("select m.userid from ".TABLE_PREFIX."users u, ".
				TABLE_PREFIX."users m, ".TABLE_PREFIX."sp_users spu where m.userorganizationid = u.userorganizationid ".
				"and spu.userid = m.userid and spu.acctmgr = 1 and u.userorganizationid > 0 and u.userid = ".$userid);
			if (!empty($Record["userid"])) {
				// Then we have an existing org manager.
				$payerid = $Record["userid"];
			}
		}
		$guid = $this->gen_guid("sp_users","guid");
		if (!$this->SWIFT4->Database->Query("INSERT INTO ".TABLE_PREFIX."sp_users (userid,guid,payerid,overdraft) VALUES (".
			intval($userid).",'".$this->gen_guid("sp_users","guid")."',".$payerid.",".$overdraft.")"))
		{
			$errmsg = $this->SWIFT4->Database->FetchLastError();
		} elseif ($addPackages) {
			$Record = $this->SWIFT4->Database->QueryFetch("SELECT 1 from ".TABLE_PREFIX."sp_users WHERE userid=".intval($userid));
			if (!empty($Record)) {
				$errmsg = "";
				$result = true;
				// Additional user details are now there. Let's see if there are any startup packages for this user's group.
				if ($this->SWIFT4->Database->Query("SELECT * from ".TABLE_PREFIX."sp_packages ".
					"WHERE startup IN (".$usergroupid.",-1) AND ".
					"(pkg_expire > ".time()." or pkg_expire is null) AND enabled=1",3))
				{
					$addPkgs = array();
					while ($this->SWIFT4->Database->NextRecord(3)) {
						$addPkgs[] = $this->SWIFT4->Database->Record3;
					}
					foreach ($addPkgs as $thisPackage) {
						$this->addPayment($errmsg, $userid,$thisPackage["minutes"],
							$thisPackage["tickets"],0,$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname")),
							$thisPackage["title"],$thisPackage["pkgid"],null,null,null);
					}
					
					$this->updateUserCredits($userid, $errmsg);
				} else {
					$errmsg = $this->SWIFT4->Database->FetchLastError();
				}
			}
		} else {
			$result = true;
		}
	} else {
		$result = true;
	}
	
	return $result;
}

public function GetCalendarDateFormats($_useHours = true) {
	$_returnValue = array();
	$_returnValue["usformat"] = ($this->SWIFT4->Settings->Get('dt_caltype') == 'us');
	
	if ($_useHours)
	{
		$_returnValue["html"] = ($_returnValue["usformat"] ? 'M d Y g:i A' : 'd M Y H:i');
		$_returnValue["cal"]  = ($_returnValue["usformat"] ? 'M d yy g:i A' : 'd M Y H:i');
	} else {
		$_returnValue["html"] = ($_returnValue["usformat"] ? 'M d Y' : 'd M Y');
		$_returnValue["cal"]  = ($_returnValue["usformat"] ? 'M d yy' : 'd M yy');
	}

	$_returnValue["hour"] = ($_returnValue["usformat"] ? '12' : '24');

	return $_returnValue;
}

public function payTickets($tktType,$tickets,$paytype="any",$checksince=0,$checkOnly=false)	{
	if (!is_array($tickets)) {
		return;
	}
	if ($tktType != SP_PAYTYPE_TICKET && $tktType != SP_PAYTYPE_LIVESUPPORT) {
		error_log("Error calling payTickets - no ticket type specified.");
		return;
	} 
	
	global $sp_license;
	$_payDepts = buildIN($this->getPayableDepts());
	
	$in = "";

	////////////////////////////////////////////////////////////
	// Create supplemental records for any new tickets.
	switch ($tktType) {
		case SP_PAYTYPE_TICKET:
			if (count($tickets) > 0) {
				$in = " AND t.ticketid IN (".buildIN($tickets).") ";
			}
			$sql = "SELECT t.ticketid,t.departmentid,userid,sum(floor(tt.timebillable/60)) as timebillable FROM ".TABLE_PREFIX."tickets as t, ".
				TABLE_PREFIX."tickettimetracks as tt WHERE tt.ticketid = t.ticketid ".$in.
				" and t.departmentid in (".$_payDepts.") ".
				" AND NOT EXISTS (SELECT 1 FROM ".TABLE_PREFIX."sp_ticket_paid as tp ".
				"WHERE tp.ticketid = t.ticketid AND tp.paytype = ".$tktType.") ".
				"group by t.ticketid,t.departmentid,userid";
			break;
		case SP_PAYTYPE_LIVESUPPORT:
			$chatName = $this->IsModuleRegistered("LIVECHAT");
			if (!empty($chatName)) {
				if (method_exists('SWIFT_Loader','LoadModel')) {
					SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
				} else {
					SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
				}
				if (count($tickets) > 0) {
					$in = " AND t.ticketid IN (".buildIN($tickets).") ";
				}
				
				$sql = "SELECT chatobjectid as ticketid,departmentid,userid,ceil((staffpostactivity-dateline)/60) as timebillable FROM ".TABLE_PREFIX."chatobjects as t ".
					"WHERE t.departmentid in (".$_payDepts.")  and staffpostactivity > dateline ".
					"AND t.chatobjectid IN (".buildIN($tickets).") ".
					"AND chatstatus = ".SWIFT_Chat::CHAT_ENDED." and chattype = ".SWIFT_Chat::CHATTYPE_CLIENT.
					" AND NOT EXISTS (SELECT 1 FROM ".TABLE_PREFIX."sp_ticket_paid as tp ".
					"WHERE tp.ticketid = t.chatobjectid AND tp.paytype = ".$tktType.")";
			}
			break;
	}

	if (!$this->SWIFT4->Database->Query($sql)) {
		$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(), $sql);
		return;
	}

	$_ticketidlist=array();
	while ($this->SWIFT4->Database->NextRecord()) {
		$_ticketidlist[$this->SWIFT4->Database->Record["ticketid"]] = $this->SWIFT4->Database->Record;
	}
	if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
		error_log(count($_ticketidlist) . " tickets to match");
	}
	
	$deptRates = $this->getPayableRates();
	
	foreach ($_ticketidlist as $tktid => $tktDetails) {
		if ($deptRates[$tktDetails["departmentid"]] > 0) {
			$sql = "INSERT INTO ".TABLE_PREFIX."sp_ticket_paid (ticketid,userid,paytype,paid_date,call_minutes,bill_minutes) ".
				"VALUES (".$tktid.",".$tktDetails["userid"].",".intval($tktType).",NULL,".
				$tktDetails["timebillable"].",".ceil($tktDetails["timebillable"] * $deptRates[$tktDetails["departmentid"]]).")";
			if (!$this->SWIFT4->Database->Query($sql)) {
				$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(), $sql);
			}
			if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
				error_log("New record for " .$tktid.", user ".$tktDetails["userid"].", type ".$tktType);
			}
		}
	}
	
	// Update any tickets which have had billable entries changed. Always do this update.
		if ($checksince != 0 || count($tickets) > 0) {
		$sql = "update ".TABLE_PREFIX."sp_ticket_paid tp set bill_minutes = ".
			"(select sum(ceil(timebillable/60*coalesce(sd.minuterate,1))) ".
			"from ".TABLE_PREFIX."tickettimetracks ttt, ".
			TABLE_PREFIX."tickets tk left join ".TABLE_PREFIX."sp_departments sd on ".
			"(sd.departmentid = tk.departmentid) where tp.ticketid = ttt.ticketid and tk.ticketid = ttt.ticketid), ".
			"call_minutes = (select sum(ceil(timebillable/60)) from ".TABLE_PREFIX."tickettimetracks ttt where tp.ticketid = ttt.ticketid) ".
			"where tp.paytype = ".SP_PAYTYPE_TICKET." ".
			"and tp.ticketid in (";
		if (count($tickets) > 0) {
			$sql .= buildIN($tickets);
		} else {
			$sql .= "select distinct ticketid from ".TABLE_PREFIX."tickettimetracks tt2 where tt2.dateline > ".$checksince." union ".
				" select distinct ticketid from ".TABLE_PREFIX."ticketauditlogs al where al.dateline > ".$checksince;
		}
		$sql .= ")";

		if (!$this->SWIFT4->Database->Query($sql)) {
			$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(), $sql);
		}
	}
	
	if ($checkOnly) {
		return;
	}
	////////////////////////////////////////////////////////////
	// Next, pay each closed ticket if possible.
	// Can't use ticket.lastactivity, it doesn't always get updated. i.e. a simple close leaves it unchanged.
	
	// TODO: Check possibility of using audit trail.
	
	// Find tickets needing paid, taking account of "closed" status if appropriate.
	$sql = "SELECT tp.ticketid,tp.userid,(1.0*tp.bill_minutes)-(1.0*tp.minutes) as timebillable, ".
		"coalesce(d.acceptmins, 1) acceptmins, coalesce(d.accepttkts, 1) accepttkts ".
		"FROM ".TABLE_PREFIX."sp_ticket_paid tp, ".TABLE_PREFIX.($tktType == SP_PAYTYPE_TICKET ? "tickets":"chatobjects")." t ".
		"left join ".TABLE_PREFIX."sp_departments d using (departmentid) ".
		"where t.".($tktType == SP_PAYTYPE_TICKET ? "ticketid":"chatobjectid")." = tp.ticketid ".
		"AND tp.paytype = ".$tktType." ".$in." AND tp.tickets = 0 and tp.minutes != tp.bill_minutes";
	if (!$this->SWIFT4->Settings->getKey("settings","sp_billimmediately")) {
		$sql .= " and ticketstatusid = ".intval($this->SWIFT4->Settings->getKey("settings","sp_statusclosed"));
	}

	$_ticketidlist=array();
	$_ticketbillable=array();
	$_ticketaccept=array();

	if (!$this->SWIFT4->Database->Query($sql)) {
		$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(), $sql);
		return;
	}
	while ($this->SWIFT4->Database->NextRecord()) {
		$_ticketidlist[$this->SWIFT4->Database->Record["ticketid"]] = $this->SWIFT4->Database->Record["userid"];
		$_ticketbillable[$this->SWIFT4->Database->Record["ticketid"]] = $this->SWIFT4->Database->Record["timebillable"];
		$_ticketaccept[$this->SWIFT4->Database->Record["ticketid"]] = 
			($this->SWIFT4->Database->Record["acceptmins"] ? SP_ACCEPT_MINUTES : 0) | 
			($this->SWIFT4->Database->Record["accepttkts"] ? SP_ACCEPT_TICKETS : 0);
	}
	if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
		error_log(count($_ticketidlist) . " tickets to pay");
	}
	
	$userin = buildIN($_ticketidlist);	// Adds the same userid multiple times...
	
	// Check that the license has been read at some point.
	if (empty($sp_license["site"])) {
		$this->readLicense($this->SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
	}

	// Work out available credit, taking Account Management into... um... account.
	if ($sp_license["allow_accounts"] && $this->SWIFT4->Settings->getKey("settings","sp_amenable")) {
		$sql = "SELECT me.userid, me.discount,coalesce(am.minutes,0)+coalesce(me.minutes,0) as minutes, ".
			" coalesce(am.tickets,0)+coalesce(me.tickets,0) as tickets, ".
			" coalesce(coalesce(me.overdraft,am.overdraft),0) as overdraft ".  // <--- Not coalesce. Do we take the max, or the manager's?
			" from ".TABLE_PREFIX."sp_users as me LEFT JOIN ".TABLE_PREFIX."sp_users as am ".
			"ON (am.userid = me.payerid AND am.acctmgr=1) WHERE me.userid IN (".$userin.")";
		
		// Also take account of organization if it's relevant.
		/*
		// THis incorrectly blocks payment for non-managers. I *think* it was intended to ensure that
		// the manager and the recipient belong to the same organisation?
		if ($this->SWIFT4->Settings->getKey("settings","sp_am_native")) {
			$sql .= " and (me.acctmgr = 1 or exists (select 1 from ".TABLE_PREFIX."users meu, ".TABLE_PREFIX."users amu ".
				"where meu.userid = me.userid and amu.userid = am.userid and me.userid != am.userid ".
				"and meu.userorganizationid = amu.userorganizationid))";
		}
		*/
		
		$this->SWIFT4->Database->Query($sql);
		$errmsg = $this->SWIFT4->Database->FetchLastError();
		if (!empty($errmsg)) {
			$this->SPErrorLog($errmsg, $sql);
			return;
		}		
	} else {
		$sql = "SELECT userid,minutes,tickets,discount,coalesce(overdraft,0) as overdraft ".
			"FROM ".TABLE_PREFIX."sp_users WHERE userid IN (".$userin.")";
		$this->SWIFT4->Database->Query($sql);
		
		$errmsg = $this->SWIFT4->Database->FetchLastError();
		if (!empty($errmsg)) {
			$this->SPErrorLog($errmsg, $sql);
		}		
	}

	$_creditlist=array();
	$allow_od = $this->SWIFT4->Settings->getKey("settings","sp_odenable");
	
	while ($this->SWIFT4->Database->NextRecord()) {
		$_creditlist[$this->SWIFT4->Database->Record["userid"]] = array(
			"minutes" => $this->SWIFT4->Database->Record["minutes"], 
			"tickets" => $this->SWIFT4->Database->Record["tickets"]
			);
		
		if ($allow_od) {
			$_creditlist[$this->SWIFT4->Database->Record["userid"]]["minutes"] += $this->SWIFT4->Database->Record["overdraft"];
		}
	}
	if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
		error_log(count($_creditlist) . " possible payers");
	}
	
	foreach ($_ticketidlist as $tkt => $user) {
		if (!isset($_creditlist[$user])) {
			// Then a ticket exists, requiring payment, for a user which no longer exists.
			$this->SPErrorLog("Payable ticket ".$tkt." exists for non-existent user #".$user);
			continue;
		}
		if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
			error_log("Ticket ".$tkt.", user ".$user.
				", rem_mins = ".$_creditlist[$user]["minutes"].
				", rem_tkts = ".$_creditlist[$user]["tickets"].
				", billable = ".$_ticketbillable[$tkt].
				", paytype = ".$paytype
				);
		}
		
		if (count($tickets) == 0 && $tktType == SP_PAYTYPE_LIVESUPPORT && 
			$this->SWIFT4->Settings->getKey("settings","sp_minchatmins") > $_ticketbillable[$tkt])
		{
			// This is a Live Support automatic payment, and is shorter than the cutoff. Just mark it paid.
			$sql = "UPDATE ".TABLE_PREFIX."sp_ticket_paid SET bill_minutes=0, paid_date=".time().
				" WHERE userid=".$user." AND ticketid=".$tkt." AND paytype = ".$tktType;
			if (!$this->SWIFT4->Database->Query($sql)) {
				$this->SPErrorLog("Update Live Support #".$tkt." failed : ".$this->SWIFT4->Database->FetchLastError(), $sql);
			} else {
				$sql = "INSERT INTO ".TABLE_PREFIX."sp_user_payments (".
					"userid,minutes,tickets,rem_minutes,rem_tickets,cost,currency,paidby,comments,ticketid,paytype,created) ".
					"VALUES (".intval($user).",0,0,0,0,0.0,'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".
					$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','".
					$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_freelivesup"))."',".$tkt.",".$tktType.",".time().")";
				if (!$this->SWIFT4->Database->Query($sql)) {
					$this->SPErrorLog("Update Live Support #".$tkt." failed : ".$this->SWIFT4->Database->FetchLastError(), $sql);
				}
			}
			
			continue;
		}
		
		$_sub_minutes = 0; $_sub_tickets = 0;
		if (isset($_creditlist[$user])) {
			if ($paytype == "minutes") {
				$_sub_minutes = max(0,min($_ticketbillable[$tkt],intval($_creditlist[$user]["minutes"])));
			} elseif ($paytype == "tickets") {
				$_sub_tickets = max(0,min(1,intval($_creditlist[$user]["tickets"])));
			} else {	// Use whatever's appropriate, provided we accept it for this department.
				$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept") & $_ticketaccept[$tkt];
				$willTakeMinutes = ($accept & SP_ACCEPT_MINUTES);
				$willTakeTickets = ($accept & SP_ACCEPT_TICKETS);
				
				if (($willTakeTickets && $_creditlist[$user]["tickets"] > 0 &&
					($_ticketbillable[$tkt] > intval($this->SWIFT4->Settings->getKey("settings","sp_preferticket")) ||
								$_ticketbillable[$tkt] > $_creditlist[$user]["minutes"]
								)) || (!$willTakeMinutes))
				{
					// Pay for this ticket using a ticket credit.
					if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
						error_log("Electing to pay with 1 ticket");
					}
					$_sub_tickets = 1;
				} else {
					// Otherwise use as many minutes as the user has.
					$_sub_minutes = min($_ticketbillable[$tkt],$_creditlist[$user]["minutes"]);
					if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
						error_log("Electing to pay with ".$_sub_minutes." minutes");
					}
				}
			}
		}
		
		$this->reconcilePayment($tktType, $tkt, null, $user, $_sub_tickets, $_sub_minutes, null, $_ticketbillable[$tkt]);
	}
}

// Update the user's credit total to avoid trawling the sp_user_payments table.
public function updateUserCredits($userid, &$errmsg) {
	$result = false;
	$errmsg = "";
	
	$mins = $tkts = 0;
	
	$Record = $this->SWIFT4->Database->QueryFetch("SELECT SUM(rem_minutes) as minutes, SUM(rem_tickets) as tickets from ".TABLE_PREFIX."sp_user_payments ".
		"WHERE userid=".intval($userid)." AND (rem_minutes != 0 or rem_tickets != 0) AND pending IS NULL");

	if (!empty($Record)) {
		$mins = intval($Record["minutes"]);
		$tkts = intval($Record["tickets"]);
	}

	// Store those values back into the user's table:
	if ($this->checkUserExists($userid, $errmsg)) {
		$this->SWIFT4->Database->Query("UPDATE ".TABLE_PREFIX."sp_users SET minutes=".intval($mins).",tickets=".intval($tkts).
			" WHERE userid=".intval($userid));
		$result = true;
	}

	return $result;
}

// Deduct payment for a ticket
public function reconcilePayment($tktType, $ticketid, $transid, $userid, $_sub_tickets, $_sub_minutes, $deductFrom=null, 
	$wanted_minutes = 0, $comment = null
	) {
	global $sp_license;
	
	if ($ticketid != null && ($tktType != SP_PAYTYPE_TICKET && $tktType != SP_PAYTYPE_LIVESUPPORT)) {
		$this->SPErrorLog("Error calling reconcilePayment - ticketid is defined (".$ticketid.") but no ticket type specified.");
		return;
	}
	
	$this->SWIFT4->Database->StartTrans();
	
	// Handle refunds.
	$_needUpdate = false;
	
	// Change parameters that will be used in SQL
	if ($tktType == null) $tktType = "null";
	if ($transid == null) $transid = "null";
	if ($ticketid == null) {
		$ticketid = "null";
		$deptPayType = array("acceptmins" => 1, "accepttkts" => 1);
	} else {
		// Handle an enforced limitation per-department on the type of payment.
		if ($tktType == SP_PAYTYPE_TICKET) {
			$deptPayType = $this->SWIFT4->Database->queryFetch("select coalesce(sd.acceptmins,1) acceptmins, ".
				"coalesce(sd.accepttkts,1) accepttkts from ".TABLE_PREFIX."tickets t left join ".TABLE_PREFIX."sp_departments sd ".
				"on (t.departmentid = sd.departmentid) where t.ticketid = ".intval($ticketid));
		} else {
			$deptPayType = $this->SWIFT4->Database->queryFetch("select coalesce(sd.acceptmins,1) acceptmins, ".
				"coalesce(sd.accepttkts,1) accepttkts from ".TABLE_PREFIX."chatobjects t left join ".TABLE_PREFIX."sp_departments sd ".
				"on (t.departmentid = sd.departmentid) where t.chatobjectid = ".intval($ticketid));
		}

		if ($this->SWIFT4->Settings->getKey("settings","t_eticketid") == "random") {
			$maskId = $this->SWIFT4->Database->QueryFetch("select ticketmaskid from ".TABLE_PREFIX."tickets where ticketid=".intval($ticketid));
			if (isset($maskId["ticketmaskid"]))
				$maskId = $maskId["ticketmaskid"];
			else
				$maskId = "(Unknown)";
		} else {
			$maskId = "#".$ticketid;
		}
	}
	
	$lastCredit = $this->getUserCredit($userid);
	
	// If these are < 0, it's a refund.
	if ($_sub_tickets < 0 || $_sub_minutes < 0) {
		$_needUpdate = true;
		
		$this->SWIFT4->Database->Query("INSERT INTO ".TABLE_PREFIX."sp_user_payments (".
			"userid,minutes,tickets,rem_minutes,rem_tickets,cost,currency,paidby,comments,created) ".
			"VALUES (".intval($userid).",".max(0,-$_sub_minutes).",".max(0,-$_sub_tickets).
			",".max(0,-$_sub_minutes).",".max(0,-$_sub_tickets).",0.0,'".
			$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','Refund for ".
			($ticketid != "null" ? "Ticket ".$maskId : "Tx. #".$transid)."',".time().")");
		$errmsg = $this->SWIFT4->Database->FetchLastError();
		if (!empty($errmsg)) {
			$this->SPErrorLog($errmsg);
		}

		if ($ticketid != "null") {
			$sql = "update ".TABLE_PREFIX."sp_ticket_paid SET tickets=tickets-".(-$_sub_tickets).
				", minutes=minutes-".(-$_sub_minutes).
				" WHERE userid=".$userid." AND ticketid=".$ticketid.	// userid here because they're the ticket owner
				" AND paytype = ".$tktType;
			$this->SWIFT4->Database->Query($sql);
			$errmsg = $this->SWIFT4->Database->FetchLastError();
			if (!empty($errmsg)) {
				$this->SPErrorLog($errmsg);
			}	
		}
		
		if ($_sub_minutes < 0) $_sub_minutes = 0;
		if ($_sub_tickets < 0) $_sub_tickets = 0;
	}
	
	$discount = 0;
	$Record = $this->SWIFT4->Database->QueryFetch("select discount,payerid from ".TABLE_PREFIX."sp_users where userid = ".$userid);
	if (!empty($Record["discount"])) {
		$discount = max(min(intval($Record["discount"]),100),-100);
	}
	
	if ($discount < 100) {
		// Check that the license has been read at some point.
		if (empty($sp_license["site"])) {
			$this->readLicense($this->SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}

		// Handle potential multiple payers. payers array already contains the primary user, now add an acct mgr if present.
		// Will use personal credit first, then mgr credit.
		$payers[] = $userid;
		if ($sp_license["allow_accounts"]) {
			if ($Record["payerid"] != null)
				$payers[] = $Record["payerid"];
		}
		
		foreach ($payers as $payerid) {
			$lastCredit = $this->getUserCredit($payerid);

			// Deduct this many tickets or minutes from sp_user_payments, starting with the oldest credit and the owner before the account manager.
			// Take pre-authorized credit before any other.
			$sql = "SELECT txid,proc_txid,rem_minutes,rem_tickets,paytype,currency,cost,tax,minutes FROM ".TABLE_PREFIX."sp_user_payments".
				" WHERE userid=".$payerid." AND (rem_minutes > 0 OR rem_tickets > 0) AND pending IS NULL";
			if (!is_null($deductFrom)) {
				if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
					error_log("Overriding credit search with txid " . $deductFrom);
				}
				$sql .= " and txid=".intval($deductFrom);
			}
			$sql .= " ORDER BY case paytype when 3 then 0 else 1 end, created ASC";

			if (!$this->SWIFT4->Database->Query($sql)) {
				$this->SPErrorLog("Fetch payment items failed : ".$this->SWIFT4->Database->FetchLastError(), $sql);
				continue;
			}
			$_updates = array();

			while (($_sub_tickets > 0 || $_sub_minutes > 0) && $this->SWIFT4->Database->NextRecord()) {
				//				echo "t=".$_sub_tickets.", m=".$_sub_minutes.", tx=".$this->SWIFT4->Database->Record["txid"];
				//				error_log("t=".$_sub_tickets.", m=".$_sub_minutes.", tx=".$this->SWIFT4->Database->Record["txid"]);
				$_ded_minutes = $_ded_tickets = 0;

				// Now the normal payment calculation.
				if ($_sub_tickets > 0 && $this->SWIFT4->Database->Record["rem_tickets"] > 0 && $deptPayType["accepttkts"] == 1) {
					// Deduct this ticket.
					$_ded_tickets = min($this->SWIFT4->Database->Record["rem_tickets"], $_sub_tickets);
				}
				if ($_sub_minutes > 0 && $this->SWIFT4->Database->Record["rem_minutes"] > 0 && $deptPayType["acceptmins"] == 1) {
					// Deduct these minutes.
					$_ded_minutes = min($this->SWIFT4->Database->Record["rem_minutes"], $_sub_minutes);
				}

				if ($this->SWIFT4->Database->Record["paytype"] == SP_PAYTYPE_DEFERRED && $_ded_minutes > 0) {
					error_log("Cost = ".$this->SWIFT4->Database->Record["cost"].", mins=".$this->SWIFT4->Database->Record["minutes"]);
					$payamt = $this->SWIFT4->Database->Record["cost"] / $this->SWIFT4->Database->Record["minutes"];
					$payamt *= $_ded_minutes;

					$taxamt = $this->SWIFT4->Database->Record["tax"] / $this->SWIFT4->Database->Record["minutes"];
					$taxamt *= $_ded_minutes;

					$resArray = $this->authTransComplete($this->SWIFT4->Database->Record["proc_txid"], $payamt, $taxamt,
						$this->SWIFT4->Database->Record["currency"], $_ded_minutes);
					
					if (strtoupper($resArray["ACK"]) != "SUCCESS") {
						$_ded_minutes = 0;
						$_ded_tickets = 0;
					}
				}
				
				// Don't update on a second DB connection so we keep the transaction intact.
				if ($_ded_minutes > 0 || $_ded_tickets > 0) {
					$_updates[$this->SWIFT4->Database->Record["txid"]] = array(
						"paytype" => $this->SWIFT4->Database->Record["paytype"], 
						"minutes" => $_ded_minutes, 
						"tickets" => $_ded_tickets
						);
					$_sub_minutes -= $_ded_minutes;
					$_sub_tickets -= $_ded_tickets;
				}
			}
			
			$tkt_complete = false;
			$_deducted_mins = 0;
			$_deducted_tkts = 0;
			
			if (count($_updates) == 0) {
				if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
					error_log("No payments were available for uid ".$payerid);
				}
			} else {
				$_needUpdate = true;
				
				foreach ($_updates as $upd => $deduct) {
					// Update this payment record.
					if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
						error_log("Updating TX#".$upd.", tickets-=".$deduct["tickets"].", minutes-=".$deduct["minutes"]);
					}
					$sql = "";
					
					if ($ticketid != "null") {
						// We're paying for a ticket.
						if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
							error_log("Paying for ticket ".$maskId." (".$ticketid.")");
						}
						$transid = "null";
						
						$tkt_complete = false;
						if ($deduct["tickets"] > 0) {
							$tkt_complete = true;
						} else {
							// See if we're paying enough minutes to complete this ticket.
							$sql = "SELECT bill_minutes-minutes as timebillable,minutes".
								" FROM ".TABLE_PREFIX."sp_ticket_paid".
								" WHERE ticketid = ".$ticketid." AND paytype = ".$tktType;
							$Record = $this->SWIFT4->Database->QueryFetch($sql);
							
							if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
								error_log("billable=".$Record["timebillable"].", paid=".$Record["minutes"].", deduct=".$deduct["minutes"]);
							}
							$tkt_complete = ($Record["timebillable"] <= $Record["minutes"] + $deduct["minutes"]);
						}
						
						$sql = "UPDATE ".TABLE_PREFIX."sp_ticket_paid SET tickets=tickets+".intval($deduct["tickets"]).
							", minutes=minutes+".intval($deduct["minutes"]).
							", paid_date=".($tkt_complete ? time() : "NULL").
							" WHERE userid=".$userid." AND ticketid=".$ticketid.	// userid here because they're the ticket owner
							" AND paytype = ".$tktType;
						$this->SWIFT4->Database->Query($sql);
						$errmsg = $this->SWIFT4->Database->FetchLastError();
						if (!empty($errmsg)) {
							$this->SPErrorLog("Update ticket ".$maskId." failed : ".$errmsg);
						} else {
							$_deducted_mins += $deduct["minutes"];
							$_deducted_tkts += $deduct["tickets"];
						}
					} elseif ($transid != "null") {	// String because it's been converted to one further up
						// We're clearing a negative transaction. userid here too, this is the tx being cleared.
						$ticketid = "null";
						if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
							error_log("Paying for Transaction #".$transid);
						}
						
						$sql = "UPDATE ".TABLE_PREFIX."sp_user_payments SET rem_tickets=rem_tickets+".intval($deduct["tickets"]).
							", rem_minutes=rem_minutes+".intval($deduct["minutes"])." WHERE userid=".$userid." AND txid=".$transid;
						$this->SWIFT4->Database->Query($sql);
						$errmsg = $this->SWIFT4->Database->FetchLastError();
						if (!empty($errmsg)) {
							$this->SPErrorLog("Update transid #".$transid." failed : ".$errmsg, $sql);
						} else {
							$_deducted_mins += $deduct["minutes"];
							$_deducted_tkts += $deduct["tickets"];
						}
					}
					
					if ($this->SWIFT4->Database->FetchLastError() == "") {
						// The payment has been added to the ticket log, now deduct payment. payerid instead of userid - this is the bill.
						if (!$this->SWIFT4->Database->Query("UPDATE ".TABLE_PREFIX."sp_user_payments".
							" SET rem_tickets=rem_tickets-".$deduct["tickets"].", rem_minutes=rem_minutes-".$deduct["minutes"].
							" WHERE userid=".$payerid." AND txid = ".$upd))
						{
							$this->SPErrorLog("Deduct Error: ".$this->SWIFT4->Database->FetchLastError(),$sql);
						}
					} else {
						$this->SPErrorLog("Payment Error: ".$this->SWIFT4->Database->FetchLastError(),$sql);
					}
				}
				
				if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
					error_log("dm = ".$_deducted_mins."; dt = ".$_deducted_tkts."; transid=".$transid);
				}
				// Add a visible record of the deduction for the user
				if ($_deducted_mins > 0 || $_deducted_tkts > 0) {
					$wanted_minutes -= $_deducted_mins;

					switch ($tktType) {
						case SP_PAYTYPE_TICKET:
							$comment = $this->SWIFT4->Language->Get("sp_paidticket")." ".$maskId;
							break;
						case SP_PAYTYPE_LIVESUPPORT:
							$comment = $this->SWIFT4->Language->Get("sp_paidlivesup");
							break;
						default:
							if (is_null($comment)) {
								$comment  = $this->SWIFT4->Language->Get("sp_paidother");
							}
					}
					
					if ($transid == "null") {	// String because it's been converted to one further up
						// Don't double-enter existing transactions.
						$sql = "INSERT INTO ".TABLE_PREFIX."sp_user_payments (".
							"userid,minutes,tickets,rem_minutes,rem_tickets,cost,currency,paidby,comments,ticketid,paytype,created) ".
							"VALUES (".intval($payerid).",".(-$_deducted_mins).",".(-$_deducted_tkts).
							",0,0,0.0,'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','".
							$this->SWIFT4->Database->Escape($comment)."',".$ticketid.",".$tktType.",".time().")";
						
						if (!$this->SWIFT4->Database->Query($sql)) {
							$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(),$sql);
						}
					}

					// Do enter separate transactions for account managers.
					if ($payerid != $userid) {
						// And a secondary one for information if this is an account.
						$sql = "INSERT INTO ".TABLE_PREFIX."sp_user_payments (".
							"userid,minutes,tickets,rem_minutes,rem_tickets,cost,currency,paidby,comments,ticketid,paytype,created) ".
							"VALUES (".intval($payerid).",".(-$_deducted_mins).",".(-$_deducted_tkts).
							",0,0,0.0,'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."',".
							"(select fullname from ".TABLE_PREFIX."users where userid=".$payerid." LIMIT 0,1),'".
							$this->SWIFT4->Database->Escape($comment)."',".$ticketid.",".$tktType.",".time().")";
						$this->SWIFT4->Database->Query($sql);
						$errmsg = $this->SWIFT4->Database->FetchLastError();
						if (!empty($errmsg)) {
							$this->SPErrorLog($errmsg,$sql);
						}
					}
					$this->updateUserCredits($payerid,$errmsg);
					$this->sendClientCreditEmail($payerid, $lastCredit, $comment);	
				}
			}
		} // End of payers loop.

		if ($ticketid != "null" && (!$tkt_complete) && $wanted_minutes > 0) {
			// This ticket still has something to pay. Can we pump it over to WHMCS?
			if ($sp_license["allow_whmcs"] && $this->SWIFT4->Settings->getKey("settings","sp_whmcs_enable")) {
				if ($this->SWIFT4->Settings->getKey("settings","sp_whmcs_pushmode") == SP_WPM_EACH) {
					SWIFT_Loader::LoadLibrary('SupportPay:SPWHMCS', "supportpay");
					$SP_WC = new SWIFT_SPWHMCS;	
					
					if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
						error_log("Sending ".$wanted_minutes." mins to WHMCS for ticket ID ".$ticketid);
					}
					
					if ($SP_WC->SendSingleTicket($ticketid,$maskId,$userid,$tktType,$wanted_minutes))
					{
						$_needUpdate = true;
						$tkt_complete = true;
						$wanted_minutes = 0;

						// Create a visible record of the transfer.
						$sql = "INSERT INTO ".TABLE_PREFIX."sp_user_payments (".
							"userid,minutes,tickets,rem_minutes,rem_tickets,cost,currency,paidby,comments,ticketid,paytype,created) ".
							"VALUES (".intval($payerid).",".(-$wanted_minutes).",0".
							",0,0,0.0,'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".
							$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','".
							$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_whmcs_pushed"))."',".
							$ticketid.",".$tktType.",".time().")";
						
						if (!$this->SWIFT4->Database->Query($sql)) {
							$this->SPErrorLog($this->SWIFT4->Database->FetchLastError(),$sql);
						}
					} else {
						error_log("Failed to send payment for ticket #".$ticketid." to WHMCS.");
					}
					
					unset($SP_WC);
				}
			}
		}
	} else {
		if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
			error_log("User is on 100% discount.");
		}
		$sql = "UPDATE ".TABLE_PREFIX."sp_ticket_paid SET bill_minutes=0, paid_date=".time().
			" WHERE userid=".$userid." AND ticketid=".$ticketid." AND paytype = ".$tktType;
		$this->SWIFT4->Database->Query($sql);
		$errmsg = $this->SWIFT4->Database->FetchLastError();
		
		// Don't need $_needUpdate because we've not changed the user's credit count.
		if (!empty($errmsg)) {
			$this->SPErrorLog("Pay 100% discount ticket ".$maskId." failed : ".$errmsg, $sql);
		}
	}	
	
	if ($_needUpdate) {
		$errmsg = "";
		if (!$this->updateUserCredits($userid,$errmsg)) {
			$this->SPErrorLog("Update credits failed : " . $errmsg);
		}
	}
	$this->SWIFT4->Database->CompleteTrans();

	return false;
}

public function sendAddCreditReply($ticketid) {
	$ticketModName = $this->IsModuleRegistered("TICKETS");
	if (method_exists('SWIFT_Loader','LoadModel')) {
		SWIFT_Loader::LoadModel('Ticket:Ticket');
	} else {
		SWIFT_Loader::LoadLibrary('Ticket:Ticket');
		SWIFT_Loader::LoadLibrary('Ticket:TicketPost', $ticketModName);
		SWIFT_Loader::LoadLibrary('AuditLog:TicketAuditLog', $ticketModName);
	}
	SWIFT_Loader::LoadLibrary('Mail:Mail');

	try {
		$tktObj = new SWIFT_Ticket(new SWIFT_DataID($ticketid));
	} catch (exception $e) {
		return;
	}
	
	if ($tktObj instanceof SWIFT_Ticket) {
		$theSubject = $this->SWIFT4->Settings->getKey("settings","sp_gk_subject");
		$theUser = $tktObj->GetUserObject();
		$accept = $this->getDeptAccept($tktObj->GetProperty('departmentid'));
		$reqMinutes = $this->SWIFT4->Database->QueryFetch("select coalesce(spd.minuterate, 1) minuterate, ".
			"coalesce(spd.mins_to_post, 0) mins_to_post, d.title ".
			"from ".TABLE_PREFIX."departments d left join ".TABLE_PREFIX."sp_departments spd using (departmentid) ".
			"where departmentid = ".$tktObj->GetProperty('departmentid'));
		
		// Need this to get the available credit and discount for this user.
		$Credit = $this->getUserCredit($theUser->GetUserID());

		// Need this for the email - calculate the *actual* number of minutes required.
		$reqMinutes["mins_to_post"] *= $reqMinutes["minuterate"];
		$reqMinutes["mins_to_post"] *= (100 - $Credit["discount"]) / 100;
		$reqMinutes["mins_to_post"] = round($reqMinutes["mins_to_post"] + 0.5, 0);
		
		// Quick paranoia check.
		if (!$this->customerCanPay($theUser->GetUserID(), $tktObj->GetProperty('departmentid'))) {
			$emailList = $theUser->GetEmailList();
			if (count($emailList) > 0) {
				$Credit = $this->getUserCredit($theUser->GetUserID());
				$Credit["minutes"] -= $Credit["overdraft"];

				$this->SWIFT4->Template->Assign("_username", $theUser->GetFullName());
				$this->SWIFT4->Template->Assign("_credit", $Credit);
				$this->SWIFT4->Template->Assign("_reqMinutes", $reqMinutes);
				$this->SWIFT4->Template->Assign("_acceptMins", (($accept & SP_ACCEPT_MINUTES) ? true:false));
				$this->SWIFT4->Template->Assign("_acceptTkts", (($accept & SP_ACCEPT_TICKETS) ? true:false));
				$this->SWIFT4->Template->Assign("_overdraft", $this->SWIFT4->Settings->getKey("settings","sp_odenable"));

				try {
					$this->SWIFT4->Template->SetTemplateGroupID($tktObj->GetProperty('tgroupid'));
				} catch (Exception $e) {
					$this->SPErrorLog("Unable to prepare insufficient credit reply",
						"User ".$theUser->GetFullName()." belongs to non-existent template group #".$tktObj->GetProperty('tgroupid'));
					return null;
				}
				
				$emailText = $this->SWIFT4->Template->Get("sp_pleaseaddcredit", SWIFT_TemplateEngine::TYPE_DB);

				// Ensure that the "totalreplies" count goes up by one so that it's not notified again
				// next time.
				// Have the user email themselves so that it doesn't appear that there have been any
				// staff responses. Also means we don't have to nominate a staff member as the sender.
				$dispatcher = SWIFT_TicketPost::Create($tktObj,$theUser->GetFullName(),$emailList[0],$emailText,
					SWIFT_Ticket::CREATOR_USER,$theUser->GetUserID(),
					SWIFT_Ticket::CREATIONMODE_SUPPORTCENTER,$theSubject);

				// Now email it out to the client.
				$mailObj = new SWIFT_Mail();
				$mailObj->SetToField($emailList[0]);
				$mailObj->SetFromField($emailList[0], $emailList[0]);
				$mailObj->SetSubjectField($theSubject);

				SWIFT_TicketAuditLog::Log($tktObj, null, SWIFT_TicketAuditLog::ACTION_UPDATETICKET, 
					"Send insufficient credit email.",
					SWIFT_TicketAuditLog::VALUE_NONE, 0, '', 0, '');

				if (stripos($emailText,"<html>") !== false) {
					$mailObj->SetDataHTML($emailText);
				} else {
					$mailObj->SetDataText($emailText);
				}

				$mailObj->sendMail(false);
			}
		} // else there *is* enough credit, what are we doing here?
	}
}

public function sendClientCreditEmail($userid, $lastCredit, $reason) {
	if ($this->SWIFT4->Settings->getKey("settings","sp_send_credemail")) {
		$fromEmail = $this->SWIFT4->Settings->getKey("settings","sp_credemail_from");
		$subject = $this->SWIFT4->Settings->getKey("settings","sp_credemail_subject");
			if (!empty($fromEmail) && !empty($subject)) {
					SWIFT_Loader::LoadLibrary('Mail:Mail');

					$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
					$willTakeMinutes = ($accept & SP_ACCEPT_MINUTES);
					$willTakeTickets = ($accept & SP_ACCEPT_TICKETS);
					
					$newCredit = $this->getUserCredit($userid);
					$newCredit["minutes"] -= $newCredit["overdraft"];
					$lastCredit["minutes"] -= $lastCredit["overdraft"];

					// Only send the mail if credit has changed, and all available methods of credit are or were
					// below their thresholds.
					$thresholdMins = $this->SWIFT4->Settings->getKey("settings","sp_credemail_threshold_mins");
					$thresholdTkts = $this->SWIFT4->Settings->getKey("settings","sp_credemail_threshold_tkts");

					if ($lastCredit != $newCredit && (
					(!$willTakeMinutes || ($willTakeMinutes && min($newCredit["minutes"],$lastCredit["minutes"]) <= $thresholdMins)) &&
					(!$willTakeTickets || ($willTakeTickets && min($newCredit["tickets"],$lastCredit["tickets"]) <= $thresholdTkts))
					)) {
							$credFreq = $this->SWIFT4->Settings->getKey("settings","sp_credemail_interval");
							$lastSent = $this->SWIFT4->Database->QueryFetch("select last_credit_email, last_notified_minutes, last_notified_tickets ".
								"from ".TABLE_PREFIX."sp_users where userid = ".$userid);
						if ($lastSent["last_credit_email"] + 3600*($credFreq) < time()) {
								$iUser = $this->SWIFT4->Database->QueryFetch("select u.fullname, coalesce(tg.tgroupid,1) as templategroupid, ue.email ".
									"from ".TABLE_PREFIX."users u left join ".TABLE_PREFIX."templategroups tg ".
									" on (tg.isenabled = 1 and tg.regusergroupid = u.usergroupid), ".TABLE_PREFIX."useremails ue ".
									" where u.userid = ".$userid." and ue.linktype = 1 and ue.linktypeid = u.userid");

								$this->SWIFT4->Template->Assign("_username", $iUser["fullname"]);
								$this->SWIFT4->Template->Assign("_oldcredit", $lastCredit);
								$this->SWIFT4->Template->Assign("_newcredit", $newCredit);
								$this->SWIFT4->Template->Assign("_changereason", $reason);
								
								try {
									$this->SWIFT4->Template->SetTemplateGroupID($iUser["templategroupid"]);
								} catch (Exception $e) {
									$this->SPErrorLog("Unable to send credit change email",
										"User ".$iUser["fullname"]." belongs to non-existent template group #".$iUser["templategroupid"]);
									return;
								}
								$emailText = $this->SWIFT4->Template->Get("sp_credit_email", SWIFT_TemplateEngine::TYPE_DB);
								
								$mailObj = new SWIFT_Mail();
								$mailObj->SetToField($iUser["email"]);
								$mailObj->SetFromField($fromEmail, $fromEmail);
								$mailObj->SetSubjectField($subject);
								$mailObj->SetDataText($emailText);

								$mailObj->sendMail(false);
							
							$this->SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_users set ".
								"last_credit_email = ".time().", last_notified_minutes = minutes, last_notified_tickets = tickets ".
								"where userid = ".$userid);
						}
					}
		} else {
			$this->SPErrorLog("Unable to send client credit-change emails because the from address or subject is invalid.",
				"Please check your settings.");
		}
	}
}

function clearPendingTransaction(&$errmsg, $procid, $txid) {
	$errmsg = "";
	
	$Record = $this->SWIFT4->Database->QueryFetch("select userid,pending,proc_txid,minutes,tickets,cost,comments from ".TABLE_PREFIX."sp_user_payments 
			where processor = ".intval($procid)." AND (pending='".$this->SWIFT4->Database->Escape($txid)."' or proc_txid='".$this->SWIFT4->Database->Escape($txid)."')");
	if (isset($Record["userid"])) {
		$userid = $Record["userid"];
		
		if ($Record["pending"] == null) {
			if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
				error_log("Transaction is already clear, not changing anything.");
			}
		} else {
			if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
				error_log("Transaction is pending, performing clear.");
			}
			$lastCredit = $this->getUserCredit($userid);
			$sql = "update ".TABLE_PREFIX."sp_user_payments SET pending=NULL ".
				"WHERE pending='".$this->SWIFT4->Database->Escape($txid)."' and processor=".intval($procid);
			if (!$this->SWIFT4->Database->Query($sql))
			{
				$errmsg = $this->SWIFT4->Database->FetchLastError();
			} else {
				$this->updateUserCredits($userid, $errmsg);
				$this->sendClientCreditEmail($userid, $lastCredit, $Record["comments"]);
				
				// Affiliate stuff
				if ($this->SWIFT4->Settings->getKey("settings","sp_affiliate")) {
					// Check that the license has been read at some point.
					if (empty($sp_license["site"])) {
						$this->readLicense($this->SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
					}
					
					if ($sp_license["allow_affiliate"]) {
						if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
							error_log("TX#".$txid.": Cost = " . $Record["cost"].", minutes=".$Record["minutes"].", tickets=".$Record["tickets"]);
						}
						
						$affminutes = ceil(intval($Record["minutes"]) * (floatval($this->SWIFT4->Settings->getKey("settings","sp_affminmult"))/100.0));
						$afftickets = ceil(intval($Record["tickets"]) * (floatval($this->SWIFT4->Settings->getKey("settings","sp_afftktmult"))/100.0));
						$affguid = $this->getAffiliateParent($userid);
						if (!empty($affguid) && ( $affminutes > 0 || $afftickets > 0) && $Record["cost"] > 0) {
							// We have an affiliate.
							$Record = $this->SWIFT4->Database->QueryFetch("SELECT userid FROM ". TABLE_PREFIX ."sp_users ".
								"WHERE guid = '".$this->SWIFT4->Database->Escape($affguid)."'");
							
							if (!empty($Record["userid"])) {
								$discount = 1;
								$Disc = $this->SWIFT4->Database->QueryFetch("SELECT discount FROM ". TABLE_PREFIX ."sp_users WHERE userid = ".intval($userid));
								if (!empty($Disc["discount"]))
									$discount = max(min(100,100 - $Disc["discount"]),-100) / 100.0;
								// error_log("Discount for user ".$Record["userid"]." = ".$discount);
								
								if ($discount != 0) {	// Don't bother with a record if we have 100% discount.
									$affuserid = intval($Record["userid"]);
									$bonusmins = intval($affminutes * $discount);
									$bonustkts = intval($afftickets * $discount);
									
									if ($this->SWIFT4->Settings->getKey("settings","sp_debug")) {
										error_log("Adding affiliate bonus of ".$bonusmins." minutes, ".$bonustkts." tickets to user#".$affuserid);
									}
									
									$lastCredit = $this->getUserCredit($affuserid);

									// Give them x percent of this purchase.
									$this->SWIFT4->Database->Query("INSERT INTO ". TABLE_PREFIX ."sp_user_payments ".
										"(userid, minutes, tickets, rem_minutes, rem_tickets, cost, currency, ".
										"paidby, comments, packageid,pending,processor, created) ".
										"VALUES (".$affuserid.",".$bonusmins.",".$bonustkts.
										",".$bonusmins.",".$bonustkts.",0.0,'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."',".
										"'".$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','".$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_affbonus")).
										"',null,null,".SP_PROCESSOR_AFFILIATE.",".time().")");
									$this->updateUserCredits($affuserid, $errmsg);
									
									$this->sendClientCreditEmail($affuserid, $lastCredit, $this->SWIFT4->Language->Get("sp_affbonus"));
								}
							}
						}
					}
				}
			}
		}
	} else {
		$errmsg = "Unable to identify transaction.";
	}
}

function getAffiliateParent($userid)
{
	$AffParent = null;
	$Record = $this->SWIFT4->Database->QueryFetch("SELECT affiliate FROM ".TABLE_PREFIX."sp_users where userid = ".intval($userid));
	if (!empty($Record["affiliate"]))
		$AffParent = $Record["affiliate"];
	
	return $AffParent;
}

// Mark a pending payment as voided; keep the record but don't allocate any credit.
function voidPendingTransaction($parent_tx, $procid) {
	$sql = "update ".TABLE_PREFIX."sp_user_payments set rem_minutes=0, rem_tickets=0, pending=null,".
		"comments='".$this->SWIFT4->Database->Escape("VOIDED Tx#".$parent_tx)."', ".
		"cost=0, tax=0, fee=0 ".
		"where proc_txid = '".$this->SWIFT4->Database->Escape($parent_tx)."' and processor = ".intval($procid);
	$this->SWIFT4->Database->Execute($sql);
}

// Deduct part of a previous payment for which a refund has been issued.
function refundPayment($parent_tx, $this_tx, $procid, $amount) {
	$amount = floatval($amount);
	if ($amount < 0 && isset($procid) && isset($parent_tx) && isset($this_tx)) {
		$Rec = $this->SWIFT4->Database->QueryFetch("select userid,txid,minutes,tickets,cost from ".TABLE_PREFIX."sp_user_payments ".
			"where proc_txid='".$this->SWIFT4->Database->Escape($parent_tx)."' and processor = ".intval($procid));
		
		if (!empty($Rec["txid"]) && floatval($Rec["cost"]) > 0) {
			// Got one.
			$ref_mins = intval(($Rec["minutes"] * $amount) / $Rec["cost"]);
			$ref_tkts = intval(($Rec["tickets"] * $amount) / $Rec["cost"]);
			
			$sql = "INSERT INTO ". TABLE_PREFIX ."sp_user_payments ".
				"(userid, minutes, tickets, rem_minutes, rem_tickets, cost, currency, ".
				"paidby, comments, pending, processor, proc_txid, created) ".
				"VALUES (".$Rec["userid"].",".$ref_mins.",".$ref_tkts.",".$ref_mins.",".$ref_tkts.",".sprintf("%0.2f",$amount).",'".
				$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".$this->SWIFT4->Database->Escape($this->SWIFT4->Language->Get("sp_sysname"))."','".
				$this->SWIFT4->Database->Escape("Rf#".$this_tx)."',null,".intval($procid).",'".$this->SWIFT4->Database->Escape($this_tx).
				"',".time().")";
			
			$this->SWIFT4->Database->Query($sql);
			$errmsg = $this->SWIFT4->Database->FetchLastError();
			if ($errmsg == "") {
				$this->updateUserCredits($Rec["userid"],$errmsg);
			} else {
				error_log("Failed to refund ".sprintf("%0.2f",-$amount)." of transaction ".$parent_tx." with transaction ".$this_tx);
				error_log($errmsg);
				error_log($sql);
			}
		} else {
			error_log("Failed to find original transaction #".$parent_tx.".");
		}
	} else {
		error_log("Refund for Tx#".$parent_tx." ignored, value = " . $amount);
	}
}

// Add a payment record to a specific user's account.
function addPayment(&$errmsg, $userid, $minutes, $tickets, $cost, $paidby, $comments, $packageid, 
	$pendingtx, $procid = null, $proctx = null, $deductFrom = null, $taxamt = 0, $payType = null, $feeamt = null,
	$migrated = null
	)
{
	global $sp_license;
	
	$txid = null;
	$errmsg = "";
	$debug = $this->SWIFT4->Settings->getKey("settings","sp_debug");

	// Check that the license has been read at some point.
	if (empty($sp_license["site"])) {
		$this->readLicense($this->SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
	}

	if ($sp_license["status"] != SP_LICENSE_GOOD) {
		$errmsg = "Payment not accepted, license is not current.";
		$this->SPErrorLog($errmsg);
		return null;
	}

	if (!is_null($deductFrom) && !is_numeric($deductFrom)) {
		// This can be passed in as an empty string, so convert any non-integer values
		// to NULL. Otherwise, negative payments will not be actually deducted until the
		// next reconciler run.
		$deductFrom = null;
	}

	if (!$this->checkUserExists($userid, $errmsg)) {
		return null;
	}

	$expiry = "null";	// In quotes because it is used to build an insert statement.
	
	if (!is_null($packageid)) {
		$Rec = $this->SWIFT4->Database->QueryFetch("select minutes, tickets, duration from ".TABLE_PREFIX."sp_packages ".
			"where pkgid = ".$packageid);
		if (is_array($Rec)) {
			if (!is_null($Rec["duration"])) {
				$expiry = time() + ($Rec["duration"] * 86400);
			}
			
			if (empty($minutes)) {
				$minutes = $Rec["minutes"];
			}
			
			if (empty($tickets)) {
				$tickets = $Rec["tickets"];
			}
		}
	}

	// Authorised-payment expiry time. This is another reason that packages can't be bought deferred.
	if ($payType == SP_PAYTYPE_DEFERRED) {
		$expiry = time() + (3 * 86400);
	}

	if (!is_null($proctx)) {
		// Check that this transaction doesn't already exist. Can't use 'replace into' because the PK is on the internal txid
		// not the actual txid.
		$Record = $this->SWIFT4->Database->QueryFetch("select txid, pending, paytype, expiry, minutes, rem_minutes, tickets, rem_tickets ".
			"from ".TABLE_PREFIX."sp_user_payments where processor=".intval($procid).
			" AND proc_txid = '".$this->SWIFT4->Database->Escape($proctx)."'");

		if (!is_null($Record["txid"])) {
			// Yes, already exists.
			$txid = $Record["txid"];

			// Take the lower of the existing minutes and new new - this one may have been
			// deducted already.
			$minutes = min($minutes, $Record["minutes"]);

			$rem_minutes = $Record["rem_minutes"] - ($Record["minutes"] - $minutes);
			$rem_tickets = $Record["rem_tickets"] - ($Record["tickets"] - $tickets);

			if ($debug) {
				error_log("Modifying existing record for tx#".$txid.". Current mins=".$Record["minutes"].", rem_minutes=".$Record["rem_minutes"].
					", tickets=".$Record["tickets"].", rem_tickets=".$Record["rem_tickets"].", expiry=".$Record["expiry"]);
				error_log("New minutes=".$minutes.", tickets=".$tickets.", rem_minutes=".$rem_minutes.", rem_tickets=".$rem_tickets);
			}

			if (!$this->SWIFT4->Database->Query("update ".TABLE_PREFIX."sp_user_payments ".
				"set minutes=".$minutes.", tickets=".$tickets.", rem_minutes=".$rem_minutes.", rem_tickets=".$rem_tickets.
				",expiry=".$expiry."where txid=".$txid))
			{
				$this->SPErrorLog("Failed to process alteration of authorised payment.",$errmsg = $this->SWIFT4->Database->FetchLastError());
				return null;
			}
		}
	}

	if (is_null($txid)) {
		$this->SWIFT4->Database->Query("INSERT INTO ". TABLE_PREFIX ."sp_user_payments (".
			"userid, minutes, tickets, rem_minutes, rem_tickets, cost, currency, ".
			"paidby, comments, packageid, pending, processor, proc_txid, tax, created, paytype, expiry, fee, migrated) ".
			"VALUES (".intval($userid).",".intval($minutes).",".intval($tickets).
			",".intval($minutes).",".intval($tickets).",".floatval($cost).",'".$this->SWIFT4->Settings->getKey("settings","sp_currency")."','".
			$this->SWIFT4->Database->Escape($paidby)."','".$this->SWIFT4->Database->Escape($comments).
			"',".(is_null($packageid) ? "NULL" : intval($packageid)).",".
			// Always mark payments from a processor as 'pending'. They get cleared immediately below, so that the affiliate payment
			// code is all in one place.
			(is_numeric($procid) ? "'".$this->SWIFT4->Database->Escape($proctx)."'" : ($pendingtx == null ? "null" : "'".$this->SWIFT4->Database->Escape($pendingtx)."'")).
			",".(is_numeric($procid) ? $procid : "null").",".
			(isset($proctx) ? "'".$this->SWIFT4->Database->Escape($proctx)."'" : "null").",".floatval($taxamt).
			",".time().",".(is_null($payType) ? "NULL" : intval($payType)).",".$expiry.",".
			(is_null($feeamt) ? "null" : floatval($feeamt)).", ".(is_null($migrated) ? "null" : intval($migrated)).")");
		$txid = $this->SWIFT4->Database->InsertID();
	}

	if (!is_null($procid) && !is_null($proctx)) {
		if ($pendingtx == null) {
			// Then it's paid. Clear it immediately.
			if ($debug) {
				error_log("Transaction is clear, calling clearPendingTransaction");
			}
			$this->clearPendingTransaction($errmsg,$procid,$proctx);
		} else {
			if ($debug) {
				error_log("Transaction is pending, not calling clearPendingTransaction");
			}
		}
	}
	
	if (!$txid) {
		$errmsg = $this->SWIFT4->Database->FetchLastError();
		error_log($errmsg);
		return null;
	} else {
		if ($tickets < 0 || $minutes < 0) {
			// It's actually a bill. Deduct it from the user's credit.
			$this->reconcilePayment(null, null, $txid, $userid, -$tickets, -$minutes, $deductFrom, $minutes, $comments);
		} else {
			$lastCredit = $this->getUserCredit($userid);
			$this->updateUserCredits($userid, $errmsg);
			
			if (is_null($procid) || is_null($proctx)) {
				// Then we'll have avoided the "clearPendingTransaction" and "reconcilePayment" calls, so send any
				// credit update emails here.
				$this->sendClientCreditEmail($userid, $lastCredit, $comments);	
			}
		}
	}

	return $txid;
}

function getPaymentWarnings() {
	$gwlive = false;
	$warning = "";
	
	switch ($this->SWIFT4->Settings->getKey("settings","sp_gateway")) {
		case SP_PROCESSOR_PAYPAL:
			$gwlive = $this->SWIFT4->Settings->getKey("settings","sp_paypallive");
			break;
		case SP_PROCESSOR_WORLDPAY:
			$gwlive = $this->SWIFT4->Settings->getKey("settings","sp_worldpaylive");
			break;
		case SP_PROCESSOR_AUTHORIZE:
			$gwlive = $this->SWIFT4->Settings->getKey("settings","sp_anlive");
			break;
	}
	
	if (!$gwlive) {
		$warning = "<span style='font-size: large; color: Red; text-decoration: blink; '>".$this->SWIFT4->Language->Get("sp_sandbox")."</span>";
	}
	
	return $warning;
}

function showPaymentWarnings($template) {
	$template->assign("infomessage", $this->getPaymentWarnings());
}

function addAudit($event) {
	if ($this->SWIFT4->Staff !== false) {
		$this->SWIFT4->Database->Query("insert into ".TABLE_PREFIX."sp_audit (staffid,is_user,created,event) VALUES (".
			$this->SWIFT4->Staff->GetStaffID().",0,".time().",'".$this->SWIFT4->Database->Escape($event)."')");
	} else {
		$this->SWIFT4->Database->Query("insert into ".TABLE_PREFIX."sp_audit (staffid,is_user,created,event) VALUES (".
			$this->SWIFT4->User->GetUserID().",1,".time().",'".$this->SWIFT4->Database->Escape($event)."')");
	}
}

// Look through unassigned chat logs for users that were already signed up.
public function matchChatUsers(&$errorMessage) {
	$chatName = $this->IsModuleRegistered("LIVECHAT");
	if (!empty($chatName)) {
		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
		} else {
			SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
		}
		
		$sql = "update ".TABLE_PREFIX."chatobjects c set userid = coalesce(".
			"(select u.userid from ".TABLE_PREFIX."useremails e, swusers u ".
			"where trim(lower(e.email)) = trim(lower(c.useremail)) and u.userid = e.linktypeid and u.dateline < c.lastpostactivity ".
			"),0) where userid = 0 and useremail is not null and chatstatus = ".SWIFT_Chat::CHAT_ENDED.
			" and chattype = ".SWIFT_Chat::CHATTYPE_CLIENT;
		if (!$this->SWIFT4->Database->Query($sql)) $errorMessage = $this->SWIFT4->Database->FetchLastError();
	}
}

public function getProcessorName($procId) {
	switch ($procId) {
		case SP_PROCESSOR_PAYPAL:
			$procName = $this->SWIFT4->Language->Get("sps_paypal");
			break;
		case SP_PROCESSOR_WORLDPAY:
			$procName = $this->SWIFT4->Language->Get("sps_worldpay");
			break;
		case SP_PROCESSOR_AUTHORIZE:
			$procName = $this->SWIFT4->Language->Get("sps_authorizenet");
			break;
		default:
			$procName = "";
	}
	
	return $procName;
}

function deleteCartData($userid, $cart_guid, $provider, $deleteRecurring = false) {
	$cart_guid = $this->SWIFT4->Database->Escape($cart_guid);
	
	$this->SWIFT4->Database->StartTrans();
	$this->SWIFT4->Database->Query("delete from ".TABLE_PREFIX."sp_cart_items where cid in ".
		"(select cid from ".
		TABLE_PREFIX."sp_cart_defs where cid='".$cart_guid.
		"' and userid = ".intval($userid)." and provider=".intval($provider).") ".
		($deleteRecurring ? "" : "and proc_txid is null")
		);

	$this->SWIFT4->Database->Query("delete from ".TABLE_PREFIX."sp_cart_defs where cid='".
		$cart_guid."' and userid = ".intval($userid)." and provider=".intval($provider).
		($deleteRecurring ? "" : " and not exists (select 1 from ".TABLE_PREFIX."sp_cart_items ".
				" where cid='".$cart_guid."' and proc_txid is not null)")
			);
	$this->SWIFT4->Database->CompleteTrans();
}

function retrieveCartData($userid, $cart_guid, $provider, $proc_txid = null) {
	$hdr = array();

	$sql = "select cd.userid, ci.* ".
		"from ".TABLE_PREFIX."sp_cart_defs cd, ".TABLE_PREFIX."sp_cart_items ci where cd.cid='".
		$this->SWIFT4->Database->Escape($cart_guid)."' ".
		(!is_null($userid) ? "and cd.userid=".intval($userid) : "").
		" and cd.provider = ".intval($provider);
	
	if (!empty($proc_txid)) {
		$sql .= " and ci.proc_txid = '".$this->SWIFT4->Database->Escape($proc_txid)."'";
	}
	$sql .= " and ci.cid = cd.cid order by itemid";
	
	if ($this->SWIFT4->Database->Query($sql)) {
		$index = 0;
		while ($this->SWIFT4->Database->NextRecord()) {
			$hdr[$index] = $this->SWIFT4->Database->Record;
			$hdr[$index]["rowcost"] = ($hdr[$index]["cost"] + $hdr[$index]["tax"]) * $hdr[$index]["itemcount"];
			$index++;
		}
	}
	

	return $hdr;
}

function encodeCartData($userid, $items, $key) {
	// Must keep below 20 chars for Authorize.net
	$cart_guid = substr(sha1(uniqid("",true), false),0,20);
	
	if ($this->SWIFT4->Database->Query("insert into ".TABLE_PREFIX."sp_cart_defs (".
		"cid, userid, created, ctype, provider) values ('".
		$cart_guid."',".$userid.",".time().",".SP_CTYPE_REALTIME.",".$key.")"))
	{
		// Also have "tax", "name", "desc", "itemtype"
		foreach ($items as $itemid => $item) {
			$this->SWIFT4->Database->Query("insert into ".TABLE_PREFIX."sp_cart_items (".
				"cid,itemid,itemcount,descr,minutes,tickets,pkgid,cost,tax,currency,recur_period,recur_unit) values ('".$cart_guid."',".$itemid.",".
				intval($item["itemcount"]).",'".$this->SWIFT4->Database->Escape(substr($item["name"],0,256))."',".
				intval($item["minutes"]).",".intval($item["tickets"]).",".
				(is_null($item["pkgid"]) ? "null":$item["pkgid"]).",".
				floatval($item["cost"]).",".floatval($item["tax"]).",'".$this->SWIFT4->Database->Escape($item["currency"])."',".
				(empty($item["recur_period"]) ? "null":$item["recur_period"]).",".
				(empty($item["recur_unit"]) ? "null":$item["recur_unit"]).
				")");
		}
	} else {
		SWIFT::Error("SupportPay","Failed to create a cart record");
		error_log($this->SWIFT4->Database->FetchLastError());
		$cart_guid = null;
	}

	return $cart_guid;
}

function encodeData($key, $data) {
	if (!is_array($data)) {
		if (strlen($data) > 0) {
			$cipher = mcrypt_module_open(MCRYPT_BLOWFISH,'',"cbc",'');
			$ivSize = mcrypt_enc_get_iv_size($cipher);
			$iv = substr($this->SWIFT4->Settings->getKey("settings","general_producturl").self::$ivBase,11,$ivSize);
			
			$key = sha1($key,false);
			mcrypt_generic_init($cipher, $key, $iv);
			$encrypted = mcrypt_generic($cipher,serialize($data));
			mcrypt_generic_deinit($cipher);
			mcrypt_module_close($cipher);
			
			return base64_encode($encrypted);
		}
	}
	return "";
}

function decodeData($key, $encrypted) {
	$results = null;

	if (strlen($encrypted) > 0) {
		$key = sha1($key,false);
		$cipher = mcrypt_module_open(MCRYPT_BLOWFISH,'','cbc','');
		$ivSize = mcrypt_enc_get_iv_size($cipher);
		$iv = substr($this->SWIFT4->Settings->getKey("settings","general_producturl").self::$ivBase,11,$ivSize);
		
		mcrypt_generic_init($cipher, $key, $iv);
		$decrypted = mdecrypt_generic($cipher,base64_decode($encrypted));
		mcrypt_generic_deinit($cipher);
		mcrypt_module_close($cipher);
		
		if (!is_null($decrypted)) {
			$results = @unserialize($decrypted);
		}
	}
	
	return $results;
}

function getSecureSetting($key) {
	$result = null;
	
	if ((!is_null($this->SWIFT4->Settings->getKey("settings",$key)))) {
		$result = $this->SWIFT4->Settings->getKey("settings",$key);

		if (substr($result,0,3) == "PW:") {
			$dec = $this->decodeData($key, substr($result,3));
			if (!is_null($dec))
				$result = $dec;
		} elseif (strlen($result) > 0) {
			// It should be encrypted but isn't.
			//			error_log("Encrypting setting '".$key."'");
			$this->writeSetting($key, "PW:".$this->encodeData($key, $result), true);
		}
	}
	
	return $result;
}

function encryptAllPasswords() {
	$modName = (class_exists('SWIFT_App') ? 'app' : 'module');
	$this->SWIFT4->Database->Query("select * from ".TABLE_PREFIX."settingsfields where ".$modName." = '"."supportpay".
		"' AND settingtype = 'password'",2);
	
	while ($this->SWIFT4->Database->NextRecord(2)) {
		if (substr($this->SWIFT4->Settings->getKey("settings",$this->SWIFT4->Database->Record2["name"]),0,3) != "PW:") {
			$this->getSecureSetting($this->SWIFT4->Database->Record2["name"]);
		}
	}
}

public function QuitIfGuest($template_name) {
	if (!is_object($this->SWIFT4->User)) {
		SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get("sp_notloggedin"));
		$this->SWIFT4->UserInterface->Header($template_name);
		$this->SWIFT4->UserInterface->Footer();
		exit;
	}
}

function checkPaymentMethod() {
	switch ($this->SWIFT4->Settings->getKey("settings","sp_gateway")) {
		case SP_PROCESSOR_PAYPAL:
			if ($this->SWIFT4->Settings->getKey("settings","sp_paypallive")) {
				if ($this->SWIFT4->Settings->getKey("settings","sp_paypaluserid") != "" && $this->SWIFT4->Settings->getKey("settings","sp_paypalpasswd") != "" && $this->SWIFT4->Settings->getKey("settings","sp_paypalsign") != "") {
					return true;
				}
			} else {
				if ($this->SWIFT4->Settings->getKey("settings","sp_paypalsbuserid") != "" && $this->SWIFT4->Settings->getKey("settings","sp_paypalsbpasswd") != "" && $this->SWIFT4->Settings->getKey("settings","sp_paypalsbsign") != "") {
					return true;
				}
			}
			break;
		case SP_PROCESSOR_WORLDPAY:
			if ($this->SWIFT4->Settings->getKey("settings","sp_worldpayinstid") != "" && $this->SWIFT4->Settings->getKey("settings","sp_wp_md5pass") != "") {
				return true;
			}
			break;
		case SP_PROCESSOR_AUTHORIZE:
			if ($this->SWIFT4->Settings->getKey("settings","sp_anloginid") != "" && $this->SWIFT4->Settings->getKey("settings","sp_antxkey") != "" && $this->SWIFT4->Settings->getKey("settings","sp_currency") == "USD") {
				return true;
			}
			break;
	}
	
	return false;
}

function showNotLoggedIn() {
	global $template;
	
	$template->assign("errormessage", $this->SWIFT4->Language->Get("perminvalid"));
	echo $template->displayTemplate("header");
	echo '<table cellspacing="3" cellpadding="1" width="50%" border="0">
		<tr>
		<td valign="top" width="15" nowrap><a href="'.SWIFT::Get('basename'). getSwiftURL('core','register') . '"><img src="'.SWIFT::Get('themepath').'register.gif" border="0"></a></td>
		<td width="98%" align="left" valign="top"><strong><a href="'.SWIFT::Get('basename').getSwiftURL('core','register') . '" id="moduletitle">'.htmlspecialchars($this->SWIFT4->Language->Get("registerlogin")).'</a></strong><br />
		<span class="smalltext">'.htmlspecialchars($this->SWIFT4->Language->Get("desc_register")).'</span></td>
		</tr>
		</table>';
	echo $template->displayTemplate("footer");
	exit;
}

function spLogo() {
	return "<span style='display: inline; position: relative; font-family: Trebuchet MS;'>\n".
		"<span style='position: absolute; top: 0.25em; left: 0px; width: 3.15em; height: 1em; border-top: solid 2px Green;'></span>".
		"<span style='color: Blue; font-style: italic; font-size: 0.9em;'>Support</span>".
		"<span style='color: Red; font-weight: normal; font-size: 1.1em;'>Pay</span>".
		"</span>";
}

function assignSectionTitle($titleString) {
	global $sp_license;

	if (!$sp_license["allow_nobranding"]) {
		$dir = $this->SWIFT4->Language->Get('textdirection');
		
		echo "<div style='float: " . ($dir == SWIFT_Language::DIRECTION_RTL ? "left":"right") . 
			"; color: #AAAAAA; font-size: 11px; font-style: italic; z-index: 99; font-weight: lighter; margin-right: 0.2em; margin-bottom: -16px;'>".
			"<div style='margin-top: 16px;'>Using <a href='http://www.support-pay.com' title='Payment Options by SupportPay' ".
			"target='_blank'>".$this->spLogo()."</a></div></div>";
	}

	$this->SWIFT4->Template->Assign("_spPageTitle", $titleString);
}

// Not actually used for anything, so just make it random for appearances' sake.
function genCartId() {
	return mt_rand(1000, 100000);
}

function paymentErrorEmail($errmsg, $actgateway) {
	global $sp_gateways;
	
	$emailtext = $errmsg . "\n";
	$emailtext .= "Current gateway : " . $sp_gateways[$this->SWIFT4->Settings->getKey("settings","sp_gateway")] . "\n";
	$emailtext .= "Error gateway : " . $sp_gateways[$actgateway] . "\n";
	$emailtext .= "\nREQUEST values:\n";
	foreach ($_REQUEST as $name => $value) {
		$emailtext .= $name . " => " . $value . "\n";
	}
	$emailtext .= "\nSERVER values:\n";
	foreach ($_SERVER as $name => $value) {
		$emailtext .= $name . " => " . $value . "\n";
	}
	
	if ($this->SWIFT4->Settings->getKey("settings","sp_erroremail") != "") {
		SWIFT_Loader::LoadLibrary('Mail:Mail');
		$mailObj = new SWIFT_Mail();
		$mailObj->setSubject("Payment Processing Error");
		
		$mailObj->setData("", $emailtext);
		$mailObj->sendMail($this->SWIFT4->Settings->getKey("settings","sp_erroremail"), $this->SWIFT4->Settings->getKey("settings","sp_erroremail"), "SupportPay", false);
	}
	
	// And the same to the PHP error log.
	error_log("*** Payment Processing Error ***");
	error_log($emailtext);
}

function getPreTaxPrice($price) {
	$vat = floatval($this->SWIFT4->Settings->getKey("settings","sp_taxrate"));
	if ($vat > 0) {
		if ($this->SWIFT4->Settings->getKey("settings","sp_reversetax")) {
			$price = round($price * (100 / (100 + $vat)),2);
		}
	}
	
	return $price;
}

// Always send in the *unprocessed* price
function getTaxOnPrice($price) {
	$vat = floatval($this->SWIFT4->Settings->getKey("settings","sp_taxrate"));
	if ($vat > 0) {
		if ($this->SWIFT4->Settings->getKey("settings","sp_reversetax")) {
			$price -= round($price * (100 / (100 + $vat)),2);
		} else {
			$price = round($price * ($vat / 100),2);
		}
	} else {
		$price = 0;
	}
	
	return $price;
}

function getFinalTaxPrice($price) {
	$vat = floatval($this->SWIFT4->Settings->getKey("settings","sp_taxrate"));
	
	if ($vat > 0) {
		if (!$this->SWIFT4->Settings->getKey("settings","sp_reversetax")) {
			$price += round($price * ($vat / 100.0),2);
		}
	}
	
	return $price;
}

public function TimestampFromDate($date) {
	if (is_numeric($date)) {
		return $date;
	}

	if ($this->SWIFT4->Settings->Get('dt_caltype') == 'us') {
		// 'm/d/Y';
		$tm = explode('/',$date);
	} else {
		// 'd/m/Y';
		$tm = explode('/',$date);
		$month = $tm[1];
		$tm[1] = $tm[0];
		$tm[0] = $month;
	}
	
	// Expects month, day, year.
	return mktime(0,0,0,$tm[0],$tm[1],$tm[2]);
}

public function isInitialGrid() {
	return (count($_POST) == 0);
}	

public function GenReportHash($Report) {
	$hash = "";
	if (is_array($Report)) {
		$hash = md5($Report["query"]."#".$Report["countsql"]);
	}
	
	return $hash;
}

public function ImportReportXML($Path) {
	// Take report definitions on disk and load them as reports.
	if (!file_exists($Path))
	{
		return false;
	}

	// Parse the Report XML File
	SWIFT_Loader::LoadLibrary('XML:XML');
	$_SWIFT_XMLObject = new SWIFT_XML();
	$_reportXMLContainer = $_SWIFT_XMLObject->XMLToTree(file_get_contents($Path));
	if (!is_array($_reportXMLContainer))
	{
		return false;
	}
	
	foreach ($_reportXMLContainer["SPReports"][0]["children"]["Report"] as &$item) {
		$this->SWIFT4->Database->Execute("delete from ".TABLE_PREFIX."sp_reports ".
			"where hash = '".$this->SWIFT4->Database->Escape($item["children"]["hash"][0]["values"][0])."'");
		$this->SWIFT4->Database->Execute("insert into ".TABLE_PREFIX."sp_reports ".
			"(title,hash,query,countsql) values (".
			"'".$this->SWIFT4->Database->Escape($item["children"]["title"][0]["values"][0])."',".
			"'".$this->SWIFT4->Database->Escape($item["children"]["hash"][0]["values"][0])."',".
			"'".$this->SWIFT4->Database->Escape($item["children"]["query"][0]["values"][0])."',".
			"'".$this->SWIFT4->Database->Escape($item["children"]["countsql"][0]["values"][0])."')"
			);
	}
}

public function ExportReportXML($repids = null) {
	// Take report definitions on disk and save them as reports.
	
	SWIFT_Loader::LoadLibrary('XML:XML');
	$_SWIFT_XMLObject = new SWIFT_XML();
	$_SWIFT_XMLObject->AddParentTag('SPReports');
	
	$sql = "select * from ".TABLE_PREFIX."sp_reports";
	if (is_array($repids)) {
		$sql .= " where repid in (".buildIN($repids).")";
	}
	
	$this->SWIFT4->Database->Query($sql);
	while ($this->SWIFT4->Database->NextRecord()) {
		$_SWIFT_XMLObject->AddParentTag('Report');
		$_SWIFT_XMLObject->AddTag('hash', $this->SWIFT4->Database->Record['hash']);
		$_SWIFT_XMLObject->AddTag('title', $this->SWIFT4->Database->Record['title']);
		$_SWIFT_XMLObject->AddTag('query', $this->SWIFT4->Database->Record['query']);
		$_SWIFT_XMLObject->AddTag('countsql', $this->SWIFT4->Database->Record['countsql']);
		$_SWIFT_XMLObject->EndParentTag('Report');
	}
	
	$_SWIFT_XMLObject->EndParentTag('SPReports');
	return $_SWIFT_XMLObject->ReturnXML();
}

public function GetWHMCSPackages() {
	// Get a list of addons - custom API.
	$res = false;
	SWIFT_Loader::LoadLibrary('SupportPay:SPWHMCS', "supportpay");
	$WHMCS = new SWIFT_SPWHMCS();
	$pkgList = array();
	
	$addons = $WHMCS->doWHMCSApiCall(array(
		"action" => "spgetaddons"
		), false, 2845);
	
	if (isset($addons)) {
		// It's an XML string.
		$xml_doc = @simplexml_load_string($addons);
		if (!empty($xml_doc)) {
			$res = true;
			
			if (!empty($xml_doc->{'addons'})) {
				foreach ($xml_doc->{'addons'}->{'addon'} as $wPkg) {
					$pkgList[SP_MIGRATED_WHMCS_ADDON][(int)($wPkg->{'id'})] = array(
						"name" => (string)($wPkg->{'name'}),
						"description" => (string)($wPkg->{'description'}),
						"price" => (string)($wPkg->{'cost_recur'})
						);

					$thisPkg = &$pkgList[SP_MIGRATED_WHMCS_ADDON][(int)($wPkg->{'id'})];
					
					// Need to map "billing" to "recur_period" and "recur_unit".
					$recur_unit = null;
					$recur_period = null;
					$duration = null;
					
					switch ((string)($wPkg->{'billing'})) {
						case "None":
							break;
						case "Free Account":
							$thisPkg["price"] = 0;
							break;
						case "One Time":
							break;
						case "Monthly":
							$recur_unit = SP_RECUR_UNIT_MONTH;
							$recur_period = 1;
							$duration = 30;
							break;
						case "Quarterly":
							$recur_unit = SP_RECUR_UNIT_MONTH;
							$recur_period = 3;
							$duration = 90;
							break;
						case "Semi-Annually":
							$recur_unit = SP_RECUR_UNIT_MONTH;
							$recur_period = 6;
							$duration = 183;
							break;
						case "Annually":
							$recur_unit = SP_RECUR_UNIT_YEAR;
							$recur_period = 1;
							$duration = 365;
							break;
						case "Bienially":
							$recur_unit = SP_RECUR_UNIT_YEAR;
							$recur_period = 2;
							$duration = 730;
							break;
					}
					
					// Purely defaults, should get actual values from the description.
					$pkgMins = 0;
					$pkgTkts = 0;
					
					$pos = strpos($thisPkg["description"],"#SPM/");
					if ($pos !== false) {
						$pkgMins = intval(substr($thisPkg["description"], $pos+5));
					}
					$pos = strpos($thisPkg["description"],"#SPT/");
					if ($pos !== false) {
						$pkgTkts = intval(substr($thisPkg["description"], $pos+5));
					}

					$thisPkg["recur_period"] = $recur_period;
					$thisPkg["recur_unit"] = $recur_unit;
					$thisPkg["duration"] = $duration;
					$thisPkg["minutes"] = $pkgMins;
					$thisPkg["tickets"] = $pkgTkts;
					$thisPkg["description"] = trim(preg_replace(":#SP[MT]/[0-9]+[\r\n]*:","",$thisPkg["description"]));
				}
			}
		}
	}
	
	/* Now load main packages. */
	if ($res) {
		$res = false;
		
		// Get a list of addons - custom API.
		$addons = $WHMCS->doWHMCSApiCall(array(
			"action" => "spgetprodfields"
			), false, 2845);
		
		if (isset($addons)) {
			// It's an XML string.
			$xml_doc = @simplexml_load_string($addons);
			if (!empty($xml_doc)) {
				$res = true;
				if (!empty($xml_doc->{'products'})) {
					foreach ($xml_doc->{'products'}->{'product'} as $wPkg) {
						$prodId = (string)($wPkg->{'id'});
						$prodName = (string)($wPkg->{'name'});
						$prdPaytype = (string)($wPkg->{'paytype'});

						$pkgMins = 0;
						$pkgTkts = 0;
						
						if (!empty($wPkg->{'field'})) {
							foreach ($wPkg->{'field'} as $wField) {
								// Purely defaults, should get actual values from the description.
								
								$pkgString = (string)($wField->{'name'});
								
								$pos = strpos($pkgString,"SP_Minutes/");
								if ($pos !== false) {
									$pkgMins = intval(substr($pkgString, $pos+11));
								}
								$pos = strpos($pkgString,"SP_Tickets/");
								if ($pos !== false) {
									$pkgTkts = intval(substr($pkgString, $pos+11));
								}
							}
						}
						
						if ($pkgMins > 0 || $pkgTkts > 0) {
							// We have a valid package. Now distribute it over the available selling settings.
							
							if (!empty($wPkg->{'period'})) {
								foreach ($wPkg->{'period'} as $wPeriod) {
									$prdSetup = (float)($wPeriod->{'setup'});
									$prdRecur = (float)($wPeriod->{'recur'});
									$prdName = (string)($wPeriod->attributes()->{'type'});
									$recur_unit = null; $recur_period = null; $duration = null;
									
									if ($prdPaytype == "recurring") {
										switch ($prdName) {
											case "monthly":
												$recur_unit = SP_RECUR_UNIT_MONTH; $recur_period = 1;
												$divisor = 12; $duration = 31;
												break;
											case "quarterly":
												$recur_unit = SP_RECUR_UNIT_MONTH; $recur_period = 3;
												$divisor = 4; $duration = 91;
												break;
											case "semiannually":
												$recur_unit = SP_RECUR_UNIT_MONTH; $recur_period = 6;
												$divisor = 2; $duration = 182;
												break;
											case "annually":
												$recur_unit = SP_RECUR_UNIT_YEAR; $recur_period = 1;
												$divisor = 1; $duration = 365;
												break;
											case "biennially":
												$recur_unit = SP_RECUR_UNIT_YEAR; $recur_period = 2;
												$divisor = 0.5; $duration = 365 * 2;
												break;
											case "triennially":
												$recur_unit = SP_RECUR_UNIT_YEAR; $recur_period = 3;
												$divisor = 1/3; $duration = 365 * 3;
												break;
										}
									} else {
										if ($prdRecur >= 0 && $prdSetup >= 0) {	// Filter out monthly packages which are disabled.
											if ($prdPaytype == "onetime") {
												// This is a static price for a non-recurring package.
												$pkgList[SP_MIGRATED_WHMCS][$prodId.'/o'] = array(
													"name" => $prodName,
													"description" => $prodName,
													"price" => $prdRecur + $prdSetup,
													"recur_period" => null, "recur_unit" => null,
													"duration" => null,
													"minutes" => intval($pkgMins), "tickets" => intval($pkgTkts)
													);
											} elseif ($prdPaytype == "free") {
												// A fixed (i.e. zero) price.
												$pkgList[SP_MIGRATED_WHMCS][$prodId.'/f'] = array(
													"name" => $prodName,
													"description" => $prodName,
													"price" => 0,
													"recur_period" => null, "recur_unit" => null,
													"duration" => null,
													"minutes" => intval($pkgMins), "tickets" => intval($pkgTkts)
													);
											}
											break;  // Don't process this as a periodic package.
										}
									}
									
									// PeriodName to be one of "monthly", "quarterly", "semiannually", "annually", "biennially"
									if ($prdSetup >= 0 && $prdRecur >= 0 && !empty($recur_unit) ) {
										// Is a valid billing period.
										if (intval(round($pkgMins / $divisor)) > 0 || intval(round($pkgTkts / $divisor)) > 0) {
											$pkgList[SP_MIGRATED_WHMCS][$prodId.'/'.strtolower(substr($prdName,0,1))] = array(
												"name" => $prodName.' ('.ucfirst($prdName).')',
												"description" => $prodName.' ('.ucfirst($prdName).')',
												"price" => $prdRecur,
												"recur_period" => $recur_period, "recur_unit" => $recur_unit,
												"duration" => $duration,
												"minutes" => intval(round($pkgMins / $divisor)), "tickets" => intval(round($pkgTkts / $divisor))
												);
										}
									}
								}
							}
						}
					}
				}
			}
		}	
		return $pkgList;
	}
	
	return null;
}

	public function DeletePackage($pkg) {
		if (!is_numeric($pkg)) {
			error_log("Non-numeric packageID '".$pkg."' passed to SPFunctions::DeletePackage");
			return;
		}
		
		$cnt = 0;
		// Check to see if anyone's bought this package. If so, disable instead of delete.
		$Rec = $this->SWIFT4->Database->QueryFetch("SELECT 1 cnt FROM ".TABLE_PREFIX."sp_user_payments ".
			"WHERE packageid = ".$pkg);
		$cnt += $Rec["cnt"];
		
		// This is a recurring package. Check that nobody's using it.
		$Rec = $this->SWIFT4->Database->QueryFetch("select count(1) cnt from ".TABLE_PREFIX."sp_cart_items i, ".
			TABLE_PREFIX."sp_cart_defs d where i.cid = d.cid and d.ctype = ".SP_CTYPE_RECURRING.
			" and i.pkgid = ".$pkg);
		$cnt += $Rec["cnt"];
		
		if ($cnt > 0) {
			SWIFT::Info("SupportPay",$this->SWIFT4->Language->Get("sp_package_in_use"));
			$sql = "UPDATE ".TABLE_PREFIX."sp_packages SET enabled=0 WHERE pkgid = ".$pkg;
		} else {
			$sql = "DELETE FROM ".TABLE_PREFIX."sp_packages WHERE pkgid = ".$pkg;
		}
		
		if (!$this->SWIFT4->Database->Execute($sql)) {
			SWIFT::Error("SupportPay",$this->SWIFT4->Database->FetchLastError());
		}
		if (!$this->SWIFT4->Database->Execute("delete from ".TABLE_PREFIX."sp_package_tgroups where pkgid not in ".
				"(select pkgid from ".TABLE_PREFIX."sp_packages)"))
		{
			SWIFT::Error("SupportPay",$this->SWIFT4->Database->FetchLastError());
		}
	}

	function SPErrorLog($message,$userData = null) {
		$logDB = new PDO(DB_TYPE.':host=' . DB_HOSTNAME . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';', 
			DB_USERNAME, DB_PASSWORD, array(PDO::ATTR_PERSISTENT => false));

		if (DB_TYPE == 'mysql') {
			$logDB->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		}
		
		if (method_exists('SWIFT_Loader','LoadModel')) {
			SWIFT_Loader::LoadModel('ErrorLog:ErrorLog');
		} else {
			SWIFT_Loader::LoadLibrary('ErrorLog:ErrorLog');
		}

		$bt = debug_backtrace(true);
		error_log($message);
		
		$stacktrace = '';
		$stInd = 0;
		if (is_array($bt)) {
			if (!empty($userData)) {
				$stacktrace = "\n\n";
				error_log($userData);
			}
			
			if ($bt[$stInd]["function"] == "SPErrorLog") {
				$stInd++;
			}
			
			if (isset($bt[$stInd]["file"])) {
				$stacktrace .= $bt[$stInd]["file"].':'.$bt[$stInd]["line"];
			}
		}
		$logDB->exec('insert into '.TABLE_PREFIX.'errorlogs (type,dateline,errordetails,userdata) values ('.
			SWIFT_ErrorLog::TYPE_EXCEPTION.','.time().',"SupportPay: '.
			$this->SWIFT4->Database->Escape($message).'",'.
			'"'.$this->SWIFT4->Database->Escape($userData.$stacktrace).'")');
			
		unset($logDB);
	}

	function array_implode( $glue, $separator, $array ) {
		if ( ! is_array( $array ) ) return $array;
		$string = array();
		foreach ( $array as $key => $val ) {
			if ( is_array( $val ) )
				$val = implode( ',', $val );
			$string[] = "{$key}{$glue}{$val}";
			
		}
		return implode( $separator, $string );	
	}
	
	function IsModuleRegistered($modName) {
		$haveChat = false;
		$chatName = null;

		if (class_exists('SWIFT_App')) {
			$chatName = constant('APP_'.$modName);
			$haveChat = SWIFT_App::IsInstalled($chatName);
		} elseif (class_exists('SWIFT_Module')) {
			$chatName = constant('MODULE_'.$modName);
			$haveChat = SWIFT_Module::IsRegistered($chatName);
		}
	
		return ($haveChat ? $chatName : null);
	}
}

global $SPFunctions;
$SPFunctions = new SWIFT_SPFunctions;

global $sp_gateways;

$S = SWIFT::GetInstance();
$sp_gateways[SP_PROCESSOR_NONE] = $S->Language->Get("sps_none");
$sp_gateways[SP_PROCESSOR_PAYPAL] = $S->Language->Get("sps_paypal");
$sp_gateways[SP_PROCESSOR_AUTHORIZE] = $S->Language->Get("sps_authorizenet");
unset($S);

?>
