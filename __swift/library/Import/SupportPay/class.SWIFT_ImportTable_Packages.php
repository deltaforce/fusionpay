<?php

class SWIFT_ImportTable_Packages extends SWIFT_ImportTable {
	private $myTableName;
	private $mySortColumn = "pkgid";
	private $myDisplayName;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "sp_packages";

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

		$this->DatabaseImport->QueryLimit("SELECT pkgid,title,description,img_url,unix_timestamp(pkg_commence) pkg_commence, ".
			"unix_timestamp(pkg_expire) pkg_expire,duration,minutes,tickets,price,enabled,startup,migrated ".
			" FROM " . $this->myTableName . " ORDER BY ".$this->mySortColumn." ASC",
			 $this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[$this->DatabaseImport->Record[$this->mySortColumn]] = $this->DatabaseImport->Record;
		}

		foreach ($RecContainer as $recID => $recData) {
			$_count++;
			
			if ($recData["pkg_expire"] > mktime(0,0,0,12,31,2037)) {
				$recData["pkg_expire"] = null;
			}
			
			$this->Database->AutoExecute($this->myTableName,
				array('pkgid' =>  $recData['pkgid'],
						'title' =>  $recData['title'],
						'description' => $recData['description'],
						'img_url' => $recData['img_url'],
						'pkg_commence' => $recData['pkg_commence'],
						'pkg_expire' => (empty($recData['pkg_expire']) ? null : $recData['pkg_expire']),
						'duration' =>  $recData['duration'],
						'minutes' => $recData['minutes'],
						'tickets' => $recData['tickets'],
						'price' => $recData['price'],
						'enabled' => $recData['enabled'],
						'startup' => ($recData['startup'] != 0 ? -1 : 0),
						'migrated' => $recData['migrated']
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
