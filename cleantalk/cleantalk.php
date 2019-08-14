<?php
	
	// Config
	require_once('ct_config.php');
	$apbct_checkjs_val = md5($auth_key);
	global $apbct_checkjs_val;
	
	// Helper functions
	require_once('lib/ct_functions.php');
	
	// Catching buffer 
	ob_start('ct_attach_js');
	function ct_attach_js($buffer){
		global $apbct_checkjs_val;
		if(
			!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') // No ajax
			&& preg_match('/^\s*(<!doctype|<html)[\s\S]*html>\s*$/i', $buffer) == 1 // Only for HTML documents
		){
			$html_addition = '<script>var apbct_checkjs_val = "' . $apbct_checkjs_val . '";</script><script src="cleantalk/js/js_test.js"></script>';
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
	setcookie('apbct_cookies_test',  md5($auth_key.$apbct_timestamp), 0, '/');
	setcookie('apbct_timezone',      '0',                             0, '/');
    setcookie('apbct_fkp_timestamp', '0',                             0, '/');
    setcookie('apbct_pointer_data',  '0',                             0, '/');
    setcookie('apbct_ps_timestamp',  '0',                             0, '/');