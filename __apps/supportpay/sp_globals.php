<?php

define("SP_DBVERSION","1.5");					// Change this *only* and *always* when the DB, settings or templates change.

// Bitfield? OR not? Not sure yet, so use 4 instead of 3 for PENDING.
define("SP_CTYPE_REALTIME",1);
define("SP_CTYPE_RECURRING",2);
define("SP_CTYPE_PENDING",4);

global $sp_currencylist;
$sp_currencylist["GBP"] = array("name"=>"British Pounds", "symbol"=>"&pound;");
$sp_currencylist["EUR"] = array("name"=>"Euros", "symbol"=>"&euro;");
$sp_currencylist["USD"] = array("name"=>"US Dollars", "symbol"=>"$");
$sp_currencylist["AUD"] = array("name"=>"Aus Dollars", "symbol"=>"A$");
$sp_currencylist["CAD"] = array("name"=>"Canadian Dollars", "symbol"=>"C$");
$sp_currencylist["CHF"] = array("name"=>"Swiss Francs", "symbol"=>"CHF");			//
$sp_currencylist["DKK"] = array("name"=>"Danish Kroner", "symbol"=>"kr");		//
$sp_currencylist["HKD"] = array("name"=>"Hong Kong Dollars", "symbol"=>"HK$");		//
$sp_currencylist["INR"] = array("name"=>"Indian Rupee", "symbol"=>"Rs");			//
$sp_currencylist["JPY"] = array("name"=>"Yen", "symbol"=>"&yen;");
$sp_currencylist["NZD"] = array("name"=>"NZ Dollars", "symbol"=>"NZ$");
$sp_currencylist["SGD"] = array("name"=>"Singapore Dollars", "symbol"=>"SN$");		//
$sp_currencylist["SEK"] = array("name"=>"Swedish Kroner", "symbol"=>"kr");		//

global $sp_localelist;
$sp_localelist = array ("GB","US","AU","DE","ES","FR","IT","CN",
	"PL","NL","CH","AT","BE");

// This is a bitfield - take care when defining! BOTH = MINUTES | TICKETS
define('SP_ACCEPT_NONE',0);
define('SP_ACCEPT_MINUTES',1);
define('SP_ACCEPT_TICKETS',2);
define('SP_ACCEPT_BOTH',3);

define('SP_LICENSE_NONE',1);				// No license entered.
define('SP_LICENSE_INVALID',2);			// License entered but signature invalid.
define('SP_LICENSE_GOOD',3);				// License entered and signature valid.
define('SP_LICENSE_BAD',4);				// License entered but unreadable.
define('SP_LICENSE_NOSSL',5);				// No SSL library is available.
define('SP_LICENSE_BADDOMAIN',6);		// Valid, but running on the wrong domain.
define('SP_LICENSE_BADSTAFF',7);			// Valid, but too many registered staff.
define('SP_LICENSE_OLD',8);				// License entered but is for V3, not V4.
define('SP_LICENSE_EXPIRED',9);			// License entered but has expired.
define('SP_LICENSE_NOSUPPORT',10);		// License entered but software is too new.

// Flags for having migrated records from specific other systems.
define('SP_MIGRATED_INSTALL',0);
define('SP_MIGRATED_TICKETPAY',1);
define('SP_MIGRATED_WHMCS_ADDON',2);
define('SP_MIGRATED_WHMCS',3);

// Values for payment classes
define('SP_PAYTYPE_TICKET',1);
define('SP_PAYTYPE_LIVESUPPORT',2);

// And incoming payments. NULL is a standard payment.
define('SP_PAYTYPE_SALE',null);
define('SP_PAYTYPE_DEFERRED',3);		// For pre-authorised, deferred payments.
//define('SP_PAYTYPE_RECURRING',4);		// For regular billing agreements


if (!isset($_SWIFT)) {
	$_SWIFT = SWIFT::GetInstance();
}

