<?php

class SWIFT_ImportTable_PaymentSummary extends SWIFT_ImportTable {
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
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		if ($this->GetOffset() == 0) {
			// Delete nothing for this one, any previously-migrated records were deleted by PointPackages.
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("select uo.userid,-coalesce(sum(pt.amount),0) as mins, -coalesce(sum(tt.amount),0) as tkts, ".
			"-coalesce(sum(pt.amount),0) as rem_mins, -coalesce(sum(tt.amount),0) as rem_tkts ".
			" from ".TABLE_PREFIX."users uo ".
			"left join (select userid,sum(amount) as amount from ".TABLE_PREFIX."points ".
						"where support_type = 'ticket' group by userid) pt ".
					"on (pt.userid = uo.userid) ".
			"left join (select userid,sum(1) as amount from ".TABLE_PREFIX."tickets ".
						"where ticketpay_info = 1 group by userid) tt on (tt.userid = uo.userid) ".
			"group by uo.userid having sum(pt.amount) > 0 or sum(tt.amount) > 0",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');

		foreach ($RecContainer as $recData) {
			$_count++;

			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			$this->Database->AutoExecute(TABLE_PREFIX."sp_user_payments",
				array(  'userid' =>  $_newUserID,
						'minutes' =>  $recData['mins'] * $this->tp_multiplier,
						'tickets' =>  $recData['tkts'],
						'rem_minutes' =>  $recData['rem_mins'] * $this->tp_multiplier,
						'rem_tickets' =>  $recData['rem_tkts'],
						'cost' => 0,
						'currency' => $_SWIFT->Settings->Get('sp_currency'),
						'paidby' => $_SWIFT->Language->Get("sp_sysname"),
						'comments' => 'TicketPay Migration',
						'migrated' =>  SP_MIGRATED_TICKETPAY
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

		$_countContainer = $this->DatabaseImport->QueryFetch("select count(1) as totalitems
			 from (select uo.userid from ".TABLE_PREFIX."users as uo
			 left join (select userid,sum(amount) as amount from ".TABLE_PREFIX."points where support_type = 'ticket' group by userid) as pt on (
				 pt.userid = uo.userid
			 )
			left join (select userid,sum(1) as amount from ".TABLE_PREFIX."tickets where ticketpay_info = 1 group by userid) as tt on (
				tt.userid = uo.userid
			 )
			group by uo.userid
			having sum(pt.amount) > 0 or sum(tt.amount) > 0) sm");

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
