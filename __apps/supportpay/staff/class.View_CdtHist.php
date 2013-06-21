<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/staff/class.View_CdtHist.php $, $Change: 3405 $, $DateTime: 2013/02/04 15:03:21 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_CdtHist extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function _gridFields($record)
	{
		global $SPFunctions, $sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();

		$record["price"] = $sp_currencylist[$record["currency"]]["symbol"].sprintf("%0.2f",$record["cost"]);
		if (intval($record["minutes"] < 0))
			$record["minutes"] = "<span style='color: Red;'>".$record["minutes"]."</span>";
		if (intval($record["tickets"] < 0))
			$record["tickets"] = "<span style='color: Red;'>".$record["tickets"]."</span>";
		
		if (is_null($record["pending"])) {
			$record["pending"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_check.gif" border="0" /> '.
				$_SWIFT->Language->Get("sp_payment_cleared");
		} else {
			$record["pending"] = '<img src="'. SWIFT::Get("themepathimages") .'icon_block.gif" border="0" /> '.
				$_SWIFT->Language->Get("sp_payment_pending");
		}
		
		$record["created"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["created"]);
		if (!is_null($record["expiry"])) {
			$record["expiry"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["expiry"]);
		} else {
			$record["expiry"] = "N/A";
		}

		if ($record["ticketid"] != null) {
			$record["comments"] = $SPFunctions->visibleLink(null,
				$_SWIFT->Language->Get("rnticketlist"),$record["comments"],
				'loadViewportData("/Tickets/Ticket/View/'.$record["ticketid"].'");');
		}

		return $record;
	}

	public function RenderPayments($ticketid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$_renderedHTML = '<div id="ticketbillingcontainertd">';
		$accept = $_SWIFT->Settings->getKey("settings","sp_accept");
		$willTakeMinutes = ($accept & SP_ACCEPT_MINUTES);
		$willTakeTickets = ($accept & SP_ACCEPT_TICKETS);

		$this->UserInterface->Start(get_class($this), '', SWIFT_UserInterface::MODE_INSERT, false);
		$parentTab = new SWIFT_UserInterfaceTab($this->UserInterface, $this->Language->Get('sp_uw_master'), '', 1, 'payments', false, false, 0, '');

		$totals = $_SWIFT->Database->QueryFetch("select sum(timespent) timespent, sum(timebillable) timebillable ".
			"from ".TABLE_PREFIX."tickettimetracks where ticketid = ".intval($ticketid));
		
		$_renderedHTML .= '<div class="ticketbillinginfocontainer2">';
		$_renderedHTML .= '<img src="' . SWIFT::Get('themepathimages') . 'icon_clock.png' . '" align="absmiddle" border="0" /> ' .
			'<b>' . $this->Language->Get('billtotalworked') . '</b> ' . SWIFT_Date::ColorTime($totals["timespent"], false, true) .
			'&nbsp;&nbsp;&nbsp;&nbsp;' .
			'<b>' . $this->Language->Get('billtotalbillable') . '</b> ' . SWIFT_Date::ColorTime($totals["timebillable"], false, true);
			
		$totalsPaid = $_SWIFT->Database->QueryFetch("select -sum(minutes) minutes, -sum(tickets) tickets ".
			"from ".TABLE_PREFIX."sp_user_payments where ticketid = ".intval($ticketid)." and paytype = ".SP_PAYTYPE_TICKET);
		
		if ($totalsPaid["minutes"] != 0 || $willTakeMinutes) {
			$_renderedHTML .= '&nbsp;&nbsp;&nbsp;&nbsp;' .
				'<b>' . $SPFunctions->formatMTP("{Minutes} ".$this->Language->Get('sp_chatpaid')) . '</b> ' . SWIFT_Date::ColorTime($totalsPaid["minutes"]*60, false, true);
		}
		if ($totalsPaid["tickets"] != 0 || $willTakeTickets) {
			$_renderedHTML .= '&nbsp;&nbsp;&nbsp;&nbsp;' .
				'<b>' . $SPFunctions->formatMTP("{Tickets} ".$this->Language->Get('sp_chatpaid')) . '</b> ' . $totalsPaid["tickets"];
		}

		$_renderedHTML .= '</div><div id="ticketbillingcontainerdiv">';

		$_SWIFT->Database->Query("select * from ".TABLE_PREFIX."sp_user_payments ".
			"where ticketid = ".intval($ticketid)." and paytype = ".SP_PAYTYPE_TICKET." order by created desc");
		while ($_SWIFT->Database->NextRecord()) {
			$paymentEntry = &$_SWIFT->Database->Record;

			$_timeTrackColor = 1; 
			$_timeTrackTitle = SWIFT_Date::Get(SWIFT_Date::TYPE_DATETIME, $paymentEntry['created']);
			$_timeTrackDetails = "";
			if ($willTakeMinutes || $paymentEntry["minutes"] != 0) {
				$_timeTrackDetails .= $SPFunctions->formatMTP(-$paymentEntry["minutes"]." {Minutes}");
			}
			if ($willTakeMinutes || $paymentEntry["tickets"] != 0) {
				if (!empty($_timeTrackDetails)) {
					$_timeTrackDetails .= "&nbsp;&nbsp;&nbsp;&nbsp;";
				}
				$_timeTrackDetails .= $SPFunctions->formatMTP(-$paymentEntry["tickets"]." {Tickets}");
			}

			$_renderedHTML .= '<div id="note' . intval($_timeTrackColor) . '" class="bubble"><div class="notebubble"><blockquote><p>' .
				htmlspecialchars($paymentEntry['comments']).
				'</p><hr class="ticketbillinghr" /><p>'. $_timeTrackDetails . '</p></blockquote></div><cite class="tip"><strong><img src="'.
				SWIFT::Get('themepath') .'images/icon_clock.png" align="absmiddle" border="0" /> ' . $_timeTrackTitle . '</strong>';
			$_renderedHTML .= '</cite></div>';
		}
		$_renderedHTML .= '</div></div>';

		$parentTab->RowHTML('<tr class="gridrow3" id="ticketbillingcontainerdivholder"><td colspan="2" align="left" valign="top class="gridrow3" id="ticketbillingcontainertd">' . $_renderedHTML . '</td></tr>');
		echo $parentTab->GetDisplayHTML(true);
		echo '<script language="Javascript" type="text/javascript">reParseDoc();</script>';

		return true;
	}
			
	public function RenderGrid($userid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$className = str_replace("View_","",get_class($this));

		if ($SPFunctions->isInitialGrid()) {
			$Rec = $SPFunctions->getUserCredit($userid,true);
			$this->Template->Assign("dotickets",true);
			$this->Template->Assign("dominutes",true);
			$this->Template->Assign("ticketscdt",$Rec["tickets"]);
			$this->Template->Assign("minutescdt",$Rec["minutes"]);
			$this->Template->Render('sp_credit',SWIFT_TemplateEngine::TYPE_DB);
		}

		$this->Load->Library("UserInterface:UserInterfaceGrid", array($className."_"."cdthist"));
		$this->UserInterfaceGrid->SetRecordsPerPage(10);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("txid", "txid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));
		
		$this->UserInterfaceGrid->SetExtendedButtons(
				array(
					array("title" => $_SWIFT->Language->Get("sp_recalc_credit"), 
						"link" => "loadViewportData('/supportpay/".$className.'/Recalc/'.$userid."');",
						"icon" => "icon_regenerate.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_DEFAULT,
						"id" => "btn_ext1"),
					)
			);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("txid", "Tx#", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("created", "Date", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,SWIFT_UserInterfaceGridField::SORT_DESC),true);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", ucfirst(strtolower($_SWIFT->Settings->getKey("settings","sp_minutestxt"))), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", ucfirst(strtolower($_SWIFT->Settings->getKey("settings","sp_ticketstxt"))), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("comments", ucfirst(strtolower($_SWIFT->Language->Get("sp_pkg_descr"))), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("paidby", "Paid By", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("price", ucfirst(strtolower($_SWIFT->Language->Get("sp_pkg_price"))), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("expiry", ucfirst(strtolower($_SWIFT->Language->Get("sp_expiry"))), 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("pending", "Status", 
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		$_selectquery = 'SELECT * FROM '.TABLE_PREFIX.'sp_user_payments WHERE userid = '.$userid;
		$_countquery = 'SELECT COUNT(*) AS totalitems FROM '. TABLE_PREFIX .'sp_user_payments '.
			'WHERE userid = '.$userid;
		
		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery($_selectquery . 
				" AND ( ".$this->UserInterfaceGrid->BuildSQLSearch('comments').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('paidby').")",
				$_countquery. 
				" AND ( ".$this->UserInterfaceGrid->BuildSQLSearch('comments').
				" OR ".$this->UserInterfaceGrid->BuildSQLSearch('paidby').")"
				);
		}
		$this->UserInterfaceGrid->SetQuery($_selectquery, $_countquery);

		$this->UserInterfaceGrid->Render(); $this->UserInterfaceGrid->Display();
		return true;
	}
};
?>
