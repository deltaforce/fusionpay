<?php
/**
 * Custom API function to get product addon definitions
 * 
 */
/*
p.paytype == "recurring" or "onetime"

pricing.relid == products.id and pricing.type = 'product'
*/

$result = mysql_query("SELECT p.id prodid, f.id fieldid, p.name product, p.hidden, p.paytype, f.fieldname, f.description, pr.*
	from tblcustomfields f, tblproducts p, tblpricing pr
	where f.type = 'product'
	and p.id = f.relid and p.id = pr.relid and pr.type = 'product'
	order by prodid, fieldid");

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
$xml .= "    <action>spgetprodfields</action>\n";
$xml .= "    <result>success</result>\n";
$xml .= "    <totalresults>".$amount."</totalresults>\n";
$xml .= "    <version>".$pVersion."</version>\n";

if($amount !== 0) {
	$xml .= "    <products>\n";
	$last_prod = null;
	
	while($row = mysql_fetch_array($result)) {
		if ($last_prod != $row['prodid']) {
			if (!is_null($last_prod)) {
				$xml .= "</product>\n";
			}
			$xml .= "<product>\n";
			$xml .= "<id>".$row['prodid']."</id>\n";
			$xml .= "<name><![CDATA[".$row['product']."]]></name>\n";
			$xml .= "<paytype><![CDATA[".$row['paytype']."]]></paytype>\n";
			$last_prod = $row['prodid'];
		}
		$xml .= "        <field>\n";
		$xml .= "            <id>".$row['fieldid']."</id>\n";
		$xml .= "            <name><![CDATA[".$row['fieldname']."]]></name>\n";
		$xml .= "            <description><![CDATA[".$row['description']."]]></description>\n";
		$xml .= "            <hidden>".$row['hidden']."</hidden>\n"; // Is 'on' or null
		$xml .= "        </field>\n";
		
		foreach (array("monthly","quarterly","semiannually","annually","biennially","trienially") as $bType) {
			if (isset($row[substr($bType,0,1)."setupfee"]) || isset($row[$bType])) {
				// We have a billing setup for this period.
				$xml .= "        <period type='".$bType."'>\n";
				$xml .= "           <setup>".floatval($row[substr($bType,0,1)."setupfee"])."</setup>\n";
				$xml .= "           <recur>".floatval($row[$bType])."</recur>\n";
				$xml .= "        </period>\n";
			}
		}
	}
	
	if (!is_null($last_prod)) {
		$xml .= "</product>\n";
	}
	$xml .= "    </products>\n";
}

$xml .= "</whmcsapi>";

echo $xml;   
?>