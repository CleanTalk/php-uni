<?php

require_once 'CleantalkBase/CleantalkSFW.php';


/*
 * CleanTalk SpamFireWall base class
 * Compatible only with SMF.
 * Version 1.5-smf
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class CleantalkSFWUni extends CleantalkBase\CleantalkSFW {

	public function ip_check() {
		if (file_exists(dirname(__FILE__) . '/../data/sfw_nets.php')) {
			require_once dirname(__FILE__) . '/../data/sfw_nets.php';
			if ($sfw_nets && is_array($sfw_nets) && count($sfw_nets) > 0 ) {
				foreach($this->ip_array as $origin => $current_ip) {
					$found_network['found'] = false;
					foreach ($sfw_nets as $net) {
						if ($net[0] == sprintf("%u", ip2long($current_ip)) & $net[1]) {
							$found_network['found'] = true;
							$found_network['network'] = $net[0];
							$found_network['mask'] = $net[1];
						}
					}
					if($found_network['found']){
						$this->pass = false;
						$this->blocked_ips[$origin] = array(
							'ip'      => $current_ip,
							'network' => long2ip($found_network['network']),
							'mask'    => CleantalkBase\CleantalkHelper::ip__mask__long_to_number($found_network['mask']),
						);
						$this->all_ips[$origin] = array(
							'ip'      => $current_ip,
							'network' => long2ip($found_network['network']),
							'mask'    => CleantalkBase\CleantalkHelper::ip__mask__long_to_number($found_network['mask']),
							'status'  => -1,
						);
					}else{
						$this->passed_ips[$origin] = array(
							'ip'     => $current_ip,
						);
						$this->all_ips[$origin] = array(
							'ip'     => $current_ip,
							'status' => 1,
						);
					}
				}

			}
		}

	}
	public function logs__update($ip, $result) {
		if($ip === NULL || $result === NULL){
			return;
		}
		
		$blocked = ($result == 'blocked' ? ' + 1' : '');
		$time = time();
		if (file_exists(dirname(__FILE__) . '/../data/sfw_logs/'.session_id().'.log')) {
			$log_file = file_get_contents(dirname(__FILE__) . '/../data/sfw_logs/'.session_id().'.log');
			$all_entries_match = preg_match('/\nall_entries = (.*?)\n/', $log_file, $matches) ? $matches[1] : '';
			$blocked_entries_match = preg_match('/\nblocked_entries = (.*?)\n/', $log_file, $matches) ? $matches[1] : '';
			if ($blocked != '') {
				$blocked_entries_match++;
			}
			file_put_contents(dirname(__FILE__) . '/../data/sfw_logs/'.session_id().'.log', "ip = ".$ip."\nall_entries = ".(intval($all_entries_match) + 1)."\nblocked_entries = ".$blocked_entries_match."\nentries_timestamp = ".intval($time));
		} else {
			file_put_contents(dirname(__FILE__) . '/../data/sfw_logs/'.session_id().'.log', "ip = ".$ip."\nall_entries = 1\nblocked_entries = 1\nentries_timestamp = ".intval($time));
		}

	}
	public function logs__send($ct_key) {
        $log_files = array_diff(scandir(dirname(__FILE__) . '/../data/sfw_logs'), array('.', '..'));

        if ($log_files && count($log_files) > 0) {

	        //Compile logs
			$data = array();

	        foreach ($log_files as $log_file) {
	        	$log_content = file_get_contents(dirname(__FILE__) . '/../data/sfw_logs/'.$log_file);
	        	$ip_match = preg_match('/ip = (.*?)\n/', $log_content, $matches) ? $matches[1] : '';
	        	$all_entries_match = preg_match('/\nall_entries = (.*?)\n/', $log_content, $matches) ? $matches[1] : '';
	        	$blocked_entries_match = preg_match('/\nblocked_entries = (.*?)\n/', $log_content, $matches) ? $matches[1] : '';
	        	$timestamp_entries_match = preg_match('/\nentries_timestamp = (.*?)$/', $log_content, $matches) ? $matches[1] : '';
	            $data[] = array(trim($ip_match), $all_entries_match, $all_entries_match-$blocked_entries_match, $timestamp_entries_match);
	        } 
	        unset($log_file);

	        $result = CleantalkBase\CleantalkAPI::method__sfw_logs($ct_key, $data);

			//Checking answer and deleting all lines from the table
			if(empty($result['error'])){
				if($result['rows'] == count($data)){
					foreach ($log_files as $log_file) {
						unlink(dirname(__FILE__) . '/../data/sfw_logs/'.$log_file);
					}
					return $result;
				}
				return array('error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH');
			}else{
				return $result;
			}          	
        }
        else{
			return array('error' => 'NO_LOGS_TO_SEND');
		}
      
	}
	public function sfw_update($ct_key, $file_url = null, $immediate = false) {
		//TODO unzip file and remote calls
		
        $get_sfw_nets = CleantalkBase\CleantalkAPI::method__get_2s_blacklists_db($ct_key);
        if ($get_sfw_nets)
            file_put_contents(dirname(__FILE__) . '/../data/sfw_nets.php', "<?php\n\$sfw_nets = ".var_export($get_sfw_nets,true).";");
	}
	public function sfw_die($api_key, $cookie_prefix = '', $cookie_domain = '', $test = false) {
				
		// Headers
		if(headers_sent() === false){
			header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', FALSE);
			header('Pragma: no-cache');
			header("HTTP/1.0 403 Forbidden");
		}
		
		// File exists?
		if(file_exists(dirname(__FILE__) . "/sfw_die_page.html")){
			
			$sfw_die_page = file_get_contents(dirname(__FILE__) . "/sfw_die_page.html");

			// Translation
			$request_uri = $_SERVER['REQUEST_URI'];
			$sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',             'SpamFireWall is activated for your IP ', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   'To continue working with web site, please make sure that you have enabled JavaScript', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          'Please click below to pass protection,', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf('Or you will be automatically redirected to the requested page after %d seconds.', 1), $sfw_die_page);
			$sfw_die_page = str_replace('{CLEANTALK_TITLE}',                'Antispam by CleanTalk', $sfw_die_page);
			$sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->test ? 'This is the testing page for SpamFireWall' : ''), $sfw_die_page);
	
			if($this->test){
				$sfw_die_page = str_replace('{REAL_IP__HEADER}', 'Real IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP__HEADER}', 'Test IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP}', $this->all_ips['sfw_test']['ip'], $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP}', (isset($this->all_ips['real']) && $this->all_ips['real']['ip']),     $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP_BLOCKED}', $this->all_ips['sfw_test']['status'] == 1 ? 'Passed' : 'Blocked', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP_BLOCKED}', (isset($this->all_ips['real']) && $this->all_ips['real']['status'] == 1) ? 'Passed' : 'Blocked',     $sfw_die_page);
			}else{
				$sfw_die_page = str_replace('{REAL_IP__HEADER}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP__HEADER}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP_BLOCKED}', '', $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP_BLOCKED}', '', $sfw_die_page);
			}
			
			$sfw_die_page = str_replace('{REMOTE_ADDRESS}', $this->blocked_ips ? $this->blocked_ips[key($this->blocked_ips)]['ip'] : '', $sfw_die_page);
			
			// Service info
			$sfw_die_page = str_replace('{REQUEST_URI}',    $request_uri,                    $sfw_die_page);
			$sfw_die_page = str_replace('{COOKIE_PREFIX}',  $cookie_prefix,                  $sfw_die_page);
			$sfw_die_page = str_replace('{COOKIE_DOMAIN}',  $cookie_domain,                  $sfw_die_page);
			
			$sfw_die_page = str_replace(
				'{SFW_COOKIE}',
				$this->test
					? $this->all_ips['sfw_test']['ip']
					: md5(current(end($this->blocked_ips)).$api_key),
				$sfw_die_page
			);
			
			if($this->debug){
				$debug = '<h1>IP and Networks</h1>'
					. var_export($this->all_ips, true)
					.'<h1>Blocked IPs</h1>'
			        . var_export($this->passed_ips, true)
			        .'<h1>Passed IPs</h1>'
			        . var_export($this->blocked_ips, true)
					. '<h1>Headers</h1>'
					. var_export(apache_request_headers(), true)
					. '<h1>REMOTE_ADDR</h1>'
					. var_export($_SERVER['REMOTE_ADDR'], true)
					. '<h1>SERVER_ADDR</h1>'
					. var_export($_SERVER['SERVER_ADDR'], true)
					. '<h1>IP_ARRAY</h1>'
					. var_export($this->ip_array, true)
					. '<h1>ADDITIONAL</h1>'
					. var_export($this->debug_data, true);
			}else
				$debug = '';
			
			$sfw_die_page = str_replace( "{DEBUG}", $debug, $sfw_die_page );
			$sfw_die_page = str_replace('{GENERATED}', "<p>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</p>",$sfw_die_page);
			
			die($sfw_die_page);
			
		}else{
			parent::sfw_die($auth_key);
		}
		
	}
}
