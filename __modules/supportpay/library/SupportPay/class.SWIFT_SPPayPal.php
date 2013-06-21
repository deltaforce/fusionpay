<?php

class SWIFT_SPPayPal {
	/********************************************
	PayPal API Module
	 
	Defines all the global variables and the wrapper functions 
	********************************************/
	
	private $API_Endpoint, $API_version, $API_UserName, $API_Password, $API_Signature;
	private $PAYPAL_URL;
	private $sBNCode;

	function __construct() {
	//'------------------------------------
	//' PayPal API Credentials
	//' Replace <API_USERNAME> with your API Username
	//' Replace <API_PASSWORD> with your API Password
	//' Replace <API_SIGNATURE> with your Signature
	//'------------------------------------
	
	global $SPFunctions;
	SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay"); // for $SPFunctions->getSecureSetting

	// BN Code 	is only applicable for partners
	$this->sBNCode = "PP-ECWizard";
	
	/*	
	' Define the PayPal Redirect URLs.  
	' 	This is the URL that the buyer is first sent to do authorize payment with their paypal account
	' 	change the URL depending if you are testing on the sandbox or the live PayPal site
	'
	' For the sandbox, the URL is       https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=
	' For the live site, the URL is        https://www.paypal.com/webscr&cmd=_express-checkout&token=
	*/
	
	if (empty($_SWIFT)) {
		$_SWIFT = SWIFT::GetInstance();
	}

	if ($_SWIFT->Settings->getKey("settings","sp_paypallive")) {
		$this->API_Endpoint = "https://api-3t.paypal.com/nvp";
		$this->PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		$this->API_UserName = $_SWIFT->Settings->getKey("settings","sp_paypaluserid");
		$this->API_Password = $SPFunctions->getSecureSetting("sp_paypalpasswd");
		$this->API_Signature = $SPFunctions->getSecureSetting("sp_paypalsign");
	} else {
		$this->API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
		$this->PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
		$this->API_UserName = $_SWIFT->Settings->getKey("settings","sp_paypalsbuserid");
		$this->API_Password = $SPFunctions->getSecureSetting("sp_paypalsbpasswd");
		$this->API_Signature = $SPFunctions->getSecureSetting("sp_paypalsbsign");
	}

	$this->API_version="58.0";
	if (session_id() == "") {
		session_start();
	}
	
	}
	/* An express checkout transaction starts with a token, that
	   identifies to PayPal your transaction
	   In this example, when the script sees a token, the script
	   knows that the buyer has already authorized payment through
	   paypal.  If no token was found, the action is to send the buyer
	   to PayPal to first authorize payment
	   */
	/*   
	'-------------------------------------------------------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
	' Inputs:  
	'		paymentAmount:  	Total value of the shopping cart
	'		currencyCodeType: 	Currency code value the PayPal API
	'		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
	'		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
	'		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
	'--------------------------------------------------------------------------------------------------------------------------------------------	
	*/
function CallShortcutExpressCheckout( $paymentAmount, $currencyCodeType, $paymentType, $returnURL, $cancelURL, $custom = "") 
	{
		//------------------------------------------------------------------------------------------------------------------------------------
		// Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation
		
		$nvpstr="&AMT=". $paymentAmount . "&NOSHIPPING=1";
		$nvpstr = $nvpstr . "&PAYMENTACTION=" . $paymentType;
		$nvpstr = $nvpstr . "&RETURNURL=" . urlencode($returnURL);
		$nvpstr = $nvpstr . "&CANCELURL=" . urlencode($cancelURL);
		$nvpstr = $nvpstr . "&CURRENCYCODE=" . $currencyCodeType;
		if (isset($custom)) $nvpstr .= "&CUSTOM=".$custom;
	
		$_SESSION["currencyCodeType"] = $currencyCodeType;
		$_SESSION["paymentType"] = $paymentType;
		//'--------------------------------------------------------------------------------------------------------------- 
		//' Make the API call to PayPal
		//' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
		//' If an error occured, show the resulting errors
		//'---------------------------------------------------------------------------------------------------------------
	    $resArray=$this->hash_call("SetExpressCheckout", $nvpstr);
		$ack = strtoupper($resArray["ACK"]);
		if ($ack=="SUCCESS") {
			$token = urldecode($resArray["TOKEN"]);
			$_SESSION['TOKEN']=$token;
		}
		   
	    return $resArray;
	}
	/*   
	'-------------------------------------------------------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
	' Inputs:  
	'		paymentAmount:  	Total value of the shopping cart
	'		currencyCodeType: 	Currency code value the PayPal API
	'		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
	'		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
	'		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
	'		shipToName:		the Ship to name entered on the merchant's site
	'		shipToStreet:		the Ship to Street entered on the merchant's site
	'		shipToCity:			the Ship to City entered on the merchant's site
	'		shipToState:		the Ship to State entered on the merchant's site
	'		shipToCountryCode:	the Code for Ship to Country entered on the merchant's site
	'		shipToZip:			the Ship to ZipCode entered on the merchant's site
	'		shipToStreet2:		the Ship to Street2 entered on the merchant's site
	'		phoneNum:			the phoneNum  entered on the merchant's site
	'--------------------------------------------------------------------------------------------------------------------------------------------	
	*/
	function CallMarkExpressCheckout( $paymentAmount, $currencyCodeType, $paymentType, $returnURL, 
									  $cancelURL, $shipToName, $shipToStreet, $shipToCity, $shipToState,
									  $shipToCountryCode, $shipToZip, $shipToStreet2, $phoneNum
									) 
	{
		//------------------------------------------------------------------------------------------------------------------------------------
		// Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation
		
		$nvpstr="&Amt=". $paymentAmount;
		$nvpstr = $nvpstr . "&PAYMENTACTION=" . $paymentType;
		$nvpstr = $nvpstr . "&ReturnUrl=" . $returnURL;
		$nvpstr = $nvpstr . "&CANCELURL=" . $cancelURL;
		$nvpstr = $nvpstr . "&CURRENCYCODE=" . $currencyCodeType;
		$nvpstr = $nvpstr . "&ADDROVERRIDE=1";
		$nvpstr = $nvpstr . "&SHIPTONAME=" . $shipToName;
		$nvpstr = $nvpstr . "&SHIPTOSTREET=" . $shipToStreet;
		$nvpstr = $nvpstr . "&SHIPTOSTREET2=" . $shipToStreet2;
		$nvpstr = $nvpstr . "&SHIPTOCITY=" . $shipToCity;
		$nvpstr = $nvpstr . "&SHIPTOSTATE=" . $shipToState;
		$nvpstr = $nvpstr . "&SHIPTOCOUNTRYCODE=" . $shipToCountryCode;
		$nvpstr = $nvpstr . "&SHIPTOZIP=" . $shipToZip;
		$nvpstr = $nvpstr . "&PHONENUM=" . $phoneNum;
		
		$_SESSION["currencyCodeType"] = $currencyCodeType;	  
		$_SESSION["paymentType"] = $paymentType;
		//'--------------------------------------------------------------------------------------------------------------- 
		//' Make the API call to PayPal
		//' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
		//' If an error occured, show the resulting errors
		//'---------------------------------------------------------------------------------------------------------------
	    $resArray=$this->hash_call("SetExpressCheckout", $nvpstr);
		$ack = strtoupper($resArray["ACK"]);
		if($ack=="SUCCESS")
		{
			$token = urldecode($resArray["TOKEN"]);
			$_SESSION['TOKEN']=$token;
		}
		   
	    return $resArray;
	}
	
