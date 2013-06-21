<?php

class Controller_PurchasePage extends Controller_client
{
	private $SWIFT4;
	
	public function __construct()
	{
		$this->SWIFT4 = SWIFT::GetInstance();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		global $SPUserFuncs;
		SWIFT_Loader::LoadLibrary('SupportPay:SPClientFunctions', "supportpay");
		$SPUserFuncs->ResumeSession(4);

		parent::__construct();
		
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	private function TemplateGlobals($unitPrice) {
		global $sp_currencylist,$SPFunctions;

		$discount = $this->getDiscount();
		$this->SWIFT4->Template->Assign("discount",$discount);
		$this->SWIFT4->Template->Assign("surcharge",max(0,-$discount));
		$discount = 1 - min(max(-100,$discount / 100),100);
		
		$unitPrice = round($unitPrice * $discount,2);
		$fullitemcost = max(0.01,$SPFunctions->getFinalTaxPrice($unitPrice));
		$this->SWIFT4->Template->Assign("unitprice",$fullitemcost);
		
		$this->SWIFT4->Template->Assign("unittax",$SPFunctions->getTaxOnPrice($unitPrice));
		$this->SWIFT4->Template->Assign("currency",$sp_currencylist[$this->SWIFT4->Settings->getKey("settings","sp_currency")]["symbol"]);
		$this->SWIFT4->Template->Assign("minsale",floatval($this->SWIFT4->Settings->getKey("settings","sp_minsale")));
	}
		
	public function Mins($itemcount=null)
	{
		return $this->ItemPage($itemcount,"minutes","min");
	}

	public function Tkts($itemcount=null)
	{
		return $this->ItemPage($itemcount,"tickets","tkt");
	}

	private function ItemPage($itemcount, $itemName, $itemAbbrev) {
		global $SPFunctions, $sp_license, $sp_currencylist;

		$SPFunctions->QuitIfGuest('sp_uw_master');

		if (!$SPFunctions->checkPaymentMethod() || $sp_license["status"] != SP_LICENSE_GOOD || intval($sp_license["death"]) <= time()) {
			SWIFT::Error("SupportPay","We are unable to process payments at the moment. Apologies for the inconvenience. ".
				"Please contact <strong><a href='mailto:" . $this->SWIFT4->Settings->getKey("settings","general_returnemail") . "'>support</a></strong> for more information.");
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
			exit;
		} else {
			SWIFT::Info("SupportPay",$SPFunctions->getPaymentWarnings());
			
			$this->TemplateGlobals($this->SWIFT4->Settings->getKey("settings","sp_costper".$itemAbbrev));
			
			$_buytype = $this->SWIFT4->Language->Get("sp_buymessage")." ".
				$this->SWIFT4->Settings->getKey("settings","sp_".$itemName."txt");
			$this->SWIFT4->Template->Assign("thingLower",$SPFunctions->formatMTP("{".$itemName."}"));
			$this->SWIFT4->Template->Assign("thingCaps",$SPFunctions->formatMTP("{".ucfirst($itemName)."}"));
			$this->SWIFT4->Template->Assign("thingMin",$this->SWIFT4->Settings->getKey("settings","sp_min".$itemAbbrev));
			if (empty($itemcount)) $itemcount = $this->SWIFT4->Settings->getKey("settings","sp_min".$itemAbbrev);
			$this->SWIFT4->Template->Assign("defItemCount",$itemcount);
			
			$this->SWIFT4->Template->Assign("saletype",$itemName);
		}

		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($_buytype);
		$this->SWIFT4->Template->Render("sp_header");
		$this->SWIFT4->Template->Render("sp_purchase");
		$this->SWIFT4->Template->Render("sp_footer");
		$this->UserInterface->Footer();
		
		return true;
	}

	private function getDiscount() {
		$discount = 0;
		$Rec = $this->SWIFT4->Database->QueryFetch("select discount from ".TABLE_PREFIX.
			"sp_users where userid = ".$this->SWIFT4->User->GetUserID());
		if (!empty($Rec["discount"])) $discount = $Rec["discount"];
		
		return $discount;
	}
	
	// Initial payment processing
	public function Item() {
		global $SPFunctions, $SPUserFuncs, $sp_license, $sp_currencylist;

		$itemDesc = "";
		
		// Clear the item list so we don't get duplicates if the form's reposted.
		unset($_SESSION["paytype"]);
		unset($_SESSION["SP_SessionID"]);
		unset($_SESSION["items"]);
		unset($_SESSION["thing"]);
		unset($_SESSION["Payment_Amount"]);
		unset($_SESSION["pkgid"]);
		unset($_SESSION["paymentType"]);
		unset($_SESSION["is_recur"]);
		
		$itemList = array();
		$discount = $this->getDiscount();
		$discount = 1 - min(max(-100,$discount / 100),100);

		if (!isset($_POST["action"])) {
			SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_unknown_payment_type'));
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
			return;
		}
		
		if (!empty($_SESSION["is_recur"])) {
			$_POST["saletype"] = "recur";
			unset($_SESSION["is_recur"]);
		} else {
			if (isset($_POST["saletype"])) {
				switch ($_POST["saletype"]) {
					case "sale":
						$_POST["saletype"] = "Sale";
						break;
					case "auth":
						if (!$this->SWIFT4->Settings->getKey("settings","sp_authbuyenable")) {
							SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_no_preauth'));
							unset($_POST["saletype"]);
						} else {
							$_POST["saletype"] = "Authorization";
						}
						break;
					default:
						SWIFT::Error("SupportPay","Unknown sale type '".htmlspecialchars($_POST["saletype"])."'");
						unset($_POST["saletype"]);
				}
			}
			
			if (!isset($_POST["saletype"])) {
				$_POST["saletype"] = "Sale";
			}
		}
		
		// Filter out fractional values.
		if (empty($_POST["thing"])) {
			if (!empty($_POST["pkgcnt"])) {
				$pkgDetail = array();
				$taxAmount = 0;
				$paymentAmount = 0;
				$_REQUEST["thing"] = "packages";
				
				if (is_array($_POST["pkgcnt"])) {
					$this->SWIFT4->Database->Query("select * from ".TABLE_PREFIX."sp_packages ".
						"where pkgid in (".buildIN(array_keys($_POST["pkgcnt"])).")");
					while ($this->SWIFT4->Database->NextRecord()) {
						$pkgDetail[$this->SWIFT4->Database->Record["pkgid"]] = $this->SWIFT4->Database->Record;
					}
					
					foreach ($_POST["pkgcnt"] as $pkgid => $cnt) {
						$cnt = intval($cnt);

						if (is_array($pkgDetail[$pkgid])) {
							if ($cnt > 0) {
								if ($pkgDetail[$pkgid]["price"] != 0) {
									$itemcost = max(0.01,$SPFunctions->roundDown($pkgDetail[$pkgid]["price"] * $discount));
								} else {
									$itemcost = 0;
								}
								
								$itemTaxAmount = $SPFunctions->getTaxOnPrice($itemcost);
								$itemPaymentAmount = number_format(round($SPFunctions->getPreTaxPrice($itemcost),2) * $cnt,2,'.','');
								
								$paymentAmount += $itemPaymentAmount;
								$taxAmount += ($itemTaxAmount * $cnt);

								$itemList[] = array ( "name" => $pkgDetail[$pkgid]["title"],
									"desc" => $pkgDetail[$pkgid]["description"], "rowcost" => $itemPaymentAmount, 
									"tax" => $itemTaxAmount, "itemtype" => $_REQUEST["thing"],
									"minutes" => $pkgDetail[$pkgid]["minutes"] * $cnt,
									"tickets" => $pkgDetail[$pkgid]["tickets"] * $cnt,
									"pkgid" => $pkgid, "itemcount" => $cnt,
									"recur_period" => $pkgDetail[$pkgid]["recur_period"],
									"recur_unit" => $pkgDetail[$pkgid]["recur_unit"],
									"currency" => $this->SWIFT4->Settings->getKey("settings","sp_currency"),
									"cost" => sprintf("%0.2f",$SPFunctions->getPreTaxPrice($itemcost)));
							}
						}
					}
				}
			}
			
			if (empty($itemList)) {
				SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_noitemsselected'));
			}
		} else {
			if ($_POST["thing"] == "minutes") {
				$unitPrice = floatval($this->SWIFT4->Settings->getKey("settings","sp_costpermin"));
			} elseif ($_POST["thing"] == "tickets") {
				$unitPrice = floatval($this->SWIFT4->Settings->getKey("settings","sp_costpertkt"));
			} else {
				$unitPrice = 0;
			}
			
			$itemcost = max(0.01,round($unitPrice * $discount,2));
			$_POST["itemcount"] = intval($_POST["itemcount"]);
			$_REQUEST["itemcount"] = intval($_REQUEST["itemcount"]);

			$paymentAmount = number_format(round($SPFunctions->getPreTaxPrice($itemcost),2) * $_POST["itemcount"],2,'.','');
			$taxAmount = $SPFunctions->getTaxOnPrice($itemcost);
			$itemList[] = array ( "name" => $this->SWIFT4->Settings->getKey("settings","general_companyname")." ".
				(($_REQUEST["thing"] == "minutes") ? $this->SWIFT4->Settings->getKey("settings","sp_minutestxt") : $this->SWIFT4->Settings->getKey("settings","sp_ticketstxt")),
				"desc" => "", "rowcost" => $paymentAmount, "tax" => $taxAmount, "itemtype" => $_REQUEST["thing"],
				"minutes" => ($_REQUEST["thing"] == "minutes") ? $_POST["itemcount"] : 0,
				"tickets" => ($_REQUEST["thing"] == "tickets") ? $_POST["itemcount"] : 0,
				"pkgid" => null, "itemcount" => $_POST["itemcount"],
				"recur_period" => false, "recur_unit" => false,
				"currency" => $this->SWIFT4->Settings->getKey("settings","sp_currency"),
				"cost" => sprintf("%0.2f",$SPFunctions->getPreTaxPrice($itemcost)));
			
			$taxAmount *= $_REQUEST["itemcount"];
		}
		
		if (!empty($itemList) && $paymentAmount > 0) {
			// Do some validation on the amounts.
			if ($SPFunctions->getFinalTaxPrice($paymentAmount) < $this->SWIFT4->Settings->getKey("settings","sp_minsale")) {
				SWIFT::Error("SupportPay","There is a minimum sale value of ".$sp_currencylist[$this->SWIFT4->Settings->getKey("settings","sp_currency")]["symbol"].
					sprintf("%0.2f",$this->SWIFT4->Settings->getKey("settings","sp_minsale")).".");
			} elseif ($_REQUEST["thing"] == "tickets" && $_REQUEST["itemcount"] < intval($this->SWIFT4->Settings->getKey("settings","sp_mintkt"))) {
				SWIFT::Error("SupportPay","You must buy at least ".intval($this->SWIFT4->Settings->getKey("settings","sp_mintkt"))." ".
					strtolower((intval($this->SWIFT4->Settings->getKey("settings","sp_mintkt")) == 1 ? $this->SWIFT4->Settings->getKey("settings","sp_tickettxt") : $this->SWIFT4->Settings->getKey("settings","sp_ticketstxt"))));
			} elseif ($_REQUEST["thing"] == "minutes" && $_REQUEST["itemcount"] < intval($this->SWIFT4->Settings->getKey("settings","sp_minmin"))) {
				SWIFT::Error("SupportPay","You must buy at least ".intval($this->SWIFT4->Settings->getKey("settings","sp_minmin"))." ".
					strtolower((intval($this->SWIFT4->Settings->getKey("settings","sp_minmin")) == 1 ? $this->SWIFT4->Settings->getKey("settings","sp_minutetxt") : $this->SWIFT4->Settings->getKey("settings","sp_minutestxt"))));
			} else {
				// Looks good, let's extract some money.
				$_SESSION["paytype"] = $_POST["action"];
				$_SESSION["items"] = $itemList;
				$_SESSION["thing"] = $_REQUEST["thing"];
				$_SESSION["Payment_Amount"] = $paymentAmount;
				$_SESSION["Tax_Amount"] = $taxAmount;
				$_SESSION["paymentType"] = $_POST["saletype"];
				$SPUserFuncs->StoreSession();
				
				return $this->ProcPurchase();
			}
			
			// Else it's gone wrong. Return to the original page.
			if ($_POST["thing"] == "minutes") {
				$this->Mins($_POST["itemcount"]);
			} elseif ($_POST["thing"] == "tickets") {
				$this->Tkts($_POST["itemcount"]);
			} else {
				if ($_POST["saletype"] == "recur") {
					$this->Pkg($_POST["pkgcnt"]);
				} else {
					$this->Recur($_POST["pkgcnt"]);
				}
			}
			return;
		} else {
			SWIFT::Error("SupportPay","Nothing to pay for!");
		}
		
		// Still here? Must have been an error. Display it.
		$this->UserInterface->Header('sp_uw_master');
		$this->UserInterface->Footer();
	}
	
	// Why not do this as a simple inline function instead of a redirect?
	public function ProcPurchase() {
		global $SPFunctions;
		$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
		$gwPath = null;
		
		switch ($gateway) {
			case SP_PROCESSOR_WORLDPAY:
				$gwPath = "worldpay";
				break;
			case SP_PROCESSOR_PAYPAL:
				$gwPath = "paypal";
				break;
			case SP_PROCESSOR_AUTHORIZE:
				$gwPath = "authnet";
				break;
		}
		
		if (!empty($gwPath)) {
			/* Create the semi-permanent cart record. */
			
			if (!empty($_SESSION["items"])) {
				
				$_SESSION["cart_id"] = $SPFunctions->encodeCartData(
					$this->SWIFT4->User->GetUserID(),$_SESSION["items"],$gateway);

				include (SWIFT_MODULESDIRECTORY."/supportpay/client/".$gwPath."/procpurchase.php");
			} else {
				SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_noitemsselected'));
				$this->UserInterface->Header('sp_uw_master');
				$this->UserInterface->Footer();
			}
		} else {
			SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_unknown_payment_type'));
			$this->UserInterface->Header('sp_uw_master');
			$this->UserInterface->Footer();
		}
	}
	
	public function Cancel() {
		if (empty($this->SWIFT4->User)) {
			throw new SWIFT_Exception('Unable to load visitor session');
			return false;
		}

		if (isset($_SESSION["cart_id"])) {
			global $SPFunctions;
			$gateway = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
			$SPFunctions->deleteCartData($this->SWIFT4->User->GetUserID(),$_SESSION["cart_id"],$gateway);
			unset($_SESSION["cart_id"]);
		}

		switch ($_SESSION["thing"]) {
			case "minutes":
				return $this->Mins();
				break;
			case "tickets":
				return $this->Tkts();
				break;
		}
		
		return true;
	}
	
	private function PkgList($selPkgs, $doRecur) {
		global $SPFunctions, $SPUserFuncs, $sp_license, $sp_currencylist;

		// Clear the item list so we don't get duplicates if the form's reposted.
		unset($_SESSION["SP_SessionID"]);
		unset($_SESSION["items"]);
		$_SESSION["is_recur"] = $doRecur;
		
		$discount = $this->getDiscount();
		$discount = 1 - min(max(-100,$discount / 100),100);

		SWIFT::Info("SupportPay",$SPFunctions->getPaymentWarnings());

		if ($this->SWIFT4->Settings->getKey("settings","sp_usepackages")) {
			if ($doRecur) {
				$_buytype = $this->SWIFT4->Language->Get("sp_addaggmessage")." ".ucwords($this->SWIFT4->Settings->getKey("settings","sp_agreementstxt"));
			} else {
				$_buytype = $this->SWIFT4->Language->Get("sp_buymessage")." ".ucwords($this->SWIFT4->Settings->getKey("settings","sp_packagestxt"));
			}
			$this->SWIFT4->Template->Assign("currency",$sp_currencylist[$this->SWIFT4->Settings->getKey("settings","sp_currency")]["symbol"]);
			$this->SWIFT4->Template->Assign("minsale",floatval($this->SWIFT4->Settings->getKey("settings","sp_minsale")));

			if ($this->SWIFT4->Database->Query("SELECT p.pkgid,title,description,pkg_expire,img_url,minutes,tickets,price ".
				"FROM ".TABLE_PREFIX."sp_packages p, ".TABLE_PREFIX."sp_package_tgroups g ".
				" WHERE g.pkgid = p.pkgid and g.tgroupid = ".$this->SWIFT4->TemplateGroup->GetTemplateGroupID().
				" and (pkg_expire is null or pkg_expire > ".time().
				") AND enabled = 1 AND startup = 0 and recur_period is ".($doRecur ? "not":"")." null"))
			{
				while ($this->SWIFT4->Database->NextRecord()) {
					$packages[] = array(
						"pkgid" => $this->SWIFT4->Database->Record["pkgid"],
						"img_url" => str_replace("'","&apos;",$this->SWIFT4->Database->Record["img_url"]),
						"title" => str_replace("'","&apos;",$this->SWIFT4->Database->Record["title"]),
						"description" => str_replace("'","&apos;",$this->SWIFT4->Database->Record["description"]),
						"price" => sprintf("%0.2f",max(0.01,$SPFunctions->getFinalTaxPrice($SPFunctions->roundDown($this->SWIFT4->Database->Record["price"] * $discount)))),
						"selected" => intval($selPkgs[$this->SWIFT4->Database->Record["pkgid"]]),
						);
				}
				
				if (empty($packages)) {
					SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_nopackagesdefined'));
				}
			} else {
				error_log("DB error getting packages : " . $this->SWIFT4->Database->FetchLastError());
				SWIFT::Error("SupportPay","Something went wrong with getting the package list.");
			}
		} else {
			SWIFT::Error("SupportPay",'Packages are not allowed');
		}
		
		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($_buytype);
		$this->SWIFT4->Template->Render("sp_header");
		if (!empty($packages)) {
			$this->SWIFT4->Template->Assign("packagelist",$packages);
			$this->SWIFT4->Template->Render("sp_purchase_pkg");
		}
		$this->SWIFT4->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}
	
	public function Recur($selPkgs = null) {
		return $this->PkgList($selPkgs, true);
	}
	
	public function Pkg($selPkgs = null) {
		return $this->PkgList($selPkgs, false);
	}

};

?>
