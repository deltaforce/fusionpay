<?php

class Controller_Packages extends Controller_admin
{
	public function __construct() {
		parent::__construct();
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function Update() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;

		$SPFunctions->checkLicense(true);
		
		$errormessage = "";
		
		if (!empty($_POST["pkgid"])) $pkgid = intval($_POST["pkgid"]); else $pkgid = 'null';
		$minutes = intval($_POST["minutes"]);
		$tickets = intval($_POST["tickets"]);
		if ($minutes < 0) $errormessage .= $SPFunctions->formatMTP("{Minutes} must be >= 0. ");
		if ($tickets < 0) $errormessage .= $SPFunctions->formatMTP("{Tickets} must be >= 0. ");
		if ($tickets <= 0 && $minutes <= 0) $errormessage .= $_SWIFT->Language->Get("sp_pkg_nopayload")." ";
		if ($_POST["title"] == "") $errormessage .= $_SWIFT->Language->Get("sp_pkg_notitle")." ";
		if ($_POST["description"] == "") $errormessage .= $_SWIFT->Language->Get("sp_pkg_nodesc")." ";
		
		if (!empty($_POST["pkg_commence"])) {
			$_POST["pkg_commence"] = $SPFunctions->TimestampFromDate($_POST["pkg_commence"]);

			if (!empty($_POST["pkg_expire"])) {
				$_POST["pkg_expire"] = $SPFunctions->TimestampFromDate($_POST["pkg_expire"]);
				if ($_POST["pkg_expire"] == 0) {
					$errormessage .= $_SWIFT->Language->Get("sp_pkg_noend")." ";
				} else {
					if ($_POST["pkg_expire"] <= $_POST["pkg_commence"]) {
						$errormessage .= $_SWIFT->Language->Get("sp_pkg_badend")." ";
					}
				}
			}
		} else {
			$errormessage .= $_SWIFT->Language->Get("sp_pkg_nostart")." ";
		}
		$duration = intval($_POST["duration"]);
		
		$startup = intval($_POST["startup"]);
		if ($startup != 0) {
			// Startup packages aren't charged.
			$pkgcost = 0;
		} else {
			$pkgcost = floatval($_POST["price"]);
			if ($pkgcost <= 0) $errormessage .= $_SWIFT->Language->Get("sp_pkg_badprice")." ";
		}
		
		if (empty($_POST["pkg_commence"])) {
			$_POST["pkg_commence"] = null;
		}
		if (empty($_POST["pkg_expire"])) {
			$_POST["pkg_expire"] = null;
		}
		
		$migrated_source = null; $migrated_id = null;
		if ($sp_license["allow_whmcs"] && !$_SWIFT->Settings->getKey("settings","sp_whmcs_packages")) {
			if (isset($_POST["migrated_pair"])) {
				if (!empty($_POST["migrated_pair"])) {
					$tmp = split("\/",$_POST["migrated_pair"]);
					if (count($tmp) == 2) {
						$migrated_source = $tmp[0];
						$migrated_id = $tmp[1];
					}
				} else {
					$migrated_id = $migrated_source = "null";
				}
			}
		} else {
			// Don't modify any migration-related settings.
		}
		
		if ($errormessage == "") {
			// Do the insert.
			
			$recur_period = intval($_POST["recur_period"]);
			$recur_unit = intval($_POST["recur_unit"]);
			if (empty($recur_period) || empty($recur_unit)) {
				$recur_period = null;
				$recur_unit = null;
			}
			
			if (!empty($_POST["pkgid"])) {
				$sql = "update ".TABLE_PREFIX."sp_packages set title='".$_SWIFT->Database->Escape($_POST["title"])."',".
					"description='".$_SWIFT->Database->Escape($_POST["description"])."',".
					"img_url='".$_SWIFT->Database->Escape($_POST["img_url"])."',".
					"pkg_commence=".$SPFunctions->nvl($_POST["pkg_commence"],"null").",".
					"pkg_expire=".$SPFunctions->nvl($_POST["pkg_expire"],"null").",".
					"duration=".$SPFunctions->nvl($duration,"null").",".
					"enabled=".intval($_POST["enabled"]).",".
					"minutes=".$minutes.",tickets=".$tickets.",startup=".$startup.",price=".$pkgcost.
					",recur_unit=".$SPFunctions->nvl($recur_unit,"null").", recur_period=".$SPFunctions->nvl($recur_period,"null").
					" where pkgid=".intval($_POST["pkgid"]); 
			} else {
				$sql = "insert into ".TABLE_PREFIX."sp_packages (pkgid,title,description,img_url,pkg_commence,pkg_expire,".
					"duration,minutes,tickets,startup,enabled,price,recur_unit,recur_period) VALUES (".
					$pkgid.",'".$_SWIFT->Database->Escape($_POST["title"])."','".$_SWIFT->Database->Escape($_POST["description"])."',".
					"'".$_SWIFT->Database->Escape($_POST["img_url"])."',".
					$SPFunctions->nvl($_POST["pkg_commence"],"null").",".
					$SPFunctions->nvl($_POST["pkg_expire"],"null").",".
					$SPFunctions->nvl($duration,"null").",".$minutes.",".$tickets.",".
					$startup.",".intval($_POST["enabled"]).",".$pkgcost.",".
					$SPFunctions->nvl($recur_unit,"null").",".
					$SPFunctions->nvl($recur_period,"null").")";
			}

			$_SWIFT->Database->Execute($sql);
			$errormessage = $_SWIFT->Database->FetchLastError();
			if (!empty($errormessage)) {				
				return $this->View->RenderPkgEditor($_POST, $errormessage);
			} else {
				// Now the template-group assigns.
				$_SWIFT->Database->Execute("delete from ".TABLE_PREFIX."sp_package_tgroups ".
					"where pkgid = ".intval($pkgid));
				
				if (isset($_POST["tgroups"]) && is_array($_POST["tgroups"])) {
					foreach ($_POST["tgroups"] as $gid) {
						$_SWIFT->Database->Execute("insert into ".TABLE_PREFIX."sp_package_tgroups (pkgid, tgroupid) ".
							"values (".intval($pkgid).", ".$gid.")");
					}
				}

				if (!is_null($migrated_id) && !is_null($migrated_source)) {
					$sql = "update ".TABLE_PREFIX."sp_packages set ".
					    "migrated = ".$migrated_id.", migrated_id = ".$migrated_id.
						" where pkgid=".intval($_POST["pkgid"]);
					$_SWIFT->Database->Execute($sql);
				}

				return $this->Main();
			}
		} else {
			// Pass the error back to the edit procedure and re-populate.
			return $this->View->RenderPkgEditor($_POST, $errormessage);
		}

		return $this->Main();
	}

