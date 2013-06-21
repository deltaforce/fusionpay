<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.Controller_UserCredit.php $, $Change: 3412 $, $DateTime: 2013/02/13 11:25:14 $ -->
<?php

class Controller_UserCredit extends Controller_staff
{
	public function __construct() {
		parent::__construct();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function delAgreement($agreeList) {
		$_SWIFT = SWIFT::GetInstance();
		$allDone = true;
		
		if (is_array($agreeList)) {
			SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
			$PP = new SWIFT_SPPayPal;
			
			$_SWIFT->Database->Query("select d.cid, i.proc_txid from ".TABLE_PREFIX."sp_cart_items i, ".
				TABLE_PREFIX."sp_cart_defs d ".
				"where i.proc_txid in (".buildIN($agreeList).") and i.cid = d.cid");

			while ($_SWIFT->Database->NextRecord()) {
				$cid = $_SWIFT->Database->Escape($_SWIFT->Database->Record["cid"]);
				$proc_txid = $_SWIFT->Database->Record["proc_txid"];
				
				// Call PayPal to cancel the agreement. ManageRecurringPaymentsProfileStatus ($Rec["proc_txid"]), cancel);
				$resArray = $PP->CancelRecurringPayment($proc_txid, "Cancelled by ".$_SWIFT->Staff->GetProperty('fullname'));
				
				// Allow a failure where the payment is unknown, delete the cart anyway.
				if ($resArray["ACK"] == "SUCCESS" || $resArray["L_ERRORCODE0"] == 11556) {
					$_SWIFT->Database->StartTrans();
					$_SWIFT->Database->Execute("delete from ".TABLE_PREFIX."sp_cart_items where cid = '".$cid.
						"' and proc_txid = '".$proc_txid."'");
					$_SWIFT->Database->Execute("delete from ".TABLE_PREFIX."sp_cart_defs where cid = '".$cid.
						"' and not exists (select 1 from ".TABLE_PREFIX."sp_cart_items i ".
						" where i.cid = '".$cid."')");
					$_SWIFT->Database->CompleteTrans();
				} else {
					// TODO: PayPal's ERRORMESSAGE text here.
					SWIFT::Error("SupportPay",$resArray['L_LONGMESSAGE0']);
					$allDone = false;
				}
			}
			
			if ($allDone) {
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_agreement_deleted"));
			}
		} else {
			SWIFT::Error("SupportPay","Invalid data");
		}
		
		return true;
	}
	
	public function Recalc($userList) {
		if (is_array($userList)) {
			global $SPFunctions;
			
			foreach ($userList as $userID) {
				$SPFunctions->fetchVMSales($userID);
				$SPFunctions->updateUserCredits($userID, $errmsg);			}
		}
		
		return true;
	}
	
	public function ShowBilling($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_rblisttitle'), 
			$SPFunctions->findStaffMenu(),4);

		$this->View->RenderBillingGrid($userid);
		
		$this->UserInterface->Footer();
		return true;
	}
	
	public function SetDscnt() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$errormessage = "";
		$infomessage = "";
		$discount = floatval($_POST["discount"]);
		
