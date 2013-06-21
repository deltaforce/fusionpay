<!-- $File: //depot/Kayako_SupportPay_V4/kayako/__modules/supportpay/client/class.Controller_ShowChat.php $, $Change: 3311 $, $DateTime: 2013/01/03 08:59:24 $ -->
<?php

class Controller_ShowChat extends Controller_client
{
	public function __construct()
	{
		parent::__construct();
		
		global $SPFunctions;
		SWIFT_Loader::LoadLibrary('SupportPay:SPFunctions', "supportpay");
		$SPFunctions->checkLicense(true);	// Check license silently.

		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Index($chatId=null)
	{
		$_SWIFT = SWIFT::GetInstance();
		global $SPFunctions, $sp_license;
		$wantContents = false;
		
		$chatName = $SPFunctions->IsModuleRegistered("LIVECHAT");
		if (empty($chatName)) {
			SWIFT::Error("SupportPay","You do not have Live Support installed.");
		} else {
			if (empty($chatId)) {
				SWIFT::Error("SupportPay","No Chat session was specified.");
			} else {
				// Check that the session exists, and that the user or manager is entitled to see it.
				if (method_exists('SWIFT_Loader','LoadModel')) {
					SWIFT_Loader::LoadModel('Chat:Chat', $chatName);
				} else {
					SWIFT_Loader::LoadLibrary('Chat:Chat', $chatName);
				}

				$userid = $_SWIFT->User->GetUserID();
				$Rec = $_SWIFT->Database->QueryFetch("select count(1) perm from ".TABLE_PREFIX."chatobjects c ".
					"where c.chatobjectid=".$chatId." and c.userid in ".
					"(select userid from ".TABLE_PREFIX."sp_users where ".$userid." in (payerid, userid))");
				if ($Rec["perm"] > 0) {
					$_SWIFT_ChatObject = @new SWIFT_Chat($chatId);

					if (!empty($_SWIFT_ChatObject)) {
						$_chatDataArray = $_SWIFT_ChatObject->GetConversationArray();

						$_conversationHTML = '';
						foreach ($_chatDataArray as $_key => $_val)
						{
							if ($_val['type'] != SWIFT_ChatQueue::MESSAGE_SYSTEM && $_val['type'] != SWIFT_ChatQueue::MESSAGE_STAFF && $_val['type'] != SWIFT_ChatQueue::MESSAGE_CLIENT)
							{
								continue;
							}

							$_conversationHTML .= '<div class="msgwrapper">';
							if ($this->Settings->Get('livechat_timestamps') == true)
							{
								$_conversationHTML .= '<span class="timestamp">' . $_val['timestamp'] . ' </span>';
							}

							// Process the message 
							if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_CLIENT)
							{
								$_cssClass = 'client';
							} else if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_STAFF) {
								$_cssClass = 'staff';
							} else {
								$_cssClass = 'staff';
							}

							if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_SYSTEM)
							{
								$_conversationHTML .= '<span class="' . $_cssClass . 'name">'.strip_tags($_val['messagehtml']).'</span>';
							} else if ($_val['type'] == SWIFT_ChatQueue::MESSAGE_STAFF || $_val['type'] == SWIFT_ChatQueue::MESSAGE_CLIENT) {
								$_conversationHTML .= '<span class="'.$_cssClass.'name">'.htmlspecialchars($_val['name']).':</span> ';
								$_conversationHTML .= '<span class="'.$_cssClass.'message">'.$_val['messagehtml'].'</span>';
							}

							$_conversationHTML .= '</div>';
							$wantContents = true;
						}
					}
				} else {
					SWIFT::Error("SupportPay","Unable to find that chat session");
				}
			}
		}
		
		$this->UserInterface->Header('home');
		$SPFunctions->assignSectionTitle($_SWIFT->Language->Get("sp_ptshowchat"));
		$this->Template->Render("sp_header");
		if ($wantContents) echo $_conversationHTML;
		$this->Template->Render("sp_footer");
		$this->UserInterface->Footer();
	}	
}