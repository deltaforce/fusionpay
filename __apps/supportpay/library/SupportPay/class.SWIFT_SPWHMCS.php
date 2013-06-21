<?php

/*
Currently:
- Create Kayako users from WHMCS users, without passwords
- Pass SP billing items into WHMCS where a user association exists
  - Per-ticket
  - Daily, Weekly, or Monthly
*/

class SWIFT_SPWHMCS
{
	private $_SWIFT4;
	private $dateFormat;
	private $accept;
	public $minAPIVersions = array(
		"spgetactivepkg" => 3263,
	);
	
	public function __construct()
	{
		global $sp_WHMCS_Dates;
		
		$this->_SWIFT4 = SWIFT::GetInstance();
		$this->dateFormat = $sp_WHMCS_Dates[$this->_SWIFT4->Settings->getKey("settings","sp_whmcs_dateformat")]["fmt"];
		$this->accept = $this->_SWIFT4->Settings->getKey("settings","sp_accept");

		if (file_exists(SWIFT_MODULESDIRECTORY.'/supportpay/library/SupportPay/class.SWIFT_SPFunctions.php')) {
			SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		}

		return true;
	}

	private function CalcTicketCost($remMinutes) {
		if ($this->accept == SP_ACCEPT_MINUTES || ($this->accept == SP_ACCEPT_BOTH && 
			$remMinutes <= $this->_SWIFT4->Settings->getKey("settings","sp_preferticket")))
		{
			$cost = $remMinutes * $this->_SWIFT4->Settings->getKey("settings","sp_costpermin");
		} else {
			$cost = $this->_SWIFT4->Settings->getKey("settings","sp_costpertkt");
		}
		
		return $cost;
	}
	
