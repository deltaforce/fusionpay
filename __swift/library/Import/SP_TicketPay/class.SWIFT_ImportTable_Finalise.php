<?php

class SWIFT_ImportTable_Finalise extends SWIFT_ImportTable {
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
		global $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', MODULE_SUPPORTPAY);

		if ($this->GetOffset() == 0) {
		}

		$_count = 0;

		// Query the local database this time, not the V3 one.
		$_SWIFT->Database->QueryLimit("select userid from ".TABLE_PREFIX."users ".
			"order by userid",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($_SWIFT->Database->NextRecord()) {
			$RecContainer[] = $_SWIFT->Database->Record;
		}

		$errmsg = "";
		$this->ImportManager->GetImportRegistry()->GetNonCached('user');
		foreach ($RecContainer as $recData) {
			$_count++;
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			$SPFunctions->updateUserCredits($_newUserID,$errmsg);
		}

		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);

		return $_count;
	}

	protected function GetTotal() {
		$_SWIFT = SWIFT::GetInstance();
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		// Query the local database, not the import one.
		$_countContainer = $_SWIFT->Database->QueryFetch(
			"select count(1) as totalitems from ".TABLE_PREFIX."users");

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
