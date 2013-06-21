<?php

if ($_SERVER["REQUEST_METHOD"] != "POST") {
	error_log("AuthNet IPN rejected due to method ".$_SERVER["REQUEST_METHOD"]);
	echo "Authorize.net IPN requires POST data.";
	exit;
} else {
	define('SWIFT_INTERFACE', 'callback');
	define('SWIFT_INTERFACEFILE', __FILE__);
	$_SERVER['PATH_INFO'] = '/supportpay/AuthNet/Main';
	if ($_SERVER["HTTP_HOST"] == "jimkeir.dyndns.org") {
		$_SERVER["HTTP_HOST"]="127.0.0.1";
	}

	require_once("../../../../__swift/swift.php");
}

?>