	public function SendTicketSummary($whmcs_userid, $local_userid, $wanted_minutes) {
		global $SPFunctions, $sp_license;

		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($this->_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		
		if (!$sp_license["allow_whmcs"] || $wanted_minutes == 0)
			return false;
		
		$res = false;
		$cost = $this->CalcTicketCost($wanted_minutes);		

		$res = $this->doWHMCSApiCall(array(
			"action" => "addbillableitem", "clientid" => $whmcs_userid,
			"description" => "SupportPay Summary ".date($this->dateFormat,time()),
			"amount" => $cost, "invoiceaction" => "nextinvoice", 
			"duedate" => date($this->dateFormat,time()),
			"hours" => ($wanted_minutes / 60.0)
			));
		// "result=success;billableid=1;"
		
		if (strpos($res,"result=success") !== false) {
			$sql = "UPDATE ".TABLE_PREFIX."sp_ticket_paid SET minutes=bill_minutes, paid_date=".time().
				" WHERE userid=".$local_userid." and (tickets = 0 and minutes < bill_minutes)";
			$this->_SWIFT4->Database->Execute($sql);
			$errmsg = $this->_SWIFT4->Database->FetchLastError();
			if (!empty($errmsg)) {
				$SPFunctions->SPErrorLog("Marking ticket paid after transmitting to WHMCS",$errmsg);
			} else {
				$res = true;
			}
		}
		
		return $res;
	}
	
	public function SendSingleTicket(&$ticketid,&$maskId,&$userid,&$tktType,$wanted_minutes)
	{
		// Make a billing entry for a single ticket. If successful, mark this ticket as paid.
		global $SPFunctions, $sp_license;
		
		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($this->_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		
		if (!$sp_license["allow_whmcs"] || $wanted_minutes == 0) {
			return false;
		}
		
		$res = false;
		$userid = intval($userid);
		
		$sql = "select whmcs_userid from ".TABLE_PREFIX."sp_users where userid=".$userid;
		
		if ($sp_license["allow_accounts"]) {
			// See if there's an account manager that we can use in preference.
			$sql = "select whmcs_userid from (".
				"select 2 o, whmcs_userid from ".TABLE_PREFIX."sp_users where userid = ".$userid.
				" union select 1 o, whmcs_userid from ".TABLE_PREFIX."sp_users ".
				"where userid = (select payerid from ".TABLE_PREFIX."sp_users where userid = ".$userid.")) p order by o";
		}
		$Rec = $this->_SWIFT4->Database->QueryFetch($sql);
		if (!is_null($Rec["whmcs_userid"])) {
			$cost = $this->CalcTicketCost($wanted_minutes);		
			
			$res = $this->doWHMCSApiCall(array(
				"action" => "addbillableitem", "clientid" => $Rec["whmcs_userid"],
				"description" => "SupportPay Ticket ".$maskId,
				"amount" => $cost, "invoiceaction" => "nextinvoice", 
				"duedate" => date($this->dateFormat,time()),
				"hours" => ($wanted_minutes / 60.0)
				));
			
			if (strpos($res,"result=success") !== false) {
				$sql = "UPDATE ".TABLE_PREFIX."sp_ticket_paid SET minutes=bill_minutes, paid_date=".time().
					" WHERE userid=".$userid." AND ticketid=".$ticketid." AND paytype = ".$tktType;
				
//				error_log("WHMCS marking ticket as paid: userid=".$userid.", ticketid=".$ticketid.", paytype=".$tktType);
				
				$this->_SWIFT4->Database->Execute($sql);
				$errmsg = $this->_SWIFT4->Database->FetchLastError();
				if (!empty($errmsg)) {
					$SPFunctions->SPErrorLog("Marking ticket paid after transmitting to WHMCS",$errmsg);
				} else {
					$res = true;
				}	
			} else {
				error_log("WHMCS didn't accept payment for ticket #".$ticketid.", WHMCS user#".$Rec["whmcs_userid"]);
				error_log("WHMCS said: ".$res);
			}
		} else {
			error_log("No WHMCS users identified for ticket #".$ticketid);
		}
		return $res;
	}
	
	public function AddUpdateUser($userXML, &$xmlRes, $doCreate = true)	{
		$resCode = false;
		$didCreateOrg = false;

		if (isset($userXML)) {
			// It's an XML string.
			$userDoc = @simplexml_load_string($userXML);
			if (!empty($userDoc)) {
				$xmlRes->{"result"}[0] = 1;
				$xmlUser = $xmlRes->addChild("user");
				$whmcs_userid = null;

				if (isset($userDoc->{'client'})) {
					// Full user registration.
					$userDetails = $userDoc->{'client'};
					$whmcs_userid = $userDetails->{'id'};
				} elseif (isset($userDoc->{'userid'})) {
					// Contact details.
					$userDetails = $userDoc;
					$whmcs_userid = $userDetails->{'userid'};
				} else {
					$xmlRes->{"result"}[0] = 0;
					return false;
				}

				$phone = "";
				$userPosition = "";	// WHMCS doesn't supply this.
				$fullName = $userDetails->{'firstname'}." ".$userDetails->{'lastname'};
				$organisationID = 0;
				$createGroup = intval($this->_SWIFT4->Settings->getKey("settings","sp_whmcs_defaultgroup"));

				$groupDets = $this->_SWIFT4->Database->queryFetch("select usergroupid, title from ".TABLE_PREFIX."usergroups ".
					"where usergroupid = ".$createGroup." order by grouptype desc, usergroupid asc");

				$xmlUser->addChild("fullname",$fullName);
				$xmlUser->addChild("usergroup", $groupDets["title"]);
				if (!empty($userDetails->{'phonenumber'})) {
					$phone = "".trim($userDetails->{'phonenumber'});
					$xmlUser->addChild("phone",$phone);
				}
				
				$coName = trim($userDetails->{'companyname'});
				if (!empty($coName) && $this->_SWIFT4->Settings->getKey("settings","sp_am_native")) {
					if (method_exists('SWIFT_Loader','LoadModel')) {
						SWIFT_Loader::LoadModel('User:UserOrganization');
					} else {
						SWIFT_Loader::LoadLibrary('User:UserOrganization');
					}

					$orgDetails = SWIFT_UserOrganization::RetrieveOnName($coName);
					if (count($orgDetails) > 0) {
						// We have a valid organisation.
						$organisationID = array_keys($orgDetails);
						$organisationID = $organisationID[0];
					} else {
						$newOrg = SWIFT_UserOrganization::Create($coName, SWIFT_UserOrganization::TYPE_RESTRICTED);
						$organisationID = $newOrg->GetUserOrganizationID();
						$didCreateOrg = true;
					}
				}
				$txtEmail = "".trim($userDetails->{'email'});
				$xmlEmail = $xmlUser->addChild("emails");
				$xmlEmail->addChild("email","".$txtEmail);

				if (method_exists('SWIFT_Loader','LoadModel')) {
					SWIFT_Loader::LoadModel('User:UserEmail');
				} else {
					SWIFT_Loader::LoadLibrary('User:UserEmail');
				}

				$_SWIFT_UserObject = SWIFT_User::RetrieveOnEmailList(array($txtEmail));

				/* But since we're nice and want to set up Fusion *properly*, test and create the user here. */
				if (empty($_SWIFT_UserObject)) {
					if ($doCreate) {
						if (IsEmailValid($txtEmail)) {
							try {
								$_SWIFT_UserObject = SWIFT_User::Create($groupDets["usergroupid"], $organisationID, SWIFT_User::SALUTATION_NONE, 
									$fullName, $userPosition, $phone, true, SWIFT_User::ROLE_USER,
									array($txtEmail), substr(BuildHash(), 0, 14), $this->_SWIFT4->Language->GetLanguageID(),
									false, false, false, false, false, false, true, false);
								
								$_SWIFT_UserObject->MarkProfilePrompt();
							} catch (Exception $e) {
								$xmlRes->addChild("message", $e->getMessage() . " while importing user '".$fullName."', email '".
									$txtEmail."' from WHMCS");
								return false;
							}
						} else {
							// Again, not really an error. This user has no valid email account.
							$xmlRes->addChild("message", "WHMCS user '".$fullName."', id#".
								$whmcs_userid.", has invalid or missing email address '".
								$txtEmail."'");
							return false;
						}
					} else {
						// We're stuffed - the user doesn't exist, and we're not allowed to create any.
						// Not really an error though, so return true.
						error_log("X1");
						return true;
					}
				} else {
					// The user already exists. Do we need to update any of the values?
					$myName = $_SWIFT_UserObject->GetFullName(false);
					$myOrg = $_SWIFT_UserObject->GetOrganization(); // Returns an object, not an ID
					$myOrgID = 0;
					if (is_object($myOrg)) {
						$myOrg->GetUserOrganizationID();
					}
					
					if ($myName != $fullName) {
						$_SWIFT_UserObject->UpdateLoginShare($_SWIFT_UserObject->GetProperty('usergroupid'), $fullName);
					}
					if ($myOrgID != $organisationID) {
						$_SWIFT_UserObject->UpdateOrganization($organisationID);
					}
				}

				// If we get here, the user exists.
				$resCode = true;

				$myDir = basename(dirname(__FILE__));
				if (file_exists(SWIFT_MODULESDIRECTORY.'/supportpay/library/SupportPay/class.SWIFT_SPFunctions.php')) {
					global $SPFunctions;
					SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
					$errlog = "";

					$SPFunctions->checkUserExists($_SWIFT_UserObject->GetUserID(), $errlog);
					$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_users set whmcs_userid = ".
						$whmcs_userid." where userid = ".$_SWIFT_UserObject->GetUserID());

					global $sp_license;
					if ($didCreateOrg && $sp_license["allow_accounts"]) {
						// This user's the first one for this organization. Make them the manager.
						$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_users ".
							"set acctmgr = 1, payerid = null where userid = ".$_SWIFT_UserObject->GetUserID());
					}

					// Store this as a valid WHMCS user, for cross-referencing deletions.
					$this->_SWIFT4->Database->Query("insert into ".TABLE_PREFIX."sp_whmcs_knownusers (userid, email) ".
						"values (".$whmcs_userid.",'".$this->_SWIFT4->Database->Escape($txtEmail)."')");
				}
				
				$newNotes = "".trim($userDetails->{'notes'});
				if (!empty($newNotes)) {
					// Bring in any notes. Can't use SWIFT_UserNote because it assumes that a note is created by a
					// logged-in staff member.
					if (method_exists('SWIFT_Loader','LoadModel')) {
						SWIFT_Loader::LoadModel('User:UserNote');
					} else {
						SWIFT_Loader::LoadLibrary('User:UserNote');
					}

					$Staff = $this->_SWIFT4->Database->queryFetch("select staffid from ".TABLE_PREFIX."staff s, ".TABLE_PREFIX."staffgroup sg ".
						"where sg.staffgroupid = s.staffgroupid and sg.isadmin = 1 and s.isenabled = 1 order by sg.staffgroupid, staffid");
					if (!empty($Staff)) {
						// Got an admin. 
						$Record = $this->_SWIFT4->Database->queryFetch("select n.usernoteid, nd.notecontents from ".TABLE_PREFIX."usernotes n, ".TABLE_PREFIX."usernotedata nd ".
							"where nd.usernoteid = n.usernoteid and n.staffname = 'WHMCS' and linktype = ".SWIFT_UserNote::LINKTYPE_USER.
							" and n.linktypeid = ".$_SWIFT_UserObject->GetUserID());
						if (!empty($Record)) {
							// Already got an WHMCS note.
							if ($Record["notecontents"] != $userDetails->{'notes'}) {
								$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX."usernotedata set notecontents='".
									$this->_SWIFT4->Database->Escape($newNotes)."' where usernoteid = ".$Record["usernoteid"]);
							}
						} else {
							$this->_SWIFT4->Database->StartTrans();
							$this->_SWIFT4->Database->Execute("insert into ".TABLE_PREFIX."usernotes (linktypeid, linktype, dateline, staffid, staffname, notecolor) values (".
								$_SWIFT_UserObject->GetUserID().", ".SWIFT_UserNote::LINKTYPE_USER.", ".time().", ".$Staff["staffid"].", 'WHMCS', 1)");
							$_lastInsert = $this->_SWIFT4->Database->InsertID();
							$this->_SWIFT4->Database->Execute("insert into ".TABLE_PREFIX."usernotedata (usernoteid, notecontents) values (".
								$_lastInsert.", '".$this->_SWIFT4->Database->Escape($newNotes)."')");
							$this->_SWIFT4->Database->CompleteTrans();
						}
					}
				}
			} else {
				$xmlRes->addChild("message", "Unable to read XML");
				global $SPFunctions;
				if (isset($SPFunctions)) {
					$SPFunctions->SPErrorLog("Unable to read XML when adding WHMCS user".$userXML);
				}
			}
		} else {
			$xmlRes->addChild("message", "No Response from WHMCS");
		}
		
