<?php

class SWIFT_ImportTable_MinutePaid extends SWIFT_ImportTable {
	private $myDisplayName;
	private $tp_multiplier = 1;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "userorders";

		if (!$this->TableExists($this->myTableName)) {
			$this->SetByPass(true);

			return false;
		}

		$this->tp_multiplier = $this->ImportManager->GetImportRegistry()->GetKey('database', 'tp_multiplier');

		return true;
	}

	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function Import() {
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		if ($this->GetOffset() == 0) {
			// Delete nothing for this one, any previously-migrated records were deleted by PointPackages.
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("select userid, amount, 
	   (select tp.pkgid from ".TABLE_PREFIX."sp_packages tp
	     where tp.title = uo.packagetype and tp.description = 'Imported from TicketPay'
	       and uo.package = 'points' and uo.packageprice = tp.price
	       and uo.amount = tp.minutes and tp.enabled = 1
		   limit 1
	   ) as packageid,
			date_purchase, packageprice from ".TABLE_PREFIX."userorders uo where package = 'points' and status = 'Paid'
			order by orderno asc",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');
		foreach ($RecContainer as $recData) {
			$_count++;
			
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			$this->Database->AutoExecute(TABLE_PREFIX."sp_user_payments",
				array(  'userid' =>  $_newUserID,
						'minutes' => $recData['amount'] * $this->tp_multiplier,
						'tickets' => 0,
						'rem_minutes' => $recData['amount'] * $this->tp_multiplier,
						'rem_tickets' => 0,
						'paidby' => 'TicketPay',
						'comments' => 'Imported from TicketPay',
						'packageid' => $recData['packageid'],
						'created' => $recData['date_purchase'],
						'cost' => $recData['packageprice'],
						'currency' => $_SWIFT->Settings->Get('sp_currency'),
						'migrated' => SP_MIGRATED_TICKETPAY
						),
					'INSERT');
		}

		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);

		return $_count;
	}

	protected function GetTotal() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_countContainer = $this->DatabaseImport->QueryFetch("SELECT COUNT(*) AS totalitems FROM ".TABLE_PREFIX."userorders ".
			"where package = 'points' and status = 'Paid'");
		if (isset($_countContainer['totalitems'])) {
			return $_countContainer['totalitems'];
		}

		return 0;
	}

	public function GetItemsPerPass() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		return 1000;
	}
}
?>
