<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_DepAccept.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_DepAccept extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
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
	
	public function Index($offerid=null)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$done = false;
		
		if (!$_SWIFT->Session->IsLoggedIn() || !$_SWIFT->User instanceof SWIFT_User || !$_SWIFT->User->GetIsClassLoaded())
		{
			$this->UserInterface->Error(true, $this->Language->Get('logintocontinue'));
			$this->Load->Controller('Default', 'Base')->Load->Index();
			exit;
		}

		if (!$sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_noacm"));
		} elseif (empty($offerid)) {
			SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_nooffer"));
		} else {
			// We have an offer, but is it valid?
			$Record = $_SWIFT->Database->QueryFetch("select o.*,u.fullname from ".TABLE_PREFIX."sp_depoffers o,".TABLE_PREFIX."users u ".
				"WHERE guid = '".$_SWIFT->Database->Escape(strtoupper($offerid))."' AND u.userid=o.userid");
			
			if (!isset($Record["userid"])) {
				SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_unknownoffer"));
			} else {
				// Does this user already have someone paying their bills?	
				$Current = $_SWIFT->Database->QueryFetch("select fullname from ".TABLE_PREFIX."users u, ".TABLE_PREFIX."sp_users sp ".
					" WHERE u.userid = sp.payerid AND sp.userid = ".$_SWIFT->User->GetUserID());
				if (isset($Current["fullname"])) {
					$_SWIFT->Template->Assign("offermessage","<b>".$Current["fullname"] . $_SWIFT->Language->Get("sp_alreadypaid")."</b>");
				}
				$done = false;
				foreach ($_SWIFT->User->GetEmailList() as $email) {
					if (strtolower($email) === strtolower($Record["email"])) {
						// Offer is completely valid. Give the user the choice.
						if (isset($_POST["accept"])) {
							$SPFunctions->checkUserExists($_SWIFT->User->GetUserID(), $errormessage);
							$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_depoffers WHERE guid = '".$_SWIFT->Database->Escape(strtoupper($offerid))."'");
							if ($Record["userid"] == $_SWIFT->User->GetUserID())
								$Record["userid"] = "null";
							
							$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users SET payerid = ".$Record["userid"]." WHERE userid = ".$_SWIFT->User->GetUserID());
							
							$SPFunctions->addAudit("User " . $_SWIFT->User->GetProperty("fullname") . " added to account of " . $Record["fullname"]);
							
							$_SWIFT->Template->Assign("offermessage",$_SWIFT->Language->Get("sp_offeraccepted"));
						} elseif (isset($_POST["reject"])) {
							$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_depoffers WHERE guid = '".$_SWIFT->Database->Escape(strtoupper($offerid))."'");
							$_SWIFT->Template->Assign("offermessage",$_SWIFT->Language->Get("sp_offerrejected"));
						} else {
							// Give the user the choice of whether to accept or not.
							$_SWIFT->Template->Assign("needAccept",true);
							$_SWIFT->Template->Assign("offerId",$offerid);
							$_SWIFT->Template->Assign("offermessage",$Record["fullname"]." ".$_SWIFT->Language->Get("sp_offertext"));
						}
						
						$done = true;
						break;
					}
				}
				
				if (!$done) {
					SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_badofferemail"));
				}
			}
		}
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($_SWIFT->Language->Get("sp_ptdepaccept"));
		$this->Template->Render("sp_header");
		if ($done) $_SWIFT->Template->Render("sp_depaccept");
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
	
	public function Remove()
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$wantContents = false;
		
		if (!$sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_noacm"));
		} else {
			$Record = $_SWIFT->Database->QueryFetch("select fullname,payerid from ".TABLE_PREFIX."sp_users sp, ".
				TABLE_PREFIX."users u ".
				"WHERE sp.userid=".$_SWIFT->User->GetUserID()." AND u.userid = sp.payerid");
	
			if (!isset($Record["payerid"])) {
				SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_noacctmgr"));
			} else {
				$_SWIFT->Template->Assign("am_name", $Record["fullname"]);
				if (isset($_POST["yes"])) {
					$_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set payerid=null where userid=".$_SWIFT->User->GetUserID());
					$msg = str_replace("{Manager}",$Record["fullname"],$_SWIFT->Language->Get('sp_am_removeddep'));
					$msg = str_replace("{Dependent}",$_SWIFT->User->GetProperty("fullname"),$msg);
					$SPFunctions->addAudit($msg);
					SWIFT::Info("SupportPay",$_SWIFT->Language->Get("sp_amremoved"));
				} elseif (isset($_POST["no"])) {
					header("Location: ".SWIFT::Get('basename'));
					exit;
				} else {
					$wantContents = true;
				}
			}
		}
		
		$this->UserInterface->Header('sp_uw_master');
		$_SWIFT->Template->Render("sp_header");
		if ($wantContents) $_SWIFT->Template->Render("sp_remacctmgr");
		$_SWIFT->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
}

?>
