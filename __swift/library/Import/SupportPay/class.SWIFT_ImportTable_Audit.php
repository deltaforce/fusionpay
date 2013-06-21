<?php

class SWIFT_ImportTable_Audit extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "auditid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_audit";

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
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		// Map old staff/user IDs to new ones
		$importReg = $this->ImportManager->GetImportRegistry();
		$importReg->GetNonCached('user');
		$importReg->GetNonCached('staff');

		if (empty($importReg->_settingsCache['staff'])) {
			SWIFT::Error("SupportPay","Your ImportRegistry has been erased by a Kayako bug. Unable to import.");
			return 0;
		}
		
		foreach ($RecContainer as $recID => $recData) {
			$_count++;

			if ($recData["is_user"])
				$_newStaffID = $importReg->GetKey('user', $recData['staffid']);
			else
				$_newStaffID = $importReg->GetKey('staff', $recData['staffid']);

			if ($_newStaffID == false) $_newStaffID = 0;
			
			$this->Database->AutoExecute($this->myTableName,
				array('staffid' =>  $_newStaffID,
						'auditid' =>  $recData['auditid'],
						'is_user' => $recData['is_user'],
						'created' => $recData['created'],
						'event' => $recData['event']
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
