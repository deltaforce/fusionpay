<?php

SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

class View_ShowLicense extends SWIFT_View
{
	private $_SWIFT;
	
	public function __construct() {
		parent::__construct();
		
		$this->_SWIFT = SWIFT::GetInstance();
		
		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}
	
	public function RenderAgreement()
	{
		$className = str_replace("View_","",get_class($this));

		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Agree', SWIFT_UserInterface::MODE_INSERT, false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_ptlicmaint'), 'icon_form.gif', 'general', true);
		$_TabObject->Title($this->_SWIFT->Language->Get("sp_agree_details"));

		$this->UserInterface->Toolbar->AddButton($this->Language->Get('sp_i_agree'), 'icon_check.gif',
			'/supportpay/ShowLicense/Agree', SWIFT_UserInterfaceToolbar::LINK_SUBMITCONFIRM);

		$lictext = "Software License Agreement

1. This is an agreement between Licensor and Licensee, who is being licensed to use the named Software.

2. Licensee acknowledges that this is only a limited nonexclusive license. Licensor is and remains the owner of all titles, rights, and interests in the Software.

3. This License permits Licensee to install the Software on more than one computer system, as long as the Software will not be used on more than one computer system simultaneously. Licensee will not make copies of the Software or allow copies of the Software to be made by others, unless authorized by this License Agreement. Licensee may make copies of the Software for backup purposes only.

4. This Software is subject to a limited warranty. Licensor warrants to Licensee that the physical medium on which this Software is distributed is free from defects in materials and workmanship under normal use, the Software will perform according to its printed documentation, and to the best of Licensor's knowledge Licensee's use of this Software according to the printed documentation is not an infringement of any third party's intellectual property rights. This limited warranty lasts for a period of 14 days after delivery. To the extent permitted by law, THE ABOVE-STATED LIMITED WARRANTY REPLACES ALL OTHER WARRANTIES, EXPRESS OR IMPLIED, AND LICENSOR DISCLAIMS ALL IMPLIED WARRANTIES INCLUDING ANY IMPLIED WARRANTY OF TITLE, MERCHANTABILITY, NONINFRINGEMENT, OR OF FITNESS FOR A PARTICULAR PURPOSE. No agent of Licensor is authorized to make any other warranties or to modify this limited warranty. Any action for breach of this limited warranty must be commenced within one year of the expiration of the warranty. Because some jurisdictions do not allow any limit on the length of an implied warranty, the above limitation may not apply to this Licensee. If the law does not allow disclaimer of implied warranties, then any implied warranty is limited to 14 days after delivery of the Software to Licensee. Licensee has specific legal rights pursuant to this warranty and, depending on Licensee's jurisdiction, may have additional rights.

5. In case of a breach of the Limited Warranty, Licensee's exclusive remedy is as follows: Licensee will return all copies of the Software to Licensor, at Licensee's cost, along with proof of purchase. (Licensee can obtain a step-by-step explanation of this procedure, including a return authorization code, by contacting Licensor at [address and toll free telephone number].) At Licensor's option, Licensor will either send Licensee a replacement copy of the Software, at Licensor's expense, or issue a full refund.

6. Notwithstanding the foregoing, LICENSOR IS NOT LIABLE TO LICENSEE FOR ANY DAMAGES, INCLUDING COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL DAMAGES, CONNECTED WITH OR RESULTING FROM THIS LICENSE AGREEMENT OR LICENSEE'S USE OF THIS SOFTWARE. Licensee's jurisdiction may not allow such a limitation of damages, so this limitation may not apply.

7. Licensee agrees to defend and indemnify Licensor and hold Licensor harmless from all claims, losses, damages, complaints, or expenses connected with or resulting from Licensee's business operations.

8. Licensor has the right to terminate this License Agreement and Licensee's right to use this Software upon any material breach by Licensee.

9. Licensee agrees to return to Licensor or to destroy all copies of the Software upon termination of the License.

10. This License Agreement is the entire and exclusive agreement between Licensor and Licensee regarding this Software. This License Agreement replaces and supersedes all prior negotiations, dealings, and agreements between Licensor and Licensee regarding this Software.

11. This License Agreement is governed by the law of the United Kingdom of Great Britain.