	static public function DeleteList($pkgId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($pkgId)) {
			global $SPFunctions;
			foreach ($pkgId as $pkg) {
				$SPFunctions->DeletePackage($pkg);
			}

			return true;
		}
		
		return false;
	}

	static public function CloneList($pkgId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($pkgId)) {
			// Don't copy MIGRATED settings.
			$sql = "insert into ".TABLE_PREFIX."sp_packages (".
				"title,description,img_url,pkg_commence,pkg_expire,duration,minutes,tickets,price,enabled,startup,recur_period,recur_unit ".
				") select ".
				"title,description,img_url,pkg_commence,pkg_expire,duration,minutes,tickets,price,enabled,startup,recur_period,recur_unit ".
				" from ".TABLE_PREFIX."sp_packages WHERE pkgid in (".BuildIN(array_values($pkgId)).")";

			$_SWIFT->Database->Execute($sql);

			return true;
		}
		
		return false;
	}

	public function Enable($pkgId,$status) {
		$_SWIFT = SWIFT::GetInstance();
		
		$sql = "UPDATE ".TABLE_PREFIX."sp_packages SET enabled = ".($status == 0 ? 0 : 1)." WHERE pkgid = ".$pkgId;
		$_SWIFT->Database->Query($sql);
				
		// Fall through to Main
		return $this->Main();
	}

	public function EditPackage($pkgId = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		$SPFunctions->checkLicense();
		
		if (!is_null($pkgId)) {
			// Need to populate the boxes.
			$Package = $_SWIFT->Database->QueryFetch("select * from ".TABLE_PREFIX."sp_packages WHERE pkgid=".
				intval($pkgId));
			if (!empty($Package)) {
				if (!is_null($Package["recur_period"])) {
					// This is a recurring package. Check that nobody's using it.
					$Record = $_SWIFT->Database->QueryFetch("select count(1) cnt from ".TABLE_PREFIX."sp_cart_items i, ".
						TABLE_PREFIX."sp_cart_defs d where i.cid = d.cid and d.ctype = ".SP_CTYPE_RECURRING.
						" and i.pkgid = ".$pkgId);
					if ($Record["cnt"] > 0) {
						SWIFT::Error("SupportPay",$_SWIFT->Language->Get("sp_package_in_use"));
						return $this->Main();
					}
				}
				$Package["duration"] = !empty($Package["duration"]) ? $Package["duration"] : 0;

				$Package["tgroups"] = array();
				$_SWIFT->Database->Query("select tgroupid from ".TABLE_PREFIX."sp_package_tgroups ".
					"where pkgid = ".intval($pkgId));
				while ($_SWIFT->Database->NextRecord()) {
					$Package["tgroups"][] = $_SWIFT->Database->Record["tgroupid"];
				}
			} else {
				SWIFT::Error("SupportPay","Unable to find that package.");
			}
		} else {
			// New display. Set some defaults.
			$Package = array("pkgid" => null, "minutes" => 0, "tickets" => 0, "duration" => 0, "price" => 5.0, 
				"pkg_commence" => time(), "pkg_expire" => null, "img_url" => null, "startup" => 0, "enabled" => true,
				"title" => $this->View->Language->Get('sp_pkg_title'), "recur_unit" => null, "recur_period" => null,
				"migrated" => null, "migrated_id" => null, "tgroups" => array(),
				"description" => $this->View->Language->Get('sp_pkg_descr'));
			
			$_templateGroupCache = $_SWIFT->Cache->Get('templategroupcache');
			foreach ($_templateGroupCache as $key => &$val) {
				$Package["tgroups"][] = $key;
			}
		}

		$this->View->RenderPkgEditor($Package,"");
		
		return true;
	}

	public function Main($dispMode = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		// This needed because some callbacks use the previously-used screen. Causes problems
		// if you delete a package immediately after adding one.
		$this->Router->SetAction("Main");

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_am_manpkg'), 1,
			$SPFunctions->findAdminBar("SupportPay"));

		$SPFunctions->checkLicense(!$SPFunctions->isInitialGrid());

		$this->View->RenderGrid($dispMode);
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
