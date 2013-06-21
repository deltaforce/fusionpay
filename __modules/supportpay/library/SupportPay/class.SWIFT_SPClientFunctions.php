<?php

class SWIFT_SPClientFunctions {
	private $SWIFT4;
	
	function __construct() {
		$this->SWIFT4 = SWIFT::GetInstance();
	}
	
	public function RenderListHeader($countsql,$fields,$options,$title,$filters = null) {
		$res = '';
		
		if (!isset($options["noform"])) {
			$res = '<form name="gridform" method="POST" action="'.$this->SWIFT4->Router->GetCurrentURL().'">';
		}
		
		if (!empty($filters)) {
			$res .= "<div class='sp_listctrlbox smalltext'>".$filters."</div>";
		}
		
		$options["hiddenfields"]["_gPage"] = (!empty($_POST["_gPage"]) ? $_POST["_gPage"] : 0);
		$options["hiddenfields"]["_gSort"] = (!empty($_POST["_gSort"]) ? $_POST["_gSort"] : (!empty($options["sortby"]) ? $options["sortby"]:""));
		$options["hiddenfields"]["_gDir"] = (!empty($_POST["_gDir"]) ? $_POST["_gDir"] : (!empty($options["sortorder"]) ? strtoupper($options["sortorder"]):"ASC"));
		
		if (empty($options["headerclass"])) {
			$options["headerclass"] = "ticketlistheaderrow";
		}
		
		foreach ($options["hiddenfields"] as $field => $value) {
			$res .= "<input type='hidden' name='".htmlspecialchars($field,ENT_QUOTES)."'";
			$res .= " value='".htmlspecialchars($value,ENT_QUOTES)."'/>";
		}
		
		$res .= '<table class="sp_list_tab"><tr>';
		if (is_array($fields)) {
			foreach ($fields as $hdr) {
				if (is_array($hdr)) {
					$isSortCol = ($hdr["name"] == $options["hiddenfields"]["_gSort"]);
					if (isset($hdr["type"]) && $hdr["type"] == "custom") {
						$canSortCol = false;
					} else {
						$canSortCol = true;
					}
					$res .= '<td class="'.$options["headerclass"].'" align="center" valign="middle" ';
					$res .= (isset($hdr["width"]) ? 'width=".'.$hdr["width"].'" ':"").'>';
					if ($canSortCol) {
						$res .= "<a href='javascript: void(0);' onclick='document.gridform.";
						$res .= "_gSort.value=\"".$hdr["name"]."\"; document.gridform._gDir.value=\"";
						$res .= ($isSortCol ? ($options["hiddenfields"]["_gDir"] == "ASC" ? "DESC":"ASC") : "ASC");
						$res .= "\"; document.gridform._gPage.value=0; document.gridform.submit();'>";
					}
					$res .= htmlspecialchars((isset($hdr["title"]) ? $hdr["title"] : $hdr["name"]));
					if ($canSortCol) {
						if ($isSortCol) {
							$res .= "&nbsp;<img src='".SWIFT::Get("themepathimages").
								($options["hiddenfields"]["_gDir"] == "ASC" ? "sortasc.gif":"sortdesc.gif")."'/>";
						}
						$res .= "</a>";	
					}
					$res .= "</td>\n";
				}
			}
		}
		
		$res .= "</tr>";
		return $res;
	}
	
	public function RenderListFooter($countsql,$options) {
		$res = "</table>";
		$res .= $this->RenderListPagination($countsql,$options);
		if (!isset($options["noform"])) {
			$res .= '</form>';
		}
		
		return $res;
	}
	
