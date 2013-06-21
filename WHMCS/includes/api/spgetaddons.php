<?php
/**
 * Custom API function to get product addon definitions
 * 
 */
/*
$result = mysql_query("SELECT a.*, case a.billingcycle
when 'Monthly' then b.msetupfee
when 'Quarterly' then b.qsetupfee
when 'Semi-Annually' then b.ssetupfee
when 'Annually' then b.asetupfee
when 'Biennially' then b.bsetupfee
else 1 end cost_setup,
case a.billingcycle
when 'Monthly' then b.monthly
when 'Quarterly' then b.quarterly
when 'Semi-Annually' then b.semiannually
when 'Annually' then b.annually
when 'Biennially' then b.biennially
else 1 end cost_recur
FROM tbladdons a, tblpricing b
where b.type = 'addon' and b.relid = a.id;
");
*/

$result = mysql_query("SELECT a.*, b.msetupfee cost_setup, b.monthly cost_recur
FROM tbladdons a, tblpricing b
where b.type = 'addon' and b.relid = a.id
");

$amount = mysql_num_rows($result);

$xml = null;
$pVersion = '$Change: 2845 $';
if (preg_match('/Change:\ (\d+)/', $pVersion, $m) >= 1) {
	$pVersion = intval($m[1]);
} else {
	$pVersion = -1;
}

$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
$xml .= "<whmcsapi version=\"4.3.1\">\n";
$xml .= "    <action>spgetaddons</action>\n";
$xml .= "    <result>success</result>\n";
$xml .= "    <totalresults>".$amount."</totalresults>\n";
$xml .= "    <version>".$pVersion."</version>\n";

if($amount !== 0) {
	$xml .= "    <addons>\n";
	
	while($row = mysql_fetch_array($result)) {
		$xml .= "        <addon>\n";
		$xml .= "            <id>".$row['id']."</id>\n";
		$xml .= "            <name><![CDATA[".$row['name']."]]></name>\n";
		$xml .= "            <description><![CDATA[".$row['description']."]]></description>\n";
		$xml .= "            <billing>".$row['billingcycle']."</billing>\n";
		$xml .= "            <cost_setup>".$row['cost_setup']."</cost_setup>\n";
		$xml .= "            <cost_recur>".$row['cost_recur']."</cost_recur>\n";
		$xml .= "        </addon>\n";
	}
	
	$xml .= "    </addons>\n";
}

$xml .= "</whmcsapi>";

echo $xml;   
?>