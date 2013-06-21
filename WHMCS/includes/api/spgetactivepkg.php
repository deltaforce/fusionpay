<?php
/**
 * Custom API function to get product addon definitions
 * 
 */

/*
select h.userid, h.packageid, h.domainstatus
  from tblhosting h
*/

/* List all hosting packages, including the status and whether or not it's been upgraded.
   Must include invoices because packages/addons are marked as "Active" before they've been paid,
   but the expiry date is set in the past.
*/
$result = mysql_query("select od.*, i.id invoiceid, i.inv_amount, 
	case when od.orderid = moj.maxorder then 'Latest' else 'Obsolete' end latest, 
	i.status, case when coalesce(i.status,'Paid') = 'Paid' then od.domainstatus else i.status end paid_status,
	moj.maxorder
from (select h.userid, h.id, h.packageid, h.orderid, h.id relid, unix_timestamp(o.date) orderdate, o.ordernum, h.domainstatus, 
             unix_timestamp(h.nextduedate) nextduedate, h.billingcycle, o.amount, 1 otype
	  from tblhosting h, tblorders o
	 where o.id = h.orderid
	 union
	select ou.userid, h.id, h.packageid, u.orderid, u.id relid, unix_timestamp(ou.date) orderdate, ou.ordernum, h.domainstatus, 
		   unix_timestamp(h.nextduedate) nextduedate, h.billingcycle, ou.amount, 2 otype
	  from tblupgrades u, tblorders ou, tblhosting h
	 where ou.id = u.orderid
	   and u.type = 'package'
	   and u.paid = 'Y' and h.id = u.relid
	) od 
	left join (select i.id, 
	                  case when substr(ii.type,1,7) = 'Prorata' or ii.type = 'PromoHosting' then 'Hosting' else ii.type end type, 
	                  ii.relid, i.status, sum(ii.amount) inv_amount
				  from tblinvoices i, tblinvoiceitems ii,
					   (select max(invoiceid) id, 
					           case when substr(type,1,7) = 'Prorata' or type = 'PromoHosting' then 'Hosting' else type end type, 
							   relid
						  from tblinvoiceitems
						 group by case when substr(type,1,7) = 'Prorata' or type = 'PromoHosting' then 'Hosting' else type end,
						       relid
					   ) maxii
				 where ii.invoiceid = i.id 
				   and i.id = maxii.id and ii.type = maxii.type and ii.relid = maxii.relid 
			  group by i.id, 
			           case when substr(ii.type,1,7) = 'Prorata' or ii.type = 'PromoHosting' then 'Hosting' else ii.type end, 
					   ii.relid, i.status
			) i on (od.relid = i.relid and case when od.otype = 1 then 'Hosting' else 'Upgrade' end = i.type),
	(select mo.id, max(mo.orderid) maxorder 
	   from (select id, orderid from tblhosting 
			  union 
			 select relid, orderid 
			   from tblupgrades u
			  where u.type = 'package' and u.paid = 'Y') mo,
			tblorders o
	  where o.id = mo.orderid group by mo.id) moj
 where od.id = moj.id
   and (od.billingcycle = 'Free Account' or i.status is not null)
 order by od.userid, od.id, od.orderid, i.id");

$amount = mysql_num_rows($result);
$total = $amount;

$xml = null;
$pVersion = '$Change: 3421 $';
if (preg_match('/Change:\ (\d+)/', $pVersion, $m) >= 1) {
	$pVersion = intval($m[1]);
} else {
	$pVersion = -1;
}

$header  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
$header .= "<whmcsapi version=\"4.3.1\">\n";
$header .= "    <action>spgetactivepkg</action>\n";
$header .= "    <result>success</result>\n";
$header .= "    <version>".$pVersion."</version>\n";

if ($amount !== 0) {
	while($row = mysql_fetch_array($result)) {
		$xml .= "        <hosting>\n";
		$xml .= "            <id>".$row['id']."</id>\n";
		$xml .= "            <userid>".$row['userid']."</userid>\n";
		$xml .= "            <orderid>".$row['orderid']."</orderid>\n";
		$xml .= "            <invoiceid>".$row['invoiceid']."</invoiceid>\n";
		$xml .= "            <itemid>".$row['itemid']."</itemid>\n";
		$xml .= "            <orderdate>".$row['orderdate']."</orderdate>\n";
		$xml .= "            <ordernum>".$row['ordernum']."</ordernum>\n";
		$xml .= "            <pkgid>".$row['packageid']."</pkgid>\n";
		$xml .= "            <status><![CDATA[".$row['paid_status']."]]></status>\n";
		$xml .= "            <upgrade>".$row['latest']."</upgrade>\n";
		$xml .= "            <nextdue>".$row['nextduedate']."</nextdue>\n";
		$xml .= "            <billing>".$row['billingcycle']."</billing>\n";
		$xml .= "            <amount>".$row['amount']."</amount>\n";
		$xml .= "            <maxorder>".$row['maxorder']."</maxorder>\n";
		$xml .= "            <otype>".$row['otype']."</otype>\n";
		$xml .= "        </hosting>\n";
	}
}

/* Now addons. */
$result = mysql_query("select h.userid, ha.id, ha.hostingid, ha.addonid, ha.status, 
	   case when ha.billingcycle in ('One Time','Free Account') then 0
	   else unix_timestamp(ha.nextduedate) end nextduedate, 
	   unix_timestamp(ha.regdate) regdate, i.amount, i.invoiceid, 
	   coalesce(i.itemid, ha.id) itemid,
	   case when coalesce(i.status,'Paid') = 'Paid' then ha.status else i.status end paid_status
  from tblhosting h, tblclients c, tblhostingaddons ha left join (
    select ii.invoiceid, ii.relid, ii.amount, i.status, ii.id itemid
      from tblinvoices i, tblinvoiceitems ii
     where ii.invoiceid = i.id
       and ii.type = 'Addon' and (ii.relid, ii.invoiceid) in (select relid, max(invoiceid) from tblinvoiceitems where type = 'Addon' group by relid)
    ) i on (i.relid = ha.id)
 where h.id = ha.hostingid and c.id = h.userid and coalesce(i.itemid, ha.id) is not null
 ");

$amount = mysql_num_rows($result);
$total += $amount;

if ($amount !== 0) {
	while($row = mysql_fetch_array($result)) {
		$xml .= "        <addon>\n";
		$xml .= "            <id>".$row['hostingid']."</id>\n";
		$xml .= "            <invoiceid>".$row['invoiceid']."</invoiceid>\n";
		$xml .= "            <itemid>".$row['id']."</itemid>\n";
		$xml .= "            <userid>".$row['userid']."</userid>\n";
		$xml .= "            <pkgid>".$row['addonid']."</pkgid>\n";
		$xml .= "            <status><![CDATA[".$row['paid_status']."]]></status>\n";
		$xml .= "            <nextdue>".$row['nextduedate']."</nextdue>\n";
		$xml .= "            <amount>".$row['amount']."</amount>\n";
		$xml .= "        </addon>\n";
	}
}

echo $header;
echo "    <totalresults>".$total."</totalresults>\n<status>";
echo $xml;   
echo "</status></whmcsapi>";
?>