	/*
	'-------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the GetExpressCheckoutDetails API Call.
	'
	' Inputs:  
	'		None
	' Returns: 
	'		The NVP Collection object of the GetExpressCheckoutDetails Call Response.
	'-------------------------------------------------------------------------------------------
	*/
	function GetShippingDetails( $token )
	{
		//'--------------------------------------------------------------
		//' At this point, the buyer has completed authorizing the payment
		//' at PayPal.  The function will call PayPal to obtain the details
		//' of the authorization, incuding any shipping information of the
		//' buyer.  Remember, the authorization is not a completed transaction
		//' at this state - the buyer still needs an additional step to finalize
		//' the transaction
		//'--------------------------------------------------------------
	   
	    //'---------------------------------------------------------------------------
		//' Build a second API request to PayPal, using the token as the
		//'  ID to get the details on the payment authorization
		//'---------------------------------------------------------------------------
	    $nvpstr="&TOKEN=" . $token;
		//'---------------------------------------------------------------------------
		//' Make the API call and store the results in an array.  
		//'	If the call was a success, show the authorization details, and provide
		//' 	an action to complete the payment.  
		//'	If failed, show the error
		//'---------------------------------------------------------------------------
	    $resArray=$this->hash_call("GetExpressCheckoutDetails",$nvpstr);
	    $ack = strtoupper($resArray["ACK"]);
		if($ack == "SUCCESS")
		{	
			$_SESSION['payer_id'] =	$resArray['PAYERID'];
		} 
		return $resArray;
	}
	
