<?php

class Controller_GetBasename extends SWIFT_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->_SWIFT = SWIFT::GetInstance();
		
		return true;
	}

	public function __destruct()
	{
		parent::__destruct();

		return true;
	}
	
	public function Main() {
		$theURL = parse_url(str_replace("/callback/","/",SWIFT::Get('basename')));
		echo $theURL["path"];
	}
}

?>
