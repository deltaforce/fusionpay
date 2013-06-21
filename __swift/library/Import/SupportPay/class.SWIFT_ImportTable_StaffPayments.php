<?php

class SWIFT_ImportTable_StaffPayments extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "spid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_staff_payments";

		if (!$this->TableExists($this->myTableName)) {
			$this->SetByPass(true);

			return false;
		}
		
		return true;
	}

	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function Import() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		if ($this->GetOffset() == 0) {
			$this->Database->Query("DELETE FROM " . $this->myTableName);
		}

		$_count = 0;

		if ($this->GetImportManager()->oldVersion < 1.3) {
			$extraFields = ",0 as tttmin, 0 as tttmax";
		} else {
			$extraFields = "";
		}
		
		$this->DatabaseImport->QueryLimit("SELECT *".$extraFields." FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			// Don't use sortcolum here, it's a composite key.
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recData) {
			$_count++;

			$this->Database->AutoExecute($this->myTableName,
				array('spid' =>  $recData['spid'],
						'email' =>  $recData['email'],
						'currency' => $recData['currency'],
						'amount' => $recData['amount'],
						'processor' => $recData['processor'],
						'txid' => $recData['txid'],
						'rundate' => $recData['rundate'],
						'tttmin' => $recData['tttmin'],
						'tttmax' => $recData['tttmax']
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

		$_countContainer = $this->DatabaseImport->QueryFetch("SELECT COUNT(*) AS totalitems FROM " . $this->myTableName);
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
