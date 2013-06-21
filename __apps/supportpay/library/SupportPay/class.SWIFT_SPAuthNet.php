<?php

class SWIFT_SPAuthNet {
	private $_SWIFT4;
	
	public function __construct()
	{
		$this->_SWIFT4 = SWIFT::GetInstance();
		return true;
	}

	function DoCapture($transid, $amount, $currency) {
		// Returning a PAYPAL-formatted result array.
		global $SPFunctions;
		
		$postArray = array(
			"x_login" => $this->_SWIFT4->Settings->getKey("settings","sp_anloginid"),
			"x_tran_key" => $SPFunctions->getSecureSetting("sp_antxkey"),
			"x_type" => "PRIOR_AUTH_CAPTURE",
			"x_amount" => sprintf("%0.2f",$amount),
			"x_trans_id" => $transid,
			"x_relay_response" => "FALSE",
			"x_delim_data" => "TRUE",
			"x_delim_char" => ",",
			"x_encap_char" => "'",
			"x_version" => "3.1",
		);

		$postArgsString = "&".$SPFunctions->array_implode('=','&', $postArray);
		if ($this->_SWIFT4->Settings->getKey("settings","sp_anlive")) {
			$host = "https://secure.authorize.net/gateway/";
		} else {
			$host = "https://test.authorize.net/gateway/";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host."transact.dll");
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postArgsString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$resArray = explode("','","',".$response.",'");
		
		$resArray["ACK"] = ($resArray[1] == 1 ? "SUCCESS":"FAILURE");
		if (count($resArray) > 7) {
			// N.B. Original cart data is available in resArray[8].
			$resArray["TRANSACTIONID"] = $resArray[7];
			$resArray["FEEAMT"] = 0;
		}

		return $resArray;
	}
};

?>
