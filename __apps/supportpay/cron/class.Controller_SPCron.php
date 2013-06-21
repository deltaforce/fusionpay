<?php

/* Don't have any RCS keywords in here, it causes an 'output before header' error. */

class Controller_SPCron extends Controller_cron
{
	public function __construct() {
		parent::__construct();

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function Pending() {
		// TODO: Check with PayPal for pending transactions for which we've somehow missed the IPN.
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions');
				
		// Clear any tickets which belong to non-existent users.
		$_SWIFT4 = SWIFT::GetInstance();
		
		$sql = "update ".TABLE_PREFIX."sp_ticket_paid set paid_date=".time().", bill_minutes=0 where userid not in (select userid from ".TABLE_PREFIX."users)";
		if (!$_SWIFT4->Database->Query($sql)) {
			$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(), $sql);
		}
		
		return true;
	}
	
	public function WHMCS() {
		global $SPFunctions, $sp_license;
		$_SWIFT4 = SWIFT::GetInstance();
		$res = true;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		
		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		
		if (!$sp_license["allow_whmcs"]) {
			return false;
		}

		if ($_SWIFT4->Settings->getKey("settings","sp_whmcs_enable")) {
			SWIFT_Loader::LoadLibrary('SupportPay:SPWHMCS', "supportpay");
			
			$SP_WC = new SWIFT_SPWHMCS;
			// Read in any new users. Is controlled by a setting inside the call.
			$res = $SP_WC->ReadUsers();

			$res &= $SP_WC->ReadPackages();

			$res &= $SP_WC->readActivePkgs();

			// Build a list of details to send to WHMCS.
			$last_auditlog_date = intval($_SWIFT4->Settings->getKey("settings","sp_lastwhmcscron"));
			if ($_SWIFT4->Settings->getKey("settings","sp_debug")) {
				error_log("Using last_cron time of ".$last_auditlog_date);
			}

			if ($last_auditlog_date == 0) {
				$last_auditlog_date = time();
				$_SWIFT4->Settings->UpdateKey('settings', 'sp_lastwhmcscron', $last_auditlog_date);
			}
			
			$pushMode = $_SWIFT4->Settings->getKey("settings","sp_whmcs_pushmode");
			if ($pushMode >= SP_WPM_DAILY) {
				switch ($pushMode) {
					case SP_WPM_DAILY:
						$next_auditlog_date = strtotime("+ 1 day",$last_auditlog_date);
						break;
					case SP_WPM_WEEKLY:
						$next_auditlog_date = strtotime("+ 1 week",$last_auditlog_date);
						break;
					case SP_WPM_MONTHLY:
						$next_auditlog_date = strtotime("+ 1 month",$last_auditlog_date);
						break;
				}

				if ($next_auditlog_date <= time()) {
					$sql = "select tp.userid, u.whmcs_userid, tp.ticketid, sum(tp.bill_minutes - ".
						"(case when tp.tickets = 1 then tp.bill_minutes else tp.minutes end)) owed ".
						"from ".TABLE_PREFIX."sp_ticket_paid tp, ".TABLE_PREFIX."sp_users u ".
						"where tp.ticketid in (select distinct al.ticketid from ".TABLE_PREFIX."ticketauditlogs al ".
						"where al.actiontype = 4 and al.dateline >= ".$last_auditlog_date.") ".
						"and u.userid = tp.userid and u.whmcs_userid is not null ".
						"group by tp.userid, tp.ticketid, u.whmcs_userid ".
						"having sum(tp.bill_minutes - (case when tp.tickets = 1 then tp.bill_minutes else tp.minutes end)) > 0";
					if (!$_SWIFT4->Database->Query($sql)) {
						$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(), $sql);
					} else {
						$userDets = array();
						
						while ($_SWIFT4->Database->NextRecord()) {
							$userDets[$_SWIFT4->Database->Record["userid"]] = $_SWIFT4->Database->Record;
						}

						foreach ($userDets as $wUser => $wDetails) {
							$res &= $SP_WC->SendTicketSummary($wDetails["whmcs_userid"], $wUser, $wDetails["owed"]);
						}
						
						if ($res) {
							// Only update this if everything was OK, otherwise re-try with the next time. Any partially-complete
							// updates will already have been marked complete in swsp_ticket_paid so they shouldn't be double-billed.
							$_SWIFT4->Settings->UpdateKey('settings', 'sp_lastwhmcscron', $next_auditlog_date);
						}
					}
				}
			} else {
				// Should we reset it here so it starts afresh if the setting is changed in future?
				$_SWIFT4->Settings->UpdateKey('settings', 'sp_lastwhmcscron', 0);
			}
			
			unset($SP_WC);
		}
		return true;
	}

	public function Reconciler() {
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions');

		if (empty($_SWIFT)) {
			$SPFunctions->SPErrorLog("Unable to process " . htmlentities($_SERVER['PHP_SELF']));
			return false;
		}
		
		$now = time();
		$debug = $_SWIFT->Settings->getKey("settings","sp_debug");

		if (!$SPFunctions->IsModuleRegistered("SP")) {
			$SPFunctions->SPErrorLog("Refusing to run reconciler - SupportPay module is disabled.");
			return false;
		}

		if (method_exists($_SWIFT->Language, "LoadModule")) {
			$_SWIFT->Language->LoadModule(SWIFT_LanguageEngine::DEFAULT_LOCALE, "supportpay");
		} else {
			$_SWIFT->Language->LoadApp(SWIFT_LanguageEngine::DEFAULT_LOCALE, "supportpay");
		}

		// Here as well as elsewhere...
		$SPFunctions->encryptAllPasswords();
		if ($debug) {
			error_log("sp reconciler starting, last run was at ".date(DATE_RFC2822,$_SWIFT->Settings->getKey("settings","sp_lastcron")));
		}

		// First, remove any expired dependency offers. Nice and simple.
		// ** TODO: Should this be in seconds?
		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_depoffers where (".$now."-offer_made) > ".
			intval($_SWIFT->Settings->getKey("settings","sp_amexpiry")));

		// Remove any expired cart contents. Leave 24 hours for regular carts, indefinitely for recurring.
		// And a week for PENDING.
		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_cart_items where cid in (select cid from ".
			TABLE_PREFIX."sp_cart_defs where (".$now."-created) > 86400 and ctype = ".SP_CTYPE_REALTIME.")");
		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_cart_defs where (".$now."-created) > 86400 ".
			"and ctype = ".SP_CTYPE_REALTIME);

		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_cart_items where cid in (select cid from ".
			TABLE_PREFIX."sp_cart_defs where (".$now."-created) > (7*86400) and ctype = ".SP_CTYPE_PENDING.")");
		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_cart_defs where (".$now."-created) > (7*86400) ".
			"and ctype = ".SP_CTYPE_PENDING);

		// Move any tickets from pending to active, if enabled
		if ($_SWIFT->Settings->getKey("settings","sp_gatekeeper")) {
			$fromStatus = $_SWIFT->Settings->getKey("settings","sp_gk_fromstatus");
			$toStatus = $_SWIFT->Settings->getKey("settings","sp_gk_tostatus");
			$payableDepts = $SPFunctions->getPayableDepts();
			$toAccept = array(); $toReject = array();
			
			$Rec = $_SWIFT->Database->QueryFetch("select title from ".TABLE_PREFIX."ticketstatus ".
				"where ticketstatusid = ".$toStatus);
			$toStatusText = $Rec["title"];
			
			$_SWIFT->Database->Query("select ticketid, userid, departmentid, ticketstatusid, totalreplies ".
				" from ".TABLE_PREFIX."tickets where ticketstatusid = ".$fromStatus);
			while ($_SWIFT->Database->NextRecord()) {
				if (in_array($_SWIFT->Database->Record["departmentid"], $payableDepts)) {
					if ($SPFunctions->customerCanPay($_SWIFT->Database->Record["userid"], $_SWIFT->Database->Record["departmentid"])) {
						$toAccept[] = $_SWIFT->Database->Record["ticketid"];
					} elseif ($_SWIFT->Database->Record["totalreplies"] == 0) {
						$toReject[] = $_SWIFT->Database->Record["ticketid"];
					}
				} else {
					$toAccept[] = $_SWIFT->Database->Record["ticketid"];
				}
			}
			
			if (count($toAccept) > 0) {
				$sql = "update ".TABLE_PREFIX."tickets set ticketstatusid=".$toStatus.
					", ticketstatustitle='".$_SWIFT->Database->Escape($toStatusText)."' ".
					" where ticketid in (".buildIN($toAccept).")";
				if (!$_SWIFT->Database->Query($sql)) {
					$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(),$sql);
				}
			}
			
			foreach ($toReject as $theTicket) {
				$SPFunctions->sendAddCreditReply($theTicket);
			}
		}
				
		// Move any expired rows to sp_user_payments_old
		// TODO: This is MySQL-specific, needs to change.
		if (intval($_SWIFT->Settings->getKey("settings","sp_pmexpiry")) > 0) {
			$sql = "replace into ".TABLE_PREFIX."sp_user_payments_old ".
				"(select * FROM ".TABLE_PREFIX."sp_user_payments ".
				"WHERE date_add(created, interval ".intval($_SWIFT->Settings->getKey("settings","sp_pmexpiry"))." day) < NOW() ".
				"AND rem_minutes = 0 AND rem_tickets = 0)";
			if (!$_SWIFT->Database->Query($sql)) {
				$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(),$sql);
			}
			
			$sql = "delete from ".TABLE_PREFIX."sp_user_payments WHERE txid in ".
				"(select txid FROM ".TABLE_PREFIX."sp_user_payments_old);";
			if (!$_SWIFT->Database->Query($sql)) {
				$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(),$sql);
			}
		}

		// Remove any expired affiliates.
		if (intval($_SWIFT->Settings->getKey("settings","sp_affexpiry") > 0)) {
			$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set affiliate=null, aff_timestamp=null ".
				" where (".$now." - aff_timestamp) / 86400 > ".intval($_SWIFT->Settings->getKey("settings","sp_affexpiry")));
		}

		$checksince = intval($_SWIFT->Settings->getKey("settings","sp_lastcron"))-10;
		// TODO : Check for re-opened tickets.
		/*
			if (ticket_recent_activity > last_check_time and
				ticket_status != closed and
				paid_date is not null)
			{
				// It's been re-opened. (?)
			}
		*/

		////////////////////////////////////////////////////////////
		// Remove any "sp_user" records from various places if the base user no longer exists.
		$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_users ".
			"where userid not in (select userid from ".TABLE_PREFIX."users)");
		$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set payerid = NULL where payerid is not null and ".
			"payerid not in (select userid from ".TABLE_PREFIX."users)");

		////////////////////////////////////////////////////////////
		// Delete any pre-auth agreements that have expired since the last check.
		// They never really existed, so don't bother reconciling a negative payment; just remove the total and
		// recalc the users' credits.
		$_transList = array();
		$_SWIFT->Database->Query("select userid, proc_txid from ".TABLE_PREFIX."sp_user_payments where paytype = ".SP_PAYTYPE_DEFERRED.
			" and proc_txid is not null and expiry < ".$now);
		while ($_SWIFT->Database->NextRecord()) {
			if (!isset($_transList[$_SWIFT->Database->Record["userid"]])) {
				$_transList[$_SWIFT->Database->Record["userid"]] = array();
			}
			$_transList[$_SWIFT->Database->Record["userid"]][] = $_SWIFT->Database->Record["proc_txid"];
		}
		
		// Must issue the PayPal "DoVoid" call here.
		foreach ($_transList as $userid => $translist) {
			foreach ($translist as $transid) {
				$SPFunctions->authTransVoid($transid, $userid);
			}
			$SPFunctions->updateUserCredits($userid, $errmsg);
		}

		////////////////////////////////////////////////////////////
		// Delete any minutes that belong to packages that have expired since the last check.
		$_SWIFT->Database->Query("select up.userid,up.txid,sum(rem_minutes) as rem_minutes, sum(rem_tickets) as rem_tickets ".
			"from ".TABLE_PREFIX."sp_user_payments AS up ".
			"where up.packageid is not null and up.expiry is not null ".
			"and up.expiry < ".$now." and (up.rem_minutes > 0 or up.rem_tickets > 0) GROUP BY up.userid,up.txid");
		$_pkglist=array();
		while ($_SWIFT->Database->NextRecord()) {
			$_pkglist[] = $_SWIFT->Database->Record;
		}

		if ($debug) {
			error_log(count($_pkglist) . " packages to clear");
		}

		foreach ($_pkglist as $Rec) {
			$_SWIFT->Database->StartTrans();
			if ($debug) {
				error_log("Clear package payload from user ".$Rec["userid"].", Tx#".$Rec["txid"]);
			}
			$sql = "INSERT INTO ".TABLE_PREFIX."sp_user_payments (userid,minutes,tickets,rem_minutes,rem_tickets,".
				"cost,currency,comments,paidby,created) VALUES (".
				$Rec["userid"].",".(-$Rec["rem_minutes"]).",".(-$Rec["rem_tickets"]).",0,0,0,'".$_SWIFT->Settings->getKey("settings","sp_currency")."',".
				"'Expired package','".$_SWIFT->Database->Escape($_SWIFT->Language->Get("sp_sysname"))."',".time().")";
			if (!$_SWIFT->Database->Query($sql)) {
				$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(),$sql);
			}
			
			$sql = "UPDATE ".TABLE_PREFIX."sp_user_payments SET rem_minutes=0,rem_tickets=0 ".
				"WHERE userid=".$Rec["userid"]." AND txid=".$Rec["txid"];
			if (!$_SWIFT->Database->Query($sql)) {
				$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(),$sql);
			}
			
			$SPFunctions->updateUserCredits($Rec["userid"], $errmsg);
			$_SWIFT->Database->CompleteTrans();
		}

		////////////////////////////////////////////////////////////
		// Handle negative rem_minutes/rem_tickets, possible overpayments
		// or deducted by expired packages
		$_SWIFT->Database->Query("SELECT userid,txid,rem_minutes,rem_tickets".
			" FROM ".TABLE_PREFIX."sp_user_payments".
			" WHERE pending is null and (rem_minutes < 0 OR rem_tickets < 0)");
		$_debitlist=array();
		while ($_SWIFT->Database->NextRecord()) {
			$_debitlist[] = $_SWIFT->Database->Record;
		}
		if ($debug) {
			error_log(count($_debitlist) . " negative payments");
		}
		foreach ($_debitlist as $dbt) {
			if ($debug) {
				error_log("Debit ".$dbt["txid"].", user ".$dbt["userid"].
					", rem_mins = ".$dbt["rem_minutes"].
					", rem_tkts = ".$dbt["rem_tickets"]
					);
			}
			
			$SPFunctions->reconcilePayment(null, null, $dbt["txid"], $dbt["userid"], -$dbt["rem_tickets"], -$dbt["rem_minutes"]);
		}

		// Remove any payerids for users who no longer belong to the correct organization.
		if ($_SWIFT->Settings->getKey("settings","sp_am_native")) {
			$orgMgrs = array();
			
			$_SWIFT->Database->Query("select u.userorganizationid, ".
				"coalesce(max(spu.userid * spu.acctmgr),'null') userid ".
				"from ".TABLE_PREFIX."users u left join ".TABLE_PREFIX."sp_users spu using (userid) ".
				"where coalesce(u.userorganizationid,0) > 0 group by u.userorganizationid");
			while ($_SWIFT->Database->NextRecord()) {
				$orgMgrs[$_SWIFT->Database->Record["userorganizationid"]] = $_SWIFT->Database->Record["userid"];
			}
			
			foreach ($orgMgrs as $orgId => $mgrId) {
				// Set the payer for any users whose orgid and payerid don't match.
				if ($mgrId != 0 and $mgrId != "null") {
					$sql = "update ".TABLE_PREFIX."sp_users spu ".
						"set payerid = ".$mgrId." where coalesce(payerid,-1) != ".$mgrId.
						" and userid != ".$mgrId." and exists (select 1 from ".TABLE_PREFIX."users u where u.userid = spu.userid ".
						" and u.userorganizationid = ".$orgId.")";
					if (!$_SWIFT->Database->Execute($sql)) {
						$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(), $sql);
					}
				}
				$sql = "update ".TABLE_PREFIX."sp_users spu ".
					"set payerid = null where coalesce(payerid,-1) = coalesce(".$mgrId.",-1)".
					" and exists (select 1 from ".TABLE_PREFIX."users u where u.userid = spu.userid ".
					" and u.userorganizationid != ".$orgId.")";

				// Clear the payer for any users who have a payerid that isn't valid for this organization.
				if (!$_SWIFT->Database->Execute($sql)) {
					$SPFunctions->SPErrorLog($_SWIFT->Database->FetchLastError(), $sql);
				}
			}
		}

		// The juicy bits.
		if ($_SWIFT->Settings->getKey("settings","sp_autobilltkt")) {
			$SPFunctions->payTickets(SP_PAYTYPE_TICKET, array(),"any",$checksince);
		}
		if ($_SWIFT->Settings->getKey("settings","sp_autobilllive")) {
			if ($SPFunctions->IsModuleRegistered("LIVECHAT")) {
				$SPFunctions->matchChatUsers($errmsg);
				if (!empty($errmsg)) {
					$SPFunctions->SPErrorLog("Matching chat users",$errmsg);
				}
				$SPFunctions->payTickets(SP_PAYTYPE_LIVESUPPORT, array(),"any",$checksince);
			}
		}

		////////////////////////////////////////////////////////////
		// Now scan for any accounts which are working on 'overdraft'
		// and spam them.
		$SPFunctions->sendAccountInvoices();
		
		////////////////////////////////////////////////////////////
		// Finally, set the 'last processed' time. Can't rely on the
		// one in the cron table because it's updated before we're called.
		$_SWIFT->Settings->UpdateKey("settings", "sp_lastcron", $now);
		
		////////////////////////////////////////////////////////////
		// Done.
		if ($debug) {
			error_log("sp reconciler ending");
		}

		return true;
	}
};

?>