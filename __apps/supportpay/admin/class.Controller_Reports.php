<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.Controller_Reports.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_Reports extends Controller_admin
{
	public function __construct() {
		parent::__construct();

		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");

		return true;
	}
	
	public function __destruct() {
		parent::__destruct();
		return true;
	}

	public function DoImport() {
		global $SPFunctions;

		if (isset($_FILES['filename']) && file_exists($_FILES['filename']['tmp_name']))
		{
			$SPFunctions->ImportReportXML($_FILES['filename']['tmp_name']);
		}

		return $this->View->RenderGrid();
	}
	
	public function Import() {
		$this->View->RenderImportDialog();
		
		return true;
	}
	
	public function Export($repIds = null) {
		global $SPFunctions;
		$_SWIFT = SWIFT::GetInstance();
		
		$XML = $SPFunctions->ExportReportXML($repIds);
		
		$FileName = "SPReports_".date("Y-m-d").'.xml';
		
		header("Content-Type: application/force-download");
		header("Content-length: " . strlen($XML)); 
		header('Content-Disposition: attachment; filename="' . $FileName . '"'); 

		echo $XML;
	}
	
	static public function CloneList($repId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($repId)) {
			$sql = "insert into ".TABLE_PREFIX."sp_reports (title,query,countsql) ".
				"select title,query,countsql ".
				" from ".TABLE_PREFIX."sp_reports WHERE repid in (".BuildIN(array_values($repId)).")";

			$_SWIFT->Database->Query($sql);

			return true;
		}
		
		return false;
	}

	public function Edit($repId = null) {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$errormessage = "";
		
		if (!is_null($repId)) {
			// Need to populate the boxes.
			$Report = $_SWIFT->Database->QueryFetch("select * from ".TABLE_PREFIX."sp_reports WHERE repid=".
				intval($repId));
			if (!isset($Report)) {
				$errormessage = "Unable to find that package.";
			}
		} else {
			// New display. Set some defaults.
			$Report = array("repid" => null, 
				"title" => "New Report", 
				"query" => "ticketmaskid,subject from {prefix}tickets where dateline between {fromdate} and {todate}",
				"countsql" => "{prefix}tickets where dateline between {fromdate} and {todate}",
				);
		}

		$this->View->RenderEditor($Report,$errormessage);
		
		return true;
	}

	public function Update() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$errormessage = "";
		
		// Validate
		
		if ($errormessage == "") {
			// Do the insert.
			
			if (!empty($_POST["repid"])) {
				$sql = 
					"update ".TABLE_PREFIX."sp_reports set ".
					"title='".$_SWIFT->Database->Escape($_POST["title"])."',".
					"hash='".$SPFunctions->GenReportHash($_POST)."',".
					"query='".$_SWIFT->Database->Escape($_POST["query"])."',".
					"countsql='".$_SWIFT->Database->Escape($_POST["countsql"])."'".
					" where repid=".intval($_POST["repid"]); 
			} else {
				$sql = "insert into ".TABLE_PREFIX."sp_reports (title,query,countsql,hash)".
					" VALUES ('".$_SWIFT->Database->Escape($_POST["title"])."','".$_SWIFT->Database->Escape($_POST["query"])."',".
					"'".$_SWIFT->Database->Escape($_POST["countsql"]).",'".
					$SPFunctions->GenReportHash($_POST)."')";
			}
			$_SWIFT->Database->Execute($sql);
			$errormessage = $_SWIFT->Database->FetchLastError();
			if (!empty($errormessage)) {
				SWIFT::Error("SupportPay",$errormessage);
				return $this->View->RenderEditor($_POST);
			} else {
				$this->Router->SetAction("Main");
				return $this->Main();
			}
		} else {
			// Pass the error back to the edit procedure and re-populate.
			return $this->View->RenderEditor($_POST);
		}

		return $this->Main();
	}
	
	public function GetDates($repId) {
		$this->View->RenderDateDialog($repId);
		
		return true;
	}
	
	public function Download($repId,$fromDate = null, $toDate = null) {
		$_SWIFT = SWIFT::GetInstance();
		
		$RepDetails = $_SWIFT->Database->QueryFetch("select * from ".TABLE_PREFIX."sp_reports where repid = ".intval($repId));
		if (empty($RepDetails["title"])) {
			SWIFT::Error("SupportPay","Report not found");
		} else {
			// The report at least exists. Try and CSV it.
			$FileName = Clean($RepDetails["title"])."_".date("Y-m-d").'.csv';
			$NewFile = "";
			
			header("Content-Type: application/force-download");
//			header("Content-length: " . filesize($NewFile)); 
			header('Content-Disposition: attachment; filename="' . $FileName . '"'); 

			$RepDetails["query"] = str_replace("{fromdate}",intval($fromDate),$RepDetails["query"]);
			$RepDetails["query"] = str_replace("{todate}",intval($toDate),$RepDetails["query"]);
			$RepDetails["countsql"] = str_replace("{fromdate}",intval($fromDate),$RepDetails["countsql"]);
			$RepDetails["countsql"] = str_replace("{todate}",intval($toDate),$RepDetails["countsql"]);
			$RepDetails["query"] = str_replace("{prefix}",TABLE_PREFIX,$RepDetails["query"]);
			$RepDetails["countsql"] = str_replace("{prefix}",TABLE_PREFIX,$RepDetails["countsql"]);
			
			$doneHdr = false;
			$Record = $_SWIFT->Database->Query("select " . $RepDetails["query"]);
			while ($_SWIFT->Database->NextRecord()) {
				if (!$doneHdr) {
					foreach ($_SWIFT->Database->Record as $title => $contents) {
						$title = ucwords($title);
						if (false != strpos($title,",")) {
							$title = '"' . str_replace('"','\"',$title) . '"';
						}
						echo $title . ",";
					}
					echo "\n";
					$doneHdr = true;
				}
				
				foreach ($_SWIFT->Database->Record as $title => $contents) {
					if (false != strpos($contents,",")) {
						// Contains a comma itself.
						$contents = '"' . str_replace('"','\"',$contents) . '"';
					}
					echo $contents . ",";
				}
				echo "\n";
			}
		}

//		$this->View->RenderResults($repId);
		
		return true;
	}
	
	public function View($repId) {
		$this->View->RenderResults($repId);
		
		return true;
	}
	
	static public function DeleteList($repId) {
		$_SWIFT = SWIFT::GetInstance();
		
		if (is_array($repId)) {
			$sql = "DELETE FROM ".TABLE_PREFIX."sp_reports WHERE repid in (".BuildIN(array_values($repId)).")";
			if (!$_SWIFT->Database->Execute($sql))  {
				SWIFT::Error("SupportPay",$_SWIFT->Database->FetchLastError());
			} else {
				SWIFT::Info("SupportPay",$_SWIFT->Language->Get('sp_repdeleted'));
			}
			return true;
		}
		
		return false;
	}
	
	public function Main() {
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions;

		$this->UserInterface->Header("SupportPay > " . $this->Language->Get('sp_rppagetitle'), 1,
			$SPFunctions->findAdminBar("SupportPay"));

		$SPFunctions->checkLicense();

		$this->View->RenderGrid();
		
		$this->UserInterface->Footer();
		return true;
	}
}

?>
