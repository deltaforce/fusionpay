<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/admin/class.View_SPImport.php $, $Change: 2808 $, $DateTime: 2012/03/23 19:56:31 $ -->
<?php
/**
 * =======================================
 * ###################################
 * SWIFT Framework
 *
 * @package	SWIFT
 * @author	Kayako Infotech Ltd.
 * @copyright	Copyright (c) 2001-2009, Kayako Infotech Ltd.
 * @license	http://www.kayako.com/license
 * @link		http://www.kayako.com
 * @filesource
 * ###################################
 * =======================================
 */

/**
 * The Import View Management Class
 *
 * @author Varun Shoor
 */
class View_SPImport extends SWIFT_View
{
	/**
	 * Constructor
	 *
	 * @author Varun Shoor
	 * @return bool "true" on Success, "false" otherwise
	 */
	public function __construct()
	{
		parent::__construct();

		return true;
	}

	/**
	 * Destructor
	 *
	 * @author Varun Shoor
	 * @return bool "true" on Success, "false" otherwise
	 */
	public function __destruct()
	{
		parent::__destruct();

		return true;
	}

	/**
	 * Render the Import Form
	 *
	 * @author Varun Shoor
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Exception If Invalid Data is Provided
	 */
	public function Render()
	{
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded())
		{
			return false;
		}

		$this->UserInterface->Start(get_class($this), '/Base/Import/RenderForm', SWIFT_UserInterface::MODE_INSERT, false);