		if (is_numeric($discount)) {
			if ($discount > 100) {
				$errormessage = $_SWIFT->Language->Get("sp_bad_discount");
			} else {
				foreach ($_POST["itemid"] as $userid) {
					$SPFunctions->checkUserExists($userid,$errormessage);
					$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set discount = ".$discount.
						" where userid = ".intval($userid));
						
					$Record = $_SWIFT->Database->QueryFetch("SELECT fullname, coalesce(spu.discount,0) as discount FROM ".TABLE_PREFIX."users u ".
						"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".intval($userid));
						
					$infomessage = "Discount changed to ".sprintf("%0.2f",$Record["discount"])."% for ".$Record["fullname"];
					$SPFunctions->addAudit($infomessage);
				}
			}
		} else {
			$errormessage = "Couldn't understand the discount value";
		}
		
		return empty($errormessage);
	}

	// This does some superficial checks and if OK, renders the border and send/close buttons.
	public function ShowInvoice($userid, $fromdate=null, $todate=null) {
		global $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_show_invoice'), 
			$SPFunctions->findStaffMenu(),4);
			
		if (empty($userid)) {
			$this->UserInterface->Error("SupportPay", "No user specified");
		} else {
			if ($_SWIFT->Settings->Get('dt_caltype') == 'us') {
				$dfmt = array(0,1,2);
			} else {
				$dfmt = array(1,0,2);
			}
			
			if (!empty($fromdate)) {
				$fromdate = explode("2F",$fromdate);
				if (is_array($fromdate)) {
					$fromdate = mktime(0,0,0,$fromdate[$dfmt[0]],$fromdate[$dfmt[1]],$fromdate[$dfmt[2]]);
				}
			}
			if (!empty($todate)) {
				$todate = explode("2F",$todate);
				if (is_array($todate)) {
					$todate = mktime(0,0,0,$todate[$dfmt[0]],$todate[$dfmt[1]],$todate[$dfmt[2]]);
				}
			}
			
			if (empty($fromdate)) {
				$Rec = $this->Database->QueryFetch("select last_invoice from ".TABLE_PREFIX."sp_users ".
					"where userid = ".$userid);

				$fromdate = intval($Rec["last_invoice"]);
			}
			if (empty($todate)) {
				$todate = time();
			}
			
			$this->View->RenderInvoiceBorder($userid, $fromdate, $todate);
		}

		$this->UserInterface->Footer();

		return true;
	}

	public function SendInvoice() {
		global $SPFunctions;
		
		if (isset($_POST["userid"])) {
			// Do we want to update the most-recent-invoice date?
			$doUpdate = false;
			$userid = intval($_POST["userid"]);
			$endDate = intval($_POST["enddate"]);
			
			// Only if the invoice being sent finishes *after* the last one sent.
			$Rec = $this->Database->QueryFetch("select last_invoice from ".TABLE_PREFIX."sp_users ".
				"where userid = ".$userid);
			$doUpdate = ($Rec["last_invoice"] <= $endDate);
			
			if (!$SPFunctions->sendOneAccountInvoice($userid, intval($_POST["startdate"]), $endDate, $doUpdate)) {
				SWIFT::Error("SupportPay","Unable to send invoice.");
			} else {
				SWIFT::Info("SupportPay","Invoice Sent.");
			}
		} else {
			SWIFT::Error("SupportPay","No user specified.");
		}
		
		$this->Router->SetAction("Main");
		return $this->Main();
	}
	
	// This actually renders the invoice into the iframe created by ShowInvoice.
	public function PureInvoice($userid, $startTime, $endTime) {
		$this->View->RenderInvoice($userid, $startTime, $endTime);
	}
	
	public function GetDscnt() {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_setdscnt'), 
			$SPFunctions->findStaffMenu(),4);

		if (!$SPFunctions->checkPerms("sp_canusercdt")) {
			$this->UserInterface->Error("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderDiscountDlg();
		}
		
		$this->UserInterface->Footer();
		return true;
	}

	public function SetOverdraft() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$errormessage = "";
		$infomessage = "";
		$overdraft = intval($_POST["overdraft"]);
		
		if (is_numeric($overdraft)) {
			if ($overdraft < 0) {
				$errormessage = $_SWIFT->Language->Get("sp_bad_overdraft");
			} else {
				if ($overdraft == 0) {
					$overdraft = 'null';
				}
				
				foreach ($_POST["itemid"] as $userid) {
					$SPFunctions->checkUserExists($userid,$errormessage);
					$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set overdraft = ".$overdraft.
						" where userid = ".intval($userid));
					
					$Record = $_SWIFT->Database->QueryFetch("SELECT fullname, coalesce(spu.overdraft,0) as overdraft FROM ".TABLE_PREFIX."users u ".
						"left join ".TABLE_PREFIX."sp_users spu on (spu.userid = u.userid) WHERE u.userid=".intval($userid));
					
					$infomessage = "Overdraft changed to ".intval($Record["overdraft"])." for ".$Record["fullname"];
					$SPFunctions->addAudit($infomessage);
				}
			}
		} else {
			$errormessage = "Couldn't understand the overdraft value";
		}
		
		return empty($errormessage);
	}

	public function GetOverdraft() {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_overdraft'), 
			$SPFunctions->findStaffMenu(),4);

		if (!$SPFunctions->checkPerms("sp_canusercdt")) {
			$this->UserInterface->Error("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderOverdraftDlg();
		}
		
		$this->UserInterface->Footer();
		return true;
	}

	public function GetMgr() {
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_set_acctmgr'), 
			$SPFunctions->findStaffMenu(),4);

		if (!$SPFunctions->checkPerms("sp_cansetmgr")) {
			$this->UserInterface->Error("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderManagerDlg();
		}
		
		$this->UserInterface->Footer();
		return true;
	}

	public function RemMyMgr($userid) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_numeric($userid)) {
			if ($_SWIFT->Database->Query("UPDATE ".TABLE_PREFIX."sp_users SET payerid = null ".
				" WHERE userid = ".$userid))
			{
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_amremoved_staff"));
			} else {
				SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
			}
		}
		
		$this->Router->SetAction("Main");
		return $this->Main();
	}
	
	public function SetMgr() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$errormessage = "";
		$infomessage = "";
		$ismgr = ($_POST["ismgr"] == 0) ? 0 : 1;
		
		foreach ($_POST["itemid"] as $userid) {
			$SPFunctions->checkUserExists($userid,$errormessage);
			$_SWIFT->Database->Query("UPDATE ".TABLE_PREFIX."sp_users SET acctmgr = ".$ismgr.
				" WHERE userid = ".$userid);
			
			$Record = $_SWIFT->Database->QueryFetch("SELECT fullname FROM ".TABLE_PREFIX."users u ".
				"WHERE u.userid=".intval($userid));
			
			$infomessage = "Manager status ".($ismgr ? "removed from":"added to")." ".$Record["fullname"];
			$SPFunctions->addAudit($infomessage);
			SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_acmgr_changed"));
		}
		
		return empty($errormessage);
	}

	public function DoAddCredit($userid = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$returnClass = null;
		if (isset($_POST["returnClass"])) {
			$returnClass = $_POST["returnClass"];
		}
		if (empty($returnClass)) {
			$returnClass = str_replace("Controller_","",get_class($this));
		}

		if ("Controller_".$returnClass != get_class($this)) {
			require_once(SWIFT_MODULESDIRECTORY."/supportpay/staff/class.Controller_".$returnClass.".php");
			require_once(SWIFT_MODULESDIRECTORY."/supportpay/staff/class.View_".$returnClass.".php");
			$rcName = "Controller_".$returnClass;
			$rc = new $rcName();
			$rcName = "View_".$returnClass;
			$rc->View = new $rcName();
		} else {
			$rc = &$this;
		}

		// Look up the username for audit.
		$errormessage = "";
		if (isset($_POST["userid"])) {
			$Record = $_SWIFT->Database->QueryFetch("select fullname FROM ".TABLE_PREFIX."users WHERE userid=".intval($_POST["userid"]));
			if (isset($Record["fullname"])) {
				if (!$SPFunctions->checkPerms("sp_canchangecredit")) {
					$errormessage = $_SWIFT->Language->Get("sp_nostaffperms");
				} else {
					$price = floatval($_POST["price"]);
					
					// Handle the case where minutes, tickets or packages aren't enabled.
					if (!isset($_POST["addmins"])) $_POST["addmins"] = null;
					if (!isset($_POST["addtkts"])) $_POST["addtkts"] = null;
					if (!isset($_POST["addpkgid"])) $_POST["addpkgid"] = null;

					if (!empty($_POST["addpkgid"])) {
						// Stop people adding arbitrary numbers of mins/tickets when adding a package.
						$PkgRecord = $_SWIFT->Database->QueryFetch("select minutes,tickets from ".TABLE_PREFIX.
							"sp_packages WHERE pkgid = ".intval($_POST["addpkgid"]));
						if (empty($PkgRecord)) {
							$errormessage = "Unknown Package ID";
						} else {
							$_POST["addmins"] = $PkgRecord["minutes"];
							$_POST["addtkts"] = $PkgRecord["tickets"];
						}
					}

					if (($_POST["addmins"] != "" || $_POST["addtkts"] != "" || $_POST["addpkgid"] != "" || $price != 0) 
						&& $_POST["comment"] != "" && empty($errormessage))
					{
						$txid = $SPFunctions->addPayment($errormessage, $_POST["userid"], intval($_POST["addmins"]), intval($_POST["addtkts"]), 
							$price, $_SWIFT->Staff->GetProperty("fullname"),
							$_POST["comment"],
							($_POST["addpkgid"] != "" ? intval($_POST["addpkgid"]):null),null,
							null, null, (empty($_POST["deductTxid"]) ? null : $_POST["deductTxid"]));
						if ($txid != null) {
							$infomessage = "Credit note added to userid ".$_POST["userid"]." as transaction #".$txid.".";
							$SPFunctions->addAudit("Manual payment ".$txid." for ".$Record["fullname"]);
						} elseif ($errormessage == "") {
							$errormessage="Transaction could not be added.";
						}
					} else {
						$errormessage = "You must enter a comment, and ";
						if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
							$errormessage .= " at least one of ".$_SWIFT->Settings->getKey("settings","sp_ticketstxt")." or ".$_SWIFT->Settings->getKey("settings","sp_minutestxt").".";
						} elseif ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES) {
							$errormessage .= $_SWIFT->Settings->getKey("settings","sp_minutestxt").".";
						} else {
							$errormessage .= $_SWIFT->Settings->getKey("settings","sp_ticketstxt").".";
						}
					}
				}
			} else {
				$errormessage = "Unable to find that user in the database.";
			}
		} else {
			$errormessage = "No user specified.";
		}
				
		if (!empty($errormessage)) {
			$this->View->RenderCreditForm($_POST,$errormessage,$returnClass);
		} else {
			$rc->Router->SetAction("Main");
			return $rc->Main();
		}
	}

	public function AddCredit($userid, $returnClass = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$CreditInfo = array( "userid" => $userid, "addmins" => 15, "addtkts" => 0, 
			"comment" => "", "price" => 0.0);
		$this->View->RenderCreditForm($CreditInfo,"", $returnClass);
		
		return true;
	}

	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		$this->Language->Load('admin_users');

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_umpagetitle'), 
			$SPFunctions->findStaffMenu(),4);

		if (count($_POST) == 0) {
			$SPFunctions->checkLicense();
			$SPFunctions->fetchVMSales();
		} else {
			$SPFunctions->checkLicense(true);
		}

		if (!$SPFunctions->checkPerms("sp_canusercdt")) {
			$this->UserInterface->DisplayError("SupportPay",$_SWIFT->Language->Get("sp_nostaffperms"));
		} else {
			$this->View->RenderGrid();
		}
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
