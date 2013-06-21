<?php

class SWIFT_ImportManager_SP_TicketPay extends SWIFT_ImportManager
{
	public $DatabaseImport = false;
	private $tp_installed = false;
	public $closedTickets = "";
	
	public function __construct() {
		$name = "DO NOT USE THIS IMPORTER";
		
		// Are we being called from the main import manager? If so, don't use.
		$stack = debug_backtrace();
		foreach ($stack as &$element) {
			if (isset($element["class"]) && $element["class"] == "View_SPImport") {
				$name = "TicketPay on Kayako V3";
			}
		}

		parent::__construct('SP_TicketPay', $name);

		return true;
	}

	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function GetImportTables() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_tableList = array("PointPackages","TicketPackages","TicketPaid","MinutePaid","PackagePaid",
			"PointPayments","TicketPayments","PointAudit","TicketAudit","PaymentSummary",
			"Matching","Finalise","Users");

		return $_tableList;
	}

	public function RenderForm(SWIFT_UserInterfaceTab $_TabObject)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		parent::RenderForm($_TabObject);

		$_TabObject->Text('dbhost', 'Database Host', '', '127.0.0.1');
		$_TabObject->Text('dbname', 'Database Name', '', 'swift');
		$_TabObject->Text('dbusername', 'Database Username', '', 'root');
		$_TabObject->Password('dbpassword', 'Database Password', '', '');
		$_TabObject->Number('dbport', 'Database Port', '', '3306');
		$_TabObject->Number('tp_multiplier', 'TicketPay "Point" in Minutes', '', '1');

		return true;
	}

	public function ProcessForm()
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		parent::ProcessForm();

		$_SWIFT_DatabaseObject = false;

		try
		{
			$_SWIFT_DatabaseObject = new SWIFT_Database(true, $_POST['dbhost'], $_POST['dbport'], $_POST['dbname'], $_POST['dbusername'], $_POST['dbpassword']);
		} catch (SWIFT_Exception $_SWIFT_ExceptionObject) {
			SWIFT::Error('Database Connection Failed', 'Unable to connect to database using the provided details.');

			return false;
		}

		if (!$_SWIFT_DatabaseObject instanceof SWIFT_Database || !$_SWIFT_DatabaseObject->IsConnected())
		{
			SWIFT::Error('Database Connection Failed', 'Unable to connect to database using the provided details.');

			return false;
		}

		$this->ImportRegistry->UpdateKey('database', 'dbhost', $_POST['dbhost']);
		$this->ImportRegistry->UpdateKey('database', 'dbport', $_POST['dbport']);
		$this->ImportRegistry->UpdateKey('database', 'dbname', $_POST['dbname']);
		$this->ImportRegistry->UpdateKey('database', 'dbusername', $_POST['dbusername']);
		$this->ImportRegistry->UpdateKey('database', 'dbpassword', $_POST['dbpassword']);
		$this->ImportRegistry->UpdateKey('database', 'tp_multiplier', $_POST['tp_multiplier']);

		return true;
	}

	public function UpdateForm($_databaseHost, $_databaseName, $_databasePort, $_dbUsername, $_dbPassword, $_multiplier)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$this->ImportRegistry->UpdateKey('database', 'dbhost', $_databaseHost);
		$this->ImportRegistry->UpdateKey('database', 'dbport', $_databasePort);
		$this->ImportRegistry->UpdateKey('database', 'dbname', $_databaseName);
		$this->ImportRegistry->UpdateKey('database', 'dbusername', $_dbUsername);
		$this->ImportRegistry->UpdateKey('database', 'dbpassword', $_dbPassword);
		$this->ImportRegistry->UpdateKey('database', 'tp_multiplier', $_multiplier);

		return true;
	}

	public function ImportPre()
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_SWIFT_DatabaseObject = false;
		$_dbHost = $this->ImportRegistry->GetKey('database', 'dbhost');
		$_dbPort = $this->ImportRegistry->GetKey('database', 'dbport');
		$_dbName = $this->ImportRegistry->GetKey('database', 'dbname');
		$_dbUser = $this->ImportRegistry->GetKey('database', 'dbusername');
		$_dbPassword = $this->ImportRegistry->GetKey('database', 'dbpassword');

		try
		{
			$_SWIFT_DatabaseObject = new SWIFT_Database(true, $_dbHost, $_dbPort, $_dbName, $_dbUser, $_dbPassword);
		} catch (SWIFT_Exception $_SWIFT_ExceptionObject) {
			SWIFT::Error('Database Connection Failed', 'Unable to connect to database using the provided details.');

			return false;
		}

		if (!$_SWIFT_DatabaseObject instanceof SWIFT_Database || !$_SWIFT_DatabaseObject->IsConnected())
		{
			SWIFT::Error('Database Connection Failed', 'Unable to connect to database using the provided details.');

			return false;
		}

		$this->DatabaseImport = $_SWIFT_DatabaseObject;

		// TicketPay 'closed' status = ??? tp_td doesn't match on Hamish's database. Possibly just use swticketstatus where statustype == 3
		$tStatus = array();
		$this->DatabaseImport->Query("select ticketstatusid from ".TABLE_PREFIX."ticketstatus where statustype = 3");
		while ($this->DatabaseImport->NextRecord()) {
			$tStatus[] = $this->DatabaseImport->Record["ticketstatusid"];
		}
		if (!empty($tStatus)) {
			$this->closedTickets = buildIN($tStatus);
		} else {
			SWIFT::Error('Migration Failed','No ticket status is marked as "Closed" in your old system');

			return false;
		}

		/*
				$_versionContainer = $this->DatabaseImport->QueryFetch("SELECT * FROM " . TABLE_PREFIX . "settings WHERE vkey = 'version'");
				if (!isset($_versionContainer['data']) || empty($_versionContainer['data']) || version_compare($_versionContainer['data'], '3.20.00') == -1)
				{
					SWIFT::Error('Version Check Failed', 'Unable to import as minimum product version required is: 3.20.00, please upgrade your existing version 3 helpdesk to continue the import process.');

					return false;
				}
		*/
		return true;
	}
}
?>