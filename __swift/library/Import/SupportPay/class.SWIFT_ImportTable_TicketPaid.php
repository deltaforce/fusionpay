<?php

class SWIFT_ImportTable_TicketPaid extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "ticketid, userid, paytype";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);
		$this->myTableName = TABLE_PREFIX . "sp_ticket_paid";

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

		$this->DatabaseImport->QueryLimit("SELECT userid,ticketid,paytype,unix_timestamp(paid_date) paid_date,".
			"call_minutes,bill_minutes,minutes,tickets,migrated FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
			
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');
		$this->ImportManager->GetImportRegistry()->GetNonCached('chatobject');
		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		foreach ($RecContainer as $recID => $recData) {
			$_count++;

			// Map old staff/user IDs to new ones
			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			if ($recData['paytype'] == SP_PAYTYPE_LIVESUPPORT) {
				$_newChatID = $this->ImportManager->GetImportRegistry()->GetKey('chatobject', $recData['ticketid']);
			} else {
				$_newChatID = $recData['ticketid'];
			}

			$this->Database->AutoExecute($this->myTableName,
				array('userid' =>  $_newUserID,
						'ticketid' =>  $_newChatID,
						'paytype' => $recData['paytype'],
						'paid_date' => $recData['paid_date'],
						'call_minutes' => $recData['call_minutes'],
						'bill_minutes' => $recData['bill_minutes'],
						'minutes' => $recData['minutes'],
						'tickets' => $recData['tickets'],
						'migrated' => $recData['migrated'],
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
