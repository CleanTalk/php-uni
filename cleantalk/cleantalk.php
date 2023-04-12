<?php

	// Config
	require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

	if( empty( $apikey ) ){
		apbct_restore_include_path();
		return;
	}

    global $apbct_salt, $apbct_checkjs_val, $antispam_activity_status, $general_postdata_test, $detected_cms;
    $apbct_checkjs_val = apbct_checkjs_hash($apikey, $apbct_salt);

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

    /**
     * Skip spamtest if antispam not active in settings
     */
    if(!$antispam_activity_status) {
        return;
    }

	// Helper functions
	require_once( CLEANTALK_ROOT . 'inc' . DS . 'functions.php');

	// Catching buffer
	ob_start('ct_attach_js');
	function ct_attach_js($buffer){
		global $apbct_checkjs_val;
		if(
			!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') // No ajax
			&& preg_match('/^\s*(<!doctype|<html)[\s\S]*html>/i', $buffer) == 1 // Only for HTML documents
		){
			$html_addition =
				'<script>var apbct_checkjs_val = "' . $apbct_checkjs_val . '";</script>'
				.'<script src="cleantalk/js/ct_js_test.js"></script>'
				.'<script src="cleantalk/js/ct_ajax_catch.js"></script>';
			$buffer = preg_replace(
				'/<\/body>\s*<\/html>/i',
				$html_addition.'</body></html>',
				$buffer,
				1
			);
		}

		apbct_restore_include_path();

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

    // Test for search form cscart
    if (
        $detected_cms === 'cscart' &&
            $general_postdata_test &&
            isset($_GET['dispatch']) &&
            $_GET['dispatch'] === 'products.search'
    ) {
        apbct_spam_test($_GET);
    }

	// General spam test
	if(!empty($_POST)){
		apbct_spam_test($_POST);
	}

	// Set Cookies test for cookie test
	$apbct_timestamp = time();

	$cookie_secure = (isset($_SERVER['HTTPS']) && !in_array($_SERVER['HTTPS'], ['off', ''])) || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT']) === 443;

	// For PHP 7.3+ and above
	if (version_compare(phpversion(), '7.3.0', '>=')) {
		$params = array(
			'expires' => 0,
			'path' => '/',
			'domain' => '',
			'secure' => $cookie_secure,
			'httponly' => true,
			'samesite' => 'Lax'
		);

		setcookie('apbct_timestamp', $apbct_timestamp, $params);
		setcookie('apbct_cookies_test', md5($apikey.$apbct_timestamp), $params);
		setcookie('apbct_timezone', '0', $params);
		setcookie('apbct_fkp_timestamp', '0', $params);
		setcookie('apbct_pointer_data', '0', $params);
		setcookie('apbct_ps_timestamp', '0', $params);

	// For PHP 5.6 - 7.2
	} else {
		setcookie('apbct_timestamp', $apbct_timestamp, 0, '/', '', $cookie_secure, true);
		setcookie('apbct_cookies_test', md5($apikey.$apbct_timestamp), 0, '/', '', $cookie_secure, true);
		setcookie('apbct_timezone', '0', 0, '/', '', $cookie_secure, true);
		setcookie('apbct_fkp_timestamp', '0', 0, '/', '', $cookie_secure, true);
		setcookie('apbct_pointer_data', '0', 0, '/', '', $cookie_secure, true);
		setcookie('apbct_ps_timestamp', '0', 0, '/', '', $cookie_secure, true);
	}

	apbct_restore_include_path();
