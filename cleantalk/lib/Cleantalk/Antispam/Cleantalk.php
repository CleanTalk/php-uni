<?php

namespace Cleantalk\Antispam;

use Cleantalk\Common\Helper;

/**
 * Cleantalk class create request
 */
class Cleantalk {

	/*
	 * Use Wordpress built-in API
	 */
	public $use_bultin_api = false;
	
    /**
	* Maximum data size in bytes
	* @var int
	*/
	private $dataMaxSise = 32768;
	
	/**
	* Data compression rate 
	* @var int
	*/
	private $compressRate = 6;
	
    /**
	* Server connection timeout in seconds 
	* @var int
	*/
	private $server_timeout = 15;

    /**
     * Cleantalk server url
     * @var string
     */
    public $server_url = null;

    /**
     * Last work url
     * @var string
     */
    public $work_url = null;

    /**
     * WOrk url ttl
     * @var int
     */
    public $server_ttl = null;

    /**
     * Time wotk_url changer
     * @var int
     */
    public $server_changed = null;

    /**
     * Flag is change server url
     * @var bool
     */
    public $server_change = false;

    /**
     * Codepage of the data 
     * @var bool
     */
    public $data_codepage = null;
    
    /**
     * API version to use 
     * @var string
     */
    public $api_version = '/api2.0';
    
    /**
     * Use https connection to servers 
     * @var bool 
     */
    public $ssl_on = false;
    
    /**
     * Path to SSL certificate 
     * @var string
     */
    public $ssl_path = '';

    /**
     * Minimal server response in miliseconds to catch the server
     *
     */
    public $min_server_timeout = 50;
	
    /**
     * Maximal server response in miliseconds to catch the server
     *
     */
    public $max_server_timeout = 1500;
	
    /**
     * Function checks whether it is possible to publish the message
     * @param CleantalkRequest $request
     * @return type
     */
    public function isAllowMessage(CleantalkRequest $request) {
        $msg = $this->createMsg('check_message', $request);
        return $this->httpRequest($msg);
    }

    /**
     * Function checks whether it is possible to publish the message
     * @param CleantalkRequest $request
     * @return type
     */
    public function isAllowUser(CleantalkRequest $request) {
        $msg = $this->createMsg('check_newuser', $request);
        return $this->httpRequest($msg);
    }

    /**
     * Function sends the results of manual moderation
     *
     * @param CleantalkRequest $request
     * @return CleantalkResponse
     */
    public function sendFeedback(CleantalkRequest $request) {
        $msg = $this->createMsg('send_feedback', $request);
        return $this->httpRequest($msg);
    }
	
