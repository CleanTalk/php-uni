<?php
	
	/*
	* Performs spam test
	* @return void or exit script
	*/
	
	function apbct_spam_test($data){
		
		global $apikey, $response_lang, $registrations_test, $general_postdata_test;
		
		// Patch for old PHP versions
		require_once( CLEANTALK_ROOT . 'lib' . DS . 'ct_phpFix.php');
		
		$msg_data = apbct_get_fields_any($data);
		
		// Data
		$sender_email    = isset($msg_data['email'])    ? $msg_data['email']    : '';
		$sender_nickname = isset($msg_data['nickname']) ? $msg_data['nickname'] : '';
		$subject         = isset($msg_data['subject'])  ? $msg_data['subject']  : '';
		$message         = isset($msg_data['message'])  ? $msg_data['message']  : array();
		
		// Flags
		$registration    = isset($msg_data['reg'])      ? $msg_data['reg']      : false;
		$skip            = isset($msg_data['skip'])     ? $msg_data['skip']     : false;
		
		// Skip check if
		if(
		    $skip || // Skip flag set by apbct_get_fields_any()
			( ! $sender_email && ! $general_postdata_test ) || // No email detected and general post data test is disabled
			( $registration && ! $registrations_test ) || // It's registration and registration check is disabled
		    ( apbct_check_exclusions() ) // Has an exclusions in POST
		)
			$skip = true;
		
		// Do check if email is not set
		if( ! $skip ){
			
			$ct_request = new \Cleantalk\Antispam\CleantalkRequest();
			
			// Service pararams
			$ct_request->auth_key             = $apikey;
			$ct_request->agent                = APBCT_AGENT;
			                                  
			// Message params                 
			$ct_request->sender_email         = $sender_email; 
			$ct_request->sender_nickname      = $sender_nickname; 
			$ct_request->message              = json_encode($message);
			
			// IPs
			$possible_ips = apbct_get_possible_ips();
			$ct_request->sender_ip            = apbct_get_ip();

			if ($possible_ips) {
				$ct_request->x_forwarded_for      = $possible_ips['X-Forwarded-For'];
				$ct_request->x_forwarded_for_last = $possible_ips['X-Forwarded-For-Last'];
				$ct_request->x_real_ip            = $possible_ips['X-Real-Ip'];				
			}

			// Misc params
			$ct_request->js_on                = apbct_js_test();
			$ct_request->submit_time          = apbct_get_submit_time();
			$ct_request->sender_info          = json_encode(apbct_get_sender_info($data));
			$ct_request->all_headers          = function_exists('apache_request_headers') ? json_encode(apache_request_headers()) : json_encode(apbct_apache_request_headers());
			$ct_request->post_info            = $registration ?  '' : json_encode(array('comment_type' => 'feedback'));
			
			// Making a request
			$ct = new \Cleantalk\Antispam\Cleantalk();
			$ct->server_url = 'http://moderate.cleantalk.org';
			
			$ct_result = $registration
				? $ct->isAllowUser($ct_request)
				: $ct->isAllowMessage($ct_request);
			
			if(!empty($ct_result->errno) && !empty($ct_result->errstr)){
				
			}elseif($ct_result->allow == 1){
				
			}else{
				apbct_die($ct_result->comment, $registration);
			}
		}
	}
	
	/**
	 * Inner function - Default data array for senders 
	 * @return array 
	 */
	function apbct_get_sender_info($data)
	{
		
		global $apikey, $response_lang;
				
		return $sender_info = array(
		
			// Common
			'remote_addr'     => $_SERVER['REMOTE_ADDR'],
			'USER_AGENT'      => htmlspecialchars($_SERVER['HTTP_USER_AGENT']),
			'REFFERRER'       => htmlspecialchars($_SERVER['HTTP_REFERER']),
			'page_url'        => isset($_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']) : null,
			// 'cms_lang'        => substr(locale_get_default(), 0, 2),
			
			'php_session'     => session_id() != '' ? 1 : 0, 
			'cookies_enabled' => apbct_cookies_test(),
			'fields_number'   => sizeof($data),
			'ct_options'      => json_encode(array('auth_key' => $apikey, 'response_lang' => $response_lang)),
			
			// PHP cookies                                                                                                                                                 
			// 'cookies_enabled'        => $cookie_is_ok,                                                                                                                     
			// 'REFFERRER_PREVIOUS'     => !empty($_COOKIE['apbct_prev_referer'])    && $cookie_is_ok     ? $_COOKIE['apbct_prev_referer']                                    : null,
			// 'site_landing_ts'        => !empty($_COOKIE['apbct_site_landing_ts']) && $cookie_is_ok     ? $_COOKIE['apbct_site_landing_ts']                                 : null,
			// 'page_hits'              => !empty($_COOKIE['apbct_page_hits'])                            ? $_COOKIE['apbct_page_hits']                                       : null,
			
			// JS params
			'mouse_cursor_positions' => isset($_COOKIE['apbct_pointer_data'])          ? json_decode(stripslashes($_COOKIE['apbct_pointer_data']), true) : null,
			'js_timezone'            => isset($_COOKIE['apbct_timezone'])              ? $_COOKIE['apbct_timezone']             : null,
			'key_press_timestamp'    => isset($_COOKIE['apbct_fkp_timestamp'])         ? $_COOKIE['apbct_fkp_timestamp']        : null,
			'page_set_timestamp'     => isset($_COOKIE['apbct_ps_timestamp'])          ? $_COOKIE['apbct_ps_timestamp']         : null,
			'form_visible_inputs'    => !empty($_COOKIE['apbct_visible_fields_count']) ? $_COOKIE['apbct_visible_fields_count'] : null,
			'apbct_visible_fields'   => !empty($_COOKIE['apbct_visible_fields'])       ? $_COOKIE['apbct_visible_fields']       : null,
			
			// Debug
			'action' => \Cleantalk\Variables\Post::get('action') ? \Cleantalk\Variables\Post::get('action') : null,
		);
	}
	
	/**
	 * Gets sender ip
	 * Filters IPv4 or IPv6
	 * @return null|int;
	 */
	function apbct_get_ip()
	{
		$ip =        filter_var(trim($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$ip = !$ip ? filter_var(trim($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) : $ip;
		return $ip;
	}
	
	/* 
	 * Gets possible IPs
	 *
	 * Checks for HTTP headers HTTP_X_FORWARDED_FOR and HTTP_X_REAL_IP and filters it for IPv6 or IPv4
	 * returns array()
	 */	
	function apbct_get_possible_ips()
	{
		$result_ips = array(
			'X-Forwarded-For' => null,
			'X-Forwarded-For-Last' => null,
			'X-Real-Ip' => null,
		);
		
		$headers = function_exists('apache_request_headers')
			? apache_request_headers()
			: apbct_apache_request_headers();
		
		// X-Forwarded-For
		if(array_key_exists( 'X-Forwarded-For', $headers )){
			$ips = explode(",", trim($headers['X-Forwarded-For']));
			// First
			$ip = trim($ips[0]);
			$ip =        filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
			$ip = !$ip ? filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) : $ip;
			$result_ips['X-Forwarded-For'] = !$ip ? '' : $ip;
			// Last
			if(count($ips) > 1){
				$ip = trim($ips[count($ips)-1]);
				$ip =        filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
				$ip = !$ip ? filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) : $ip;
				$result_ips['X-Forwarded-For-Last'] = !$ip ? '' : $ip;
			}
		}
		
		// X-Real-Ip
		if(array_key_exists( 'X-Real-Ip', $headers )){
			$ip = trim($headers['X-Real-Ip']);
			$ip =        filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
			$ip = !$ip ? filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) : $ip;
			$result_ips['X-Real-Ip'] = !$ip ? '' : $ip;
		}
		return ($result_ips) ? $result_ips : null;
	}
	
	/**
	 * Gets submit time
	 * Uses Cookies with check via apbct_cookies_test()
	 * @return null|int;
	 */
	function apbct_get_submit_time()
	{
		$cookie_test_result = apbct_cookies_test();
		if(!empty($cookie_test_result)){
			return time() - $_COOKIE['apbct_timestamp'];
		}else{
			return null;
		}
	}
	
	/*
	* Get data from an ARRAY recursively
	* @return array
	*/ 
	function apbct_get_fields_any($arr, $message=array(), $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''), $subject = null, $skip = false, $reg = false, $not_reg=false, $prev_key = '')
	{
        global $detected_cms;

		// Skip request if fields exists
		$skip_params = array( 
			'ipn_track_id', 	// PayPal IPN #
			'txn_type', 		// PayPal transaction type
			'payment_status', 	// PayPal payment status
			'ccbill_ipn', 		// CCBill IPN 
		);
		
		$registration = array(
			'registration',
			'register',
			'submitCreate', // PrestaShop
		);
		
		// Fields to replace with ****
		$obfuscate_params = array( 
			'password',
			'pass',
			'pwd',
			'pswd'
		);
		
		// Array for strings in keys to skip and known service fields
		$skip_fields_with_strings = array( 
			// Common
			'ct_checkjs', //Do not send ct_checkjs
			'nonce', //nonce for strings such as 'rsvp_nonce_name'
			'security',
			'action',
			'http_referer',
			// Formidable Form
			'form_key',
			'submit_entry',
			// Custom Contact Forms
			'form_id',
			'ccf_form',
			'form_page',
			// Qu Forms
			'iphorm_uid',
			'form_url',
			'post_id',
			'iphorm_ajax',
			'iphorm_id',
			// Fast SecureContact Froms
			'fs_postonce_1',
			'fscf_submitted',
			'mailto_id',
			'si_contact_action',
			// Ninja Forms
			'formData_id',
			'formData_settings',
			'formData_fields_\d+_id',
			// E_signature
			'recipient_signature',
			'output_\d+_\w{0,2}',
			// Contact Form by Web-Settler protection
			'_formId',
			'_returnLink',
		);
		
		// Reset $message if we have a sign-up data
		$skip_message_post = array( 
			'edd_action', // Easy Digital Downloads
		);
		
		// Flag for skipping check
		foreach($skip_params as $value){
			if(array_key_exists($value,$_GET) || array_key_exists($value,$_POST))
				$skip = true;
		} unset($value);	
		
		$reg = false;

		if(count($arr)){
			foreach($arr as $key => $value){
				
				if(is_string($value)){
					$decoded_json_value = json_decode($value, true);
					if($decoded_json_value !== null)
						$value = $decoded_json_value;
				}
				
				if(!is_array($value) && !is_object($value)){
					
					if($value === '')
						continue;
					
					// Flag for detecting registrations
					if(strlen($value) > 40 && $not_reg){
						$reg = false;
						$not_reg = true;
					}else{
						foreach($registration as $needle){
							if(stripos($key, $needle) !== false){
								$reg = true;
								continue(2);
							}
						} unset($needle);
					}
					
					
					// Skipping fields names with strings from (array)skip_fields_with_strings
					foreach($skip_fields_with_strings as $needle){
						if (preg_match("/".$needle."/", $prev_key.$key) == 1){
							continue(2);
						}
					}unset($needle);
					
					// Obfuscating params
					foreach($obfuscate_params as $needle){
						if (strpos($key, $needle) !== false){
							$value = apbct_obfuscate_param($value);
							continue(2);
						}
					}unset($needle);
					
					// Decodes URL-encoded data to string.
					$value = urldecode($value);	

					// Email
					if (!$email && preg_match("/^\S+@\S+\.\S+$/", $value)){
						$email = $value;
						
					// Names
					} elseif (
                        preg_match( "/name/i", $key ) ||
                        ( $detected_cms == 'Question2Answer' && preg_match( "/^handle$/i", $key ) )
                    ){
						
						preg_match("/(first.?name)?(name.?first)?(forename)?/", $key, $match_forename);
						preg_match("/(last.?name)?(family.?name)?(second.?name)?(surname)?/", $key, $match_surname);
						preg_match("/(nick.?name)?(user.?name)?(nick)?(login)?/", $key, $match_nickname);
						
						if(count($match_forename) > 1)
							$nickname['first'] = $value;
						elseif(count($match_surname) > 1)
							$nickname['last'] = $value;
						elseif(count($match_nickname) > 1)
							$nickname['nick'] = $value;
						else
                            $nickname[$prev_key.$key] = $value;
					
					// Subject
					}elseif ($subject === null && preg_match("/subject/i", $key)){
						$subject = $value;
					
					// Message
					}else{
						$message[$prev_key.$key] = $value;					
					}
					
				}else if(!is_object($value)&&@get_class($value)!='WP_User'){
					
					$prev_key_original = $prev_key;
					$prev_key = ($prev_key === '' ? $key.'_' : $prev_key.$key.'_');
					
					$temp = apbct_get_fields_any($value, $message, $email, $nickname, $subject, $skip, $reg, $not_reg, $prev_key);
					
					$message 	= ($temp['subject']  ? array_merge(array('subject' => $temp['subject']), $temp['message']) : $temp['message']);
					$email 		= ($temp['email']    ? $temp['email']    : null);
					$nickname 	= ($temp['nickname'] ? $temp['nickname'] : null);				
					$skip       = ($temp['skip']     ? true              : $skip);
					$reg        = ($temp['reg']      ? true              : $reg);
					$prev_key 	= $prev_key_original;
				}
			} unset($key, $value);
		}
		
		foreach ($skip_message_post as $v) {
			if (isset($_POST[$v])) {
				$message = null;
				break;
			}
		} unset($v);
		
		//If top iteration, returns compiled name field. Example: "Nickname Firtsname Lastname".
		if($prev_key === ''){
			if(!empty($nickname)){
				$nickname_str = '';
				foreach($nickname as $value){
					$nickname_str .= ($value ? $value." " : "");
				}unset($value);
			}
			$nickname = $nickname_str;
		}

		$return_param = array(
			'email'    => $email,
			'nickname' => $nickname,
			'subject'  => $subject,
			'message'  => $message,
			'skip' 	   => $skip,
			'reg'      => $reg,
		);	
		return $return_param;
	}

	/**
	* Masks a value with asterisks (*)
	* @return string
	*/
	function apbct_obfuscate_param($value = null)
	{		
		if ($value && (!is_object($value) || !is_array($value))) {
			$length = strlen($value);
			$value = str_repeat('*', $length);
		}
		return $value;
	}
		
	/**
	 * JavaScript test for sender
	 * return null|0|1;
	 */
	 function apbct_js_test(){
		 global $apikey;
		 if(isset($_COOKIE['apbct_checkjs'])){
			if($_COOKIE['apbct_checkjs'] == md5($apikey))
				return 1;
			else
				return 0;
		 }else{
			return null;
		 }
	 }
	
	/**
	 * Cookies test for sender 
	 * Also checks for valid timestamp in $_COOKIE['apbct_timestamp']
	 * @return null|0|1;
	 */
	function apbct_cookies_test()
	{
		global $apikey;
		if(isset($_COOKIE['apbct_cookies_test'], $_COOKIE['apbct_timestamp'])){			
			if($_COOKIE['apbct_cookies_test'] == md5($apikey.$_COOKIE['apbct_timestamp']))
				return 1;
			else
				return 0;
		}else			
			return null;
	}
	
	/* 
	 * Gets every HTTP_ headers from $_SERVER
	 * 
	 * If Apache web server is missing then making
	 * Patch for apache_request_headers()
	 * 
	 * returns array
	 */
	function apbct_apache_request_headers(){
		
		$headers = array();	
		foreach($_SERVER as $key => $val){
			if(preg_match('/\AHTTP_/', $key)){
				$server_key = preg_replace('/\AHTTP_/', '', $key);
				$key_parts = explode('_', $server_key);
				if(count($key_parts) > 0 and strlen($server_key) > 2){
					foreach($key_parts as $part_index => $part){
						$key_parts[$part_index] = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
						$key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);					
					}
					$server_key = implode('-', $key_parts);
				}
				$headers[$server_key] = $val;
			}
		}
		return $headers;
	}
	
	function apbct_print_form( $arr, $k ){
		
		foreach( $arr as $key => $value ){
			
			if( !is_array( $value ) ){
				
				if( $k == '' )
					print '<textarea name="'.$key.'" style="display:none;">'.htmlspecialchars( $value ).'</textarea>';
				else
					print '<textarea name="'.$k.'['.$key.']" style="display:none;">'.htmlspecialchars( $value ).'</textarea>';
				
			}else{
				
				if( $k == '' )
					ct_print_form( $value,$key );
				else
					ct_print_form( $value, $k . '['.$key.']' );
				
			}
		}
	}
	
	function apbct_die($comment, $registration = false, $additional_text = null){
		
		// AJAX
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
			die(json_encode(array('apbct' => array('blocked' => true, 'comment' => $comment,))));
			
		// File exists?
		}elseif(file_exists( getcwd() . '/cleantalk/lib/die_page.html')){
			$die_page = file_get_contents( getcwd() . '/cleantalk/lib/die_page.html');
		
		// Default
		}else{
			die($comment);
		}
		
		$die_page = str_replace('{BLOCK_REASON}', $comment, $die_page);
		
		// Headers
		if(headers_sent() === false){
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Pragma: no-cache");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
			header("Expires: 0");
			header("HTTP/1.0 403 Forbidden");
			$die_page = str_replace('{GENERATED}', "", $die_page);
		}else{
			$die_page = str_replace('{GENERATED}', "<h2 class='second'>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</h2>",$die_page);
		}
		
		die($die_page);
	}
	
	function apbct_check_exclusions(){
		global $exclusions;
		if( ! empty ( $exclusions ) ){
			foreach ( $exclusions as $name => $value ){
				if( isset( $_POST[ $name ] ) ){
					if( empty( $value ) || ( $value && $_POST[ $name ] === $value ) ){
						return true;
					}
				}
			}
		}
		return false;
	}