<?php

class Controller_ReviewOrder extends Controller_client
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
	
	public function Index()
	{
		if (empty($this->SWIFT4->User))
		{
			SWIFT::Error("SupportPay", 'Unable to load visitor session');
			$this->SWIFT4->Template->Render("header");
			$this->SWIFT4->Template->Render("footer");
			return true;
		}
		
		// Check to see if the Request object contains a variable named 'token'	
		global $sp_currencylist, $SPFunctions;
		$Sandbox = $this->SWIFT4->Settings->getKey("settings","sp_gateway");
		if (empty($Sandbox)) {
			SWIFT::Info("SupportPay","<span style='font-size: large; color: Red; text-decoration: blink; '>".
				$this->SWIFT4->Language->Get("sp_sandbox")."</span>");
		}
		
		if (is_array($_REQUEST) && !empty($_REQUEST["token"]) && isset($_SESSION["cart_id"])) {
			SWIFT_Loader::LoadLibrary('SupportPay:SPPayPal', "supportpay");
			$PP = new SWIFT_SPPayPal;
			$_SESSION["token"] = $_REQUEST["token"];
			
			// TODO: Handle the case where this isn't found.
			$cartData = $SPFunctions->retrieveCartData(
				$this->SWIFT4->User->GetUserID(), $_SESSION["cart_id"], SP_PROCESSOR_PAYPAL);
			
			$curtxt = $_SESSION['currencyCodeType'];
			if (!empty($sp_currencylist[$curtxt]))
				$curtxt = $sp_currencylist[$curtxt]["symbol"];
			else
				$curtxt = "???";
			
			if ($_REQUEST["token"] == "harnessbad") {
				$resArray = array("ACK" => "FAILURE",
					"L_SHORTMESSAGE0" => "Test Harness - Failure",
					"L_LONGMESSAGE0" => "Description of Test Failure");
			} elseif ($_REQUEST["token"] == "harnessgood") {
				$resArray = array("ACK" => "SUCCESS", "L_SHORTMESSAGE0" => "Test Harness - Success",
					"EMAIL" => "bob@home.com", "FIRSTNAME" => "Bob", "LASTNAME" => "Hope");
			} else {
				$resArray = $PP->GetShippingDetails( $_REQUEST["token"] );
			}
			
			$resArray["ACK"] = strtoupper($resArray["ACK"]);
			if ($resArray["ACK"] != "SUCCESS") {
				SWIFT::Error("SupportPay",$resArray["L_SHORTMESSAGE0"]);
			} else {
				$_SESSION["PayerName"] = $resArray["FIRSTNAME"]." ".$resArray["LASTNAME"];
			}

			$cost = 0; $tax = 0;
			foreach ($cartData as &$lineitem) {
				$cost += $lineitem["cost"] * $lineitem["itemcount"];
				$tax += $lineitem["tax"] * $lineitem["itemcount"];
				$lineitem["cost"] = sprintf("%s%0.2f",$curtxt,$lineitem["cost"]);
				$lineitem["tax"] = sprintf("%s%0.2f",$curtxt,$lineitem["tax"]);
				$lineitem["rowcost"] = sprintf("%s%0.2f",$curtxt,$lineitem["rowcost"]);
			}
			$this->SWIFT4->Template->Assign("paydetails",$resArray);
			$this->SWIFT4->Template->Assign("lineitems",$cartData);
			$this->SWIFT4->Template->Assign("payamount",sprintf("%s%0.2f",$curtxt,$cost));
			$this->SWIFT4->Template->Assign("taxamount",sprintf("%s%0.2f",$curtxt,$tax));
			$this->SWIFT4->Template->Assign("fullamount",sprintf("%s%0.2f",$curtxt,$cost+$tax));

			// Generic template elemtns
			$this->SWIFT4->Template->Assign("navigation", '<a href="'.SWIFT::Get('basename').'" id="navlink">'.
				$this->SWIFT4->Language->Get("navhome").'</a> &raquo; <a href="'.SWIFT::Get('basename').'_m=tickets&_a=viewlist" id="navlink">'.
				$this->SWIFT4->Language->Get("tticketlist")."</a>");
			$this->SWIFT4->Template->Assign("sectiontitle", $this->SWIFT4->Language->Get("tticketlist"));
			$this->SWIFT4->Template->Assign("sectiondesc", $this->SWIFT4->Language->Get("desc_tticketlist"));
		} else {
			SWIFT::Error("SupportPay",$this->SWIFT4->Language->Get('sp_pp_notoken'));
			$resArray["ACK"] = "FAILURE";
			$resArray["L_SHORTMESSAGE0"] = "No Token";
			$resArray["L_LONGMESSAGE0"] = "No transaction token was returned from the payment provider.";
			$this->SWIFT4->Template->Assign("paydetails",$resArray);
		}

		$this->UserInterface->Header('sp_uw_master');
		$SPFunctions->assignSectionTitle($this->SWIFT4->Language->Get("sp_payreview"));
		$this->SWIFT4->Template->Render("sp_header");
		$this->SWIFT4->Template->Render("sp_reviewpay");
		$this->SWIFT4->Template->Render("sp_footer");
		$this->UserInterface->Footer();
		
		return true;
	}
};

?>