	public function RenderListPagination($countsql, $options) {
		$startPage = (!empty($_POST["_gPage"]) ? intval($_POST["_gPage"]) : 0);
		$perPage = (!empty($options["recordsperpage"]) ? intval($options["recordsperpage"]) : 15);
		$res = "";
		
		$Rec = $this->SWIFT4->Database->QueryFetch($countsql);
		if (is_array($Rec)) {
			$key = array_keys($Rec);
			$totalItems = $Rec[$key[0]];
			$maxPages = intval(($totalItems + ($perPage-1)) / $perPage);
		} else {
			$totalItems = 0;
			$maxPages = $startPage+1;
			$res = "<div style='width: 100%;'><div class='sp_pagebtns' style='color: Red;'>";
			$res .= $this->SWIFT4->Language->Get("sp_bad_sql")."</div></div>";
		}
		
		if ($totalItems > 0) {
			$res = "<div style='width: 100%;'><div class='sp_pagebtns'>";

			// First button
			$res .= "<a href='javascript: void(0);' onclick='document.gridform._gPage.value=0".
				"; document.gridform.submit();'";
			if ($startPage == 0) $res .= " style='visibility: hidden;'";
			$res .= ">".$this->SWIFT4->Language->Get("sp_first")."</a>";
			
			// Prev button
			$res .= "<span ".($startPage <= 0 ? "style='visibility: hidden;'":"").">";
			$res .= "<a href='javascript: void(0);' onclick='document.gridform._gPage.value=".($startPage-1).
				"; document.gridform.submit();'>";
			$res .= $this->SWIFT4->Language->Get("buttonback")."</a></span>";
			
			// Page Buttons
			$firstPage = max(0,$startPage-2);
			$lastPage = min($maxPages, $firstPage+5);
			for ($pg = $firstPage; $pg < $lastPage; $pg++) {
				$res .= "<a href='javascript: void(0);' onclick='document.gridform._gPage.value=".$pg.
					"; document.gridform.submit();'> ";
				if ($pg == $startPage) {
					$res .= "<b>".($pg+1)."</b>";
				} else {
					$res .= ($pg+1);
				}
				$res .= " </a>";
			}
			
			// Next button
			$res .= "<span ".($startPage >= ($maxPages-1) ? "style='visibility: hidden;'":"").">";
			$res .= "<a href='javascript: void(0);' onclick='document.gridform._gPage.value=".($startPage+1).
				"; document.gridform.submit();'>";
			$res .= $this->SWIFT4->Language->Get("buttonnext")."</a></span>";

			if ($totalItems != 0 && $startPage != $maxPages-1) {
				// Last button
				$res .= "<a href='javascript: void(0);' onclick='document.gridform._gPage.value=".($maxPages-1).
					"; document.gridform.submit();'>";
				$res .= $this->SWIFT4->Language->Get("sp_last")."</a>";
			}
			
			$res .= "</div></div>";
		}
		return $res;
	}
	
	public function RenderListContents($sql, $fields, $options) {
		$res = "";
		
		if (is_array($fields)) {
			
			$startItem = (!empty($_POST["_gPage"]) ? intval($_POST["_gPage"]) : 0);
			$perPage = (!empty($options["recordsperpage"]) ? intval($options["recordsperpage"]) : 15);
			$sortItem = (!empty($_POST["_gSort"]) ? $_POST["_gSort"] : (!empty($options["sortby"]) ? $options["sortby"]:""));
			$sortOrder = (!empty($_POST["_gDir"]) ? $_POST["_gDir"] : (!empty($options["sortorder"]) ? $options["sortorder"]:"ASC"));
	
			if (!empty($sortItem)) {
				$sortClause = " ORDER BY ".$sortItem." ".$sortOrder;
			} else {
				$sortClause = "";
			}
					
			$contents = array();
			if ($this->SWIFT4->Database->QueryLimit($sql.$sortClause,$perPage,$startItem*$perPage)) {
				while ($this->SWIFT4->Database->NextRecord()) {
					$params = $this->SWIFT4->Database->Record;
					if (!empty($options["callback"])) {
						call_user_func_array($options["callback"],array(&$params));
					}
					$contents[] = $params;
				}
			}
			
			if (count($contents) > 0) {
				$row = 0;
				
				foreach ($contents as $item) {
					$res .= '<tr>';
					
					foreach ($fields as $hdr) {
						$res .= '<td class="sp_row'.($row+1).'" '.
							(isset($hdr["align"]) ? ' align="'.$hdr["align"].'"':"").'>';
						if (isset($item[$hdr["name"]])) {
							$res .= $item[$hdr["name"]];
						}
						$res .= "</td>\n";
					}
					$row = 1-$row;				
					$res .= '</tr>';
				}
			} else {
				$res = '<tr>';
				$res .= '<td rowspan="'.count($fields).'" ><div class="infotextcontainer">';
				$res .= htmlspecialchars($this->SWIFT4->Language->Get('noinfoinview')).'</td></td></tr>';
			}
		}
		
		return $res;
	}

