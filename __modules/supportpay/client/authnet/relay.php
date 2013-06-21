<?php

if ($_SERVER["REQUEST_METHOD"] != "POST") {
	error_log("AuthNet relay rejected due to method ".$_SERVER["REQUEST_METHOD"]);
	echo "Authorize.net Relay requires POST data.";
	exit;
} else {
	// Can't define a session here, SWIFT deletes it.

	// Not callback here, Authorize.net wants a displayable page. Must be a validated client page.
	define('SWIFT_INTERFACE', 'client');
//	define('SWIFT_INTERFACEFILE', __FILE__);

	// This one for me...
	if ($_SERVER["HTTP_HOST"] == "jimkeir.dyndns.org") {
		$_SERVER["HTTP_HOST"]="127.0.0.1";
	}
	
	// Pick up the previous session using no SWIFT components, because they don't exist yet.
	// TODO: Check that this still works when coming from a different browser/IP etc.

	// If this is a true IPN call, "sessId" and "" are unset.
	$_COOKIE['SWIFT_sessionid40'] = $_POST["sessId"];
	
	// Long way round - use a separate callback to derive the expected value for REQUEST_URI.
	// This comes from a value in the database plus one in config.php, neither of which we can
	// read without having SWIFT:: defined first. Can't do that without first fiddling the session.
	$newReqURI = dirname($_SERVER["REQUEST_URI"])."/../getBaseName.php";
	$newReqURI = ($_SERVER["HTTPS"] == "on" ? "HTTPS":"HTTP")."://".$_SERVER["HTTP_HOST"].":".$_SERVER["SERVER_PORT"].$newReqURI;

	$_SERVER["REQUEST_URI"] = file_get_contents($newReqURI);
	$_SERVER['PATH_INFO'] = '/supportpay/MakeOrder/Index/'.urlencode($_POST["cartData"]);

	require_once("../../../../__swift/swift.php");
}
?>