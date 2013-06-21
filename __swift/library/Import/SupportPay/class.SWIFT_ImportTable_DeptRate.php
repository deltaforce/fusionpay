<?php

class SWIFT_ImportTable_DeptRate extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "departmentid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_deptrate";

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

		$this->ImportManager->GetImportRegistry()->GetNonCached('department');
		$_count = 0;

		$this->DatabaseImport->QueryLimit("SELECT * FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recID => $recData) {
			$_count++;

			// Map old staff/user IDs to new ones

			$_newDeptID = $this->ImportManager->GetImportRegistry()->GetKey('department', $recData['departmentid']);

			if ($_newDeptID == false) $_newDeptID = null;
			
			$this->Database->AutoExecute($this->myTableName,
				array('departmentid' =>  $_newDeptID,
						'minuterate' =>  $recData['minuterate'],
						'ticketrate' => $recData['ticketrate'],
						'enabled' => $recData['enabled']
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

		if ($this->GetImportManager()->oldVersion < 1.3) {
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
