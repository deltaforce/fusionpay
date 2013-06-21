<?php

class SWIFT_ImportTable_PointPackages extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "packageid";
	private $myDisplayName;
	private $tp_multiplier = 1;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "pointpackages";

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
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		if ($this->GetOffset() == 0) {
			// First table to copy. Delete any previously-migrated rows in all affected tables.
			$this->Database->Query("delete from ".TABLE_PREFIX."sp_ticket_paid WHERE migrated=".SP_MIGRATED_TICKETPAY);
			$this->Database->Query("delete from ".TABLE_PREFIX."sp_user_payments WHERE migrated=".SP_MIGRATED_TICKETPAY);
			$this->Database->Query("delete from ".TABLE_PREFIX."sp_user_payments_old WHERE migrated=".SP_MIGRATED_TICKETPAY);
			$this->Database->Query("delete from ".TABLE_PREFIX."sp_packages WHERE migrated=".SP_MIGRATED_TICKETPAY.
				" and not exists (select 1 from ".TABLE_PREFIX."sp_user_payments where packageid = pkgid)");
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("SELECT packageid,packagetype,points,amount ".
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
						'minutes' => intval($recData['points'] * $this->tp_multiplier),
						'tickets' => 0,
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
