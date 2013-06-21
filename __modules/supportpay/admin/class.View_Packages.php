<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_Packages.php $, $Change: 3406 $, $DateTime: 2013/02/04 20:10:00 $ -->
<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_Packages extends SWIFT_View
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	static public function _gridFields($record) {
		global $SPFunctions,$sp_currencylist;
		$_SWIFT = SWIFT::GetInstance();

		if (0 == intval($record["duration"])) {
			$record["duration"] = "Never";
		} else {
			$record["duration"] = $record["duration"] . " days";
		}
		
		if ($record["img_url"] != "") {
			$record["img_url"] = "<img style='border: solid 1px black; width: 80px; height: 64px;' src='".
				str_replace("'","&apos;",$record["img_url"])."'/>";
		} else $record["img_url"] = "None";
		
		$record["pkg_commence"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["pkg_commence"]);
		if (!empty($record["pkg_expire"]))
			$record["pkg_expire"] = date(SWIFT_Date::GetCalendarDateFormat(),$record["pkg_expire"]);
		else
			$record["pkg_expire"] = "";
		
		// Don't show tax calculations if no tax is being charged.
		if (floatval($_SWIFT->Settings->getKey("settings","sp_taxrate")) > 0) {
			$record["vat"] = $sp_currencylist[$_SWIFT->Settings->getKey("settings","sp_currency")]["symbol"].sprintf("%0.2f",
				$SPFunctions->getTaxOnPrice($record["price"]));
			$record["tprice"] = $sp_currencylist[$_SWIFT->Settings->getKey("settings","sp_currency")]["symbol"].sprintf("%0.2f",
				$SPFunctions->getFinalTaxPrice($record["price"]));
		}
		
		$record["price"] = $sp_currencylist[$_SWIFT->Settings->getKey("settings","sp_currency")]["symbol"].sprintf("%0.2f",
			$SPFunctions->getPreTaxPrice($record["price"]));
		
		$record["options"] = '<a onClick="javascript:doConfirm(\''. $_SWIFT->Language->Get("actionconfirm") .'\', \''.SWIFT::Get('basename').
			$SPFunctions->getSwiftURL('supportpay','packages') . '&action=delete&pkgid='.
			$record["pkgid"] .'\');" href="#" title="'.$_SWIFT->Language->Get("delete").'"><img style="vertical-align: middle;" src="'.SWIFT::Get("themepathimages") .'icon_delete.gif" border="0">&nbsp;'.
			$_SWIFT->Language->Get("delete").'</a>';
		$record["options"] .= '&nbsp;|&nbsp;<a href="'.SWIFT::Get('basename').$SPFunctions->getSwiftURL('supportpay','packages') . '&action=clone&pkgid='.
			$record["pkgid"].'" title="'.$_SWIFT->Language->Get("sp_copypkg").'"><img style="vertical-align: middle;" src="'.SWIFT::Get("themepathimages") .'icon_inserttopic.gif" border="0">&nbsp;'.
			$_SWIFT->Language->Get("sp_copypkg").'</a>';

		$record["enabled"] = $SPFunctions->visibleLink(null,"",($record["enabled"] ? "Enabled":"Disabled"),
			'loadViewportData("/supportpay/Packages/Enable/'.$record["pkgid"].'/'.(intval($record["enabled"]) == 0 ? 1 : 0).'")');
		$record["startup"] = $record["startup"] ? $_SWIFT->Language->Get("yes"):$_SWIFT->Language->Get("no");
		$record["title"] = $SPFunctions->visibleLink("/supportpay/Packages/EditPackage/".$record["pkgid"],
			$_SWIFT->Language->Get("sp_editpkg"),$record["title"]);

		switch ($record["recur_unit"]) {
			case SP_RECUR_UNIT_WEEK:
				$record["recur_unit"] = $_SWIFT->Language->Get("week");
				break;
			case SP_RECUR_UNIT_MONTH:
				$record["recur_unit"] = $_SWIFT->Language->Get("month");
				break;
			case SP_RECUR_UNIT_YEAR:
				$record["recur_unit"] = $_SWIFT->Language->Get("year");
				break;
			case null:
				$record["recur_unit"] = "";
				break;
			default:
				$record["recur_unit"] = $_SWIFT->Language->Get("sps_unknown");
		}
		
		return $record;
	}
		
	public function RenderGrid($dispmode) {		// Actual package grid
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->Load->Library("UserInterface:UserInterfaceGrid", array("packages"));
		
		$this->UserInterfaceGrid->SetRecordsPerPage(15);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("pkgid", "pkgid", SWIFT_UserInterfaceGridField::TYPE_ID));
		$this->UserInterfaceGrid->SetRenderCallback(array("View_Packages", "_gridFields"));

		$this->UserInterfaceGrid->SetExtendedArguments($dispmode);
		$this->UserInterfaceGrid->SetNewLink("loadViewportData('".SWIFT::Get('basename')."/supportpay/Packages/EditPackage');");
		/*
		$this->UserInterface->Toolbar->AddButton("Test", 'icon_check.gif', 
			'/Knowledgebase/Article/EditSubmit/' . $_SWIFT_KnowledgebaseArticleObject->GetKnowledgebaseArticleID() . '/1',
			 SWIFT_UserInterfaceToolbar::LINK_FORM);
*/
		$className = str_replace("View_","",get_class($this));

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
				$this->Language->Get('delete'), 
				'icon_delete.gif', array('Controller_'.$className, 'DeleteList'), $this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->AddMassAction(
			new SWIFT_UserInterfaceGridMassAction(
				$this->Language->Get('sp_copypkg'), 
				'icon_copy.gif', array('Controller_'.$className, 'CloneList'), $this->Language->Get('actionconfirm')));

		$this->UserInterfaceGrid->SetExtendedButtons(
			array(
					array("title" => $_SWIFT->Language->Get("sp_show"), 
						"link" => 'UIDropDown(\'grid_actionmenu\', event, \'grid_dropdown\', \'gridextendedtoolbar\');',
						"icon" => "icon_funnel.gif",
						"type" => SWIFT_UserInterfaceGrid::BUTTON_MENU,
						"id" => "grid_dropdown"),
					)
			);

		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("title", "Title",
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT,
			SWIFT_UserInterfaceGridField::SORT_ASC),true);
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("description", "Description",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("img_url", "Image",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("pkg_commence", "Start Date",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("pkg_expire", "End Date",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("duration", "Expiry Days",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("minutes", $SPFunctions->formatMTP("{Minutes}"),
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("tickets", $SPFunctions->formatMTP("{Tickets}"),
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("price", "Cost",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		// TODO: WTF is this?
		if (floatval($_SWIFT->Settings->getKey("settings","sp_taxrate")) > 0) {
			$fields[] = array("type" => "custom", "name" => "vat", "title" => "Tax", "width" => "");
			$fields[] = array("type" => "custom", "name" => "tprice", "title" => "Total", "width" => "");
		}
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("enabled", "Enabled",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("startup", "Startup",
				SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("recur_period", $_SWIFT->Language->Get("sp_recur_period"),
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));
		$this->UserInterfaceGrid->AddField(new SWIFT_UserInterfaceGridField("recur_unit", $_SWIFT->Language->Get("sp_recur_unit"),
			SWIFT_UserInterfaceGridField::TYPE_DB, 0, SWIFT_UserInterfaceGridField::ALIGN_LEFT));

		switch ($dispmode) {
			case "2":
				$where = " enabled = 1";
				break;
			case "3":
				$where = " enabled = 0";
				break;
			default:
				$where = " 1=1";
				break;
		}

		if ($this->UserInterfaceGrid->GetMode() == SWIFT_UserInterfaceGrid::MODE_SEARCH) {
			$where .= ' AND ('.$this->UserInterfaceGrid->BuildSQLSearch('title');
			$where .= ' OR '.$this->UserInterfaceGrid->BuildSQLSearch('description');
			$where .= ')';
			$this->UserInterfaceGrid->SetSearchQuery(
				'SELECT * FROM '.TABLE_PREFIX.'sp_packages WHERE '.$where,
				'SELECT count(1) totalitems FROM '.TABLE_PREFIX.'sp_packages WHERE '.$where);
		}
		$this->UserInterfaceGrid->SetQuery(
			'SELECT * FROM '.TABLE_PREFIX.'sp_packages WHERE '.$where,
			'SELECT COUNT(1) totalitems FROM '.TABLE_PREFIX.'sp_packages WHERE '.$where);

		$this->UserInterfaceGrid->Render();
		
		$this->DispatchMenu($dispmode);
		$this->UserInterfaceGrid->Display();
		
		return true;
	}
	
	public function RenderPkgEditor($Package, $errormessage = "") {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		
		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_am_manpkg'), 1,
			$SPFunctions->findAdminBar("SupportPay"));
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Update', SWIFT_UserInterface::MODE_INSERT, 
			false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_am_manpkg'), 'icon_form.gif', 'general', true);

		$_TabObject->Info("SupportPay",$SPFunctions->getPaymentWarnings());
		$_TabObject->Error("SupportPay",$SPFunctions->checkLicense());
		$_TabObject->Error("SupportPay",$errormessage);

		if (is_array($Package)) {
			// _POST overrides passed-in values to Date.
			if (!empty($_POST["pkg_commence"]))
				$_POST["pkg_commence"] = date(SWIFT_Date::GetCalendarDateFormat(), $Package["pkg_commence"]);
			if (!empty($_POST["pkg_expire"]))
				$_POST["pkg_expire"] = date(SWIFT_Date::GetCalendarDateFormat(), $Package["pkg_expire"]);

			$_TabObject->Title($_SWIFT->Language->Get("settings"), 'doublearrows.gif');
			
			$lock_settings = false;
			
			if ($sp_license["allow_whmcs"]) {
				// Show a drop-down with all current WHMCS packages if the license allows,
				// and the admin isn't allowing automatic sync.
				$_optionsContainer = array();
				$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("no"), "value" => "",
					"selected" => (is_null($Package["migrated_id"]) || 
							!in_array($Package["migrated"],array(SP_MIGRATED_WHMCS, SP_MIGRATED_WHMCS_ADDON))));

				$pkgList = $SPFunctions->GetWHMCSPackages();
				
				if (is_array($pkgList)) {		
					if (count($pkgList) > 0) {
						foreach ($pkgList as $migType => &$migList) {
							foreach ($migList as $pkgId => &$pkgInfo) {
								$ddId = $migType.'/'.$pkgId;
								$_optionsContainer[] = array(
									"title" => $pkgInfo["name"], 
									"value" => $ddId, 
									"selected" => (($Package["migrated"].'/'.$Package["migrated_id"]) == $ddId)
									);
							}
						}
					}
				}
				
				$lock_settings = $_SWIFT->Settings->getKey("settings","sp_whmcs_packages");
				
				// Need to pass back both the migrated value and the foreign package id.
				$_TabObject->Select("migrated_pair",$_SWIFT->Language->Get("sp_pkg_whmcs"), $_SWIFT->Language->Get("sp_pkg_whmcs_d"),
					$_optionsContainer);
			}

			$_TabObject->Text("title",$_SWIFT->Language->Get("sp_pkg_title"),$_SWIFT->Language->Get("sp_pkg_title_d"), $Package["title"]);
			$_TabObject->TextArea("description", $_SWIFT->Language->Get("sp_pkg_descr"), $_SWIFT->Language->Get("sp_pkg_descr_d"), $Package["description"],60,5);
			$_TabObject->Text("img_url",$_SWIFT->Language->Get("sp_pkg_imgurl"),$_SWIFT->Language->Get("sp_pkg_imgurl_d"), $Package["img_url"], 70);
			
			$_TabObject->Description($_SWIFT->Language->Get("sp_pkg_validity"), "doublearrows.gif");
			$_TabObject->YesNo("enabled",$_SWIFT->Language->Get("sp_pkg_setenabled"),$_SWIFT->Language->Get("sp_pkg_setenabled_d"),
				$Package["enabled"]);

			$_TabObject->Date("pkg_commence", $_SWIFT->Language->Get("sp_pkg_start"), $_SWIFT->Language->Get("sp_pkg_start_d"), 
				date(SWIFT_Date::GetCalendarDateFormat(), $Package["pkg_commence"]),0,false,true);
			$_TabObject->Date("pkg_expire", $_SWIFT->Language->Get("sp_pkg_end"), $_SWIFT->Language->Get("sp_pkg_end_d"), 
				(empty($Package["pkg_expire"]) ? "" : date(SWIFT_Date::GetCalendarDateFormat(), $Package["pkg_expire"])),
				0,false,false);
			$_TabObject->Number("duration", $_SWIFT->Language->Get("sp_pkg_duration"), $_SWIFT->Language->Get("sp_pkg_duration_d"),$Package["duration"]);
			$_TabObject->Description($_SWIFT->Language->Get("sp_pkg_payload"), "doublearrows.gif");
			if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_MINUTES || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
				$_TabObject->Number("minutes", $SPFunctions->formatMTP("{Minutes}"), $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_pkg_inclmins")), $Package["minutes"]);
			} else {
				$_TabObject->Hidden("minutes", $Package["minutes"]);
			}
			if ($_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_TICKETS || $_SWIFT->Settings->getKey("settings","sp_accept") == SP_ACCEPT_BOTH) {
				$_TabObject->Number("tickets", $SPFunctions->formatMTP("{Tickets}"), $SPFunctions->formatMTP($_SWIFT->Language->Get("sp_pkg_incltkts")), $Package["tickets"]);
			} else {
				$_TabObject->Hidden("tickets", $Package["tickets"]);
			}
			$_TabObject->Description($_SWIFT->Language->Get("sp_pkg_price"), "doublearrows.gif");

			$_optionsContainer = array();
			$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("no"), "value" => 0,
				"selected" => ($Package["startup"] == 0));
			$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("sp_allusergroups"), "value" => -1,
				"selected" => ($Package["startup"] == -1));
			$Record = $_SWIFT->Database->Query("select usergroupid, title from ".TABLE_PREFIX."usergroups where grouptype = 1 order by title");
			while ($_SWIFT->Database->NextRecord()) {
				$_optionsContainer[] = array("title" => $_SWIFT->Database->Record["title"], "value" => $_SWIFT->Database->Record["usergroupid"], 
					"selected" => ($Package["startup"] == $_SWIFT->Database->Record["usergroupid"])
					);
			}
			$_TabObject->Select("startup",$_SWIFT->Language->Get("sp_pkg_startup"), $_SWIFT->Language->Get("sp_pkg_startup_d"),
				$_optionsContainer);
				
			$_TabObject->Number("price", $_SWIFT->Language->Get("sp_pkg_cost"), $_SWIFT->Language->Get("sp_pkg_cost_d"), $Package["price"]);

			$_optionsContainer = array();
			$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("week"), "value" => SP_RECUR_UNIT_WEEK, 
				"selected" => ($Package["recur_unit"] == SP_RECUR_UNIT_WEEK)
				);
			$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("month"), "value" => SP_RECUR_UNIT_MONTH, 
				"selected" => ($Package["recur_unit"] == SP_RECUR_UNIT_MONTH)
				);
			$_optionsContainer[] = array("title" => $_SWIFT->Language->Get("year"), "value" => SP_RECUR_UNIT_YEAR, 
				"selected" => ($Package["recur_unit"] == SP_RECUR_UNIT_YEAR)
				);

			$_TabObject->Number("recur_period", $_SWIFT->Language->Get("sp_recur_period"), $_SWIFT->Language->Get("sp_recur_period_d"),$Package["recur_period"]);
			$_TabObject->Select("recur_unit", $this->Language->Get('sp_recur_unit'), $this->Language->Get('sp_recur_unit_d'), $_optionsContainer);

			// Template-group associations
			$_templateGroupCache = $_SWIFT->Cache->Get('templategroupcache');
			$tgroupOptions = array();
			foreach ($_templateGroupCache as $_key => $_val) {
				$tgroupOptions[] = array("title" => $_val["title"], "value" => $_key,
					"selected" => in_array($_key, $Package["tgroups"]));
			}
			$_TabObject->SelectMultiple("tgroups", $this->Language->Get('sp_tgroup'), $this->Language->Get('sp_tgroup_d'), $tgroupOptions);
			
			$this->UserInterface->Toolbar->AddButton($this->Language->Get('update'), 'icon_check.gif');
			$this->UserInterface->Toolbar->AddButton($this->Language->Get('back'), 'icon_back.gif', '/supportpay/Packages/Main', SWIFT_UserInterfaceToolbar::LINK_VIEWPORT);
			$_TabObject->Hidden("pkgid", $Package["pkgid"]);
			$_TabObject->Hidden("migrated_id", $Package["migrated_id"]);
			$_TabObject->Hidden("migrated", $Package["migrated"]);
		}
		$this->UserInterface->End();
		$this->UserInterface->Footer();
		return true;
	}

	public function DispatchMenu($dispMode) {	
		$fullURL = 'loadViewportData("/supportpay/Packages/Main/';

		$actions = array($this->Language->Get('sp_oall') => $fullURL . '1");',
			$this->Language->Get('sp_pkg_enabled') => $fullURL . '2");',
			$this->Language->Get('sp_pkg_disabled') => $fullURL . '3");'
			);
			
		echo '<ul class="swiftdropdown" id="grid_actionmenu">';
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
