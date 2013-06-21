<?php

class SWIFT_ImportTable_PointPayments extends SWIFT_ImportTable {
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

		$this->DatabaseImport->QueryLimit("select itemid,userid,max(dateorder) dateorder,".			"sum(amount) amount from ".TABLE_PREFIX."points ".			"where support_type = 'ticket' group by itemid, userid
			order by itemid,userid asc",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');

		foreach ($RecContainer as $recData) {
			$_count++;
			
			// TODO: Seems we can have duplicate payments from TicketPay.
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);

			// Don't need to worry about per-department rates here. Once bill_minutes is set, it will
			// be used as the master.
			$this->Database->AutoExecute(TABLE_PREFIX."sp_ticket_paid",
				array(  'ticketid' => $recData['itemid'],
						'userid' =>  $_newUserID,
						'paytype' =>  SP_PAYTYPE_TICKET,
						'paid_date' =>  $recData['dateorder'],
						'call_minutes' =>  $recData['amount'] * $this->tp_multiplier,
						'bill_minutes' =>  $recData['amount'] * $this->tp_multiplier,
						'minutes' =>  $recData['amount'] * $this->tp_multiplier,
						'tickets' =>  0,
						'migrated' =>  SP_MIGRATED_TICKETPAY,
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

		$_countContainer = $this->DatabaseImport->QueryFetch("SELECT count(1) AS totalitems FROM (select 1 from ".TABLE_PREFIX."points ".
			"where support_type='ticket' group by itemid, userid) ti");
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
