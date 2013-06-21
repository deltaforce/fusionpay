<?php

class SWIFT_ImportTable_Matching extends SWIFT_ImportTable {
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
		global $SPFunctions;

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', MODULE_SUPPORTPAY);

		$_count = 0;

		$_SWIFT->Database->QueryLimit("SELECT userid,txid,rem_minutes,rem_tickets".
			" FROM ".TABLE_PREFIX."sp_user_payments WHERE comments = 'TicketPay Migration'
			order by userid, txid",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($_SWIFT->Database->NextRecord()) {
			$RecContainer[] = $_SWIFT->Database->Record;
		}

		foreach ($RecContainer as $dbt) {
			$_count++;

			// No user mapping here, we're querying the local DB.
			$SPFunctions->reconcilePayment(null, null, $dbt["txid"], $dbt["userid"], -$dbt["rem_tickets"], 
				-$dbt["rem_minutes"]);
		}

		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);

		return 1;
	}

	protected function GetTotal() {
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

// Can't select the actual number here, they've not been transferred yet.
/*
		$_countContainer = $_SWIFT->Database->QueryFetch(
			"SELECT count(1) as totalitems".
			" FROM ".TABLE_PREFIX."sp_user_payments WHERE comments = 'TicketPay Migration'");
		if (isset($_countContainer['totalitems'])) {
			return $_countContainer['totalitems'];
		}

		return 0;
		*/
		
		return 1;
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