12. This License Agreement is valid without Licensor's signature. It becomes effective upon the earlier of Licensee's signature or Licensee's use of the Software.";

		$_outputData = '<textarea id="agreetext" style="WIDTH: 99%;" class="swifttextarea" name="agreetext" cols="60" '.
			'rows="12" readonly="readonly">'. htmlspecialchars($lictext) .'</textarea>'.SWIFT_CRLF;
		
		$_columnContainer = array(array("align" => "left", "valign" => "top", "value" => $_outputData, "colspan" => 2));
		$_TabObject->Row($_columnContainer, "", "agreetxt");

		$_TabObject->YesNo('agreed', $this->Language->Get('sp_i_agree'), "", false);
		
		$this->UserInterface->End();

		return true;
	}
	
	public function Render($testLicense = null)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $sp_licensetxt, $SPFunctions;

		$warningtxt = "";
				
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start($className, '/supportpay/'.$className.'/Update', SWIFT_UserInterface::MODE_INSERT, false, false);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_ptlicmaint'), 'icon_form.gif', 'general', true);

		$_TabObject->Info("SupportPay",$SPFunctions->getPaymentWarnings());
		$_TabObject->Error("SupportPay",$SPFunctions->checkLicense());

		if (!empty($testLicense)) {
			$SPFunctions->readLicense(trim($testLicense), $results);
			$_TabObject->Title($_SWIFT->Language->Get("sp_newlicdetails"));//,"50%");
		} else {
			$SPFunctions->readLicense($_SWIFT->Settings->getKey("settings","sp_license"), $results);
			$_TabObject->Title($_SWIFT->Language->Get("sp_oldlicdetails"));//,"50%");
		}
		
		$_TabObject->Row(array( 
			array("value" => $_SWIFT->Language->Get("sp_thissite") . ": " . $_SERVER["HTTP_HOST"], "class"=>"tabletitlerowtitle",
				"colspan" => 2)
		));

		$theKey = array_search($_SERVER["HTTP_HOST"],$results["sitelist"]);
		if ($theKey !== FALSE) {
			$results["sitelist"][$theKey] = "<b>".$results["sitelist"][$theKey]."</b>";
		}
		
		if (count($results["sitelist"]) == 0) {
			$results["sitelist"] = array("<b>None</b>");
		}
		$_TabObject->Row(array( 
			array("value" => $_SWIFT->Language->Get("sp_licsites") . ": " . implode(", ",$results["sitelist"]), "class"=>"tabletitlerowtitle",
						"colspan" => 2)
					));

		switch ($results["status"]) {
			case SP_LICENSE_GOOD:
				$Specials = "";

				if ($results["allow_accounts"]) $Specials .= $_SWIFT->Language->Get("sp_feat_accts").", ";
				if ($results["allow_wpp"]) $Specials .= "Website Payments Pro, ";
				if ($results["allow_affiliate"]) $Specials .= $_SWIFT->Language->Get("sps_affiliate").", ";
				if ($results["allow_nobranding"]) $Specials .= $_SWIFT->Language->Get("sp_feat_nobranding").", ";
				if ($results["allow_whmcs"]) $Specials .= $_SWIFT->Language->Get("sp_feat_whmcs").", ";
				
				if ($Specials != "") {
					$Specials = preg_replace("/, $/","",$Specials);
				} else {
					$Specials = "None";
				}
				$validtxt = "<span style='color: Green'>". $sp_licensetxt[$results["status"]]."</span>";
				if ($_SWIFT->Settings->getKey("settings","sp_paypalwpp") == "WPP" && !$results["allow_wpp"]) {
					$warningtxt .= "<li>".htmlspecialchars($_SWIFT->Language->Get("sp_nowpp"))."</li>";
				}
				if ($_SWIFT->Settings->getKey("settings","sp_amenable") && !$results["allow_affiliate"]) {
					$warningtxt .= "<li>".htmlspecialchars($_SWIFT->Language->Get("sp_noaffiliate"))."</li>";
				}
				$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licsite"), "class"=>"tabletitle"), array("value" =>$results["site"])));
				
				if ($results["type"] == "Unlimited") {
					$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licexpiry"), "class"=>"tabletitle"), 
						array("value" => "Never")));
					$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licsupexpiry"), "class"=>"tabletitle"), array("value" =>
						date("l, F jS Y",$results["supexpiry"])." (".intval((intval($results["supexpiry"])-time())/86400)." days)")));
				} else {
					$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licexpiry"), "class"=>"tabletitle"), array("value" =>
						date("l, F jS Y",$results["expiry"])." (".intval((intval($results["expiry"])-time())/86400)." days)")));
				}
				$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licmaxstaff"), "class"=>"tabletitle"), array("value" =>$results["staff"])));
				$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licactstaff"), "class"=>"tabletitle"), 
					array("value" => ($_SWIFT->Settings->getKey("settings","sp_staffcnt") / $results["staff"] >= 0.9 ? "<span style='color: Red; text-decoration: blink;'>".
									$_SWIFT->Settings->getKey("settings","sp_staffcnt")."</span>" : $_SWIFT->Settings->getKey("settings","sp_staffcnt"))
								)));
				$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_licfeatures"), "class"=>"tabletitle"), array("value" =>$Specials)));
				$_TabObject->Row(array( array("value" => $_SWIFT->Language->Get("sp_lictype"), "class"=>"tabletitle"), array("value" =>$results["type"])));
				if ($warningtxt != "") {
					$_TabObject->Row(array( array("value" => "<span style='color: Red'>".$_SWIFT->Language->Get("sp_warning")."</span>", "class"=>"tabletitle"), 
						array("value"=>"<span style='text-align: left; margin-left: 1em;'><ul>".$warningtxt."</span>")));
				}
				break;
			default:
				$validtxt = "<span style='color: Red'>". $sp_licensetxt[$results["status"]]."</span>"; break;
				break;
		}
		
		$_TabObject->Row(array( 
			array("value" => $_SWIFT->Language->Get("sp_licvalid"), "class"=>"tabletitle"), 
			array("value" => $validtxt)));
		
		
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('update'), 'icon_check.gif','/supportpay/ShowLicense/Update', SWIFT_UserInterfaceToolbar::LINK_SUBMITCONFIRM);
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('sp_check'), 'icon_verifyconnection.png', '/supportpay/ShowLicense/Check', SWIFT_UserInterfaceToolbar::LINK_FORM);

		if (!isset($_POST["license"])) {
			$_POST["license"] = $_SWIFT->Settings->getKey("settings","sp_license");
		}
		$_TabObject->Title($_SWIFT->Language->Get("sp_newlicdetails"), "doublearrows.gif");
		$_TabObject->TextArea("license", $_SWIFT->Language->Get("sp_lickeytitle"), 
			$_SWIFT->Language->Get("sp_lickeytitle_d"), $_POST["license"], 60, 10);

		//printMainTableFooter();
		//echo "</div><br/>";

		$this->UserInterface->End();

		return true;
	}

};
?>
