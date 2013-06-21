<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_Settings.php $, $Change: 3422 $, $DateTime: 2013/03/06 17:06:48 $ -->
<?php


class View_Settings extends SWIFT_View
{
	public function __construct() {
		parent::__construct();

		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function RenderPayPalDlg($isSandbox, $ppUserId, $ppAPIPass, $ppAPIKey) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start(null, "nowhere", SWIFT_UserInterface::MODE_INSERT, 
			true, false, true);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_paypal_test'), 
			'icon_form.gif', 'general', true);

		if ($isSandbox) {
			$sb = "sb";
		} else {
			$sb = "";
		}
		
		if (substr($ppAPIPass,0,3) == "PW:") {
			$dec = $SPFunctions->decodeData("sp_paypal".$sb."passwd", substr($ppAPIPass,3));
			if (!is_null($dec))
				$ppAPIPass = $dec;
		}
		if (substr($ppAPIKey,0,3) == "PW:") {
			$dec = $SPFunctions->decodeData("sp_paypal".$sb."sign", substr($ppAPIKey,3));
			if (!is_null($dec))
				$ppAPIKey = $dec;
		}

		if (!$isSandbox) {
			$API_Endpoint = "https://api-3t.paypal.com/nvp";
			$PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		} else {
			$API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
			$PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
		curl_setopt($ch, CURLOPT_POST, 1);
		
		$nvpreq="METHOD=GetBalance&VERSION=58.0&PWD=".urlencode($ppAPIPass). 
			"&USER=".urlencode($ppUserId)."&SIGNATURE=".urlencode($ppAPIKey). "&BUTTONSOURCE=PP-ECWizard";

		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		$response = curl_exec($ch);

		curl_close($ch);

		if (is_string($response) && strpos(strtoupper($response),"ACK=SUCCESS") !== FALSE) {
			$_TabObject->Info("SupportPay", $this->Language->Get('sp_paypal_ok'));
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_paypal_bad'));
		}

		$this->UserInterface->End();
		return true;
	}
	
	public function RenderWHMCSDlg($API_Endpoint, $UserId, $APIPass, $HTTPUser, $HTTPPass) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPWHMCS', "supportpay");
		$myWHMCS = new SWIFT_SPWHMCS;
		
		$className = str_replace("View_","",get_class($this));
		$this->UserInterface->Start(null, "nowhere", SWIFT_UserInterface::MODE_INSERT, 
			true, false, true);
		$_TabObject = $this->UserInterface->AddTab($this->Language->Get('sp_whmcs_test'), 
			'icon_form.gif', 'general', true);

		if (substr($UserId,0,3) == "PW:") {
			$dec = $SPFunctions->decodeData("sp_whmcs_api_userid", substr($UserId,3));
			if (!is_null($dec))
				$UserId = $dec;
		}

		if (substr($APIPass,0,3) == "PW:") {
			$dec = $SPFunctions->decodeData("sp_whmcs_api_pass", substr($APIPass,3));
			if (!is_null($dec))
				$APIPass = $dec;
		}

		if (substr($HTTPPass,0,3) == "PW:") {
			$dec = $SPFunctions->decodeData("sp_whmcs_api_pass", substr($HTTPPass,3));
			if (!is_null($dec))
				$HTTPPass = $dec;
		}

		$API_Endpoint .= "/includes/api.php";
		$_params = array(
			"username" => $UserId,
			"password" => md5($APIPass),
			);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$API_Endpoint);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 100);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
		if (!empty($HTTPUser) || !empty($HTTPPass)) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $HTTPUser.":".$HTTPPass);
		}

		// Try the new three actions, plus one basic default one.
		$errstring = "";
		$errPayload = "";
		$haveFailed = false;
		foreach (array("getclients","spgetactivepkg","spgetaddons","spgetprodfields") as $action) {
			$_params["action"] = $action;
			$fileVersion = "";
			$errstring = "";

			curl_setopt($ch, CURLOPT_POSTFIELDS, $_params);
			$data = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($data === false) {
				$errstring = curl_error($ch);
			} elseif ($httpCode != 200) {
				$errstring = "HTTP Error " . $httpCode;
			} elseif (strpos($data,"result=error") !== false) {
				$res = explode(";", $data);
				foreach ($res as $pair) {
					if (strpos($pair, "message=") === 0) {
						$errstring = substr($pair, 8);
						break;
					}
				}
				
				if (empty($errstring)) {
					$errstring = $data;
				}
			} else {
				$xml_doc = @simplexml_load_string($data);
				$fileVersion = null;
				
				if (!empty($xml_doc)) {
					if (isset($myWHMCS->minAPIVersions[$action])) {
						$minVersion = $myWHMCS->minAPIVersions[$action];
						$fileVersion = (string)($xml_doc->{'version'});
						if (empty($fileVersion)) {
							$errstring = "Version not found in returned XML.";
						} elseif ($fileVersion < $minVersion) {
							$errString = "Version of XML (".$fileVersion.") is older than required version ".$minVersion;
						} else {
							$fileVersion .= ", OK";
						}
					} else {
						$fileVersion = "OK";
					}
				} else {
					$errstring = "API call succeeded, but didn't return valid XML.";
				}
			}
				
			if (!empty($errstring)) {
				$_TabObject->Row(array(
					array("value" => "Action '".$action."'", "class"=>"tabletitle"),
					array("value" => $errstring)
					));
			} else {
				$_TabObject->Row(array(
					array("value" => "Action '".$action."'", "class"=>"tabletitle"),
					array("value" => "Version ".$fileVersion)
					));
			}

			if (!empty($errstring)) {
				$haveFailed = true;
				$errPayload = $data;
			}
		}

		curl_close($ch);

		if (!$haveFailed) {
			$_TabObject->Info("SupportPay", $this->Language->Get('sp_whmcs_ok'));
		} else {
			$_TabObject->Error("SupportPay", $this->Language->Get('sp_whmcs_bad'));
			$_TabObject->TextArea("dummy", $_SWIFT->Language->Get("sp_lastweberror"), "", $errPayload, 40, 5);
		}

		$this->UserInterface->End();
		return true;
	}
};
?>
