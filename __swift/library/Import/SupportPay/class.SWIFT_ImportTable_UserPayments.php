<?php

class SWIFT_ImportTable_UserPayments extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "txid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_user_payments";

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

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');

		// Cache the package expiry dates
		$pkgexp = array();
		$this->Database->Query("select pkgid, duration from ".TABLE_PREFIX."sp_packages where duration is not null");
		while ($this->Database->NextRecord()) {
			$pkgexp[$this->Database->Record["pkgid"]] = $this->Database->Record["duration"] * 86400;
		}

		if ($this->GetImportManager()->oldVersion < 1.6) {
			$extraFields = "0 as ";
		} else {
			$extraFields = "";
		}
		
		$this->DatabaseImport->QueryLimit("SELECT userid,txid,minutes,tickets,rem_minutes,rem_tickets,".
			"cost,currency,paidby,comments,packageid,unix_timestamp(created) created,".
			"pending,ticketid,paytype,migrated,processor,proc_txid,".$extraFields." tax ".
			" FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());

		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recID => $recData) {
			$_count++;

			// Map old staff/user IDs to new ones
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);

			$this->Database->AutoExecute($this->myTableName,
				array('userid' =>  $_newUserID,
						'txid' =>  $recData['txid'],
						'minutes' => $recData['minutes'],
						'tickets' => $recData['tickets'],
						'rem_minutes' => $recData['rem_minutes'],
						'rem_tickets' => $recData['rem_tickets'],
						'cost' => $recData['cost'],
						'currency' => $recData['currency'],
						'paidby' => $recData['paidby'],
						'comments' => $recData['comments'],
						'packageid' => $recData['packageid'],
						'created' => $recData['created'],
						'pending' => $recData['pending'],
						'ticketid' => $recData['ticketid'],
						'paytype' => $recData['paytype'],
						'migrated' => $recData['migrated'],
						'processor' => $recData['processor'],
						'proc_txid' => $recData['proc_txid'],
						'tax' => (isset($recData['tax']) ? $recData['tax'] : 0),
						'expiry' => (isset($recData['pkgid']) && ($recData['rem_minutes'] > 0 || $recData['rem_tickets'] > 0)) ?
							$recData['created'] + $pkgexp[$recData['pkgid']] : null
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
