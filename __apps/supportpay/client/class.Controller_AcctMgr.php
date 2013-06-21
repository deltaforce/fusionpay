<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_AcctMgr.php $, $Change: 3405 $, $DateTime: 2013/02/04 15:03:21 $ -->
<?php

class Controller_AcctMgr extends Controller_client
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
	
	static public function _gridFields(&$record) {
		global $sp_currencylist, $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();
		
		if (isset($record["fullname"])) {
			$record["fullname"] = $SPFunctions->visibleLink("/supportpay/ListDebits/Index/".$record["userid"],
				$_SWIFT->Language->Get("sp_uppagetitle"),$record["fullname"]);
			
			$record["fullname"] = "<span title='".htmlspecialchars($record["email"])."'>".$record["fullname"]."</span>";
		}
		
		if (isset($record["offer_made"])) {
			$record["offer_made"] = date(SWIFT_Date::GetCalendarDateFormat(), $record["offer_made"]);
		}
		
		if (isset($record["guid"])) {
			$delUrl = "RemGuid/".$record["guid"]."/2";
			$delMsg = "sp_remoffer";
		} else {
			$delUrl = "RemUser/".$record["userid"]."/1";
			$delMsg = "sp_remdependent";
		}
		
		$record["options"] = "<a href='#' title='".htmlspecialchars($_SWIFT->Language->Get("sp_delete"),ENT_QUOTES).
			"' onclick='if (confirm(\"".
			htmlspecialchars($_SWIFT->Language->Get($delMsg))."\")) window.location=\"".
			SWIFT::Get('basename')."/supportpay/AcctMgr/".$delUrl."\";'".
			"><img src='".SWIFT::Get('themepathimages')."icon_trash.gif' style='vertical-align: middle; border: none;'/>".
			"&nbsp;".htmlspecialchars($_SWIFT->Language->Get("sp_delete"))."</a>";
	}
	
	// Remove an already-dependent user.
	public function RemUser($userid, $dispmode=1) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_numeric($userid)) {
			if ($_SWIFT->Database->Query("update ".TABLE_PREFIX."sp_users set payerid = null where userid=".intval($userid))) {
				global $SPFunctions;
				$Record = $_SWIFT->Database->QueryFetch("select fullname FROM ".TABLE_PREFIX."users WHERE userid=".intval($userid));

				$msg = str_replace("{Manager}",$_SWIFT->User->GetProperty('fullname'),$_SWIFT->Language->Get('sp_am_removeddep'));
				$msg = str_replace("{Dependent}",$Record["fullname"],$msg);
				$SPFunctions->addAudit($msg);
				SWIFT::Info("SupportPay",$msg);
			} else {
				SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
			}
		}
		
		$this->Router->SetAction("Main");
		return $this->Main($dispmode);
	}

	// Remove an unaccepted offer.
	public function RemGuid($guid, $dispmode=2) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (!empty($guid)) {
			if ($_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_depoffers where guid='".
				$_SWIFT->Database->Escape(strtoupper($guid))."'"))
			{
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get('sp_am_removedoffer'));
			} else {
				SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
			}
		}
		
		$this->Router->SetAction("Main");
		return $this->Main($dispmode);
	}
	
	public function AddDep() {
		$_SWIFT = SWIFT::GetInstance();
		$this->Load->Library('Mail:Mail');

		$fromemail = $_SWIFT->User->GetEmailList();
		$fromemail = $fromemail[0];
		
		if (IsEmailValid($fromemail)) {
			$depemail = $_POST["addusername"];
			
			if (IsEmailValid($depemail)) {
				if (strtolower($fromemail) != strtolower($depemail)) {
					global $SPFunctions;
					$offerid = $SPFunctions->gen_guid("sp_depoffers","guid");
					$fullname = $_SWIFT->User->GetProperty('fullname');
					$_SWIFT->Template->Assign("username", $fullname);
					$_SWIFT->Template->Assign("offerlink", SWIFT::Get('basename')."/supportpay/DepAccept/Index/".$offerid );
					
					$_SWIFT->Template->Assign("ishtml", false);
					$emailtext = $_SWIFT->Template->RenderTemplate("sp_dependentemail");
					$_SWIFT->Template->Assign("ishtml", true);
					$emailhtml = $_SWIFT->Template->RenderTemplate("sp_dependentemail");
					
					$_SWIFT->Database->Query("insert into ".TABLE_PREFIX."sp_depoffers (userid,guid,email,offer_made) VALUES (".
						$_SWIFT->User->GetUserID().",'".strtoupper($offerid)."','".$_SWIFT->Database->Escape($depemail).
						"',".time().")");
					
					$this->Mail->SetToField($depemail);
					$this->Mail->SetFromField($fromemail, $fullname);
					$this->Mail->SetSubjectField($_SWIFT->Settings->getKey("settings","sp_accmgrsubject"));
					$this->Mail->SetDataText($emailtext);
					$this->Mail->SetDataHTML($emailhtml);
					if ($this->Mail->SendMail(false)) {
						SWIFT::Info("SupportPay",$this->Language->Get('sp_messagesent'));
					} else {
						$_SWIFT->Database->Query("delete from ".TABLE_PREFIX."sp_depoffers ".
							"where guid='".strtoupper($offerid)."'");
						SWIFT::Error("SupportPay",$this->Language->Get('sp_cantsendemail'));
					}
				} else {
					SWIFT::Error("SupportPay",$this->Language->Get('sp_affnotyourself'));
				}
			} else {
				SWIFT::Error("SupportPay",$this->Language->Get('invalidemail'));
			}
		} else {
			SWIFT::Error("SupportPay",$this->Language->Get('sp_youremailbad'));
		}
		
		$this->Router->SetAction("Main");
		return $this->Main(2);
	}
	
	public function Main($dispmode=1)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license, $SPUserFuncs;
		$wantContents = false;
		
		if (!$sp_license["allow_accounts"] && $_SWIFT->Settings->getKey("settings","sp_amenable")) {
			SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_noacm"));
		} else {
			$userid = intval($_SWIFT->User->GetUserID());
			$Record = $_SWIFT->Database->QueryFetch("select acctmgr from ".TABLE_PREFIX.
				"sp_users WHERE userid=".$userid);
			if ($Record["acctmgr"] != 1) {
				SWIFT::Error("SupportPay", $_SWIFT->Language->Get("sp_notacctmgr"));
			} else {
				if (isset($_POST["dispmode"])) {
					if ($dispmode != intval($_POST["dispmode"])) {
						unset($_POST["_gSort"]);
						unset($_POST["_gPage"]);
						unset($_POST["_gDir"]);
					}
					$dispmode = intval($_POST["dispmode"]);
				}
				$this->Router->SetArguments(array($dispmode));
				
				$errmsg = "";
				$Record = $_SWIFT->Database->QueryFetch("select fullname,email from ".TABLE_PREFIX."users u ".
					'LEFT JOIN '.TABLE_PREFIX.'useremails AS useremails ON (u.userid = useremails.linktypeid and linktype = 1) '.
					" where u.userid=".$userid);
				$fullname = $Record["fullname"];
				$fromemail = $Record["email"];
				
				$options["recordsperpage"] = "10";
				$options["sortorder"] = "desc";
				$options["callback"] = array(get_class(),"_gridFields");
				$_payDepts = buildIN($SPFunctions->getPayableDepts());
				switch ($dispmode) {
					case 1:	// List of existing dependents
						$gridTitle = $_SWIFT->Language->Get("sp_deplist");
						$fields[] = array("name" => "fullname", "title" => $_SWIFT->Language->Get("sp_userid"));
						$fields[] = array("name" => "numtickets", "title" => $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_numtickets")));
						$fields[] = array("name" => "paidtickets", "title" => $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_paidtickets")));
						$fields[] = array("name" => "paidminutes", "title" => $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_paidminutes")));
						$fields[] = array("name" => "options", "type" => "custom", "title" => $_SWIFT->Language->Get("sp_options"));
						$options["sortby"] = "u.fullname";
						
						$_selectquery = "SELECT u.userid,u.fullname,useremails.email,count(t.ticketid) as numtickets, ".
							" coalesce(sum(-up.tickets),0) as paidtickets, coalesce(sum(-up.minutes),0) as paidminutes FROM ".TABLE_PREFIX."users AS u ".
							" LEFT JOIN ".TABLE_PREFIX."useremails AS useremails ON (u.userid = useremails.linktypeid and linktype = 1) ".
							" LEFT JOIN ".TABLE_PREFIX."tickets AS t ON (t.userid = u.userid and t.departmentid in (".$_payDepts.")) ".
							" LEFT JOIN ".TABLE_PREFIX."sp_user_payments as up ON (up.userid = u.userid AND up.ticketid = t.ticketid AND up.ticketid IS NOT NULL),".
							"".TABLE_PREFIX."sp_users AS pua WHERE pua.payerid = '".$userid."' AND u.userid = pua.userid ".
							' GROUP BY u.userid,u.fullname,useremails.email,up.tickets,up.minutes ';
						$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'users AS users, '.
							TABLE_PREFIX."sp_users AS pua WHERE pua.payerid = '".$userid."' AND users.userid = pua.userid";
						break;
					case 2:	// List of outstanding invitations
						$gridTitle = $_SWIFT->Language->Get("sp_depoffers");
						$fields[] = array("name" => "email", "title" => $_SWIFT->Language->Get("sp_userid"));
						$fields[] = array("name" => "guid", "title" => $_SWIFT->Language->Get("sp_offerid"));
						$fields[] = array("name" => "offer_made", "title" => $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_offermade")));
						$fields[] = array("name" => "options", "type" => "custom", "title" => $_SWIFT->Language->Get("sp_options"));
						$options["sortby"] = "email";
						
						$_selectquery = "SELECT * FROM ".TABLE_PREFIX."sp_depoffers WHERE userid = ".$userid;
						$_countquery = "SELECT count(*) as totalitems FROM ".TABLE_PREFIX."sp_depoffers WHERE userid = ".$userid;
						break;
					default:
				}
				
				$filters = $_SWIFT->Language->Get('sp_show').'&nbsp;';
				$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="1"'.
					($dispmode==1?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_deplist').'&nbsp;&nbsp;';
				$filters .= '<input name="dispmode" onclick="document.gridform.submit();" type="radio" value="2"'.
					($dispmode==2?'checked="checked"':'').'/>'.$_SWIFT->Language->Get('sp_depoffers');
				$gridContents  = $SPUserFuncs->RenderListHeader($_countquery,$fields,$options,$gridTitle,$filters);
				$gridContents .= $SPUserFuncs->RenderListContents($_selectquery, $fields,$options);
				$gridContents .= $SPUserFuncs->RenderListFooter($_countquery,$options);
				$_SWIFT->Template->Assign("gridcontents", $gridContents);
				
				// Add 'add user' form.
				$userForm = $SPUserFuncs->StartForm("adddep",SWIFT::Get('basename')."/supportpay/AcctMgr/AddDep");
				$userForm .= $SPUserFuncs->Title($_SWIFT->Language->Get("sp_adddep"));
				$userForm .= $SPUserFuncs->Text("addusername",$_SWIFT->Language->Get("commentemail"),"","");
				$userForm .= $SPUserFuncs->Submit();
				$userForm .= $SPUserFuncs->EndForm();
				$_SWIFT->Template->Assign("adduserform",$userForm);
				
				$accept = $_SWIFT->Settings->getKey("settings","sp_accept");
				$_SWIFT->Template->Assign("dominutes",($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH));
				$_SWIFT->Template->Assign("dotickets",($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH));
				
				$wantContents = true;
			}
		}		

		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($_SWIFT->Language->Get("sp_ptacctmgr"));
		$_SWIFT->Template->Render("sp_header");
		if ($wantContents) {
			$_SWIFT->Template->Render("sp_acctmanager");
		}
		$_SWIFT->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
}

?>