	/*
	'-------------------------------------------------------------------------------------------------------------------------------------------
	' Purpose: 	Prepares the parameters for the GetExpressCheckoutDetails API Call.
	'
	' Inputs:  
	'		sBNCode:	The BN code used by PayPal to track the transactions from a given shopping cart.
	' Returns: 
	'		The NVP Collection object of the GetExpressCheckoutDetails Call Response.
	'--------------------------------------------------------------------------------------------------------------------------------------------	
	*/
	function ConfirmPayment( $FinalPaymentAmt, $customField )
	{
		/* Gather the information to make the final call to
		   finalize the PayPal payment.  The variable nvpstr
		   holds the name value pairs
		   */
		$_SWIFT = SWIFT::GetInstance();
		
		//Format the other parameters that were stored in the session from the previous calls	
		$token 				= urlencode($_SESSION['token']);
		$paymentType 		= urlencode($_SESSION['paymentType']);
		$currencyCodeType 	= urlencode($_SESSION['currencyCodeType']);
		$payerID 			= urlencode($_SESSION['payer_id']);
		$serverName 		= urlencode($_SERVER['SERVER_NAME']);
		$nvpstr  = '&TOKEN=' . $token . '&PAYERID=' . $payerID . '&PAYMENTACTION=' . $paymentType . '&AMT=' . $FinalPaymentAmt;
		$nvpstr .= '&CURRENCYCODE=' . $currencyCodeType . '&IPADDRESS=' . $serverName; 
		
		if ($_SWIFT->Settings->getKey("settings","sp_paypalipn")) {
			if ($_SWIFT->Settings->getKey("settings","sp_paypalipnurl") == "") {
				error_log("PayPal IPN is enabled, but the callback URL is not set.");
			} else {
				$nvpstr .= "&NOTIFYURL=".urlencode($_SWIFT->Settings->getKey("settings","sp_paypalipnurl"));
				if ($customField != "") {
					$nvpstr .= "&CUSTOM=".$customField;
				}
			}
		}
	
		 /* Make the call to PayPal to finalize payment
		    If an error occured, show the resulting errors
		    */
		$resArray=$this->hash_call("DoExpressCheckoutPayment",$nvpstr);
		/* Display the API response back to the browser.
		   If the response from PayPal was a success, display the response parameters'
		   If the response was an error, display the errors received using APIError.php.
		   */
		$resArray["ACK"] = strtoupper($resArray["ACK"]);
		return $resArray;
	}

	function DoVoid( $transid )
	{
		$_SWIFT = SWIFT::GetInstance();
		$transid = urlencode($transid);
		
		$nvpstr  = '&AUTHORIZATIONID='.$transid;
		
		$resArray=$this->hash_call("DoVoid",$nvpstr);

		$ack = strtoupper($resArray["ACK"]);
		return $resArray;
	}

	function DoCapture( $transid, $amount, $currency )
	{
		$_SWIFT = SWIFT::GetInstance();
		$transid = urlencode($transid);
		$currency = urlencode($currency);
		$amount = sprintf("%0.2f",$amount);
	
		$nvpstr  = '&AUTHORIZATIONID='.$transid.'&AMT='.$amount.'&CURRENCYCODE='.$currency."&COMPLETETYPE=Complete";
		
		$resArray=$this->hash_call("DoCapture",$nvpstr);

		$ack = strtoupper($resArray["ACK"]);
		return $resArray;
	}