	public function EndForm() {
		return "</table></form>";
	}
	
	public function StoreSession() {
		$intId = $this->SWIFT4->Interface->GetInterface();
		
		if (isset($_REQUEST["SWIFT_sessionid".$intId])) {
			$_SESSION["SP_SessionID"] = $_REQUEST["SWIFT_sessionid".$this->SWIFT4->Interface->GetInterface()];
//		} else {
//			var_dump($_REQUEST);
//			var_dump($_SESSION);
//			exit;
		}
	}
	
	public function ResumeSession($paramNum = -1) {
		if (empty($_REQUEST['sessionid'.$this->SWIFT4->Interface->GetInterface()])) {
			$sID = "";
			foreach ($_REQUEST as $key => $value) {
				if (substr($key,0,12) == '/supportpay/') {
					$parts = explode('/',$key);
					
					if ($paramNum < 0) {
						$paramNum = count($parts) + $paramNum;
					}
					
					if ($paramNum > 3) {
						if (isset($parts[$paramNum])) {
							session_id($parts[$paramNum]);
						}
					}
					break;
				}
			}
		}
		
		session_start();
		if (isset($_SESSION["SP_SessionID"])) {
			$_POST['sessionid'] = $_SESSION["SP_SessionID"];
		}
	}
	
	public function MakeCreditHeader($title) {
		global $SPFunctions;
		
		$cdtTitle = $title;

		$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
		// Allow credit info to be shown if WHMCS is enabled, because credits could come in from there.
		if (is_object($this->SWIFT4->User) 
			&& ($gateway != SP_PROCESSOR_NONE || $this->SWIFT4->Settings->getKey("settings","sp_whmcs_enable")))
		{
			$Record = $SPFunctions->getUserCredit($this->SWIFT4->User->GetUserID());
			$this->SWIFT4->Template->Assign("sp_chdr_title",$title);
			$this->SWIFT4->Template->Assign("sp_chdr_credit",$Record);
			if ($this->SWIFT4->Settings->getKey("settings","sp_whmcs_enable")) {
				// Also provide the WHMCS UserID.
				$UserID = $this->SWIFT4->Database->queryFetch("select whmcs_userid from ".TABLE_PREFIX."sp_users where ".
					"userid = ".$this->SWIFT4->User->GetUserID());
				// Shouldn't ever be empty because even if this is a new user, the above getUserCredit call should
				// create the relevant record in sp_users .
				$this->SWIFT4->Template->Assign("sp_chdr_whmcsid",$UserID["whmcs_userid"]);
			}
			
			if ($Record["discount"] < 100) {
				$accept = $this->SWIFT4->Settings->getKey("settings","sp_accept");
				$addOverdraft = $this->SWIFT4->Settings->getKey("settings","sp_odshowinclusive");
				$doMinutes = false; $doTickets = false;

				if ($accept == SP_ACCEPT_MINUTES || $accept == SP_ACCEPT_BOTH || $Record["minutes"] > 0) {
					if (!$addOverdraft) $Record["minutes"] -= $Record["overdraft"];
					$doMinutes = true;
					$this->SWIFT4->Template->Assign("minutescdt",$Record["minutes"]);
					$this->SWIFT4->Template->Assign("purchaseMins",max(-$Record["minutes"],$this->SWIFT4->Settings->getKey("settings","sp_minmin")));
					$this->SWIFT4->Template->Assign("sp_chdr_minutetext", $SPFunctions->formatMTP($Record["minutes"] == 1 ? "{Minute}" : "{Minutes}"));
				}
				if ($accept == SP_ACCEPT_TICKETS || $accept == SP_ACCEPT_BOTH || $Record["tickets"] > 0) {
					$doTickets = true;
					$this->SWIFT4->Template->Assign("ticketscdt",$Record["tickets"]);
					$this->SWIFT4->Template->Assign("purchaseTkts",max(-$Record["tickets"],$this->SWIFT4->Settings->getKey("settings","sp_mintkt")));
					$this->SWIFT4->Template->Assign("sp_chdr_tickettext", $SPFunctions->formatMTP($Record["tickets"] == 1 ? "{Ticket}" : "{Tickets}"));
				}

				$this->SWIFT4->Template->Assign("dominutes",$doMinutes);
				$this->SWIFT4->Template->Assign("dotickets",$doTickets);
				$cdtTitle = $this->SWIFT4->Template->Get('sp_creditheader', SWIFT_TemplateEngine::TYPE_DB);
			}
		}
		
		return $cdtTitle;
	}
	
