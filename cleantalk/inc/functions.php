<?php

use Cleantalk\Common\Arr;
use Cleantalk\Common\GetFieldsAny;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Post;

/*
* Performs spam test
* @return void or exit script
*/
function apbct_spam_test($data){

		global $apikey,
               $response_lang,
               $registrations_test,
               $general_postdata_test,
               $detected_cms,
               $exclusion_key,
               $general_post_exclusion_usage;

		// Patch for old PHP versions.
		require_once( CLEANTALK_ROOT . 'lib' . DS . 'ct_phpFix.php');

        // ShopScript integration: test only reviews and order requests
        if(
            $detected_cms === 'ShopScript' &&
            ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ) &&
            ( ! ( isset( $_POST['name'], $_POST['email'], $_POST['rate'] ) ) )
        ) {
            return;
        }

		$msg_data = apbct_get_fields_any($data);

		// Data
		$sender_email    = isset($msg_data['email'])    ? $msg_data['email']    : '';
		$sender_nickname = isset($msg_data['nickname']) ? $msg_data['nickname'] : '';
		$subject         = isset($msg_data['subject'])  ? $msg_data['subject']  : '';
		$message         = isset($msg_data['message'])  ? $msg_data['message']  : array();

		// Flags
		$registration    = isset($msg_data['reg'])      ? $msg_data['reg']      : false;
		$skip            = isset($msg_data['skip'])     ? $msg_data['skip']     : false;

        // Check registration for CsCart
        if (
            $detected_cms === 'cscart' &&
            isset($data['user_data']['password1'], $data['user_data']['password2'])
        ) {
            $registration = true;
        }

        //init exclusions array if general_post_exclusion_usage is enabled
        if ( isset($exclusion_key, $general_post_exclusion_usage) && $general_post_exclusion_usage ) {
            $exclusions_in_post = array(
                'ct_service_data' => $exclusion_key,
            );
        } else {
            $exclusions_in_post = array();
        }


        // Skip check if
        if ( $skip || // Skip flag set by apbct_get_fields_any()
            (!$sender_email && !$general_postdata_test) || // No email detected and general post data test is disabled
            ($registration && !$registrations_test) || // It's registration and registration check is disabled
            (apbct_check__exclusions()) || // main exclusion function
            (apbct_check__exclusions_in_post($exclusions_in_post)) || // Has an exclusions in POST
            (apbct_check__url_exclusions()) // Has an exclusions in URL
        ) {
            $skip = true;
        }

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

			$comment_type = 'feedback';

			if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'checkout') !== false) {
				$comment_type = 'order';
			}

			// Misc params
			$ct_request->js_on                = apbct_js_test();
			$ct_request->submit_time          = apbct_get_submit_time();
			$ct_request->sender_info          = json_encode(apbct_get_sender_info($data));
			$ct_request->all_headers          = json_encode( \Cleantalk\ApbctUni\Helper::http__get_headers() );
			$ct_request->post_info            = $registration ?  '' : json_encode(array('comment_type' => $comment_type));

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

        // Visible fields processing
        $visible_fields_collection = '';
        if ( Cookie::getVisibleFields() ) {
            $visible_fields_collection = Cookie::getVisibleFields();
        } elseif ( Post::get('apbct_visible_fields') ) {
            $visible_fields_collection = stripslashes(Post::get('apbct_visible_fields'));
        }

        $visible_fields = apbct_visible_fields__process($visible_fields_collection);

		return array(

			// Common
			'remote_addr'     => $_SERVER['REMOTE_ADDR'],
			'USER_AGENT'      => htmlspecialchars($_SERVER['HTTP_USER_AGENT']),
			'REFFERRER'       => isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : '',
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
            'form_visible_inputs'    => ! empty($visible_fields['visible_fields_count']) ? $visible_fields['visible_fields_count'] : null,
            'apbct_visible_fields'   => ! empty($visible_fields['visible_fields']) ? $visible_fields['visible_fields'] : null,
            'form_invisible_inputs'  => ! empty($visible_fields['invisible_fields_count']) ? $visible_fields['invisible_fields_count'] : null,
            'apbct_invisible_fields' => ! empty($visible_fields['invisible_fields']) ? $visible_fields['invisible_fields'] : null,

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

		$headers = \Cleantalk\ApbctUni\Helper::http__get_headers();

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

    /**
     * Get data from an ARRAY recursively
     *
     * @param array $arr
     * @param array $message
     * @param null|string $email
     * @param array $nickname
     * @param null $subject
     * @param bool $contact
     * @param string $prev_name
     *
     * @return array
     * @deprecated Use ct_gfa()
     */
	function apbct_get_fields_any($arr, $message=array(), $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''), $subject = null, $skip = false, $reg = false, $not_reg=false, $prev_key = '')
	{
        if ( is_array($nickname) ) {
            $nickname_str = '';
            foreach ( $nickname as $value ) {
                $nickname_str .= ($value ? $value . " " : "");
            }
            $nickname = trim($nickname_str);
        }

        return ct_gfa($arr, $email, $nickname);
	}

    /**
     * Get data from an ARRAY recursively
     *
     * @param array $input_array
     * @param string $email
     * @param string $nickname
     *
     * @return array
     */
    function ct_gfa($input_array, $email = '', $nickname = '')
    {
        $gfa = new GetFieldsAny($input_array);

        return $gfa->getFields($email, $nickname);
    }

	/**
	* Masks a value with asterisks (*)
	* @return string
	*/
    function apbct_obfuscate_param($value = null)
    {
        if ($value && (!is_object($value) || !is_array($value))) {
            $value = (string)$value;
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
		 global $apikey, $apbct_salt, $detected_cms;
		 if(isset($_COOKIE['apbct_checkjs'])){
			if(
                $_COOKIE['apbct_checkjs'] == apbct_checkjs_hash($apikey, $apbct_salt) ||
                ($detected_cms === 'cscart' && $_COOKIE['apbct_checkjs'] == md5($apikey))
            )
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

	function apbct_print_form( $arr, $k ){

		foreach( $arr as $key => $value ){

			if( !is_array( $value ) ){

				if( $k == '' )
					print '<textarea name="'.$key.'" style="display:none;">'.htmlspecialchars( $value ).'</textarea>';
				else
					print '<textarea name="'.$k.'['.$key.']" style="display:none;">'.htmlspecialchars( $value ).'</textarea>';

			}else{

				if( $k == '' )
					apbct_print_form( $value,$key );
				else
					apbct_print_form( $value, $k . '['.$key.']' );

			}
		}
	}

	function apbct_die($comment, $registration = false, $additional_text = null){

        global $detected_cms;

        // AJAX
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){

            // ShopScript integration
            if( $detected_cms === 'ShopScript' ) {
                if( ! headers_sent() ) {
                    header('Content-Type:application/json' );
                }
                if( isset( $_POST['name'], $_POST['email'], $_POST['rate'] ) ) {
                    die(json_encode(array('status' =>'fail', 'errors' => array('email' => $comment))));
                }
                die(json_encode(array('status' =>'ok', 'data' => array('errors' => $comment))));
            }

            // DLE integration
            if( $detected_cms === 'DLE' ) {
                if( ! headers_sent() ) {
                    header('Content-Type:application/json' );
                }

                if (isset($_REQUEST['mod']) && $_REQUEST['mod'] === 'addcomments') {
                    die(json_encode(array("error" => true, "content" => "<script>\nvar form = document.getElementById('dle-comments-form');\n\n DLEalert('" . $comment . "', 'Добавление комментария');\n var timeval = new Date().getTime();\n\n\n\t\t\n\tif ( dle_captcha_type == \"1\" ) {\n\t\tif ( typeof grecaptcha != \"undefined\"  ) {\n\t\t   grecaptcha.reset();\n\t\t}\n    } else if (dle_captcha_type == \"2\") {\n\t\tif ( typeof grecaptcha != \"undefined\"  ) {\n\t\t\tvar recaptcha_public_key = $('#g-recaptcha-response').data('key');\n\t\t\tgrecaptcha.execute(recaptcha_public_key, {action: 'comments'}).then(function(token) {\n\t\t\t$('#g-recaptcha-response').val(token);\n\t\t\t});\n\t\t}\n\t}\n\n\tif ( form.question_answer ) {\n\n\t   form.question_answer.value ='';\n       jQuery('#dle-question').text('');\n    }\n\n\tif ( document.getElementById('dle-captcha') ) {\n\t\tform.sec_code.value = '';\n\t\tdocument.getElementById('dle-captcha').innerHTML = '<img src=\"' + dle_root + 'engine/modules/antibot/antibot.php?rand=' + timeval + '\" width=\"160\" height=\"80\" alt=\"\">';\n\t}\n\t\t\n </script>" ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ));
                }

                die(json_encode(array('status' =>'ok', 'text' => $comment)));
            }

			// Custom ajax response
			require_once CLEANTALK_CONFIG_FILE;
			global $ajax_response;

			if (!empty($ajax_response)) {
				die(json_encode($ajax_response));
			}

			die(json_encode(array('apbct' => array('blocked' => true, 'comment' => $comment,))));


        }
        // Die page file exists?
        $path = CLEANTALK_ROOT . '/lib/die_page.html';
        if ( file_exists($path) ) {
            //if so setup die page template
            $die_page = file_get_contents($path);
        } else {
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


	/**
	 * Check POST parameters for exclusions
	 *
	 * @param array $exclusions Associative array
	 *
	 * @return bool
	 */
	function apbct_check__exclusions_in_post( $exclusions = array() ){

	    # Array by default: post_field_name => post_field_value
        $exclusions_default = array();

        $exclusions = array_merge($exclusions_default, $exclusions);

		foreach ( $exclusions as $name => $exclusion ){
			if( \Cleantalk\Variables\Post::equal( $name, $exclusion ) ){
				return true;
			}
		}

		return false;
	}

	/**
	 * Check URI string for exclusions
	 *
	 * @param array $exclusions
	 *
	 * @return bool
	 */
	function apbct_check__url_exclusions( $exclusions = array() ){
        global $detected_cms;

        //custom login word transform ruleset
        $login_word = 'login';
        if ( isset($detected_cms) ) {
            switch ( $detected_cms ) {
                //moodle case
                case 'moodle':
                {
                    $login_word = 'login/index.php';
                    break;
                }
                //add a new rule if needs
            }
        }
		$exclusions[] = $login_word;

		foreach ( $exclusions as $name => $exclusion ){
			if( \Cleantalk\Variables\Server::has_string('REQUEST_URI', $exclusion ) ){
				return true;
			}
		}

		return false;
	}

    /**
     * Another function for excluding validation based on any number of parameters
     */
    function apbct_check__exclusions() {

        global $detected_cms;

        # Exclude refresh captcha in phpbb registration form
        if(
            $detected_cms === 'phpBB' &&
            apbct_check__exclusions_in_post(array('refresh_vc' => true)) &&
            apbct_check__url_exclusions(array('mode=register'))
        ) {
            return true;
        }

        # Exclude unnecessary requests when filling out an order
        if(
            $detected_cms === 'cscart' &&
            apbct_check__exclusions_in_post(
                array(
                    'dispatch' => 'products.quick_view'
                )
            ) ||
            apbct_check__exclusions_in_post(
                array(
                    'dispatch' => 'checkout.customer_info'
                )
            ) ||
            apbct_check__exclusions_in_post(
                array(
                    'dispatch' => 'checkout.update_steps'
                )
            ) ||
            apbct_check__exclusions_in_post(
                array(
                    'dispatch' => 'products.view'
                )
            )  ||
            apbct_check__exclusions_in_post(
                array(
                    'dispatch' => 'categories.view'
                )
            )
        ) {
            return true;
        }

        return false;
    }

/**
 * Process visible fields for specific form to match the fields from request
 *
 * @param string|array $visible_fields JSON string
 *
 * @return array
 */
function apbct_visible_fields__process($visible_fields)
{
    $visible_fields = is_array($visible_fields)
        ? json_encode($visible_fields, JSON_FORCE_OBJECT)
        : $visible_fields;

    // Do not decode if it's already decoded
    $fields_collection = json_decode($visible_fields, true);

    if ( ! empty($fields_collection) ) {
        // These fields belong this request
        $fields_to_check = apbct_get_fields_to_check();

        foreach ( $fields_collection as $current_fields ) {
            if ( isset($current_fields['visible_fields'], $current_fields['visible_fields_count']) ) {
                $fields = explode(' ', $current_fields['visible_fields']);

                if ( count(array_intersect(array_keys($fields_to_check), $fields)) > 0 ) {
                    // WP Forms visible fields formatting
                    if ( strpos($current_fields['visible_fields'], 'wpforms') !== false ) {
                        $current_fields = preg_replace(
                            array('/\[/', '/\]/'),
                            '',
                            str_replace(
                                '][',
                                '_',
                                str_replace(
                                    'wpforms[fields]',
                                    '',
                                    $visible_fields
                                )
                            )
                        );
                    }

                    return $current_fields;
                }
            }
        }
    }

    return array();
}

/**
 * Get fields from POST to checking on visible fields.
 *
 * @return array
 */
function apbct_get_fields_to_check()
{
    //Formidable fields
    if ( isset($_POST['item_meta']) && is_array($_POST['item_meta']) ) {
        $fields = array();
        foreach ( $_POST['item_meta'] as $key => $item ) {
            $fields['item_meta[' . $key . ']'] = $item;
        }

        return $fields;
    }

    // @ToDo we have to implement a logic to find form fields (fields names, fields count) in serialized/nested/encoded items. not only $_POST.
    return $_POST;
}

function apbct_array($array)
{
    return new Arr($array);
}