		$this->UserInterface->Toolbar->AddButton($this->Language->Get('next'), 'icon_next.gif');
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('help'), 'icon_help.gif', SWIFT_Help::RetrieveHelpLink('import'), SWIFT_UserInterfaceToolbar::LINK_NEWWINDOW);

		/*
		 * ###############################################
		 * BEGIN GENERAL TAB
		 * ###############################################
		 */
		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabimport'), 'icon_import.png', 'general', true);

		$_importProductList = SWIFT_ImportManager::GetProductList();
		foreach ($_importProductList as $_productName)
		{
			if ($_productName != "SupportPay" && $_productName != "SP_TicketPay")
				continue;

			$_SWIFT_ImportManagerObject = SWIFT_ImportManager::GetImportManagerObject($_productName);
			if (!$_SWIFT_ImportManagerObject instanceof SWIFT_ImportManager || !$_SWIFT_ImportManagerObject->GetIsClassLoaded())
			{
				throw new SWIFT_Exception(SWIFT_INVALIDDATA);
			}

			$_columnContainer = array();
			$_columnContainer[0]['value'] = '<input type="radio" name="productname" class="swiftradio" value="' . $_productName . '" id="productname_' . $_productName . '"' . IIF($_productName == 'SupportPay', ' checked="checked"') . ' />';
			$_columnContainer[0]['width'] = '16';
			$_columnContainer[0]['align'] = 'center';
			$_columnContainer[1]['value'] = '<label for="productname_' . $_productName . '">' . $_SWIFT_ImportManagerObject->GetProductTitle() . '</label>';
			$_columnContainer[1]['align'] = 'left';
			$_columnContainer[1]['class'] = 'tabletitle';

			$_GeneralTabObject->Row($_columnContainer);
		}

		/*
		 * ###############################################
		 * END GENERAL TAB
		 * ###############################################
		 */

		$this->UserInterface->End();

		return true;
	}
	
	/**
	 * Render the Import Form
	 *
	 * @author Varun Shoor
	 * @param SWIFT_ImportManager $_SWIFT_ImportManagerObject
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Exception If Invalid Data is Provided
	 */
	public function RenderForm(SWIFT_ImportManager $_SWIFT_ImportManagerObject)
	{
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded())
		{
			return false;
		}

		$this->UserInterface->Start(get_class($this), '/supportpay/Import/ProcessForm/' . $_POST['productname'], 
			SWIFT_UserInterface::MODE_INSERT, false);

		$this->UserInterface->Toolbar->AddButton($this->Language->Get('start'), 'icon_check.gif');
		$this->UserInterface->Toolbar->AddButton($this->Language->Get('help'), 'icon_help.gif', SWIFT_Help::RetrieveHelpLink('import'), SWIFT_UserInterfaceToolbar::LINK_NEWWINDOW);

		/*
		 * ###############################################
		 * BEGIN GENERAL TAB
		 * ###############################################
		 */
		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabimport'), 'icon_import.png', 'general', true);

		$_SWIFT_ImportManagerObject->RenderForm($_GeneralTabObject);

		/*
		 * ###############################################
		 * END GENERAL TAB
		 * ###############################################
		 */

		$this->UserInterface->End();

		return true;
	}

	/**
	 * Render the Progress
	 *
	 * @author Varun Shoor
	 * @param SWIFT_ImportManager $_SWIFT_ImportManagerObject
	 * @param SWIFT_ImportTable $_SWIFT_ImportTableObject
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Exception If Invalid Data is Provided
	 */
	public function RenderProgress(SWIFT_ImportManager $_SWIFT_ImportManagerObject, SWIFT_ImportTable $_SWIFT_ImportTableObject)
	{
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded())
		{
			return false;
		}

		$_logContainer = $_SWIFT_ImportManagerObject->GetLog();
		$_percentage = $_SWIFT_ImportManagerObject->GetProcessedPercent();
		$_totalRecordCount = $_SWIFT_ImportManagerObject->GetTotalRecordCount();
		$_startTime = $_SWIFT_ImportManagerObject->GetStartTime();
		$_recordsProcessed = $_SWIFT_ImportManagerObject->GetProcessedRecordCount();
		$_remainingRecords = $_totalRecordCount - $_recordsProcessed;

		$_tableTitle = $_SWIFT_ImportTableObject->GetTableName();

		$_isFailed = false;

		foreach ($_logContainer as $_logEntry)
		{
			if ($_logEntry[0] == SWIFT_ImportManager::LOG_FAILURE)
			{
				$_isFailed = true;
			}
		}

		$this->UserInterface->Start(get_class($this), '/supportpay/Import/ImportProcess/' . $_SWIFT_ImportManagerObject->GetProduct(),
		 SWIFT_UserInterface::MODE_INSERT, false);

		$this->UserInterface->Toolbar->AddButton($this->Language->Get('help'), 'icon_help.gif', SWIFT_Help::RetrieveHelpLink('import'), SWIFT_UserInterfaceToolbar::LINK_NEWWINDOW);

		/*
		 * ###############################################
		 * BEGIN GENERAL TAB
		 * ###############################################
		 */
		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabimport'), 'icon_import.png', 'general', true);

		$_progressHTML = '<tr><td colspan="2">';
		$_progressHTML .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="gridlayoutborder">' . SWIFT_CRLF;
		$_progressHTML .= '<tbody><tr><td class="gridcontentborder">';
		$_progressHTML .= '<table width="100%" border="0" cellspacing="1" cellpadding="4">';

		$_progressHTML .= '<tr><td align="center" valign="top" class="' . IIF($_isFailed == false, 'rowhighlight', 'errorrow') . '" colspan="4" nowrap>';
		$_progressHTML .= '<span class="tabletitle">' . number_format($_percentage, 2) . '%' . IIF($_isFailed, $this->Language->Get('failedext')) . '</span>' . IIF($_percentage < 100 && $_isFailed == false, '<br /><img src="'. SWIFT::Get('themepath') . 'images/barloadingdark.gif" align="absmiddle" border="0" />') . '<BR />';

		if ($_percentage >= 100)
		{
			$_progressHTML .= $this->Language->Get('completed');
		} else if (!$_isFailed) {
			$_progressHTML .= $this->Language->Get('importinprogress');
		}
		$_progressHTML .= '</td></tr>';

		$_averageTime = 0;
		if (!empty($_startTime) && $_totalRecordCount > 0)
		{
			$_averageTime = (DATENOW-$_startTime)/$_totalRecordCount;
		}

		$_timeRemaining = $_remainingRecords * $_averageTime;

		$_progressHTML .= '<tr><td colspan="4" align="left" valign="top" class="settabletitlerowmain2">'. $this->Language->Get('generalinformation') . '</td></tr>';

		$_progressHTML .= '<tr><td align="left" valign="top" class="gridrow1">' . $this->Language->Get('recordsprocessed') . '</td><td align="left" valign="top" class="gridrow2">' . number_format($_recordsProcessed) . '</td><td align="left" valign="top" class="gridrow1">' . $this->Language->Get('totalrecords') . '</td><td align="left" valign="top" class="gridrow2">' . number_format($_totalRecordCount) . '</td></tr>';

		$_progressHTML .= '<tr><td align="left" valign="top" class="gridrow1">' . $this->Language->Get('timeelapsed') . '</td><td align="left" valign="top" class="gridrow2">' . SWIFT_Date::ColorTime(DATENOW-$_startTime) . '</td><td align="left" valign="top" class="gridrow1">' . $this->Language->Get('timeremaining') . '</td><td align="left" valign="top" class="gridrow2">' . SWIFT_Date::ColorTime($_timeRemaining, true) . '</td></tr>';

		$_progressHTML .= '<tr><td align="left" valign="top" class="gridrow1">' . $this->Language->Get('tablename') . '</td><td align="left" valign="top" class="gridrow2">' . htmlspecialchars($_tableTitle) . '</td><td align="left" valign="top" class="gridrow1">&nbsp;</td><td align="left" valign="top" class="gridrow2">&nbsp;</td></tr>';

		$_progressHTML .= '</table></td></tr></tbody></table>';

		//		$_progressHTML .= '<a href="javascript: loadViewportData(\'' . SWIFT::Get('basename') . '/Base/Import/ImportProcess/' . $_SWIFT_ImportManagerObject->GetProduct() . '\');">move</a>';

		if (!$_isFailed && $_percentage < 100)
		{
			$_progressHTML .= '<script language="Javascript" type="text/javascript">';
			$_progressHTML .= 'QueueFunction(function(){';
			$_progressHTML .= 'TabLoading(\'View_Import\', \'general\'); loadViewportData(\'' . SWIFT::Get('basename') . '/supportpay/Import/ImportProcess/' . $_SWIFT_ImportManagerObject->GetProduct() . '\');';
			$_progressHTML .= '});';
			$_progressHTML .= '</script>';
		}

		$_progressHTML .= '</td></tr>';

		$_GeneralTabObject->RowHTML($_progressHTML);

		$_GeneralTabObject->Title($this->Language->Get('importlog'), 'icon_doublearrows.gif');
		foreach ($_logContainer as $_logEntry)
		{
			$_errorMessage = '';
			if (isset($_logEntry[2]))
			{
				$_errorMessage = $_logEntry[2];
			}
			
			$_columnContainer = array();
			$_columnContainer[0]['value'] = htmlspecialchars($_logEntry[1]) . IIF(!empty($_errorMessage), '<div style="padding: 6px;">' . htmlspecialchars($_errorMessage) . '</div>');
			$_columnContainer[0]['align'] = 'left';

			$_icon = 'icon_block.gif';
			if ($_logEntry[0] == SWIFT_ImportManager::LOG_SUCCESS)
			{
				$_icon = 'icon_check.gif';
			} else if ($_logEntry[0] == SWIFT_ImportManager::LOG_WARNING) {
				$_icon = 'icon_exclamation.gif';
			}

			$_columnContainer[1]['width'] = '16';
			$_columnContainer[1]['align'] = 'center';
			$_columnContainer[1]['value'] = '<img src="' . SWIFT::Get('themepathimages') . $_icon . '" align="absmiddle" border="0" />';

			$_GeneralTabObject->Row($_columnContainer, IIF($_logEntry[0] == SWIFT_ImportManager::LOG_FAILURE, 'errorrow', ''));
		}

		/*
		 * ###############################################
		 * END GENERAL TAB
		 * ###############################################
		 */

		$this->UserInterface->End();

		return true;
	}
}
?>