    /**
     * Create msg for cleantalk server
     * @param type $method
     * @param CleantalkRequest $request
     * @return \xmlrpcmsg
     */
    private function createMsg($method, CleantalkRequest $request) {
	
	    $request->method_name = $method;
    	
        switch ($method) {
            case 'check_message':
                // Convert strings to UTF8
                $request->message         = Helper::toUTF8($request->message,         $this->data_codepage);
                $request->example         = Helper::toUTF8($request->example,         $this->data_codepage);
                $request->sender_email    = Helper::toUTF8($request->sender_email,    $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                $request->message = $this->compressData($request->message);
				$request->example = $this->compressData($request->example);
                break;

            case 'check_newuser':
                // Convert strings to UTF8
                $request->sender_email    = Helper::toUTF8($request->sender_email,    $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                break;

            case 'send_feedback':
                if (is_array($request->feedback)) {
                    $request->feedback = implode(';', $request->feedback);
                }
                break;
        }
        
        // Removing non UTF8 characters from request, because non UTF8 or malformed characters break json_encode().
        foreach ($request as $param => $value) {
            if(is_array($request->$param) || is_string($request->$param))
				$request->$param = Helper::removeNonUTF8($value);
        }
        
        // Conver $request->message to array
        $request->message = is_scalar( $request->message ) ? array($request->message) : $request->message;
        
        // Cleaning from null values
	    array_filter( $request->message, function( $var ){
		    return ! is_null( $var );
	    } );
		
		// Wiping cleantalk's headers but, not for send_feedback
		if($request->method_name != 'send_feedback'){
			
			$ct_tmp = apache_request_headers();
			
			if(isset($ct_tmp['Cookie']))
				$cookie_name = 'Cookie';
			elseif(isset($ct_tmp['cookie']))
				$cookie_name = 'cookie';
			else
				$cookie_name = 'COOKIE';
			
			$ct_tmp[$cookie_name] = preg_replace(array(
				'/\s?ct_checkjs=[a-z0-9]*[^;]*;?/',
				'/\s?ct_timezone=.{0,1}\d{1,2}[^;]*;?/', 
				'/\s?ct_pointer_data=.*5D[^;]*;?/', 
				'/\s?apbct_timestamp=\d*[^;]*;?/',
				'/\s?apbct_site_landing_ts=\d*[^;]*;?/',
				'/\s?apbct_cookies_test=%7B.*%7D[^;]*;?/',
				'/\s?apbct_prev_referer=http.*?[^;]*;?/',
				'/\s?ct_cookies_test=.*?[^;]*;?/',
				'/\s?ct_ps_timestamp=.*?[^;]*;?/',
				'/\s?ct_fkp_timestamp=\d*?[^;]*;?/',
				'/\s?ct_sfw_pass_key=\d*?[^;]*;?/',
				'/\s?apbct_page_hits=\d*?[^;]*;?/',
				'/\s?apbct_visible_fields_count=\d*?[^;]*;?/',
				'/\s?apbct_visible_fields=%7B.*%7D[^;]*;?/',
			), '', $ct_tmp[$cookie_name]);
			$request->all_headers = json_encode($ct_tmp);
		}
		
        return $request;
    }
    
	/**
     * Compress data and encode to base64 
     * @param type string
     * @return string 
     */
	private function compressData($data = null){
		
		if (strlen($data) > $this->dataMaxSise && function_exists('gzencode') && function_exists('base64_encode')){

			$localData = gzencode($data, $this->compressRate, FORCE_GZIP);

			if ($localData === false)
				return $data;
			
			$localData = base64_encode($localData);
			
			if ($localData === false)
				return $data;
			
			return $localData;
		}

		return $data;
	} 
	
    /**
     * httpRequest 
     * @param $msg
     * @return boolean|CleantalkResponse
     */
    private function httpRequest($msg) {
		
		// Using current server without changing it
        $result = !empty($this->work_url) && ($this->server_changed + $this->server_ttl > time())
	        ? $this->sendRequest($this->work_url, $msg, $this->server_timeout)
			: false;

		// Changing server
        if ($result === false || (is_object($result) && $result->errno != 0)) {
			
            // Split server url to parts
            preg_match("/^(https?:\/\/)([^\/:]+)(.*)/i", $this->server_url, $matches);
            
            $url_protocol = isset($matches[1]) ? $matches[1] : '';
            $url_host     = isset($matches[2]) ? $matches[2] : '';
            $url_suffix   = isset($matches[3]) ? $matches[3] : '';
            
			$servers = $this->get_servers_ip($url_host);

			// Loop until find work server
			foreach ($servers as $server) {
				
				$dns = Helper::ip__resolve__cleantalks($server['ip']);
				if(!$dns)
					continue;
				
				$this->work_url = $url_protocol.$dns.$url_suffix;
				$this->server_ttl = $server['ttl'];

				$result = $this->sendRequest($this->work_url, $msg, $this->server_timeout);

				if ($result !== false && $result->errno === 0) {
					$this->server_change = true;
					break;
				}
			}
        }
		
        $response = new CleantalkResponse(null, $result);
		
        if (!empty($this->data_codepage) && $this->data_codepage !== 'UTF-8') {
            if (!empty($response->comment))
            $response->comment = $this->stringFromUTF8($response->comment, $this->data_codepage);
            if (!empty($response->errstr))
            $response->errstr = $this->stringFromUTF8($response->errstr, $this->data_codepage);
            if (!empty($response->sms_error_text))
            $response->sms_error_text = $this->stringFromUTF8($response->sms_error_text, $this->data_codepage);
        }
		
        return $response;
    }
    
    /**
     * Function DNS request
     * @param $host
     * @return array
     */
    public function get_servers_ip($host)
	{
        if (!isset($host))
            return null;
		
		$servers = array();
		
		// Get DNS records about URL
        if (function_exists('dns_get_record')) {
            $records = dns_get_record($host, DNS_A);
            if ($records !== FALSE) {
                foreach ($records as $server) {
                    $servers[] = $server;
                }
            }
        }

		// Another try if first failed
        if (count($servers) == 0 && function_exists('gethostbynamel')) {
            $records = gethostbynamel($host);
            if ($records !== FALSE) {
                foreach ($records as $server) {
                    $servers[] = array(
						"ip" => $server,
                        "host" => $host,
                        "ttl" => $this->server_ttl
                    );
                }
            }
        }

		// If couldn't get records
        if (count($servers) == 0){
			
            $servers[] = array(
				"ip" => null,
                "host" => $host,
                "ttl" => $this->server_ttl
            );
		
		// If records recieved
        } else {
			
            $tmp = null;
            $fast_server_found = false;
                
            foreach ($servers as $server) {
				
                if ($fast_server_found) {
                    $ping = $this->max_server_timeout;
                } else {
                    $ping = $this->httpPing($server['ip']);
                    $ping = $ping * 1000;
                }
                
				$tmp[$ping] = $server;
                
				$fast_server_found = $ping < $this->min_server_timeout ? true : false;

			}

            if (count($tmp)){
                ksort($tmp);
                $response = $tmp;
			}

		}

        return empty($response) ? null : $response;
    }
	
	/**
	 * Send JSON request to servers
	 * @param $msg
	 * @return boolean|\CleantalkResponse
	 */
	private function sendRequest($url, $data, $server_timeout = 3)
	{
		// Convert to JSON
		$data = json_encode($data);
		
		// Adding API version
		if( isset( $this->api_version ) && strpos( $url, $this->api_version ) === false ) {
			$url .= $this->api_version;
		}
		
		// Default preset is 'api'
		$presets = array( 'moderate_api' );
		
		// Add ssl to 'presets' if enabled
		if($this->ssl_on){
			array_push( $presets, 'ssl' );
			$url = ! preg_match( "/^https:/", $url ) ? preg_replace( "/^(http)/i", "$1s", $url ) : $url;
		}
		
		$result = \Cleantalk\ApbctUni\Helper::http__request( $url, $data, $presets);
		
		// Retry with SSL enabled if failed
		if( ! empty ( $result['error'] ) && ! $this->ssl_on ){
			array_push( $presets, 'ssl' );
			$url = ! preg_match( "/^https:/", $url ) ? preg_replace( "/^(http)/i", "$1s", $url ) : $url;
			$result = \Cleantalk\ApbctUni\Helper::http__request( $url, $data, $presets );
		}
		
		// Check if the answer is JSON
		if( empty ( $result['error'] ) && ! Helper::is_json($result)){
			$response['error'] = 'Wrong server response format: ' . substr( $result, 100 );
		}
		return $result;
	}
	
	/**
    * Function to check response time
    * param string
    * @return int
    */
    function httpPing($host){

        // Skip localhost ping cause it raise error at fsockopen.
        // And return minimun value
        if ($host == 'localhost')
            return 0.001;

        $starttime = microtime(true);
        $file      = @fsockopen ($host, 80, $errno, $errstr, $this->max_server_timeout/1000);
        $stoptime  = microtime(true);
		
        if (!$file) {
            $status = $this->max_server_timeout/1000;  // Site is down
        } else {
            fclose($file);
            $status = ($stoptime - $starttime);
            $status = round($status, 4);
        }
        
        return $status;
    }
}
