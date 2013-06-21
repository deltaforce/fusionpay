<?php

$__LANG = array (
		'supportpay' => 'SupportPay',		// General title text
		'sps_wording' => 'Wording',			// Wording section of settings page
		'sps_paypal' => 'PayPal',			// Paypal section of settings page
		'sps_payment' => 'Payment',			// Payment section of settings page
		'sps_affiliate' => 'Affiliate',		// Word used to describe affiliate system
		'sps_acctmgr' => 'Account Management',
		'sps_worldpay' => 'WorldPay',
		'sps_gcheckout' => 'Google Checkout',
		'sps_2co' => '2Checkout',
		'sps_staffpay' => 'Staff Payments',
		'sps_authorizenet' => 'Authorize.net',
		'sps_none' => 'None',				// Message for 'no payment processing available'
		'sps_unknown' => 'Unknown',			// Shouldn't ever be used... unknown payment provider records.
		
		////////////////////////////////////////////////////////////
		// Settings titles
		'sp_dummy' => 'That\'s all, folks!',
		
		'sp_erroremail' => 'Email this address with payment problems',
		'd_sp_erroremail' => 'Any error messages from payment gateways go here',
		'sp_minutetxt' => 'Minute Text',
		'd_sp_minutetxt' => 'Word to use for <b>minute</b> (single)',
		'sp_minutestxt' => 'Minutes Text',
		'd_sp_minutestxt' => 'Word to use for <b>minutes</b> (plural)',
		'sp_tickettxt' => 'Ticket Text',
		'd_sp_tickettxt' => 'Word to use for <b>ticket</b> (single)',
		'sp_ticketstxt' => 'Tickets Text',
		'd_sp_ticketstxt' => 'Word to use for <b>tickets</b> (plural)',
		'sp_packagetxt' => 'Package Text',
		'd_sp_packagetxt' => 'Word to use for <b>package</b> (single)',
		'sp_packagestxt' => 'Packages Text',
		'd_sp_packagestxt' => 'Word to use for <b>packages</b> (plural)',
		'sp_version' => 'Version',
		
		'sp_paypaluserid' => 'Live Account Name',
		'd_sp_paypaluserid' => 'Your PayPal <b>Live</b> API account name',
		'sp_paypalpasswd' => 'Live Account Password',
		'd_sp_paypalpasswd' => 'Your PayPal <b>Live</b> API Password',
		'sp_paypalsign' => 'Live Account Signature',
		'd_sp_paypalsign' => 'Your PayPal <b>Live</b> API Signature',
		
		'sp_paypalsbuserid' => 'Sandbox Account Name',
		'd_sp_paypalsbuserid' => 'Your PayPal <b>Sandbox</b> API account name',
		'sp_paypalsbpasswd' => 'Sandbox Account Password',
		'd_sp_paypalsbpasswd' => 'Your PayPal <b>Sandbox</b> Sandbox API Password',
		'sp_paypalsbsign' => 'Sandbox Account Signature',
		'd_sp_paypalsbsign' => 'Your PayPal <b>Sandbox</b> Sandbox API Signature',
		
		'sp_paypallive' => 'Use PayPal Live Servers',
		'd_sp_paypallive' => 'Use the real PayPal server or the sandbox?',
		'sp_paypalipn' => 'Override IPN',
		'd_sp_paypalipn' => 'Override you account\'s default IPN URL.',
		'sp_paypalipnurl' => 'IPN Callback URL',
		'd_sp_paypalipnurl' => 'PHP Page that PayPal should call when a payment changes.',
		'sp_paypalimgurl' => 'Use Header Image',
		'd_sp_paypalimgurl' => 'Header image URL (should be https://), image 750 x 90',
		'sp_paypalbgcolor' => 'Header Background Colour',
		//		'd_sp_paypalbgcolor' => '',
		'sp_paypalformcolor' => 'Form Background Colour',
		//		'd_sp_paypalformcolor' => '',
		'sp_paypallocale' => 'Order Page Region',
		'd_sp_paypallocale' => 'PayPal\'s login process will be tailored for this region.',
		'sp_currency' => 'Currency',
		'd_sp_currency' => 'Request payments in this currency.',
		'sp_paypalwpp' => 'Process Credit Cards using',
		'd_sp_paypalwpp' => 'You can use Website Payments Pro if you have a suitable PayPal account.',
		'sp_wp_md5pass' => 'WorldPay MD5 Password',
		'd_sp_wp_md5pass' => 'Shared secret used to secure transactions.',
		'sp_wp_callbackpw' => 'WorldPay Callback Password',
		'd_sp_wp_callbackpw' => 'WorldPay will use this password to authenticate their payment reports to Kayako',
		'sp_no_purchase_found' => 'No purchase data could be found.',
		'sp_gco_merchantid' => 'Merchant ID',
		'd_sp_gco_merchantid' => 'Your personal Google Checkout Merchant ID',
		'sp_gco_sandboxid' => 'Sandbox Merchant ID',
		'd_sp_gco_sandboxid' => 'Your Google Checkout Merchant ID for the Sandbox',
		'sp_gco_buymessage' => 'Your purchase will be added to your account in a few minutes\' time.',
		
		'sp_gcolive' => 'Use Google Checkout Live Servers',
		'd_sp_gcolive' => 'Use the real Google server or the Sandbox?',
		'sp_gco_merchantkey' => 'Google Checkout Live Merchant Key',
		'sp_gco_sandboxkey' => 'Google Checkout Sandbox Merchant Key',
		'sp_gcauthcheck' => 'Authenticate Notifications',
		'd_sp_gcauthcheck' => 'Switch off <b>only</b> if you can\'t receive authentication headers. See manual.',
		
		'sp_paystafftype' => 'Staff Payment Type',
		'd_sp_paystafftype' => 'Pay staff a fixed amount per ticket or per minute?',
		'sp_sp_minrate' => 'Payment per Minute',
		'd_sp_sp_minrate' => 'Staff will get paid this much per minute billed',
		'sp_sp_tktrate' => 'Payment per Ticket',
		'd_sp_sp_tktrate' => 'Staff will get paid this much per ticket billed',
		
		'sp_accept' => 'Accept Payments',
		'd_sp_accept' => 'Choose whether you accept payment in minutes or tickets.',
		'sp_accept_minutes' => 'Accept Minutes',
		'sp_accept_tickets' => 'Accept Tickets',
		'sp_accept_both' => 'Accept Both',
		'sp_autobilllive' => 'Automatically Bill Live Support',
		'd_sp_autobilllive' => 'Set to No, you can change the billable time for Live Support',
		'sp_minchatmins' => 'Minimum Live Support Bill Time',
		'd_sp_minchatmins' => 'Live Support shorter than this many minutes will not be billed automatically.',
		
		'sp_costpermin' => 'Cost per Minute',
		//		'd_sp_costpermin' => '',
		'sp_costpertkt' => 'Cost per Ticket',
		//		'd_sp_costpertkt' => '',
		'sp_minmin' => 'Minimum Minute Purchase',
		//		'd_sp_minmin' => '',
		
		'sp_mintkt' => 'Minimum Ticket Purchase',
		//		'd_sp_mintkt' => '',
		'sp_minsale' => 'Minimum Sale Value',
		'd_sp_minsale' => 'Don\'t allow a sale for less than this amount',
		'sp_preferticket' => 'Use ticket when billable above',
		'd_sp_preferticket' => 'If both minutes and tickets are available, pay for work using a ticket when the billable time is greater than this value.',
		'sp_chargedepts' => 'Chargeable Departments',
		'd_sp_chargedepts' => 'Only tickets belonging to these departments can be billed.',
		
		'sp_usepackages' => 'Allow Packages',
		'd_sp_usepackages' => 'Sell fixed-price packages of minutes and tickets.',
		'sp_nopackagesdefined' => 'No packages are available.',
		'sp_invoicefooter' => 'Footer text for clients\' invoices',
		//		'd_sp_invoicefooter' => '',
		
		'sp_gateway' => 'Payment Gateway',
		'd_sp_gateway' => 'This company will be used to process payments',
		'sp_worldpayinstid' => 'Installation ID',
		'd_sp_worldpayinstid' => 'Your unique Installation ID provided by WorldPay',
		'sp_worldpaylive' => 'Use WorldPay Live Servers',
		'd_sp_worldpaylive' => 'Choose to use the Live or Test server',
		
		'sp_affiliate' => 'Allow Affiliates',
		'd_sp_affiliate' => 'People can collect credits from others they sign up.',
		'sp_affminmult' => 'Minute Percentage Rate',
		'd_sp_affminmult' => 'Affiliates earn this percentage of their signups\' purchased minutes.',
		'sp_afftktmult' => 'Ticket Percentage Rate',
		'd_sp_afftktmult' => 'Affiliates earn this percentage of their signups\' purchased tickets.',
		'sp_affmsg' => 'Affiliate Button Message',
		'd_sp_affmsg' => 'A <b>short</b> message to go on the affiliation buttons',
		'sp_affexpiry' => 'Affiliate Exipiry',
		'd_sp_affexpiry' => 'Affiliates stop earning credit after this many days',
		'sp_affs_added' => 'Affiliates added OK.',
		'sp_affs_removed' => 'Affiliates removed OK.',
		'sp_affiliates' => 'Affiliates',
		'sp_affnotyourself' => "You can't send a payment offer to yourself.",
		'sp_youremailbad' => 'You do not have a valid email address.',
		'sp_cantsendemail' => 'Unable to send email.',
		'sp_messagesent' => 'Message sent.',
		'sp_amenable' => 'Enable Account Management',
		'd_sp_amenable' => 'Users can be account managers',
		'sp_am_native' => 'Use Kayako Organisations',
		'd_sp_am_native' => 'Set to Yes to use inbuilt Kayako Organisations',
		'sp_accmgrsubject' => 'EMail Subject for Account Manager Signup',
		'd_sp_accmgrsubject' => 'When an account manager sends a signup email, this subject will be used.',
		
		'sps_maintenance' => 'Maintenance',
		'sp_pmexpiry' => 'Retire Old Data After',
		'd_sp_pmexpiry' => 'After this many days, move payment data offline. 0 = Never.',
		
		'sp_anlive' => 'Use Authorize.net Live servers?',
		//'d_sp_anlive' => '',
		'sp_anloginid' => 'Login ID?',
		'd_sp_anloginid' => 'Your Authorize.net API Login ID',
		'sp_antxkey' => 'Transaction Key?',
		'd_sp_antxkey' => 'Your Authorize.net API Transaction Key',
		'sp_anipnurl' => 'Relay Response URL',
		'd_sp_anipnurl' => 'Leave this empty to use the default URL',
		'sp_anmd5' => 'MD5 Secret Key',
		'd_sp_anmd5' => 'The MD5 Secret key that is used to sign the Authorize.net notifications.',
		
		'sp_2covendorid' => 'Vendor Number',
		'd_sp_2covendorid' => 'Your 2Checkout Merchant Vendor number.',
		'sp_2comd5secret' => 'MD5 Secret',
		'd_sp_2comd5secret' => 'The "Secret Word" for verifying notifications.',
		'sp_2colive' => 'Use 2Checkout Live Servers?',
		//'d_sp_2colive' => '',
		'sp_2coipnurl' => 'URL for notifications',
		'd_sp_2coipnurl' => 'Override your account default notification URL',
		
		'sp_2coiscrap' => '2Checkout do not allow integration testing. To complete this test purchase, you must copy and paste these paremeters
			into their "INS Simulator" twice:
			<ol><li>With "ORDER_CREATED - 1 product"</li><li>With "INVOICE_STATUS_CHANGED - pending"</li></ol>',
		////////////////////////////////////////////////////////////
		// Affiliates screen
		'sp_aff_addnew' => 'Add a new affiliate',
		'sp_affparent' => 'Affiliate Parent',
		
		////////////////////////////////////////////////////////////
		// Ticketpay Migration screen
		'sp_pt_tpmigrate' => 'Migration from TicketPay',
		'sp_noticketpay' => 'TicketPay does not appear to be installed.',
		
		////////////////////////////////////////////////////////////
		// New package screen
		'sp_newpkg' => 'Create Package',
		'sp_copypkg' => 'Copy',
		'sp_pkg_copied' => 'Package copied.',
		'sp_pkg_deleted' => 'Package deleted.',
		
		'sp_pkg_enabled' => 'Only Enabled',
		'sp_pkg_disabled' => 'Only Disabled',
		'sp_pkg_adddisabled' => '(Disabled)',
	
		'sp_pkg_inclmins' => 'Number of {minutes} to include with this package',
		'sp_pkg_incltkts' => 'Number of {tickets} to include with this package',
		'sp_pkg_nopayload' => 'This package has no payload.',
		'sp_pkg_notitle' => 'Title must be set.',
		'sp_pkg_nodesc' => 'Description must be set.',
		'sp_pkg_nostart' => 'Start date could not be understood.',
		'sp_pkg_noend' => 'End date could not be understood.',
		'sp_pkg_badend' => 'End date is before the start date.',
		'sp_pkg_badprice' => 'The price is <= 0.',
		
		'sp_pkg_title' => 'Package Title',
		'sp_pkg_title_d' => 'Short title to appear in lists, statements etc.',
		'sp_pkg_descr' => 'Description',
		'sp_pkg_descr_d' => 'Longer description to appear beneath the title.',
		'sp_pkg_imgurl' => 'Image URL',
		'sp_pkg_imgurl_d' => 'An 80 x 64 pixel image to use with this package.',
		'sp_pkg_start' => 'Valid From',
		'sp_pkg_start_d' => 'The package will go on sale on this date.',
		'sp_pkg_end' => 'Valid To',
		'sp_pkg_end_d' => 'The package will not be on sale after this date.',
		'sp_pkg_duration' => 'Payload expires after',
		'sp_pkg_duration_d' => 'The contents of the package will be removed from the user\'s account after this many days. Use 0 for \'Never\'.',
		'sp_pkg_startup' => 'Starter Package',
		'sp_pkg_startup_d' => 'This package is not for sale, but will be added to any new user in the chosen group.',
		'sp_pkg_setenabled' => 'Enabled',
		'sp_pkg_setenabled_d' => 'This package is not available at all.',
		'sp_pkg_cost' => 'Package Cost',
		'sp_pkg_cost_d' => 'Cost of this package in your chosen currency.',
		
		// Section headings
		'sp_pkg_validity' => 'Validity',
		'sp_pkg_payload' => 'Payload',
		'sp_pkg_price' => 'Price',
		
		////////////////////////////////////////////////////////////
		// Others
		'sp_sandbox' => 'Sandbox mode is active. All payments are simulated, no real cash will change hands.',
		'sp_updated' => 'Settings updated.',
		'sp_feat_accts' => 'Accounts',
		'sp_feat_staffpay' => 'Staff Payments',
		'sp_check' => 'Check',
		'sp_paidticket' => 'Payment for Ticket',	// Message in user transaction log
		'sp_paidlivesup' => 'Payment for Live Support',	// Message in user transaction log
		'sp_freelivesup' => 'Free Live Support',	// Message in user transaction log
		'sp_paidother' => 'Other Payment',
		'sp_sysname' => 'Automatic',		// Name for user statements when transaction was system-generated
		'sp_affbonus' => 'Affiliate Bonus', // Text to appear in user statements for affiliate bonus
		'sp_show' => 'Show:',			// Title in some filters
		'sp_versionerror' => 'A database upgrade is required. Please re-run setup.',
		'sp_livesup_updated' => 'Live Support billable time updated.',
		
		// License status messages
		'sp_lic_none' => 'No license has been entered.',
		'sp_lic_invalid' => 'The license has been tampered with.',
		'sp_lic_valid' => 'The license is valid.',
		'sp_lic_bad' => 'The license is unreadable.',
		'sp_lic_ssl' => 'SSL is not available, the license can\'t be decoded.',
		'sp_lic_domain' => 'The license is valid, but not for this domain.',
		'sp_lic_staff' => 'The license is valid, but you have too many active staff.',
		'sp_lic_old' => 'The license is valid, but for an older version of SupportPay.',
		'sp_lic_expired' => 'The license is valid, but has expired.',
		'sp_lic_nosupport' => 'The license is valid, but your support expired before this version was released.',

		// PayPal locale codes
		'sp_pplc_AU' => 'English (Australian)',
		'sp_pplc_DE' => 'German',
		'sp_pplc_FR' => 'French',
		'sp_pplc_GB' => 'English (British)',
		'sp_pplc_IT' => 'Italian',
		'sp_pplc_ES' => 'Spanish',
		'sp_pplc_JP' => 'Japanese',
		'sp_pplc_CN' => 'Chinese',
		'sp_pplc_PL' => 'Polish',
		'sp_pplc_NL' => 'Dutch',
		'sp_pplc_CH' => 'Swiss',
		'sp_pplc_AT' => 'Austrian',
		'sp_pplc_BE' => 'Belgian',
		'sp_pplc_US' => 'English (American)',
		
		// Default values for settings
		'sp_defminute' => 'minute',
		'sp_defminutes' => 'minutes',
		'sp_defticket' => 'ticket',
		'sp_deftickets' => 'tickets',
		'sp_defcurrency' => 'GBP',
		
		// Add Credit page
		'sp_setdscnt' => 'Set Discount',		// Title for Set Discount window
		'sp_setdscntd' => 'Change the discount rate.',	// Description for Set Discount window
		'sp_addcredit' => 'Add Credit',		// Title for Add Credit window
		'sp_addcreditd' => 'Add minutes or tickets to a client free of charge.',	// Description for Add Credit window
		'sp_userid' => 'User',				// Form title for username
		'sp_add' => 'Add',					// Title for both "Add minutes" and "Add tickets"
		'sp_comment' => 'Comment',			// Title for Comment field
		'sp_commentd' => 'This text will be added to the client\'s transaction log.',			// Desription for Comment field
		'sp_nouser' => 'No user specified.',	// Error message when screen is called without userid
		'sp_ac_priced' => 'Value of this transaction. Enter a negative value for a refund.',
		
		// User chat view page
		'sp_ptshowchat' => 'View Live Support Record',
		'sp_chatbillable' => 'Billable',		// Column header
		'sp_chatpaid' => 'Paid',
		'sp_due' => 'Due',
		
		// User payment listings
		'sp_uppagetitle' => 'View Ticket Payments',	// Name of the user payment page
		'sp_ucredittitle' => 'Current Credit',		// Top of the 'current credit' box in user payments screen.
		'sp_uclisttitle' => 'Recent Payments',		// Top of the 'recent payments' box in user payments screen.
		'sp_buytitle' => 'Top up your account',	// Top of the 'buy minutes/tickets' boxout.
		'sp_buymessage' => 'Purchase More',		// Exhortation to spend money as in "Purchase More" + (tickets/minutes)
		'sp_buyer' => 'Added By',					// Field heading
		'sp_comments' => 'Comments',				// Field heading
		'sp_cost' => 'Cost',						// Price of invoice row
		'sp_cleared' => 'Clr',						// Whether a payment is cleared or pending
		'sp_invoice' => 'Print Selected',			// Hover text for printer icon
		'sp_print' => 'Print',						// Column header for printer icons
		'sp_invoicetitle' => 'Statement',			// Title for the actual invoice
		'sp_opurchases' => 'Only Purchases',
		'sp_opayments' => 'Only Payments',
		'sp_oldpayments' => 'Transactions older than {days} days may not appear.',
		
		// User debit listings
		'sp_dppagetitle' => 'View Chargeable Tickets',	// Name of the unpaid ticket page
		'sp_stmterror' => 'You must give either specific transactions or a date range.',
		'sp_notuseracctmgr' => 'You are not this user\'s account manager.',
		'sp_notacctmgr' => 'You are not an account manager.',
		'sp_acmgr_hover' => 'List Account Dependents',
		'sp_acmgr_changed' => 'Account Manager status changed',
		'sp_ounpaid' => 'Only Unpaid',
		'sp_opaid' => 'Only Paid',
		'sp_oall' => 'All',
		
		// User purchase page
		'sp_invpopups' => 'You seem to have a popup blocker running. Please allow popups to see your invoices.',
		'sp_udlisttitle' => 'Unpaid Tickets',		// Top of the 'unpaid tickets' box in user payments screen.
		'sp_buynow' => 'Buy Now',
		'sp_created' => 'Created',
		'sp_payreview' => 'Payment Review',
		'sp_payreviewtext' => 'Please check the details of your payment:',
		
		'sp_paydetails' => 'Payment Details',
		
		// Credit card page
		'sp_cc_cardtype' => 'Card Type',
		'sp_cc_cardno' => 'Card Number',
		'sp_cc_cvv' => 'CVV',
		'sp_cc_startdate' => 'Start Date',
		'sp_cc_enddate' => 'Expiry Date',
		'sp_cc_issue' => 'Issue Number',
		'sp_cc_forename' => 'First Name',
		'sp_cc_surname' => 'Surname',
		'sp_cc_street1' => 'Address 1',
		'sp_cc_street2' => 'Address 2',
		'sp_cc_city' => 'Town',
		'sp_cc_state' => 'Region',
		'sp_cc_country' => 'Country',
		'sp_cc_zip' => 'Postcode',
		
		// Staff ticket-payment listings
		'sp_ptpagetitle' => 'Pay for Closed Tickets',	// Name of the staff pay-now page
		'sp_paywith' => 'Pay with',					// Pay With button prefix
		'sp_closenow' => 'Close Unpaid',
		
		// Staff work payment screen
		'sp_sppertkt' => 'Fixed price per {Ticket}',
		'sp_sppermin' => 'Pay per {Minute} worked',
		
		// Previous staff payments page
		'sp_ptsplist' => 'Staff Payment Runs',
		'sp_sppayrunid' => 'ID',
		'sp_sppayments' => '# Paid',
		'sp_sptotal' => 'Total',
		'sp_spshowrun' => 'Show payment run details',
		
		// Chat pay page
		'sp_plspagetitle' => 'Pay for Live Support',
		'sp_ulslisttitle' => 'Unpaid Live Support Sessions',
		'sp_lsview' => 'View Transcript',
		
		// User management page
		'sp_umpagetitle' => 'User Credits',			// Name of the copied user management page
		'sp_credit' => 'Credit',						// Appended to tickets/minutes text for column header
		'sp_discount' => 'Discount',
		'sp_discounttxt' => 'Percentage discount to grant (-100 to 100); 100 = free, use -ve values to add cost.',
		'sp_awpagetitle' => 'Tickets Awaiting Response',
		'sp_alpagetitle' => 'Affiliate List',
		'sp_chpagetitle' => 'Transaction History',
		'sp_editpkg' => 'Edit Package',
		'sp_accounts_on' => 'User credit may be shown as two numbers; the first is \'available credit\', the second is \'personal credit\'.',
		'sp_acctmgr' => 'IsMgr',		// Is this user an account manager?
		'sp_amname' => 'Manager',	// Who is this user's account manager?

	// Org management page
	'sp_ompagetitle' => 'Organisation Credits',
	
		////////////////////////////////////////////////////////////
		// Account Manager page
		'sp_ptacctmgr' => 'Manage Account',
		'sp_adddep' => 'Add Dependent',
		'sp_deplist' => 'Dependent Users',
		'sp_numtickets' => '# {Tickets}',
		'sp_paidtickets' => 'Paid {Tickets}',
		'sp_paidminutes' => 'Paid {Minutes}',
		'sp_remdependent' => 'Are you sure you want to remove this dependent?',
		'sp_remoffer' => 'Are you sure you want to remove this offer?',
		'sp_offermade' => 'Offer made on',
		
		////////////////////////////////////////////////////////////
		// Dependent offer acceptance
		'sp_ptdepaccept' => 'Accept Dependency Offer',
		'sp_nooffer' => 'No offer was specified.',
		'sp_unknownoffer' => 'We have no record of that offer.',
		'sp_badofferemail' => 'The email of the original offer does not match.',
		'sp_offeraccepted' => 'The offer has been accepted.',
		'sp_offerrejected' => 'The offer has been rejected.',
		'sp_alreadypaid' => ' already pays your bills. Are you sure you want to change?',
		'sp_depoffers' => 'Outstanding Offers',
		'sp_offerid' => 'Offer ID',
		'sp_offertext' => 'has offered to pay your bills. However, he or she will be able to see your tickets. Do you want to accept this offer?',
		'sp_offeraccept' => 'Accept',
		'sp_offerreject' => 'Reject',
		'sp_remacctmgr' => 'Remove your Account Manager',
		'sp_amremoved' => 'Your Account Manager has been removed. They will no longer pay your bills or be able to see your tickets.',
		'sp_amremoved_staff' => 'Account Manager removed OK.',
		'sp_am_depremoved_staff' => 'Account dependent removed OK.',
		'sp_am_current' => 'Your bills are currently paid by',
		'sp_am_willremove' => 'If you remove your Account Manager, they will no longer pay your bills. Do you want to do this?',
		'sp_am_removeddep' => 'User {Dependent} removed from account of {Manager}.',
		'sp_am_removedoffer' => 'The offer has been withdrawn.',
		'sp_unknown_payment_type' => 'Unknown or undefined payment type.',
		
		////////////////////////////////////////////////////////////
		// Dependent offer acceptance
		'sp_ptremacctmgr' => 'Remove Account Manager',
		'sp_noacctmgr' => 'You have no Account Manager.',
		
		////////////////////////////////////////////////////////////
		// Reporting page
		'sp_rppagetitle' => 'Reports',
		'sp_report' => 'Choose Report',
		'sp_rep_start' => 'Start Date',
		'sp_rep_end' => 'End Date',
		'sp_rep_params' => 'Report Parameters',
		'sp_rep_results' => 'Results',
		'sp_downloadcsv' => 'Download as CSV',
		'sp_rep_run' => 'Run Report',
		'sp_rep_refresh' => 'Refresh',
		'sp_rep_nodata' => 'No data found',
		
		////////////////////////////////////////////////////////////
		// Updates page
		'sp_ptupdates' => 'Updates',
		'sp_updateinfo' => 'Update Information',
		'sp_updateerror' => 'Error while retrieving update',
		'sp_updateinvalid' => 'Your license is not current. Please update your license before checking for updates.',
		'sp_supportlink' => 'For support on SupportPay, please visit ',
		
		////////////////////////////////////////////////////////////
		// License Management page
		'sp_ptlicmaint' => 'License Maintenance',
		'sp_newlicdetails' => 'New License Details',
		'sp_oldlicdetails' => 'Existing License Details',
		'sp_licsite' => 'Site',
		'sp_licexpiry' => 'Expiry Date',
		'sp_licsupexpiry' => 'Support Expiry Date',
		'sp_licmaxstaff' => 'Max. Staff',
		'sp_licactstaff' => 'Active Staff',
		'sp_licfeatures' => 'Extra Features',
		'sp_lictype' => 'Type',
		'sp_licvalid' => 'Validity',
		'sp_lickeytitle' => 'License Key',
		'sp_lickeytitle_d' => 'Paste your license key in here, taking care to make no changes to it.',
		'sp_licgetdemo' => 'Get Demo License',
		'sp_licgetannual' => 'Buy 1 Year',
		'sp_licgetpayg' => 'Buy PAYG',
		'sp_warning' => 'Warning',
		'sp_nowpp' => 'Your license does not allow using Website Payments Pro.',
		'sp_noacm' => 'Your license does not allow using Account Management.',
		'sp_noaffiliate' => 'Your license does not allow using Affiliates.',
		'sp_licenseupdated' => 'Your license has been updated.',
		'sp_nolicensegiven' => 'No license was given, not updating.',
		
		////////////////////////////////////////////////////////////
		// Audit Trail
		'sp_ptaudit' => 'Audit Trail',
		'sp_adtevent' => 'Event',
		
		////////////////////////////////////////////////////////////
		// Staff and Admin menu items
		'sp_sm_answer' => 'Answer Tickets',
		'sp_sm_paytkt' => 'Pay Tickets',
		'sp_sm_paylive' => 'Pay Live Support',
		'sp_sm_usercdt' => 'User Credits',
		'sp_sm_orgcdt' => 'Org. Credits',

		'sp_am_manlic' => 'Manage License',
		'sp_am_import' => 'Import',
		'sp_am_updchk' => 'Check for Updates',
		'sp_am_manpkg' => 'Manage Packages',
		'sp_am_tpimp' => 'Migrate Data',
		'sp_am_paystaff' => 'Pay Staff',
		'sp_am_listsp' => 'List Staff Payments',
		'sp_am_rep' => 'Reports',
		'sp_am_audit' => 'Audit',

		////////////////////////////////////////////////////////////
		// User Widgets
		'sp_uw_viewpay' => 'View Ticket Payments',
		'd_sp_uw_viewpay' => 'List previous payments and top up your account.',
		'sp_uw_viewbill' => 'View Ticket Bills',
		'd_sp_uw_viewbill' => 'List tickets with their payments.',
		'sp_uw_mandep' => 'Manage Dependents',
		'd_sp_uw_mandep' => 'Manage people you pay bills for.',
		'sp_uw_manacc' => 'Accept an Account Manager',
		'sp_deps_added' => 'Dependends added OK.',
		'd_sp_uw_manacc' => '{ManagerName} has offered to pay your bills.',	// ManagerName will be replaced at runtime.

// Post V1.0.2031
		'sp_ptcdtlanding' => 'Account Top-up',
		'sp_pthistlanding' => 'Account History',
		'sp_widgetstyle' => '"Buy Credit" Widget',
		'd_sp_widgetstyle' => 'Show "Buy Credit" as a separate control as well as on other pages?',
		'sp_ws_separate' => 'Separate',
		'sp_ws_combined' => 'Combined',
		
		'sp_uw_cdtsum' => 'Buy Credit',
		'd_sp_uw_cdtsum' => 'View and add credit to your account',
		'sp_nossl' => 'OpenSSL is not enabled. Please check your server configuration.',
		'sp_am_language' => 'Language',
		'sp_ptlanguage' => 'Language Helper',
		'sp_langavail' => 'Available Languages',
		'sp_langselect' => 'Select Language to Compare',
		'sp_langdiff' => 'Missing Phrases',
		'sp_langdefault' => 'Default Language',
		'sp_langadmin' => 'Admin Language',
		'sp_langphrase' => 'Phrase',
		'sp_langcontents' => 'Contents',
		'sp_langmissingtext' => 'The following phrases are present in the default language file but missing in your active one.',
		'sp_allowupdatecheck' => 'Allow automatic update checks',
		'sp_thissite' => 'This Site',
		'sp_licsites' => 'Licensed Sites',
		'sp_debug' => 'Extra log output from reconciler',
		'sp_nostaffpay' => 'Your license does not allow Staff Payments.',
		'sp_am_deptrates' => 'Department Rates',
		'sp_dr_mins' => 'Rate per {Minute}',
		'd_sp_dr_mins' => 'Staff receive this much per {minute} worked.',
		'sp_dr_tkts' => 'Rate per {Ticket}',
		'd_sp_dr_tkts' => 'Staff receive this much per {ticket} worked.',
		'sp_sm_paydets' => 'Staff Payments',
		'sp_spaydets' => 'Personal Payment Details',
		'sp_billimmediately' => 'Deduct credit immediately',
		'd_sp_billimmediately' => 'Choose to deduct credit after each billing entry, or after the ticket is closed.',
		'sp_credemail_interval' => 'Minimum interval between credit-change emails',
		'd_sp_credemail_interval' => 'Minimum number of hours between credit-change emails',

		'sp_specificcdt' => 'If you are deducting credit, you can optionally choose a specific credit line to deduct from.',
		'sp_creditline' => 'Available credit items',
		
		// Permissions stuff
		'sp_cananswertkts' => 'Can view "Answer Tickets" page',
		'sp_canpaytkts' => 'Can view "Pay Tickets" page',
		'sp_canpaylive' => 'Can view "Pay Live Support" page',
		'sp_canusercdt' => 'Can view "User Credit" page',
		'sp_canlistaff' => 'Can view "Affiliate Control" page',
		'sp_cansetmgr' => 'Can set User Managers',
		'sp_cansetdscnt' => 'Can set Discount',
		'sp_canchangecredit' => 'Can change credit',
		'sp_cansetoverdraft' => 'Can set overdraft limit',
		'sp_nostaffperms' => 'You do not have permission to access this page.',
		
		
		// VirtueMart
		'sps_virtuemart' => 'VirtueMart',
		'sp_vm_dbhost' => 'Server hosting the VirtueMart database',
		'd_sp_vm_dbhost' => 'Use host:port notation i.e. localhost:3306',
		'sp_vm_dbname' => 'Database hosting the VirtueMart tables',
		'sp_vm_username' => 'Username for the VirtueMart/Joomla Database',
		'sp_vm_password' => 'Password for the VirtueMart/Joomla Database',
		'sp_vm_vendorid' => 'VirtueMart Vendor ID for SupportPay',
		'd_sp_vm_vendorid' => 'Sales for this Vendor will be imported',
		'sp_feat_virtuemart' => 'VirtueMart Integration',
		'sp_feat_whmcs' => 'WHMCS Integration',
		
		// Post V1.0.2081
	'sp_erpagetitle' => 'Edit Reports',
	'sp_am_edrep' => 'Edit Reports',
	'sp_reped_avail' => 'Available Reports',
	'sp_newrep' => 'Create Report',
	'sp_repdeleted' => 'Report has been deleted.',
	'sp_repinserted' => 'Report has been inserted.',
	'sp_repedited' => 'Report has been edited.',
	'sp_repxml' => 'Report XML File',
	'sp_reptitle' => 'Title',
	'sp_repsql' => 'SQL',
	'd_sp_repsql' => 'SQL to produce the report.<br/>Do NOT include the initial "select" keyword.<br/>'.
					'Use "{fromdate}" and "{todate}" to accept dates, and {prefix} for the Kayako table prefix.',
	'sp_repcsql' => 'Count SQL',
	'd_sp_repcsql' => 'SQL to report how many rows to expect.<br/>Do NOT include the initial "select" clause - start after the "FROM" keyword.',
	'sp_bad_sql' => 'Error in syntax, pages not available.',
	
	'sp_statusclosed' => '"Closed" Ticket Status',
	'd_sp_statusclosed' => 'Select which Ticket Status means a closed ticket',
	
	'sp_credit_unknown' => 'Unknown Credit',
	'sp_nochargedept' => 'This department is not chargeable.',
	'sp_feat_nobranding' => 'Branding Removal',

	// Post V1.0.2085
	'sp_refundedticket' => 'Refund for Ticket',	// Message in user transaction log
	'sp_nocredittopost' => 'You must have credit available to post to this department.',
	'sp_autobilltkt' => 'Automatically Bill Tickets',
	'd_sp_autobilltkt' => 'Set to No, you must manually charge for closed tickets.',
	'sp_amexpiry' => 'Account Management offer expiry',
	'd_sp_amexpiry' => "An offer to become someone's account manager expires after this many days.",
	'sp_taxrate' => 'Tax Rate',
	'd_sp_taxrate' => 'If you need to add sales tax, enter the percentage rate here e.g. 17.5',
	'sp_reversetax' => 'Reverse Tax?',
	'd_sp_reversetax' => 'Set to "Yes" if your entered prices include tax, or "No" if tax should be added.',
	'sp_gctaxoride' => 'Override Merchant Tax Rules?',
	'd_sp_gctaxoride' => 'Set to "Yes" to force tax at the rate set above, or "No" to use your Merchant Center tax rules.',
	
	'sp_bad_discount' => 'Discount must be between -100 and 100.',
	
	'settings_sp_perms' => 'SupportPay Permissions',
	'settings_supportpay' => 'SupportPay',
	'template_supportpay' => 'SupportPay',
	
	'sp_noitemsselected' => 'No items selected',
	'sp_recalc_credit' => 'Recalculate Credit',
	'sp_set_acctmgr' => 'Make Manager',
	'sp_is_acctmgr' => 'Is Account Manager?',
	'd_sp_is_acctmgr' => 'This user can buy credits for a group',
	'sp_payment_cleared' => 'Cleared',
	'sp_payment_pending' => 'Pending',
	'sp_tx_credit' => "Transfer the old owner's credit to the new owner?",
	'sp_org_makemanager' => 'Set this account to be a Manager?',
	
	'sp_uw_master' => 'Payments',
	'sp_first' => 'First',
	'sp_last' => 'Last',
	'sp_options' => 'Options',
	'sp_delete' => 'Delete',
	
	'sp_reconciler' => 'SupportPay Reconciler',
	'sp_pendingtx' => 'SupportPay Transaction Checker',
	'sp_paynow' => 'Pay Now',
	'sp_pp_notoken' => 'No Token returned from PayPal',
	
	'sp_itemnum' => 'Item #',
	'sp_itemdesc' => 'Description',
	'sp_itemcount' => 'Item Count',
	'sp_unitprice' => 'Unit Price',
	'sp_tax' => 'Tax',
	'sp_total' => 'Total',
	'sp_amount' => 'Amount',
	
	'sp_am_deptrules' => 'Department Limits',
	'sp_rate_mult' => 'Rate Multiplier',
	'sp_rate_mincdt' => 'Min. Credit',
	
	'sps_overdraft' => 'Overdraft (Payment on Account)',
	'sp_odenable' => 'Allow Overdraft',
	'sp_overdraft' => 'Overdraft', // Column heading in Staff "Users" grid
	'd_sp_odenable' => 'With this set, you can assign "overdraft" credits to selected clients.',
	'sp_odshowinclusive' => 'Show balance including overdraft',
	'd_sp_odshowinclusive' => 'Set to "Yes" to show all available credit or "No" to show overdraft as negative.',
	'sp_bad_overdraft' => 'Overdraft must be 0 (none) or higher.',
	'sp_show_invoice' => 'Show Invoice',
	'sp_oddefault' => 'Default Overdraft Value',
	'd_sp_oddefault' => 'Amount of "minute" credits',
	'sp_invoicesubject' => 'EMail Subject for monthly invoices',
	'sp_invoicesender' => 'EMail Account for monthly invoices',
	'd_sp_invoicesender' => 'The invoices have this as the reply-to address.',
	
	'sp_never' => 'Never',	// Column value for non-expiring payments.
	'sp_expiry' => 'Expiry',// Column header for package/payment expiry.
	
	'sp_authbuyenable' => 'Allow Pre-authorised Payments',
	'sp_recurenable' => 'Allow Recurring Packages',
	'sp_no_preauth' => 'Pre-authorised payments are not enabled.',
	'd_sp_recurenable' => 'Show or hide the "Billing Agreements" button in the Client pages.',
	'sp_saletype_sale' => 'Normal Sale',
	'd_sp_saletype_sale' => 'Buy credits for instant use.',
	'sp_saletype_auth' => 'Pre-Authorised Sale',
	'd_sp_saletype_auth' => 'Authorise a maximum amount of credits that you want to be available for a short period of time. '.
							'You will only be charged for the used portion.',
	'sp_depsupdated' => 'Department rules have been updated.',
	'sp_payment_taken' => 'Payment has been requested.',
	'sp_payerror_title' => 'Payment Error',
	'sp_payerror_message' => 'There was an unexpected problem with this payment.',
	'sp_payerror_details' => 'Payment Details',
	'sp_payerror_topmessage' => 'Please review the messages below to see if you can alter your payment in any way.',
	'sp_payerror_unknowncode' => 'This error has been logged with the site administrator.',
	'sp_payerror_knowncode' => 'Please check your information and re-try the payment.',
	'sp_agree_cancelled' => 'Cancelled by {fullname}',
	'sp_tgroup' => 'Template Groups',
	'sp_tgroup_d' => 'Which template groups this package is available to',
	
	'sp_send_credemail' => 'Credit-change Emails',
	'd_sp_send_credemail' => 'Send email to clients when their credit levels change',
	'sp_credemail_from' => 'Credit-change emails from address',
	'd_sp_credemail_from' => 'Use this email address for sender and reply-to.',
	'sp_credemail_subject' => 'Credit-change emails subject',
	//	'd_sp_credemail_subject' => 'Credit-change emails subject',
	'sp_credemail_threshold_mins' => 'Credit-change email threshold (minutes)',
	'd_sp_credemail_threshold_mins' => 'Only send credit-change emails if less than this many minutes remain',
	'sp_credemail_threshold_tkts' => 'Credit-change email threshold (tickets)',
	'd_sp_credemail_threshold_tkts' => 'Only send credit-change emails if less than this many tickets remain',

	'sp_package_in_use' => 'This package is used in a billing agreement by your clients and can\'t be edited while it\'s active.',
	'sp_recur_period' => 'Recur Period',
	'sp_recur_period_d' => 'Recurring billing period (i.e. this many units)',
	'sp_recur_unit' => 'Recur Unit',	
	'sp_recur_unit_d' => 'Recurring billing units',
	'sp_uw_agreements' => 'Agreements',
	'year' => 'Year',
	'month' => 'Month',
	'week' => 'Week',
	
	'sp_rblisttitle' => 'Billing Agreements',
	'sp_del_agreement' => 'Are you sure you want to stop this billing agreement?',
	'sp_agreement_deleted' => 'Billing agreement deleted.',
	'sp_last_paid' => 'Last Paid',
	'sp_too_many_billing_items' => 'Unable to add multiple items to a recurring billing agreement.',
	'sp_agreementstxt' => 'Text for Billing Agreements',
	'd_sp_agreementstxt' => 'This is the label on the purchase button',
	'sp_addaggmessage' => 'Add a new',
	'sp_agree_id' => 'Agreement ID',
	
	'sp_allusergroups' => 'All Registered Groups',
	
	'sp_agreement' => 'License Agreement',
	'sp_agree_details' => 'Before you can use SupportPay, you must read and agree to the license terms below.',
	'sp_i_agree' => 'I Agree',
	
	'sp_notloggedin' => 'This page is only available if you are logged in.',
	
	'sps_whmcs' => 'WHMCS Integration',
	'sp_whmcs' => 'SupportPay WHMCS Integration',
	'sp_whmcs_enable' => 'Enable WHMCS Integration',
	'sp_whmcs_each' => 'Per-Ticket',
	'sp_whmcs_api_userid' => 'API User ID',
	'sp_whmcs_api_pass' => 'API Password',
	'sp_whmcs_addusers' => 'Create Users Automatically?',
	'd_sp_whmcs_addusers' => 'When a new user is found in WHCMS, add the user to Fusion',
	'sp_whmcs_defaultgroup' => 'Default User Group',
	'd_sp_whmcs_defaultgroup' => 'Assign created users to this group',
	'sp_whmcs_pushmode' => 'Push Transactions to WHMCS',
	'd_sp_whmcs_pushmode' => 'Ticket costs may be pushed to WHMCS for billing',
	'sp_whmcs_api_baseURL' => 'Base URL for WHMCS',
	'sp_whmcs_dateformat' => 'Date Format',
	'd_sp_whmcs_dateformat' => 'This must match the selected date format in WHMCS',
	'sp_whmcs_packages' => 'Create WHMCS Packages?',
	'd_sp_whmcs_packages' => 'Automatically detect credit packages defined in WHMCS?',
	'sp_whmcs_lisfallback' => 'WHMCS LoginShare fallback to Fusion',
	'd_sp_whmcs_lisfallback' => "If WHMCS doesn't authenticate the user, try a Fusion account",

	'sp_upgrade_required' => 'The database requires an upgrade. Please upgrade '.
				'the SupportPay module using the Modules or Apps menu.',

	'sp_pkg_whmcs' => 'WHMCS Package',
	'd_sp_pkg_whmcs' => 'This package is equivalent to an existing WHMCS package',
	'sp_is_whmcs' => 'WHMCS?',
	'sp_whmcs_expired' => 'WHMCS Expired',
	
	'sp_forcebill' => 'Add billable time automatically',
	'sp_forcebill_d' => 'This many minutes of billable time will be set on staff replies automatically',
	'sp_forcebillany' => 'Make billable time a required field',
	'sp_forcebillany_d' => 'Ticket replies will not be able to be posted without entering a value for billable time',
	'sp_forcebillafter' => '... after this many replies',
	'sp_forcebillafter_d' => 'Only apply the automatic billable time after staff have replied this many times.',
	'sp_forcebillmessage' => 'Please enter a value in the Billable Time box on the Reply tab.',
	
	'sp_is_org_acct' => 'Is Org. Acct.',
	'sp_org_nummembers' => '# Members',
	'sp_org_members' => 'List Members',
	'sp_org_owners' => 'Org. Owners', // i.e. "# org owners" error in org management screen
	'sp_org_owner' => 'Org. Owner', // Column heading
	
	'sp_testsettings' => 'Test Settings',
	'sp_paypal_test' => 'Test PayPal',
	'sp_paypal_ok' => 'PayPal credentials tested OK',
	'sp_paypal_bad' => 'PayPal credentials NOT OK',

	'sp_whmcs_test' => 'Test WHMCS',
	'sp_whmcs_ok' => 'WHMCS credentials tested OK',
	'sp_whmcs_bad' => 'WHMCS credentials NOT OK',
	'sp_lastweberror' => 'Last Error',
	'sp_whmcs_web_userid' => 'HTTP username for WHMCS',
	'd_sp_whmcs_web_userid' => 'If you have HTTP authentication for your WHMCS API, enter the name here',
	'sp_whmcs_web_pass' => 'HTTP password for WHMCS',
	'd_sp_whmcs_web_pass' => 'If you have HTTP authentication for your WHMCS API, enter the password here',
	
	'sp_softwaredate' => 'Software Release Date',
	'sp_whmcs_pushed' => 'Invoiced by WHMCS',
	'sp_purchase_redirect' => 'Redirect ticket posts to paid departments',
	'd_sp_purchase_redirect' => 'If a client has too little credit to post, redirect to the payment page?',
	'sp_purchase_redirect_msg' => 'Submit Ticket message for insufficient credit',
	'd_sp_purchase_redirect_msg' => 'If a client has too little credit to post, show this message on the Submit Tickets page.',
	'sp_purchase_redirect_url' => 'URL to redirect payments to.',
	'd_sp_purchase_redirect_url' => 'Leave empty to use the default SupportPay payment page.',
	
	'sps_gatekeeper' => 'Ticket Status Filter',
	'sp_gatekeeper' => 'Change ticket status according to credit',
	'd_sp_gatekeeper' => 'Move tickets between statuses depending on whether the client can pay or not',
	'sp_gk_fromstatus' => 'Original status',
	'd_sp_gk_fromstatus' => 'Reclassify status only when it is set to this',
	'sp_gk_tostatus' => 'New status',
	'd_sp_gk_tostatus' => 'Accepted tickets to get reclassified to this',
	'sp_gk_subject' => 'Subject line for the emails',
	'd_sp_gk_subject' => '',
	
	'sp_from_date' => 'From Date',
	'sp_to_date' => 'To Date',
	
	'sp_changedept' => 'Change Dept.',
	'sp_changedepart' => 'Change Chat Department',
	'sp_chatsupdated' => 'Chat session departments updated.',
	'sp_baddepartment' => 'Not a valid department.',
	);

if (!defined('SWIFT_MODULESDIRECTORY')) {
	define('SWIFT_MODULESDIRECTORY', SWIFT_APPSDIRECTORY);
}

require_once(SWIFT_MODULESDIRECTORY."/supportpay/locale/en-us/sp_menus.php");

if (DB_TYPE == "mysql" && !empty($_SWIFT->Database)) {
	$_SWIFT->Database->Execute("SET SQL_BIG_SELECTS=1");
}

?>
