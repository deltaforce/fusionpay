<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_Reports.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Reports extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function _null($record)
	{
		return $record;
	}

	static public function _gridFields($record)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$record["options"] = $SPFunctions->visibleLink(null,$_SWIFT->Language->Get('edit'),$_SWIFT->Language->Get('edit'),
			'loadViewportData("/supportpay/Reports/Edit/'.$record["repid"].'")');

		if (strpos($record["query"],"{fromdate}") !== false ||
			strpos($record["query"],"{todate}") !== false)
		{
			$record["title"] = "<a href='javascript: void(0);' onclick=\"".
				"UICreateWindow('".SWIFT::Get('basename')."/supportpay/Reports/GetDates/".$record["repid"].
				"', 'getdt', 'SupportPay', '".$_SWIFT->Language->Get('loadingwindow') ."', 560, 300, true, this);\"".
				" title='".$_SWIFT->Language->Get("sp_rep_run")."'>".htmlspecialchars($record["title"]).
				"&nbsp;<img style='vertical-align: middle; padding-right: 0.3em; border: none;' ".
				"src='".SWIFT::Get("themepathimages")."/icon_newwindow_gray.png'/></a>";
		} else {
			// No dates used. Just run it.
			$record["title"] = $SPFunctions->visibleLink(null,
				$_SWIFT->Language->Get("sp_rep_run"),htmlspecialchars($record["title"]),
				'loadViewportData("/supportpay/Reports/View/'.$record["repid"].'");');
		}

		return $record;
	}
	
	
	public function RenderDateDialog($repId) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_rep_run'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, "/supportpay/Reports/View/".$repId, 
			SWIFT_UserInterface::MODE_INSERT, true, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_rep_params'), 'icon_form.gif', 'general', true);

		$_TabObject->Date("date_start", $_SWIFT->Language->Get("sp_rep_start"), '', 
			date(SWIFT_Date::GetCalendarDateFormat(), strtotime("-1 month",time())),0,false,true);

		$_TabObject->Date("date_end", $_SWIFT->Language->Get("sp_rep_end"), '', 
			date(SWIFT_Date::GetCalendarDateFormat(), time()),0,false,true);
		
		$this->UserInterface->End();
		$this->UserInterface->Footer();

		return true;
	}

	public function RenderImportDialog() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_currencylist;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('import'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		$this->UserInterface->Start($className.'_'.__FUNCTION__, "/supportpay/Reports/DoImport", 
			SWIFT_UserInterface::MODE_INSERT, true, false);

		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('import'), 'icon_form.gif', 'general', true);
		$_TabObject->File("filename", $this->Language->Get("sp_repxml"), '' );

		$this->UserInterface->End();
		$this->UserInterface->Footer();

		return true;
	}
	
	public function RenderGrid() {		// Actual package grid
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->Load->Library("UserInterface:UserInterfaceGrid", array("sp_represgrid"));
		$this->Language->Load('templates');
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterfaceGrid->SetRecordsPerPage(20);

		$this->UserInterfaceGrid->SetExtendedButtons(
			array(
					array("title" => $_SWIFT->Language->Get("sp_newrep"), 
						"link" => "loadViewportData('/supportpay/".$className."/Edit');",
						"icon" => "icon_addplus2.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_DEFAULT,
						"id" => "btn_add1"),
					array("title" => $_SWIFT->Language->Get("importexport"), 
						"link" => 'UIDropDown(\'rep_actionmenu\', event, \'rep_dropdown\', \'gridextendedtoolbar\');',
						"icon" => "icon_funnel.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_MENU,
						"id" => "rep_dropdown"),
					)
				);

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
					$this->Language->Get('delete'), 
					'icon_delete.gif', array('Controller_'.$className, 'DeleteList'), $this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
					$this->Language->Get('sp_copypkg'), 
					'icon_copy.gif', array('Controller_'.$className, 'CloneList'), $this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->SetRenderCallback(array("View_".$className, "_gridFields"));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("repid", "repid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("title", $_SWIFT->Language->Get("sp_reptitle"), 
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,
				SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField('options', $_SWIFT->Language->Get('sp_options'), 
			SWIFT_UserInterfaceGridField::TYPE_CUSTOM, 0, SWIFT_UserInterfaceGridField::ALIGN_CENTER));

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$this->UserInterfaceGrid->SetSearchQuery('select * from '.TABLE_PREFIX.'sp_reports '.
				'where '.$this->UserInterfaceGrid->BuildSQLSearch('title'),
				'select count(1) totalitems from '.TABLE_PREFIX.'sp_reports '.
				'where '.$this->UserInterfaceGrid->BuildSQLSearch('title')
				);
		}
		$this->UserInterfaceGrid->SetQuery('select * from '.TABLE_PREFIX.'sp_reports',
			'select count(1) totalitems from '.TABLE_PREFIX.'sp_reports');

		$this->UserInterfaceGrid->Render();
		$this->DispatchMenu();
		$this->UserInterfaceGrid->Display();

		return true;
	}
	
	public function RenderResults($repid) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
		
		$errMsg = null;
		$needDates = false;
		$RepDetails = $_SWIFT->Database->QueryFetch("select * from ".TABLE_PREFIX."sp_reports where repid = ".intval($repid));
		if (empty($RepDetails["title"])) {
			$errMsg = "Report not found.";
		} else {
			// Found it. Does it need dates?
			if ((strpos($RepDetails["query"],"{fromdate}") !== false ||
				strpos($RepDetails["query"],"{todate}") !== false))
			{
				// Yes. Do we have any?
				$needDates = true;
				
				if (!isset($_POST["date_start"]) || !isset($_POST["date_end"])) {
					$errMsg = "No dates supplied for this report.";
				} else {
					// ... but are they valid?
					$_POST["date_start"] = $SPFunctions->TimestampFromDate($_POST["date_start"]);
					$_POST["date_end"] = $SPFunctions->TimestampFromDate($_POST["date_end"]);
					if (empty($_POST["date_start"]) || empty($_POST["date_end"])) {
						$errMsg = "No dates supplied for this report.";
					}
				}
			}
		}
		
		if (!is_null($errMsg)) {
			SWIFT::Error("SupportPay",$errMsg);
			$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_rep_results'), 1,
				$SPFunctions->findAdminBar("SupportPay"));
			$this->UserInterface->End();
			$this->UserInterface->Footer();
			return false;
		}
				
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_rep_results'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start("grid", '/supportpay/'.$className.'/View/'.$repid, SWIFT_UserInterface::MODE_INSERT, 
			false, false);
		$_TabObject = $this->UserInterface->AddTab($RepDetails["title"], 'icon_form.gif', 'general', true);
		
		// Having the default submit() action blocks the form here, where it's come from the date dialog.
		// Some kind of JSON problem. To avoid, juse use explicit submit...
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('sp_rep_refresh'), 'icon_regenerate.gif', 
			'this.blur(); document.gridform.submit();', SWIFT_UserInterfaceToolbar::LINK_JAVASCRIPT);
			
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('back'), 'icon_back.gif', '/supportpay/'.$className.'/Main', SWIFT_UserInterfaceToolbar::LINK_VIEWPORT);

		$RepDetails["query"] = str_replace("{prefix}",TABLE_PREFIX,$RepDetails["query"]);
		$RepDetails["countsql"] = str_replace("{prefix}",TABLE_PREFIX,$RepDetails["countsql"]);
		if ($needDates) {
			$RepDetails["query"] = str_replace("{fromdate}",$_POST["date_start"],$RepDetails["query"]);
			$RepDetails["query"] = str_replace("{todate}",$_POST["date_end"],$RepDetails["query"]);
			$RepDetails["countsql"] = str_replace("{fromdate}",$_POST["date_start"],$RepDetails["countsql"]);
			$RepDetails["countsql"] = str_replace("{todate}",$_POST["date_end"],$RepDetails["countsql"]);
			$_TabObject->Hidden("date_start", $_POST["date_start"]);
			$_TabObject->Hidden("date_end", $_POST["date_end"]);
		} else {
			$_POST["date_end"] = $_POST["date_start"] = 0;
		}
		
		$firstRow = $_SWIFT->Database->QueryFetch("select " . $RepDetails["query"]);
		if (is_array($firstRow) && count($firstRow) > 0) {			
			$this->UserInterface->Toolbar->AddButton($this->Language->Get('sp_downloadcsv'), 'icon_save.gif', 'PopupSmallWindow(\''.
				SWIFT::Get('basename') .'/supportpay/'.$className.'/Download/'.$repid.'/'.intval($_POST["date_start"]).
				'/'.intval($_POST["date_end"]).'\');',
				SWIFT_UserInterfaceToolbar::LINK_JAVASCRIPT);
			$_TabObject->Title($_SWIFT->Language->Get("sp_rep_results"), "doublearrows.gif");
			
			$options = array();
			$options["recordsperpage"] = "15";
			$options["noform"] = true;
			$options["headerclass"] = "gridtabletitlerow";

			$fields = array();
			foreach ($firstRow as $keyName => $dummy) {
				$fields[] = array("name" => $keyName, "title" => ucwords($keyName), "width" => "");
			}

			$_SWIFT->Template->SetTemplateGroupID(1);
			$_reportResultHTML = $_SWIFT->Template->Get("sp_css", SWIFT_TemplateEngine::TYPE_DB);
			$_reportResultHTML .= $SPUserFuncs->RenderListHeader("select count(1) totalitems from " . $RepDetails["countsql"],
				$fields,$options,"ReportGrid");
			$_reportResultHTML .= $SPUserFuncs->RenderListContents("select " . $RepDetails["query"],$fields,$options);
			$_reportResultHTML .= $SPUserFuncs->RenderListFooter("select count(1) totalitems from " . $RepDetails["countsql"],$options);
			
			$_TabObject->RowHTML("<tr><td>".$_reportResultHTML."</td></tr>");

		} elseif ($firstRow === false) {
			// No data.
			$errMsg = $_SWIFT->Database->FetchLastError();
			if ($errMsg === false) {
				// No data.
				$_TabObject->Description($_SWIFT->Language->Get("sp_rep_nodata"), "doublearrows.gif");
			} else {
				// Error in SQL.
				$_TabObject->Description($_SWIFT->Language->Get("sp_bad_sql"), "doublearrows.gif");
				$_TabObject->RowHTML("<tr><td><pre>".$errMsg."</pre></td></tr>");
				$_TabObject->RowHTML("<tr><td><pre>".htmlspecialchars("select " . $RepDetails["query"])."</pre></td></tr>");
			}
		}

		$this->UserInterface->End();
		$this->UserInterface->Footer();

		return true;
	}

	public function RenderEditor($Report) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_erpagetitle'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Update', SWIFT_UserInterface::MODE_INSERT, 
			false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_erpagetitle'), 'icon_form.gif', 'general', true);

		$_TabObject->Info("SupportPay",$SPFunctions->getPaymentWarnings());
		$_TabObject->Error("SupportPay",$SPFunctions->checkLicense());

		if (is_array($Report)) {
			$_TabObject->Title($_SWIFT->Language->Get("sp_rep_params"), "doublearrows.gif");
			$_TabObject->Text("title",$_SWIFT->Language->Get("sp_reptitle"),"",$Report["title"]);
			$_TabObject->TextArea("query",$_SWIFT->Language->Get("sp_repsql"),$_SWIFT->Language->Get("d_sp_repsql"),$Report["query"],80,5);
			$_TabObject->TextArea("countsql",$_SWIFT->Language->Get("sp_repcsql"),$_SWIFT->Language->Get("d_sp_repcsql"),$Report["countsql"],80,5);
			$this->UserInterface->Toolbar->AddButton($this->Language->Get('update'), 'icon_check.gif');
			$this->UserInterface->Toolbar->AddButton($this->Language->Get('back'), 'icon_back.gif', '/supportpay/'.$className.'/Main', SWIFT_UserInterfaceToolbar::LINK_VIEWPORT);
			$_TabObject->Hidden("repid", $Report["repid"]);
		}
		
		$this->UserInterface->End();
		$this->UserInterface->Footer();
		return true;
	}

	public function DispatchMenu() {
		$actions = array($this->Language->Get('export') => 'PopupSmallWindow(\''.SWIFT::Get('basename') .'/supportpay/Reports/Export\');',
			$this->Language->Get('importxml') => 'UICreateWindow(\''.SWIFT::Get('basename') .'/supportpay/Reports/Import\''.
			', \'getdt\', \'SupportPay\', \''.$this->Language->Get('loadingwindow') .'\', 560, 300, true, this);'
			);
		
		echo '<ul class="swiftdropdown" id="rep_actionmenu">';
		foreach ($actions as $aTitle => $aJS) {
			echo '<li class="swiftdropdownitemparent" onclick="'.str_replace('"',"'",$aJS).'"><div class="swiftdropdownitem">';
			//			echo '<div class="swiftdropdownitemimage"><img src="'.SWIFT::Get('themepathimages').'images/menu_newaction.gif';
			//			echo '" align="absmiddle" border="0" /></div>';
			echo '<div class="swiftdropdownitemtext" onclick="javascript: void(0);">';
			echo htmlspecialchars($aTitle).'</div></div></li>';
		}
		echo '</ul>';

		return true;
	}
};
?>