		return $resCode;
	}
   
	public function ReadUsers()
	{
		global $SPFunctions, $sp_license;
		$safeDelete = 0;
		
		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($this->_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		
		if (!$sp_license["allow_whmcs"]) {
			return false;
		}
		
		$this->_SWIFT4->Database->Query("delete from ".TABLE_PREFIX."sp_whmcs_knownusers");
		
		// Get a list of users and optionally create any that don't already exist.
		$users = $this->doWHMCSApiCall(array(
			"action" => "getclients",
			"limitnum" => "99999999",
			));

		$doCreate = $this->_SWIFT4->Settings->getKey("settings","sp_whmcs_addusers");
		$createGroup = $this->_SWIFT4->Settings->getKey("settings","sp_whmcs_defaultgroup");

		if (isset($users)) {
			// It's an XML string.
			$xml_doc = @simplexml_load_string($users);
			if (!empty($xml_doc)) {
				$safeDelete++;

				if ($xml_doc->{'numreturned'} > 0) {
					foreach ($xml_doc->{'clients'}->{'client'} as $wUser) {
						$whmcs_userid = (int)($wUser->{'id'});
						// Handle adding or updating this WHMCS user. Fake loginshare XML result entry.
						$xmlRes = simplexml_load_string("<loginshare><result>0</result></loginshare>");
						
						$userDetails = $this->doWHMCSApiCall(array(
							"action" => "getclientsdetails", 
							"clientid" => $whmcs_userid,
							"responsetype" => "xml"
							));

						if (!$this->AddUpdateUser($userDetails, $xmlRes, $doCreate)) {
							$SPFunctions->SPErrorLog("Add/Update User Failed: ".$xmlRes->{'message'});
						}
					}
				}
			}
		}

		// Same again, for "contacts" or sub-accounts.
		$users = $this->doWHMCSApiCall(array(
			"action" => "getcontacts",
			"limitnum" => "99999999",
			));
		
		if (isset($users)) {
			// It's an XML string.
			$xml_doc = @simplexml_load_string($users);
			if (!empty($xml_doc)) {
				$safeDelete++;
				if ($xml_doc->{'numreturned'} > 0) {
					foreach ($xml_doc->{'contacts'}->{'contact'} as $wUser) {
						// Handle adding or updating this WHMCS user. Fake loginshare XML result entry.
						$xmlRes = simplexml_load_string("<loginshare><result>0</result></loginshare>"); 
						if (!$this->AddUpdateUser($wUser->asXML(), $xmlRes, $doCreate)) {
							$SPFunctions->SPErrorLog($xmlRes->{'message'});
						}
					}
				}
			}
		}

		if ($safeDelete == 2) {
			// Unlink any local accounts where the WHMCS userid no longer exists.
			$this->_SWIFT4->Database->Query("update ".TABLE_PREFIX."sp_users set whmcs_userid = NULL ".
				"where whmcs_userid is not null and whmcs_userid not in (select distinct userid from ".TABLE_PREFIX."sp_whmcs_knownusers)");
			
			// Unlink any where the userid is still valid, but the email address has changed.
			$this->_SWIFT4->Database->Query("update ".TABLE_PREFIX."sp_users su set whmcs_userid = null ".
			"where userid in (select u.userid from ".TABLE_PREFIX."useremails e, ".TABLE_PREFIX."users u ".
			"where e.linktypeid = u.userid and e.linktype = 1 and su.userid = u.userid and su.whmcs_userid is not null ".
			"and lower(e.email) not in (select lower(email) from ".TABLE_PREFIX."sp_whmcs_knownusers))"
			);
		}

		// Two separate queries to WHMCS - need both to succeed.
		return ($safeDelete == 2);
	}
	
	public function ReadPackages()
	{
		global $SPFunctions, $sp_license;
		
		$res = false;
		
		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($this->_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		if (!$sp_license["allow_whmcs"] || !$this->_SWIFT4->Settings->getKey("settings","sp_whmcs_packages")) {
			return false;
		}

		$pkgList = array();
		$MyPkgs = array();
		
		$this->_SWIFT4->Database->Query("select * from ".TABLE_PREFIX.
			"sp_packages where migrated in (".SP_MIGRATED_WHMCS.", ".SP_MIGRATED_WHMCS_ADDON.")");
		while ($this->_SWIFT4->Database->NextRecord()) {
			$MyPkgs[$this->_SWIFT4->Database->Record["migrated"]][$this->_SWIFT4->Database->Record["migrated_id"]] = 
					$this->_SWIFT4->Database->Record;
		}

		$pkgList = $SPFunctions->GetWHMCSPackages();
		
		if (is_array($pkgList)) {		
			if (count($pkgList) > 0) {
				// We have packages.
				foreach (array_keys($pkgList) as $migType) {
					if (!isset($MyPkgs[$migType])) {
						$MyPkgs[$migType] = array();
					}
					
					$pkgKeys = array_keys($MyPkgs[$migType]);
					foreach ($pkgList[$migType] as $pkgId => &$pkgInfo) {
						if ($pkgInfo["minutes"] != 0 || $pkgInfo["tickets"] != 0) {	
							if (in_array($pkgId, $pkgKeys, true)) {	// Strict comparison
								$existingPkg = &$MyPkgs[$migType][$pkgId];
								
								// This package is already registered. Update the values if necessary, avoid
								// unnecessary DB updates.
								if ($pkgInfo["minutes"] != $existingPkg["minutes"] ||
									$pkgInfo["tickets"] != $existingPkg["tickets"] ||
									$pkgInfo["price"] != $existingPkg["price"] ||
									$pkgInfo["name"] != $existingPkg["title"] ||
									$pkgInfo["description"] != $existingPkg["description"] ||
									$pkgInfo["recur_period"] != $existingPkg["recur_period"] ||
									$pkgInfo["recur_unit"] != $existingPkg["recur_unit"] ||
									$pkgInfo["duration"] != $existingPkg["duration"]
								) {
									$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_packages ".
										"set title = '".$this->_SWIFT4->Database->Escape(substr($pkgInfo["name"],0,128)).
										"', description = '".$this->_SWIFT4->Database->Escape(substr($pkgInfo["description"],0,256))."', ".
										"minutes = ".$pkgInfo["minutes"].", tickets = ".$pkgInfo["tickets"].", price = ".floatval($pkgInfo["price"]).", ".
										"recur_period = ".($pkgInfo["recur_period"] == null ? "null":$pkgInfo["recur_period"]).", ".
										"recur_unit = ".($pkgInfo["recur_unit"] == null ? "null":$pkgInfo["recur_unit"]).", ".
										"duration = ".($pkgInfo["duration"] == null ? "null":$pkgInfo["duration"])." ".
										" where migrated = ".$migType." and migrated_id = '".$this->_SWIFT4->Database->Escape($pkgId)."'");
								}
							} else {
								// Not registered, insert it.
								$this->_SWIFT4->Database->Execute("insert into ".TABLE_PREFIX."sp_packages ".
									"(title, description, pkg_commence, minutes, tickets, enabled, ".
									"price, recur_period, recur_unit, duration, migrated, ".
									"migrated_id) values ('".$this->_SWIFT4->Database->Escape(substr($pkgInfo["name"],0,128)).
									"', '".$this->_SWIFT4->Database->Escape(substr($pkgInfo["description"],0,256))."', ".
									time().", ".$pkgInfo["minutes"].", ".$pkgInfo["tickets"].", 1, ".floatval($pkgInfo["price"]).", ".
									($pkgInfo["recur_period"] == null ? "null":$pkgInfo["recur_period"]).", ".
									($pkgInfo["recur_unit"] == null ? "null":$pkgInfo["recur_unit"]).", ".
									($pkgInfo["duration"] == null ? "null":$pkgInfo["duration"]).", ".
									$migType.", '".$this->_SWIFT4->Database->Escape($pkgId)."')");
							}
						} else {
							// Got a package but can't find #SP prefixes.
						}
					}
				}
			}
				
			// Now disable any that don't exist any more.
			foreach (array_keys($MyPkgs) as $migType) {
				if (!isset($pkgList[$migType])) {
					$pkgList[$migType] = array();
				}
				$pkgKeys = array_keys($pkgList[$migType]);

				foreach ($MyPkgs[$migType] as $pkgId => &$pkgInfo) {
					if (!in_array($pkgId, $pkgKeys)) {
						if ($pkgInfo["enabled"]) {
							$SPFunctions->DeletePackage($MyPkgs[$migType][$pkgId]["pkgid"]);
						}
					}
				}
			}
		}
		
		return $res;
	}	
	
	public function readActivePkgs() {
		global $SPFunctions, $sp_license;

		$res = false;
		
		if ($sp_license["status"] == SP_LICENSE_NONE) {
			$SPFunctions->readLicense($this->_SWIFT4->Settings->getKey("settings","sp_license"), $sp_license);
		}
		if (!$sp_license["allow_whmcs"] || !$this->_SWIFT4->Settings->getKey("settings","sp_whmcs_packages")) {
			return false;
		}

		// Get a list of active hosting packages and addons.
		$active = $this->doWHMCSApiCall(array(
			"action" => "spgetactivepkg"
			),
			false
		);
		
		if (isset($active)) {
			// It's an XML string.
			$xml_doc = @simplexml_load_string($active);

			if (!empty($xml_doc)) {
				$addPackages = array();
				$remPackages = array();
				$errmsg = null;
				$sp_string_expired = $this->_SWIFT4->Language->Get("sp_whmcs_expired");
				
				$pkgMap = array();
				$userMap = array();

				$this->_SWIFT4->Database->Query("select pkgid, minutes, tickets, price, description, migrated, migrated_id ".
					"from ".TABLE_PREFIX."sp_packages where migrated in (".SP_MIGRATED_WHMCS.", ".SP_MIGRATED_WHMCS_ADDON.") and ".
					"migrated_id is not null");
				while ($this->_SWIFT4->Database->NextRecord()) {
					$pkgMap[$this->_SWIFT4->Database->Record["migrated"]][$this->_SWIFT4->Database->Record["migrated_id"]] = 
							$this->_SWIFT4->Database->Record;
				}

				$this->_SWIFT4->Database->Query("select userid, whmcs_userid from ".TABLE_PREFIX."sp_users ".
					"where whmcs_userid is not null");
				while ($this->_SWIFT4->Database->NextRecord()) {
					$userMap[$this->_SWIFT4->Database->Record["whmcs_userid"]] = $this->_SWIFT4->Database->Record["userid"];
				}
				
				// Get a list of incoming packages.
				$to_process = array();
				foreach (array(array("e" => "hosting", "v" => SP_MIGRATED_WHMCS),
					array("e" => "addon", "v" => SP_MIGRATED_WHMCS_ADDON)) as $hType)
				{
					foreach ($xml_doc->{'status'}->{$hType["e"]} as $wHosting) {
						$hostingId = $hType["v"] . '_' . (int)($wHosting->{'id'});
						// ... but the ID of addons is the parent product ID, so this isn't enough...
						if ($hType["v"] == SP_MIGRATED_WHMCS_ADDON) {
							$hostingId .= "_" . (int)($wHosting->{'itemid'});
						}

						$mig_pkgid = (int)($wHosting->{'pkgid'});
						if ($hType["v"] == SP_MIGRATED_WHMCS) {
							$mig_pkgid .= '/'.strtolower(substr((string)$wHosting->{'billing'},0,1));
						}
						
						if (isset($userMap[(int)$wHosting->{'userid'}])) {
							$thisHosting = array(
								"userid" => $userMap[(int)$wHosting->{'userid'}],
								"id" => (int)$wHosting->{'id'},
								"itemid" => (int)$wHosting->{'itemid'},
								"invoiceid" => (int)$wHosting->{'invoiceid'},
								"package" => &$pkgMap[$hType["v"]][$mig_pkgid],
								"status" => (string)$wHosting->{'status'},
								"nextdue" => (int)$wHosting->{'nextdue'},
								"billing" => (string)$wHosting->{'billing'},
								"amount" => (float)$wHosting->{'amount'},
								"migtype" => $hType["v"]
								);
							if (!isset($to_process[$hostingId])) {
								$to_process[$hostingId] = array();
							}
							if ((string)$wHosting->{'upgrade'} == 'Latest' || $hType["v"] == SP_MIGRATED_WHMCS_ADDON) {
								// Merge instead of set, in case we've already got obsolete packages.
								$to_process[$hostingId] = array_merge($to_process[$hostingId], $thisHosting);
							} else {
								// TODO: Had planned to only have one here, the most recent.
								// What happens is multiple upgrades have happened since the last
								// one we've got on record?
								$to_process[$hostingId]['obsolete'][] = $thisHosting;
							}
						} else {
							$SPFunctions->SPErrorLog("Unknown userid ".(int)$wHosting->{'userid'}." from WHMCS when importing sold packages.",
								$wHosting->asXML());
						}
					}
				}

				$LastImportedOrder = array(
					SP_MIGRATED_WHMCS => -1, SP_MIGRATED_WHMCS_ADDON => -1
				);
			
				$debugLog = $this->_SWIFT4->Settings->getKey("settings","sp_debug");
				if ($debugLog) error_log("Processing WHMCS purchases");

				// Now cycle through all the to_process records to see if we need to do anything.
				foreach ($to_process as $productID => &$thisHosting) {
					$needUpdate = false;

					if (is_array($thisHosting["package"])) {
						if (!isset($thisHosting["invoiceid"])) {
							$SPFunctions->SPErrorLog("Undefined invoiceid for obsolete WHMCS order",
								print_r(print_r($thisHosting, true)));
							error_log(print_r($xml_doc, true));
						} else {
							if ($thisHosting["invoiceid"] == 0) {
								// Then there's no invoice. Cook up a unique ID for this order and
								// run as normal. Is probably a manually-added order.
								$thisHosting["invoiceid"] = $thisHosting["id"] . '|' . $thisHosting["itemid"];
								$thisHosting['nextdue'] = 0;
							}

							$uniqueID = $this->_SWIFT4->Database->Escape($productID . '/' . $thisHosting["invoiceid"]);
							if ($debugLog) error_log("Process purchase unique ID ".$uniqueID);

							$LastRecord = $this->_SWIFT4->Database->QueryFetch("select * ".
								"from ".TABLE_PREFIX."sp_user_payments ".
								"where proc_txid='".$uniqueID."' ".
								"and migrated=".$thisHosting["migtype"]." and userid = ".$thisHosting['userid']." order by txid desc");
							if (!empty($LastRecord)) {
								// Then we already have an active record for this product, invoice and item.
								if ($thisHosting["status"] == 'Active') {
									if ($debugLog) error_log("  Old active record, new paid record.");

									// Was paid, is currently paid. Has it changed? i.e. manual changes to package.
									// Shouldn't happen, but apparently does.
									if ($LastRecord["packageid"] == $thisHosting["package"]["pkgid"]) {
										if ($debugLog) error_log("    Nothing to do here.");
										continue;
									} else {
										if ($debugLog) error_log("    Package has changed on existing active record.");
									}
								}
								
								if ($debugLog) error_log("  Old active record, status or package changed. Deducting credit for current purchase.\n");

								// Was paid, is now something other than paid, so deduct all original credit.
								$SPFunctions->addPayment($errmsg, $thisHosting['userid'], 
									-$LastRecord["minutes"], -$LastRecord["tickets"], -$LastRecord['amount'],
									"WHMCS Removed", "(Changed) ".$thisHosting['package']["description"], 
									null, null);

								// Remove the MIGRATED flag so this doesn't get picked up again.
								$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX."sp_user_payments ".
									"set migrated = NULL where migrated = ".$thisHosting["migtype"].
									" and proc_txid='".$uniqueID."' and userid = ".$thisHosting['userid']);

								$needUpdate = true;
							}
							
							if ($thisHosting["status"] == 'Active') {
								if ($debugLog) error_log("  Processing active payment.");

								// Does this product already have an active payment on a different invoice?
								$this->_SWIFT4->Database->query("select * from ".TABLE_PREFIX.
									"sp_user_payments where migrated = ".$thisHosting["migtype"].
									" and proc_txid like '".$productID."/%' and userid = ".$thisHosting['userid']);
								
								while ($this->_SWIFT4->Database->NextRecord()) {
									$LastRecord = &$this->_SWIFT4->Database->Record;

									if ($debugLog) error_log("  ... updating existing purchase ".$LastRecord["proc_txid"]);

									if ($LastRecord["packageid"] != $thisHosting["package"]["pkgid"]) {
										// New payment entry from WHMCS, new packageID but an existing hosting or addon.
										// Must be an upgrade. Remove all original credit for this hosting/addon, it's
										// been replaced.
										if ($debugLog) error_log("  Package changed, removing all old credit for this purchase.");

										$SPFunctions->addPayment($errmsg, $thisHosting['userid'], 
											-$LastRecord["minutes"], -$LastRecord["tickets"], 0,
											"WHMCS Upgraded", "(Upgraded) ".$thisHosting['package']["description"], 
											null, null);
									} else {
										// Simple renewal. Remove remaining credit only.
										// Question: Should this maybe be left to the normal package expiry processing?
										if ($debugLog) error_log("  Renewal. Remove all remaining credit for this purchase.");
										
										$SPFunctions->addPayment($errmsg, $thisHosting['userid'], 
											-$LastRecord["rem_minutes"], -$LastRecord["rem_tickets"], 0,
											"WHMCS Renewed", "(Renewed) ".$thisHosting['package']["description"], 
											null, null);
									}
									
									// Remove the MIGRATED flag so this record doesn't get picked up again.
									$this->_SWIFT4->Database->Execute("update ".
										TABLE_PREFIX."sp_user_payments set migrated = NULL where migrated = ".$thisHosting["migtype"].
										" and proc_txid = '".$LastRecord["proc_txid"]."' and userid = ".$thisHosting['userid']);
								}
								
								// Add the new credit.
								if ($debugLog) {
									error_log("  Add package #".$thisHosting['package']['pkgid'].
										" (".$thisHosting['package']['minutes']." mins, ".
										" ".$thisHosting['package']['minutes']." tkts) to userid ".$thisHosting['userid']);
								}

								$SPFunctions->addPayment($errmsg, $thisHosting['userid'], 0, 0, $thisHosting['amount'],
									"WHMCS Purchase", $thisHosting['package']["description"], 
									$thisHosting['package']['pkgid'], null, null, 
									$uniqueID,
									null,0,null,null,$thisHosting["migtype"]);
								
								// The expiry date may be wrong - this is based on the date of purchase normally, but we
								// need to take the WHMCS "next due date" into account instead.
								// addPayment will set it to the current date.
								if (intval($thisHosting['nextdue']) > 0) {
									$this->_SWIFT4->Database->Execute("update ".TABLE_PREFIX.
										"sp_user_payments set expiry = ".intval($thisHosting['nextdue'])." where migrated = ".$thisHosting["migtype"].
										" and proc_txid = '".$uniqueID."' and userid = ".$thisHosting['userid']);
								}

								$needUpdate = true;
							} else {
								// It's a new record, but not active. Ignore.
								if ($debugLog) error_log("  New record, but inactive.");
							}
						}
					}

					if ($needUpdate) {
						$SPFunctions->updateUserCredits($thisHosting["userid"], $errmsg);
					}
				}

				if ($debugLog) error_log("WHMCS Purchase import done.");

				$res = true;
			}
		}

		return $res;
	}
	
	private function getSecureSetting($settingName) {
		global $SPFunctions;
		$value = null;
		
		if (!isset($SPFunctions)) {
			$value = $this->_SWIFT4->Settings->getKey("settings",$settingName);
		} else {
			$value = $SPFunctions->getSecureSetting($settingName);
		}
		
		return $value;
	}
	
	public function doWHMCSApiCall($_params, $wantErrors = false) {
		// N.B. This function MUST not use any SPFunctions calls, since this is
		// not present in the pure LoginShare module.
		$data = null;

		$url = $this->_SWIFT4->Settings->getKey("settings","sp_whmcs_api_baseURL");
		$username = $this->getSecureSetting("sp_whmcs_api_userid");
		$password = $this->getSecureSetting("sp_whmcs_api_pass");
		$HTTP_username = $this->getSecureSetting("sp_whmcs_web_userid");
		$HTTP_password = $this->getSecureSetting("sp_whmcs_web_pass");

		if (!empty($url)) {
			$url .= "/includes/api.php";
			$_params["username"] = $username;
			$_params["password"] = md5($password);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 100);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $_params);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
			if (!empty($HTTP_username) || !empty($HTTP_password)) {
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($ch, CURLOPT_USERPWD, $HTTP_username.":".$HTTP_password);
			}
			$data = curl_exec($ch);

			if ($data === false) {
				$data = curl_error($ch);
				$this->SPErrorLog("Curl error for WHMCS fetch",$data);
			}
			curl_close($ch);

			if (strpos($data,"result=error") !== false && !$wantErrors) {
				$this->SPErrorLog("Calling to WHMCS",$data);
				return null;
			}

			if (isset($this->minAPIVersions[$_params["action"]])) {
				$minVersion = $this->minAPIVersions[$_params["action"]];
				$xml_doc = @simplexml_load_string($data);
				$fileVersion = null;
				
				if (!empty($xml_doc)) {
					$fileVersion = (string)($xml_doc->{'version'});
				}
				
				if (empty($fileVersion)) {
					$this->SPErrorLog("Unable to find XML version in WHMCS response to command '".$_params["action"]."'.",
						"Please check that you have correctly installed the WHMCS commands under includes/api.");
					$data = null;
				} elseif ($fileVersion < $minVersion) {
					$this->SPErrorLog("WHMCS command '".$_params["action"]." is too old - want version ".$minVersion.", found version ".$fileVersion,
						"Please check that you have correctly installed the WHMCS commands under includes/api.");
					$data = null;
				}
			}			
			return $data;
		} else {
			$this->SPErrorLog("Tried to call WHMCS but no URL is defined. Please check your settings.");
		}
		
		return null;
	}

	private function SPErrorLog($message, $data = null) {
		global $SPFunctions;
		
		if (!is_null($SPFunctions)) {
			$SPFunctions->SPErrorLog($message, $data);
		} else {
			if (method_exists('SWIFT_Loader','LoadModel')) {
				SWIFT_Loader::LoadModel('ErrorLog:ErrorLog');
			} else {
				SWIFT_Loader::LoadLibrary('ErrorLog:ErrorLog');
			}
			error_log($message);
			$this->_SWIFT4->Database->Execute('insert into '.TABLE_PREFIX.'errorlogs (type,dateline,errordetails,userdata) values ('.
				SWIFT_ErrorLog::TYPE_EXCEPTION.','.time().',"SupportPay: '.$this->_SWIFT4->Database->Escape($message).'",'.
				'"'.$this->_SWIFT4->Database->Escape($userData).'")');
		}
	}
};

?>