	/**
	  '-------------------------------------------------------------------------------------------------------------------------------------------
	  * hash_call: Function to perform the API call to PayPal using API signature
	  * @methodName is name of API  method.
	  * @nvpStr is nvp string.
	  * returns an associtive array containing the response from the server.
	  '-------------------------------------------------------------------------------------------------------------------------------------------
	*/
	function hash_call($methodName,$nvpStr)
	{
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
		curl_setopt($ch, CURLOPT_POST, 1);
		
		//NVPRequest for submitting to server
		$nvpreq="METHOD=" . urlencode($methodName) . "&VERSION=" . urlencode($this->API_version) . "&PWD=" . urlencode($this->API_Password) . 
			"&USER=" . urlencode($this->API_UserName) . "&SIGNATURE=" . urlencode($this->API_Signature) . $nvpStr . 
			"&BUTTONSOURCE=" . urlencode($this->sBNCode);
		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		
		//getting response from server
		$response = curl_exec($ch);
		//convrting NVPResponse to an Associative Array
		$nvpResArray=$this->deformatNVP($response);
		$nvpReqArray=$this->deformatNVP($nvpreq);
		$_SESSION['nvpReqArray']=$nvpReqArray;
		if (curl_errno($ch)) 
		{
			// moving to display page to display curl errors
			  $_SESSION['curl_error_no']=curl_errno($ch) ;
			  $_SESSION['curl_error_msg']=curl_error($ch);
			  //Execute the Error handling module to display errors. 
		} 
		else 
		{
			 //closing the curl
		  	curl_close($ch);
		}
		
	if (empty($nvpResArray)) {
		$nvpResArray = array(
			"ACK" => "TIMEOUT", 
			"L_SHORTMESSAGE0" => "TIMEOUT",
			"L_LONGMESSAGE0" => "The payment server timed out. Please try again later.");
	}
		return $nvpResArray;
	}
	/*'----------------------------------------------------------------------------------
	 Purpose: Redirects to PayPal.com site.
	 Inputs:  NVP string.
	 Returns: 
	----------------------------------------------------------------------------------
	*/
	function RedirectToPayPal ( $token )
	{
		// Redirect to paypal.com here
		$payPalURL = $this->PAYPAL_URL . $token;
		header("Location: ".$payPalURL);
	}
	
	/*'----------------------------------------------------------------------------------
	 * This function will take NVPString and convert it to an Associative Array and it will decode the response.
	  * It is usefull to search for a particular key and displaying arrays.
	  * @nvpstr is NVPString.
	  * @nvpArray is Associative Array.
	   ----------------------------------------------------------------------------------
	  */
	function deformatNVP($nvpstr)
	{
		$intial=0;
	 	$nvpArray = array();
		while(strlen($nvpstr))
		{
			//postion of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);
			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr,$intial,$keypos);
			$valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
			//decoding the respose
			$nvpArray[urldecode($keyval)] =urldecode( $valval);
			$nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
	     }
		return $nvpArray;
	}

function DirectPayment($paymentType,$amount,$taxAmount,$creditCardType,$creditCardNumber,
	$expDate,$cvv2Number,$firstName,$lastName,$address1,$city,$state,$zip,$country,$currencyID,$other)
{
	$serverName = urlencode($_SERVER['REMOTE_ADDR']);

		$nvpStr = "&PAYMENTACTION=$paymentType&AMT=".sprintf("%0.2f",floatval($amount) + floatval($taxAmount)).
			"&ITEMAMT=".sprintf("%0.2f",floatval($amount)).
			"&TAXAMT=".sprintf("%0.2f",floatval($taxAmount))."&CREDITCARDTYPE=$creditCardType&ACCT=$creditCardNumber".
			"&EXPDATE=$expDate&CVV2=$cvv2Number&FIRSTNAME=$firstName&LASTNAME=$lastName".
			"&STREET=$address1&CITY=$city&STATE=$state&ZIP=$zip&COUNTRYCODE=$country&CURRENCYCODE=$currencyID".
			"&IPADDRESS=$serverName".$other;
			
	$_SWIFT = SWIFT::GetInstance();
	if ($_SWIFT->Settings->getKey("settings","sp_paypalipn")) {
		if ($_SWIFT->Settings->getKey("settings","sp_paypalipnurl") == "") {
			error_log("PayPal IPN is enabled, but the callback URL is not set.");
		} else {
			$nvpStr .= "&NOTIFYURL=".urlencode($_SWIFT->Settings->getKey("settings","sp_paypalipnurl"));
		}
	}

	$resArray=$this->hash_call("DoDirectPayment",$nvpStr);
	$ack = strtoupper($resArray["ACK"]);
	return $resArray;
}