	public function StartForm($name, $url) {
		$res = "<form name='".htmlspecialchars($name,ENT_QUOTES)."' method='POST' ";
		$res .= "action='".$url."'>";
		
		$res .= '<table width="90%" border="0" cellPadding="1" cellSpacing="4">';
		
		return $res;
	}
	
	public function Title($text) {
		$res = '<tr><td colspan="2"><table class="hlineheader"><tr><th rowspan="2" nowrap>';
		$res .= htmlspecialchars($text);
		$res .= '</th><td>&nbsp;</td></tr><tr><td class="hlinelower">&nbsp;</td></tr></table></td></tr>';
		
		return $res;
	}
	
	public function Submit($title="") {
		if (empty($title)) {
			$title = $this->SWIFT4->Language->Get("buttonsubmit");
		}
		
		$res = "<tr><td colspan='2'><div class='subcontent'>";
		$res .= '<input name="button" class="rebuttonwide2" type="submit" value="'.htmlspecialchars($title,ENT_QUOTES).'"/>';
		$res .= '</div></td></tr>';
		
		return $res;
	}
	
	public function Text($name,$title,$descr,$value) {
		$res = '<tr><td width="200" align="left" class="zebraodd" vAlign="middle">'.htmlspecialchars($title);
		$res .= '</td><td><input name="'.htmlspecialchars($name,ENT_QUOTES).'" class="swifttextwide" ';
		$res .= 'id="'.htmlspecialchars($name,ENT_QUOTES).'" type="text" size="45" ';
		$res .= 'value="'.htmlspecialchars($value,ENT_QUOTES).'"/></td></tr>';
		
		return $res;
	}
	
	public function GenPaymentErrorPage(&$resArray) {
		$message = "<table style='width: 100%;'>";
		$row = 1;
		$valid_keys = array('TIMESTAMP','CORRELATIONID','ACK',
			'L_ERRORCODE0','L_SHORTMESSAGE0','L_LONGMESSAGE0','L_SEVERITYCODE0',
			'L_ERRORCODE1','L_SHORTMESSAGE1','L_LONGMESSAGE1','L_SEVERITYCODE1',
			'L_ERRORCODE2','L_SHORTMESSAGE2','L_LONGMESSAGE2','L_SEVERITYCODE2',
			'L_ERRORCODE3','L_SHORTMESSAGE3','L_LONGMESSAGE3','L_SEVERITYCODE3',
			'L_ERRORCODE4','L_SHORTMESSAGE4','L_LONGMESSAGE4','L_SEVERITYCODE4',
			'L_ERRORCODE5','L_SHORTMESSAGE5','L_LONGMESSAGE5','L_SEVERITYCODE5',
			'L_ERRORCODE6','L_SHORTMESSAGE6','L_LONGMESSAGE6','L_SEVERITYCODE6',
			'L_ERRORCODE7','L_SHORTMESSAGE7','L_LONGMESSAGE7','L_SEVERITYCODE7',
			'L_ERRORCODE8','L_SHORTMESSAGE8','L_LONGMESSAGE8','L_SEVERITYCODE8',
			'L_ERRORCODE9','L_SHORTMESSAGE9','L_LONGMESSAGE9','L_SEVERITYCODE9'
			);
		
		foreach ($resArray as $key => $value) {
			if (in_array($key,$valid_keys)) {
				$message .= "<tr class='sp_row".$row."'><th>".htmlspecialchars($key).
					"</th><td>".htmlspecialchars($value)."</td></tr>";
				$row = 3 - $row;
			}
		}
		$message .= "</table>";

		// Always error-log it.		
		error_log("Payment Error");
		if (!empty($this->SWIFT4->User)) {
			error_log("UserID ".$this->SWIFT4->User->GetUserID()." (".$this->SWIFT4->User->GetProperty("fullname").")");
		} else {
			error_log("UserID undefined!");
		}
		error_log(print_r($resArray, true));

		return $message;
	}
	
