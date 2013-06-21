<?php

if ($_SERVER["REQUEST_METHOD"] != "POST") {
	echo "PayPal IPN requires POST data.";
	exit;
} else {
	define('SWIFT_INTERFACE', 'callback');
	define('SWIFT_INTERFACEFILE', __FILE__);
	$_SERVER['PATH_INFO'] = '/supportpay/PayPal/Main';

	require_once("../../../../__swift/swift.php");
}
?>