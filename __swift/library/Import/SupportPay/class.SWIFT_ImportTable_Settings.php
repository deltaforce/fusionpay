<?php

class SWIFT_ImportTable_Settings extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "settingid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "settings";

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

		$moduleName = (class_exists('SWIFT_App') ? 'app' : 'module');
		if ($this->GetOffset() == 0) {
			$this->Database->Query("DELETE FROM " . $this->myTableName . 
				" WHERE section = 'settings' and vkey in ".
				"(select name from ".TABLE_PREFIX."settingsfields where ".$moduleName." = 'supportpay')");
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("SELECT * FROM " . $this->myTableName . 
			" WHERE section = 'settings' and vkey in ".
			"(select name from ".TABLE_PREFIX."settingsfields where ".$moduleName." = 'supportpay') ".
				"ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			// Don't use sortcolumn here, it's a composite key.
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('ticketstatus');
		$this->ImportManager->GetImportRegistry()->GetNonCached('department');

		foreach ($RecContainer as $recData) {
			$_count++;
			
			if ($recData["vkey"] == "sp_statusclosed") {
				$recData["data"] = $this->ImportManager->GetImportRegistry()->GetKey('ticketstatus', $recData["data"]);
			} elseif ($recData["vkey"] == "sp_chargedepts") {
				$dummy = unserialize($recData["data"]);
				if (is_array($dummy)) {
					foreach ($dummy as &$oldDeptId) {
						$oldDeptId = $this->ImportManager->GetImportRegistry()->GetKey('department', $oldDeptId);
					}
				}
				$recData["data"] = serialize($dummy);
			}

			if (substr($recData["data"],0,2) == "a:") {
				$dummy = unserialize($recData["data"]);
				if (is_array($dummy)) {
					// It's serialized data.
					$recData["data"] = "SERIALIZED:" . $recData["data"];
				}
			}
			
			$this->Database->AutoExecute($this->myTableName,
				array('section' =>  $recData['section'],
						'vkey' =>  $recData['vkey'],
						'data' => $recData['data']
						),
						'INSERT');
		}

		$LicKey = $this->DatabaseImport->QueryFetch("SELECT data FROM " . $this->myTableName . 
			" WHERE section = 'settings' and vkey = 'sp_license'");
		if (!empty($LicKey["data"])) {
			$this->Database->Execute("DELETE FROM " . $this->myTableName . 
				" WHERE section = 'settings' and vkey = 'sp_license'");
			$this->Database->AutoExecute($this->myTableName,
				array('section' =>  'settings',
						'vkey' =>  'sp_license',
						'data' => $LicKey['data']
						),
					'INSERT');
		}
		$_SWIFT = SWIFT::GetInstance();
		$_SWIFT->Settings->RebuildCache();
		
		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);
		return $_count;
	}

	protected function GetTotal() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$moduleName = (class_exists('SWIFT_App') ? 'app' : 'module');
		$_countContainer = $this->DatabaseImport->QueryFetch(
			"SELECT COUNT(*) AS totalitems FROM " . $this->myTableName .
			" WHERE section = 'settings' and vkey in ".
			"(select name from ".TABLE_PREFIX."settingsfields where ".$moduleName." = 'supportpay')");
				
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
