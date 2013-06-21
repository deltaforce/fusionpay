<?php
/* Don't have the version number here, any extra output messes up LoginShare. */

class Controller_WHMCSLS extends Controller_client
{
	private $_SWIFT, $SP_WHMCS;
	
	public function __construct()
	{
		parent::__construct();
		
		// This file can exist in both supportpay and sp_whmcslis
		SWIFT_Loader::LoadLibrary('SupportPay:SPWHMCS', "supportpay");

		$this->_SWIFT = SWIFT::GetInstance();
		$this->SP_WHMCS = new SWIFT_SPWHMCS;
		
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Index() {
		$xmlRes = simplexml_load_string("<loginshare><result>0</result></loginshare>");
		
		if (empty($_POST)) {
			$xmlRes->addChild("message","No Post Parameters");
		} else {
			if (!empty($_POST["username"]) && !empty($_POST["password"])) {
				$res = $this->SP_WHMCS->doWHMCSApiCall(array(
					"action" => "validatelogin", "email" => $_POST["username"],
					"password2" => $_POST["password"],
					), true);

				$data = explode(";",$res);
				$results = array();
				foreach ($data AS $temp) {
					if (strpos($temp, "=") !== FALSE) {
						$temp = explode("=",$temp);
						$results[$temp[0]] = $temp[1];
					}
				}

				if (empty($results["result"])) {
					$xmlRes->addChild("message","No Response from WHMCS");
				} elseif ($results["result"]=="success" && is_numeric($results["userid"])) {
					// Now try to get the user details. Use the same code as in the
					// normal manual WHMCS user fetch.
					
					$userDetails = $this->SP_WHMCS->doWHMCSApiCall(array(
						"action" => "getclientsdetails", 
						"clientid" => $results["userid"],
						"responsetype" => "xml"
						));

					$xml_doc = @simplexml_load_string($userDetails);
					$txtEmail = "".trim($xml_doc->{'client'}->{'email'});
					if ($txtEmail != $_POST["username"]) {
						// Otherwise, retrieve the list of contacts for this account and check them in turn.
						$uid = intval(trim($xml_doc->{'client'}->{'userid'}));
						
						if ($uid != 0) {
							$subusers = $this->SP_WHMCS->doWHMCSApiCall(array(
								"action" => "getcontacts",
								"limitnum" => "99999999",
								"userid" => $uid
								));
							
							if (isset($subusers)) {
								// It's an XML string.
								$xml_doc = @simplexml_load_string($subusers);
								if (!empty($xml_doc)) {
									foreach ($xml_doc->{'contacts'}->{'contact'} as $wUser) {
										$txtEmail = "".trim($wUser->{'email'});
										
										if ($txtEmail == $_POST["username"]) {
											$userDetails = $wUser->asXML();
											break;
										}
									}
								}
							}
						}
					}
					
					$this->SP_WHMCS->AddUpdateUser($userDetails, $xmlRes);
				} else {
					// WHMCS wouldn't validate. Do we want to try a fallback?
					if ($this->_SWIFT->Settings->Get('sp_whmcs_lisfallback')) {
						if (method_exists('SWIFT_Loader','LoadModel')) {
							SWIFT_Loader::LoadModel('User:User');
						} else {
							SWIFT_Loader::LoadLibrary('User:User');
						}

						$this->_SWIFT->Settings->UpdateLocalCache("settings","loginshare_userenable",'0');
						$auth = SWIFT_User::Authenticate($_POST["username"],$_POST["password"]);
						$this->_SWIFT->Settings->UpdateLocalCache("settings","loginshare_userenable",'1');
						if ($auth != false) {
							// Then a local user exists, and all the details are correct.
							$xmlUser = $xmlRes->addChild("user");
							$xmlUser->addChild("fullname",$auth->GetFullName(false));
							
							$_userGroupCache = $this->Cache->Get('usergroupcache');
							$userGroupId = intval($auth->GetProperty('usergroupid'));
							
							if (isset($_userGroupCache[$userGroupId]["title"])) {
								$xmlUser->addChild("usergroup", $_userGroupCache[$userGroupId]["title"]);
								$xmlUser->addChild("phone", $auth->GetProperty('phone'));
								
								$xmlEmail = $xmlUser->addChild("emails");
								$emails = $auth->GetEmailList();
								foreach ($emails as &$email) {
									$xmlEmail->addChild("email",$email);
								}

								$xmlRes->{"result"}[0] = 1;
								$results["message"] = null;
							}
						}
					}

					if ($xmlRes->{"result"}[0] == 0) {
						if (!empty($results["message"])) {
							$xmlRes->addChild("message","validatelogin: " . $results["message"]);
						} else {
							$xmlRes->addChild("message",$this->_SWIFT->Language->Get('invaliduser'));
						}
					}
				}
			} else {
				$xmlRes->addChild("message","Loginshare Parameters Incorrect");
			}
		}
		
		Header('Content-type: text/xml');
		echo $xmlRes->asXML();
	}
}
?>
