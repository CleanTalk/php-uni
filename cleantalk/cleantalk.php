<?php
	
	// Config
	require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

	if( empty( $apikey ) )
		return;
	
	$apbct_checkjs_val = md5($apikey);
	global $apbct_checkjs_val;
	if ($spam_firewall == 1) {
		$is_sfw_check  = true;
		$sfw           = new \Cleantalk\ApbctUni\SFW();
		$sfw->ip_array = (array) $sfw->ip__get(array('real'), true);

		foreach ($sfw->ip_array as $key => $value)
		{
			if (isset($_COOKIE['apbct_sfw_pass_key']) && $_COOKIE['apbct_sfw_pass_key'] == md5($value . $apikey))
			{
				$is_sfw_check = false;
				if (isset($_COOKIE['apbct_sfw_passed']))
				{
					@setcookie('apbct_sfw_passed'); //Deleting cookie
					$sfw->logs__update($value, 'passed');
				}
			}
		}
		unset($key, $value);
		
		if ($is_sfw_check)
		{
			$sfw->ip_check();

			if($sfw->test){
				$sfw->logs__update(current(current($sfw->blocked_ips)), 'blocked');
				$sfw->sfw_die($apikey, '', '', 'test');
			}

			if ($sfw->pass === false)
			{
				$sfw->logs__update(current(current($sfw->blocked_ips)), 'blocked');
				$sfw->sfw_die($apikey);
			}
		}
		
	}	
	// Helper functions
	require_once( CLEANTALK_ROOT . 'inc' . DS . 'functions.php');
	
	// Catching buffer 
	ob_start('ct_attach_js');
	function ct_attach_js($buffer){
		global $apbct_checkjs_val;
		if(
			!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') // No ajax
			&& preg_match('/^\s*(<!doctype|<html)[\s\S]*html>\s*$/i', $buffer) == 1 // Only for HTML documents
		){
			$html_addition = 
				'<script>var apbct_checkjs_val = "' . $apbct_checkjs_val . '";</script>'
				.'<script src="cleantalk/js/ct_js_test.js"></script>'
				.'<script src="cleantalk/js/ct_ajax_catch.js"></script>';
			$buffer = preg_replace(
				'/<\/body>\s*<\/html>\s*$/i',
				$html_addition.'</body></html>',
				$buffer,
				1
			);
		}
		return $buffer;
	}
	
	// External forms
	if( isset($_SERVER['REQUEST_METHOD'], $_POST['ct_method'], $_POST['ct_action']) && $_SERVER['REQUEST_METHOD'] == 'POST' ){
    	$action = htmlspecialchars($_POST['ct_action']);
    	$method = htmlspecialchars($_POST['ct_method']);
    	unset($_POST['ct_action'], $_POST['ct_method']);
    	apbct_spam_test($_POST);
		if(empty($_POST['cleantalk_hidden_ajax'])){
			print "<html><body><form method='$method' action='$action'>";
			apbct_print_form($_POST, '');
			print "</form></body></html>";
			print "<script>
				if(document.forms[0].submit != 'undefined'){
					var objects = document.getElementsByName('submit');
					if(objects.length > 0)
						document.forms[0].removeChild(objects[0]);
				}
				document.forms[0].submit();
			</script>";
			die();
		}
    }
	
	// General spam test
	if(!empty($_POST)){
		apbct_spam_test($_POST);
	}
			
	// Set Cookies test for cookie test
	$apbct_timestamp = time();
	setcookie('apbct_timestamp',     $apbct_timestamp,                0, '/');
	setcookie('apbct_cookies_test',  md5($apikey.$apbct_timestamp), 0, '/');
	setcookie('apbct_timezone',      '0',                             0, '/');
    setcookie('apbct_fkp_timestamp', '0',                             0, '/');
    setcookie('apbct_pointer_data',  '0',                             0, '/');
    setcookie('apbct_ps_timestamp',  '0',                             0, '/');