function GetProfileDetails($profileID)
{
	$nvpStr = "&PROFILEID=".$profileID;
	
	$resArray=$this->hash_call("GetRecurringPaymentsProfileDetails",$nvpStr);
	$resArray["ACK"] = strtoupper($resArray["ACK"]);
	return $resArray;
}

function GetTransactionDetails($paymentType,$transid)
{
	$nvpStr = "&PAYMENTACTION=$paymentType&TRANSACTIONID=".$transid;
	
	$resArray=$this->hash_call("GetTransactionDetails",$nvpStr);
	$resArray["ACK"] = strtoupper($resArray["ACK"]);
	
	return $resArray;
}

function GetBalance()
{
	$nvpStr = "&RETURNALLCURRENCIES=1";
	$resArray=$this->hash_call("GetBalance",$nvpStr);
	
	return $resArray;
}

function MassPay($subject,$receivers)
{
	$_SWIFT = SWIFT::GetInstance();
		
	if (is_array($receivers)) {
		$nvpStr = "&EMAILSUBJECT=".urlencode($subject)."&RECEIVERTYPE=EmailAddress&CURRENCYCODE=".$_SWIFT->Settings->getKey("settings","sp_currency");
		$rcount = 0;
		foreach ($receivers as $email => $detail) {
			$nvpStr .= "&L_EMAIL".$rcount."=".urlencode($detail["email"])."&L_AMT".$rcount."=".sprintf("%0.2f",$detail["amount"]);
			$nvpStr .= "&L_NOTE".$rcount."=".urlencode("Payment from ".$_SWIFT->Settings->getKey("settings","general_companyname"));
			$nvpStr .= "&L_UNIQUEID".$rcount."=".urlencode($detail["unique"]);
			$rcount++;
		}
		
		$resArray=$this->hash_call("MassPay",$nvpStr);
	} else {
		$resArray["ACK"] = "NODATA";
	}
	
	return $resArray;
}

function CancelRecurringPayment($transid,$note)
{
	$nvpStr = "&ACTION=Cancel&PROFILEID=".$transid."&NOTE=".urlencode($note);
	
	$resArray=$this->hash_call("ManageRecurringPaymentsProfileStatus",$nvpStr);
	$resArray["ACK"] = strtoupper($resArray["ACK"]);
	return $resArray;
}

function ConfirmBillingAgreement($cid,$token,$description,$rec_unit,$rec_freq,$amount,$tax,$currency)
{
	$freqUnitText = "";
	switch ($rec_unit) {
		case SP_RECUR_UNIT_WEEK: $freqUnitText = "Week"; break;
		case SP_RECUR_UNIT_MONTH: $freqUnitText = "Month"; break;
		case SP_RECUR_UNIT_YEAR: $freqUnitText = "Year"; break;
	}
	
	$startDate = strtotime("+".intval($rec_freq)." ".$freqUnitText,time());
	$startDate = gmdate("Y-m-d\TH:i:s\Z",$startDate);
//	$startDate = gmstrftime("%F", $startDate); // 2008-09-16T07:00:00.0000000Z
	$nvpStr = "&TOKEN=".urlencode($token)."&PROFILEREFERENCE=".urlencode($cid);
	$nvpStr .= "&PROFILESTARTDATE=".$startDate."&INITAMT=".sprintf("%0.2f",($amount+$tax));
	$nvpStr .= "&DESC=".urlencode($description)."&BILLINGPERIOD=".$freqUnitText;
	$nvpStr .= "&BILLINGFREQUENCY=".intval($rec_freq)."&AMT=".sprintf("%0.2f",$amount);
	$nvpStr .= "&CURRENCYCODE=".$currency."&TAXAMT=".sprintf("%0.2f",$tax);
	
	$resArray=$this->hash_call("CreateRecurringPaymentsProfile",$nvpStr);
	$resArray["ACK"] = strtoupper($resArray["ACK"]);
	
	return $resArray;
}

}
?>