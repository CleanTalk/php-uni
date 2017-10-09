<?php
	
	// Config
	require_once('config.php');
		
	if(!empty($_POST)){
				
		// Patch for old PHP versions
		require_once('lib/phpFix.php');
		
		// Helper functions
		require_once('lib/functions.php');
		
		// Libs
		require_once('lib/Cleantalk.php');
		require_once('lib/CleantalkRequest.php');
		require_once('lib/CleantalkResponse.php');
				
		$msg_data = apbct_get_fields_any($_POST);
		
		// Data
		$sender_email    = ($msg_data['email']    ? $msg_data['email']    : '');
		$sender_nickname = ($msg_data['nickname'] ? $msg_data['nickname'] : '');
		$subject         = ($msg_data['subject']  ? $msg_data['subject']  : '');
		$message         = ($msg_data['message']  ? $msg_data['message']  : array());
		
		// Flags
		$skip            = ($msg_data['contact']  ? $msg_data['contact']  : false);
		$registration    = ($msg_data['reg']      ? $msg_data['reg']      : false);
		
		// Do check if email is set
		if(!empty($sender_email) && !$skip){
			
			$ct_request = new CleantalkRequest();
			
			// Service pararams
			$ct_request->auth_key             = $auth_key;
			$ct_request->agent                = 'php-uni';
			                                  
			// Message params                 
			$ct_request->sender_email         = $sender_email; 
			$ct_request->sender_nickname      = $sender_nickname; 
			$ct_request->message              = $message;
			
			// IPs
			$possible_ips = apbct_get_possible_ips();
			$ct_request->sender_ip            = apbct_get_ip();
			$ct_request->x_forwarded_for      = $possible_ips['X-Forwarded-For'];
			$ct_request->x_forwarded_for_last = $possible_ips['X-Forwarded-For-Last'];
			$ct_request->x_real_ip            = $possible_ips['X-Real-Ip'];
			
			// Misc params
			$ct_request->js_on                = apbct_js_test();
			$ct_request->submit_time          = apbct_get_submit_time();
			$ct_request->sender_info          = apbct_get_sender_info();
			$ct_request->all_headers          = function_exists('apache_request_headers') ? apache_request_headers() : apbct_apache_request_headers();
			$ct_request->post_info            = ''; //array('comment_type' => 'comment');
			$ct_request->response_lang        = $response_lang;
						
			// Making a request
			$ct = new Cleantalk();
			$ct->server_url = 'http://moderate.cleantalk.org/api2.0/';
			
			$ct_result = $registration ? $ct->isAllowUser($ct_request) : $ct->isAllowMessage($ct_request);
			
			if(!empty($ct_result->errno) && !empty($ct_result->errstr)){
				
			}elseif($ct_result->allow == 1){
				
			}else{
				apbct_die($ct_result->comment, $registration);
			}			
		}
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