	public function ShowPaymentErrorPage(&$resArray) {
		global $SPFunctions;
		$errormessage = $this->GenPaymentErrorPage($resArray);

		$errTitle = $this->SWIFT4->Language->Get("sp_payerror_title");

		$basemessage = "";
		$knownErrors = array(
			10502, // Invalid credit card
			10504, // Invalid CVV
			10507, // Account restricted,
			10508, // Invalid credit-card date
			10510, // Unknown credit card type
			10512, // Missing first name,
			10513, // Missing surname
			10519, // Blank credit card,
			10521, // Invalid card number
			10527, // Invalid card number
			10534, // Card restricted
			10535, // Card declined,
			// and many more. Got bored at this point.
		);
		
		for ($i=0; $i < 10; $i++) {
			if (isset($resArray['L_ERRORCODE'.$i]) && in_array($resArray['L_ERRORCODE'.$i],$knownErrors)) {
				$basemessage = $this->SWIFT4->Language->Get('sp_payerror_knowncode');
				break;
			}
		}
		
		if (empty($basemessage)) {
			$basemessage = $this->SWIFT4->Language->Get('sp_payerror_unknowncode');
			// Also email the site admin with the full details.
			$errEmail = $this->SWIFT4->Settings->getKey("settings","sp_erroremail");
			if (empty($errEmail)) {
				$errEmail = $this->SWIFT4->Settings->getKey("settings","general_returnemail");
			}
			if (!empty($errEmail)) {
				SWIFT_Loader::LoadLibrary('Mail:Mail');

				$errContents = "UserID ".(!is_object($this->SWIFT4->User) ? "Unknown" : 
					$this->SWIFT4->User->GetUserID()." (".$this->SWIFT4->User->GetProperty("fullname").")");
				$errContents .= "\r\n".print_r($resArray,true);
				
				$mailObj = new SWIFT_Mail();
				$mailObj->SetToField($errEmail);
				$mailObj->SetFromField($errEmail, $errEmail);
				$mailObj->SetSubjectField($errTitle);
				$mailObj->SetDataText($errContents);
				if (!$mailObj->SendMail(false)) {
					error_log("Unable to send invoice mail to ".$errEmail);
					SWIFT::Error("Unable to send error mail to ".$errEmail);
				}
			}
		}

		$this->SWIFT4->UserInterface->Header('sp_payerror_title');
		$SPFunctions->assignSectionTitle($this->SWIFT4->Language->Get('sp_payerror_title'));
		$this->SWIFT4->Template->Assign("errordetails",$errormessage);
		$this->SWIFT4->Template->Assign("error_basemessage",$basemessage);
		$this->SWIFT4->Template->Render("sp_header");
		$this->SWIFT4->Template->Render("sp_pay_error");
		$this->SWIFT4->Template->Render("sp_footer");
		$this->SWIFT4->UserInterface->Footer();
	}
};

global $SPUserFuncs;
$SPUserFuncs = new SWIFT_SPClientFunctions;

?>
