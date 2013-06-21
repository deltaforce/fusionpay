<?php

unset($_adminBarContainer);
unset($_adminLinkContainer);
unset($_staffMenuContainer);
unset($_staffLinkContainer);

$_SWIFT = SWIFT::GetInstance();

if ($_SWIFT->Interface->GetInterface() == SWIFT_Interface::INTERFACE_STAFF) {
	$_staffMenuContainer = SWIFT::Get('staffmenu');
	$_staffLinkContainer = SWIFT::Get('stafflinks');
} elseif ($_SWIFT->Interface->GetInterface() == SWIFT_Interface::INTERFACE_ADMIN) {
	$_adminBarContainer = SWIFT::Get('adminbar');
	$_adminLinkContainer = SWIFT::Get('adminbaritems');
}

if (isset($_staffMenuContainer)) {
	// Now the actual menu items.
	array_push($_staffMenuContainer, array ('SupportPay', 90, "supportpay", 3, 'sp_entab'));
	foreach ($_staffMenuContainer as $ind => $abentry) {
		if ($abentry[0] == 'SupportPay') {
			$menunum = $ind;
			break;
		}
	}

	$_staffLinkContainer[$menunum] = array (
		array($__LANG["sp_sm_answer"], "/supportpay/Awaiting/Main", false, true),
		array ($__LANG["sp_sm_paytkt"], "/supportpay/Unpaid/Main", true, true), 
		array ($__LANG["sp_sm_paylive"], "/supportpay/UnpaidLive/Main", true, true), 
		array ($__LANG["sp_sm_usercdt"], "/supportpay/UserCredit/Main", true, true), 
		array ($__LANG["sp_sm_orgcdt"], "/supportpay/OrgCredit/Main", true, true), 
		//		array ($__LANG["sp_sm_paydets"], "/supportpay/StaffPayDets/Main", true, false), 
		);
} elseif (isset($_adminBarContainer)) {
	array_push($_adminBarContainer, array ('SupportPay', '../../../../'.SWIFT_MODULESDIRECTORY.
		'/supportpay/resources/bar_sp.gif', "supportpay"));
	foreach ($_adminBarContainer as $ind => $abentry) {
		if ($abentry[0] == 'SupportPay') {
			$menunum = $ind;
			break;
		}
	}
	$_adminLinkContainer[$menunum] = array (
		array ($__LANG["sp_am_manlic"], "/supportpay/ShowLicense/Main"),
		array ($__LANG["sp_am_import"], "/supportpay/SPImport/Manage"),
		array ($__LANG["sp_am_updchk"], "/supportpay/Updates/Main"),
//		array ($__LANG["sp_am_deptrates"], "/supportpay/DeptRate/Main"),
		array ($__LANG["sp_am_manpkg"], "/supportpay/Packages/Main"),
		array ($__LANG["sp_am_rep"], "/supportpay/Reports/Main"),
		array ($__LANG["sp_am_audit"], "/supportpay/Audit/Main"),
		array ($__LANG["sp_am_deptrules"], "/supportpay/DeptRules/Main"),
		);

/*	
	// Staff Payment options
	array_push($_adminBarContainer, array ('SupportPayRoll', '../../../../'.
		SWIFT_MODULESDIRECTORY.'/supportpay/resources/bar_sp.gif', "supportpay"));
	$menunum++;
	$_adminLinkContainer[$menunum] = array (
		array ($__LANG["sp_am_paystaff"], "/supportpay/PayStaff/Main"),
		array ($__LANG["sp_am_listsp"], "/supportpay/PSList/Main"),
		array ($__LANG["sp_am_deptrates"], "/supportpay/PSDeptRates/Main"),
		);
*/
}

if ($_SWIFT->Interface->GetInterface() == SWIFT_Interface::INTERFACE_STAFF) {
	SWIFT::Set('staffmenu', $_staffMenuContainer);
	SWIFT::Set('stafflinks', $_staffLinkContainer);
} elseif ($_SWIFT->Interface->GetInterface() == SWIFT_Interface::INTERFACE_ADMIN) {
	SWIFT::Set('adminbar', $_adminBarContainer);
	SWIFT::Set('adminbaritems', $_adminLinkContainer);
}

?>