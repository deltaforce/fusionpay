<?php

class SWIFT_ImportTable_TicketPayments extends SWIFT_ImportTable {
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

		$this->DatabaseImport->QueryLimit("select t.ticketid,t.userid,t.lastactivity,".			"coalesce(sum(tt.timespent),0) timespent,coalesce(sum(tt.timebillable),0) timebillable ".			"from ".TABLE_PREFIX."tickets t left join ".TABLE_PREFIX."tickettimetrack tt on t.ticketid = tt.ticketid ".			"where ticketstatusid  in (".$this->ImportManager->closedTickets.") and ticketpay_info = 1 ".
				"group by t.ticketid, t.userid, t.lastactivity order by t.ticketid, t.userid",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');
		foreach ($RecContainer as $recData) {
			$_count++;

			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			$this->Database->AutoExecute(TABLE_PREFIX."sp_ticket_paid",
				array(  'ticketid' => $recData['ticketid'],
						'userid' =>  $_newUserID,
						'paytype' =>  SP_PAYTYPE_TICKET,
						'paid_date' =>  $recData['lastactivity'],
						'call_minutes' =>  $recData['timespent'],
						'bill_minutes' =>  $recData['timebillable'],
						'minutes' =>  0,
						'tickets' =>  1,
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

		$_SWIFT = SWIFT::GetInstance();

		$_countContainer = $this->DatabaseImport->QueryFetch("SELECT count(1) AS totalitems FROM (".
			"select 1 from ".TABLE_PREFIX."tickets where ticketpay_info = 1 ".
			"and ticketstatusid in (".$this->ImportManager->closedTickets.") group by ticketid, userid) ti");
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
