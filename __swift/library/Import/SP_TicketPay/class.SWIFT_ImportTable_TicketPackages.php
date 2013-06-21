<?php

class SWIFT_ImportTable_TicketPackages extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "packageid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "ticketpackages";

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

		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		if ($this->GetOffset() == 0) {
			// Delete nothing for this one, any previously-migrated packages were deleted by PointPackages.
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("SELECT packageid,packagetype,tickets,amount ".
			" FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recID => $recData) {
			$_count++;
			
			$this->Database->AutoExecute(TABLE_PREFIX."sp_packages",
				array(  'title' =>  $recData['packagetype'],
						'description' => 'Imported from TicketPay',
						'pkg_commence' => time(),
						'pkg_expire' => null,
						'minutes' => 0,
						'tickets' => $recData['tickets'],
						'price' => $recData['amount'],
						'enabled' => 1,
						'startup' => 0,
						'migrated' => SP_MIGRATED_TICKETPAY
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