global $sp_licensetxt;
$sp_licensetxt[SP_LICENSE_NONE] = $_SWIFT->Language->Get("sp_lic_none");
$sp_licensetxt[SP_LICENSE_INVALID] = $_SWIFT->Language->Get("sp_lic_invalid");
$sp_licensetxt[SP_LICENSE_GOOD] = $_SWIFT->Language->Get("sp_lic_valid");
$sp_licensetxt[SP_LICENSE_BAD] = $_SWIFT->Language->Get("sp_lic_bad");
$sp_licensetxt[SP_LICENSE_NOSSL] = $_SWIFT->Language->Get("sp_lic_ssl");
$sp_licensetxt[SP_LICENSE_BADDOMAIN] = $_SWIFT->Language->Get("sp_lic_domain");
$sp_licensetxt[SP_LICENSE_BADSTAFF] = $_SWIFT->Language->Get("sp_lic_staff");
$sp_licensetxt[SP_LICENSE_OLD] = $_SWIFT->Language->Get("sp_lic_old");
$sp_licensetxt[SP_LICENSE_EXPIRED] = $_SWIFT->Language->Get("sp_lic_expired");
$sp_licensetxt[SP_LICENSE_NOSUPPORT] = $_SWIFT->Language->Get("sp_lic_nosupport");

global $sp_license;
$sp_license["key"] = "";
$sp_license["expiry"] = 0;
$sp_license["site"] = "";
$sp_license["staff"] = 0;
$sp_license["status"] = SP_LICENSE_NONE;

// Flags for payment processors. Included here instead of sp_main because this can be called from cron and ipn.
define ('SP_PROCESSOR_AFFILIATE',-1);
define ('SP_PROCESSOR_NONE',0);
define ('SP_PROCESSOR_PAYPAL',1);
define ('SP_PROCESSOR_WORLDPAY',2);
//define ('SP_PROCESSOR_GCHECKOUT',3);
//define ('SP_PROCESSOR_2CO',4);
define ('SP_PROCESSOR_AUTHORIZE',5);

// Unit identifyers for recurring packages.
define ('SP_RECUR_UNIT_WEEK',1);
define ('SP_RECUR_UNIT_MONTH',2);
define ('SP_RECUR_UNIT_YEAR',3);

// WHMCS Transaction Push modes
global $sp_WHMCS_Modes;
define ('SP_WPM_NONE',0);
define ('SP_WPM_EACH',1);
define ('SP_WPM_DAILY',2);
define ('SP_WPM_WEEKLY',3);
define ('SP_WPM_MONTHLY',4);

// Some of these from staff_ticketsmanage
$testLang = $_SWIFT->Language->Get("recurrence_none");
if (empty($testLang)) {
	$_SWIFT->Language->Load('staff_ticketsmanage');
}
$sp_WHMCS_Modes[SP_WPM_NONE] = $_SWIFT->Language->Get("recurrence_none");
$sp_WHMCS_Modes[SP_WPM_EACH] = $_SWIFT->Language->Get("sp_whmcs_each");
$sp_WHMCS_Modes[SP_WPM_DAILY] = $_SWIFT->Language->Get("recurrence_daily");
$sp_WHMCS_Modes[SP_WPM_WEEKLY] = $_SWIFT->Language->Get("recurrence_weekly");
$sp_WHMCS_Modes[SP_WPM_MONTHLY] = $_SWIFT->Language->Get("recurrence_monthly");

// WHMCS Transaction Date Formats
global $sp_WHMCS_Dates;
$sp_WHMCS_Dates = array(
	array("name" => "DD/MM/YYYY", "fmt" => "d/m/Y"),
	array("name" => "DD.MM.YYYY", "fmt" => "d.m.Y"),
	array("name" => "MM/DD/YYYY", "fmt" => "m/d/Y"),
	array("name" => "DD.MM.YYYY", "fmt" => "m.d.Y"),
	);

?>
