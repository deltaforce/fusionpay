<?php

class SWIFT_ImportTable_Users extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "userid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_users";

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

		$_oldChatObjectIDList = $_chatHitContainer = array();

		$this->DatabaseImport->QueryLimit("SELECT * FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');

		foreach ($RecContainer as $recID => $recData) {
			$_count++;

			// Map old staff/user IDs to new ones

//			$_newStaffID = $this->ImportManager->GetImportRegistry()->GetKey('staff', $recData['staffid']);
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			$_newPayerID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['payerid']);
//			$_newDeptID = $this->ImportManager->GetImportRegistry()->GetKey('department', $recData['staffid']);

			if (empty($_newUserID)) {
				$this->ImportManager->AddToLog('Not importing user #' . htmlspecialchars($recData['userid']) . " due to missing data.", 
					SWIFT_ImportManager::LOG_WARNING);
				continue;
			}
			
			if ($_newPayerID == false) $_newPayerID = null;
			
			$this->Database->AutoExecute($this->myTableName,
				array('userid' =>  $_newUserID,
						'guid' =>  $recData['guid'],
						'affiliate' => $recData['affiliate'],
						'aff_timestamp' => $recData['aff_timestamp'],
						'minutes' => $recData['minutes'],
						'tickets' => $recData['tickets'],
						'payerid' => $_newPayerID,
						'discount' => $recData['discount'],
						'acctmgr' => $recData['acctmgr']
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
