<?php
	
	// Config
	require_once('ct_config.php');
	// Helper functions
	require_once('lib/ct_functions.php');
	
	if( isset($_SERVER['REQUEST_METHOD'], $_POST['ct_method'], $_POST['ct_action']) && $_SERVER['REQUEST_METHOD'] == 'POST' ){
    	$action = htmlspecialchars($_POST['ct_action']);
    	$method = htmlspecialchars($_POST['ct_method']);
    	unset($_POST['ct_action'], $_POST['ct_method']);
    	apbct_spam_test($_POST);
		if(empty($_POST['cleantalk_hidden_ajax'])){
			print "<html><body><form method='$method' action='$action'>";
			apbct_print_form($_POST, '');
			print "</form><center>Redirecting to ".$action."... Anti-spam by CleanTalk.</center></body></html>";
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
	$apbct_checkjs_val = md5($auth_key);