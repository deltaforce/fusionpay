<?php

class SWIFT_ImportTable_Reports extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "repid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_reports";

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

		$this->DatabaseImport->QueryLimit("SELECT * FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recData) {
			$_count++;

			if (!is_null($recData["query"]) && !is_null($recData["countsql"])) {
				$this->Database->AutoExecute($this->myTableName,
					array('repid' =>  $recData['repid'],
							'title' =>  $recData['title'],
							'query' => $recData['query'],
							'countsql' => $recData['countsql'],
							),
						'INSERT');
			}
		}

		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);
		return $_count;
	}

	protected function GetTotal() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		if ($this->GetImportManager()->oldVersion < 1.4) {
			// Table doesn't exist.
			return 0;
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
