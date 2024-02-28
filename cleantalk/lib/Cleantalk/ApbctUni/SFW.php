<?php

namespace Cleantalk\ApbctUni;

/*
 * CleanTalk SpamFireWall base class
 * Compatible only with SMF.
 * Version 1.5-smf
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

use Cleantalk\ApbctUni\File\FileDB;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Server;
use Cleantalk\Variables\Get;

class SFW extends \Cleantalk\Antispam\SFW
{
	private static $test_domains = array( 'lc', 'loc', 'lh', 'test' );

	public function check()
	{
		$results = array();

		foreach( $this->ip_array as $ip_origin => $current_ip ) {
			$ip_type = Helper::ip__validate($current_ip);

			if (!$ip_type || $ip_type !== 'v4') {
				continue;
			}

			$current_ip_v4 = sprintf("%u", ip2long($current_ip));

			// Creating IPs to search
			for ($needles = array(), $m = 6; $m <= 32; $m++) {
				$mask      = str_repeat('1',$m);
				$mask      = str_pad($mask, 32, '0');
				$needles[] = sprintf("%u", bindec($mask & base_convert($current_ip_v4, 10, 2)));
			}
			$needles = array_unique($needles);

			$db = new FileDB('fw_nets');
			$db_results = $db
				->setWhere(array('network' => $needles))
				->setLimit(0, 20)
				->select('network', 'mask', 'status', 'is_personal');

			if (!empty($db_results)) {
				foreach( $db_results as $entry ) {
					$this->pass = true;

                    $is_personal = isset($entry['is_personal']) ? $entry['is_personal'] : 0;
                    $status = isset($entry['status']) ? $entry['status'] : null;

                    if (is_null($status)) {
                        continue;
                    }

					$result_entry = array(
						'ip' => $current_ip,
						'network' => $entry['network'],
						'mask' => $entry['mask'],
                        'status' => $status,
                        'is_personal' => $is_personal,
					);

                    $handled_record = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($entry['network']),
                        'mask'    => $this->helper()->ip__mask__long_to_number($entry['mask']),
                        'status'  => $status,
                        'is_personal' => $is_personal,
                    );

                    if ($status == 0) {
                        $this->blocked_ips[$ip_origin] = $handled_record;
                        $this->pass = false;
                    } else {
                        $this->passed_ips[$ip_origin] = $handled_record;
                    }

                    $this->all_ips[$ip_origin] = array(
                        'ip'      => $current_ip,
                        'network' => long2ip($entry['network']),
                        'mask'    => $this->helper()->ip__mask__long_to_number($entry['mask']),
                        'status'  => $status,
                        'is_personal' => $is_personal,
                    );

					$results[] = $result_entry;
				}

				continue;
			}

			$this->passed_ips[$ip_origin] = array(
				'ip'     => $current_ip,
			);
			$this->all_ips[$ip_origin] = array(
				'ip'     => $current_ip,
				'status' => 1,
			);

			$results[] = array(
				'ip' => $current_ip,
				'network' => null,
				'mask' => null,
				'status' => 'PASS',
			);
		}
		return $results;
	}

	public function logs__update($ip, $result, $status = 1) {
		if($ip === NULL || $result === NULL)
			return;

		global $apbct_salt;

		$time = time();
		$log_path = CLEANTALK_ROOT . 'data/sfw_logs/'. hash('sha256', $ip . $apbct_salt) .'.log';

		if( file_exists( $log_path ) ){

			$log             = file_get_contents( $log_path );
			$log             = explode( ',', $log );

			$all_entries     = isset( $log[1] ) ? $log[1] : 0;
			$blocked_entries = isset( $log[2] ) ? $log[2] : 0;
			$blocked_entries = $result == 'blocked' ? $blocked_entries+1 : $blocked_entries;

			$log = array( $ip, intval( $all_entries ) + 1, $blocked_entries, $time, (int)$status );

		}else{

			$blocked = $result == 'blocked' ? 1 : 0;

			$log = array( $ip, 1, $blocked, $time, (int)$status);

		}

		file_put_contents( $log_path, implode( ',', $log) );

	}
	public function logs__send($ct_key) {

		$log_dir_path = CLEANTALK_ROOT . 'data/sfw_logs';

		if( is_dir( $log_dir_path ) ){

			$log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );

			if( ! empty( $log_files ) ){

				//Compile logs
				$data = array();

				foreach ( $log_files as $log_file ){
					$log = file_get_contents( $log_dir_path . DS . $log_file );
					$log = explode( ',', $log );
					$ip                = isset( $log[0] ) ? $log[0] : '';
					$all_entries       = isset( $log[1] ) ? $log[1] : 0;
					$blocked_entries   = isset( $log[2] ) ? $log[2] : 0;
					$timestamp_entries = isset( $log[3] ) ? $log[3] : 0;
					$status 		   = isset( $log[4] ) && (int)$log[4] === 1 ? 'PERSONAL_LIST_MATCH' : 'DENY_SFW';
					$data[] = array(
						$ip,
						$all_entries,
						$all_entries - $blocked_entries,
						$timestamp_entries,
						$status
					);
				}
				unset( $log_file );

				$result = $this->api()->method__sfw_logs( $ct_key, $data );

				//Checking answer and deleting all lines from the table
				if( empty( $result['error'] ) ){

					if( $result['rows'] == count( $data ) ){

						foreach ( $log_files as $log_file ){
							$file_path = $log_dir_path . DS . $log_file;
							if (file_exists($file_path)) {
								unlink( $file_path );
							}
						}

						return $result;
					}else
						return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
				}else
					return $result;
			}else
				return array( 'error' => 'NO_LOGS_TO_SEND' );
		}else
			return array( 'error' => 'NO_LOGS_TO_SEND' );
	}

	public function sfw_update($ct_key, $file_url = null, $immediate = false)
	{
		$sub_action = Get::get('sub_action') === "" ? "download_data" : Get::get('sub_action');
		$out = 0;
		$update_folder_path = CLEANTALK_ROOT . 'data' . DS . 'fw_files' . DS;

		switch ($sub_action) {
			case "download_data":
				$update_folder = $this->sfw_update__prepare_upd_dir($update_folder_path);
				if (!empty( $update_folder['error'])) {
					return $update_folder;
				}

				$result = $this->api()->method__get_2s_blacklists_db($ct_key, 'file', '3_0');
				if (isset($result['error']) && !empty($result['error'])) {
					Err::add('SpamFirewall update', $result['error'] );
					return $out;
				}

				$file = Helper::http__download_remote_file($result['file_url'], $update_folder_path);
				if (isset($file['error']) && !empty($file['error'])) {
					Err::add('SpamFirewall update', $file['error'] );
					return $out;
				}

				$this->sfw_update__remote_call($ct_key, "uncompress_gz");
				break;

			case "uncompress_gz":
				$gz_files = glob($update_folder_path . 'bl_list_*.csv.gz');

				if (!$gz_files || !isset($gz_files[0])) {
					Err::add('SpamFirewall update', 'No files to parse');
					return $out;
				}

				$buffer_size = 4096;
				$out_file_name = $update_folder_path . 'sfw_data.csv';
				$file = gzopen($gz_files[0], 'rb');
				$out_file = fopen($out_file_name, 'wb');
				while (!gzeof($file)) {
					fwrite($out_file, gzread($file, $buffer_size));
				}
				fclose($out_file);
				gzclose($file);

				if (file_exists($gz_files[0])) {
					unlink($gz_files[0]);
				}

				$this->sfw_update__remote_call($ct_key, "split_csv");
				return $out;

			case "split_csv":
				$outputFile = $update_folder_path . 'sfw_data-';
				$split_size = 5000;
				$data_file = $update_folder_path . 'sfw_data.csv';
				$in = fopen($data_file, 'r');
				$rows = 0;
				$file_count = 1;
				$out = null;

				while (!feof($in)) {
					if (($rows % $split_size) == 0) {
						if ($rows > 0) {
							fclose($out);
						}

						$file_count++;
						$file_counter = sprintf("%04d", $file_count);
						$file_name = $outputFile . $file_counter . ".csv";
						$out = fopen($file_name, 'w');
					}

					$data = fgetcsv($in);
					if ($data) {
						fputcsv($out, $data);
					}

					$rows++;
				}
				fclose($out);

				if (file_exists($data_file)) {
					unlink($data_file);
				}

				$this->sfw_update__clear_temp_data();
				File::replace__variable(CLEANTALK_ROOT . 'config.php', 'sfw_entries', 0);

					$this->sfw_update__remote_call($ct_key, "insert_to_db");
				break;

			case "insert_to_db":
				$files = glob($update_folder_path . 'sfw_data-*.csv');
				if (!$files || !isset($files[0])) {
					$this->sfw_update__remote_call($ct_key, "replace_temp_to_permanent");
					break;
				}

				$path = reset($files);
				$result = $this->sfw_update__write_to_db($path);
				if (empty($result['error']) || $result['error'] === 'Couldn\'t get data') {
					if (file_exists(reset($files))) {
						unlink(reset($files));
					}

					if (empty($result['error']) && is_int($result)) {
						$path = CLEANTALK_ROOT . 'config.php';
						$count = File::get__variable($path, 'sfw_entries');
						File::replace__variable($path, 'sfw_entries', (int)$count + $result);
					}

					$this->sfw_update__remote_call($ct_key, "insert_to_db");
					break;
				}

				break;

			case "replace_temp_to_permanent":
				rename(CLEANTALK_ROOT . 'data' . DS . 'fw_nets_network_temp.btree', CLEANTALK_ROOT . 'data' . DS . 'fw_nets_network.btree');
				rename(CLEANTALK_ROOT . 'data' . DS . 'fw_nets_temp.storage', CLEANTALK_ROOT . 'data' . DS . 'fw_nets.storage');
				break;
		}

		return $out;
	}

	private function sfw_update__write_to_db($path)
	{
		// Get data
		if (!file_exists($path)) {
			return array('error' => 'File doesn\'t exists: ' . $path);
		}

		if (!is_readable($path)) {
			return array('error' => 'File is not readable: ' . $path);
		}

		$data = file_get_contents($path);

		if (!$data) {
			return array('error' => 'Couldn\'t get data');
		}

		// Write to DB
		$db = new FileDB('fw_nets');
		$networks_to_skip = array();
		if (in_array( Server::get_domain(), self::$test_domains)) {
			$networks_to_skip[] = ip2long('127.0.0.1');
		}

		$inserted = 0;
		while($data !== '') {
			for(
				$i = 0, $nets_for_save = array();
				$i < 2500 && $data !== '';
				$i++
			){
				$entry = Helper::buffer__csv__pop_line_to_array($data);
				if (in_array($entry[0], $networks_to_skip)) {
					continue;
				}

				//skip ipv6 because of reasons :(
				if (!is_numeric($entry[0])) {
					continue;
				}

				$nets_for_save[] = array(
					'network'     => $entry[0],
					'mask'        => $entry[1],
					'status'      => isset( $entry[2] ) ? $entry[2] : 0,
					'is_personal' => isset( $entry[3] ) ? intval( $entry[3] ) : 0,
				);

			}

			if(empty($nets_for_save)) {
				Err::add( 'Updating FW', 'No data to save' );
				return $inserted;
			}

			$inserted += $db->insertTemp($nets_for_save);

			if (Err::check()) {
				Err::prepend('Updating FW');
				error_log(var_export(Err::get_all('string'), true));
				return array('error' => Err::get_last('string'));
			}
		}

		return $inserted;
	}

	private function sfw_update__clear_temp_data()
	{
		$db = new FileDB('fw_nets');
		$db->deleteTemp();

		return array('success' => true);
	}

	private function sfw_update__remote_call($ct_key, $sub_action)
	{
		Helper::http__request(
			Server::get( 'HTTP_HOST' ) . CT_AJAX_URI,
			array(
				'apbct_remote_call_token'  => md5($ct_key),
				'apbct_remote_call_action' => 'update_ct_firewall',
				'plugin_name'			   => 'apbct',
				'delay'					   => 3,
				'sub_action'	           => $sub_action,
			),
			array('get', 'async')
		);
	}

	private function sfw_update__prepare_upd_dir($dir_name)
	{
        if ($dir_name === '') {
            return array( 'error' => 'FW dir can not be blank.' );
        }

		if (!is_dir(CLEANTALK_ROOT . 'data')) {
			mkdir(CLEANTALK_ROOT . 'data');
		}

        if (!is_dir($dir_name) && !mkdir($dir_name)) {
            return !is_writable(CLEANTALK_ROOT . 'data')
                ? array('error' => 'Can not to make FW dir. Low permissions: ' . fileperms(CLEANTALK_ROOT . 'data'))
                : array('error' => 'Can not to make FW dir. Unknown reason.');

        }

		$files = glob( $dir_name . '/*' );
		if( $files === false ){
			return array( 'error' => 'Can not find FW files.' );
		}
		if( count( $files ) === 0 ){
			return (bool) file_put_contents( $dir_name . 'index.php', '<?php' . PHP_EOL );
		}
		foreach( $files as $file ){
			if( is_file( $file ) && unlink( $file ) === false ){
				return array( 'error' => 'Can not delete the FW file: ' . $file );
			}
		}

        return (bool) file_put_contents( $dir_name . 'index.php', '<?php' );
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
		if( file_exists( CLEANTALK_ROOT . 'lib/sfw_die_page.html' ) ){

			$sfw_die_page = file_get_contents(CLEANTALK_ROOT . 'lib/sfw_die_page.html' );

			// Translation
			$request_uri = $_SERVER['REQUEST_URI'];
			$sfw_die_page = str_replace('{SFW_DIE_NOTICE_IP}',             'SpamFireWall is activated for your IP ', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_MAKE_SURE_JS_ENABLED}',   'To continue working with web site, please make sure that you have enabled JavaScript', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_CLICK_TO_PASS}',          'Please click below to pass protection,', $sfw_die_page);
			$sfw_die_page = str_replace('{SFW_DIE_YOU_WILL_BE_REDIRECTED}', sprintf('Or you will be automatically redirected to the requested page after %d seconds.', 3), $sfw_die_page);
			$sfw_die_page = str_replace('{CLEANTALK_TITLE}',                'Antispam by CleanTalk', $sfw_die_page);
			$sfw_die_page = str_replace('{TEST_TITLE}',                     ($this->test ? 'This is the testing page for SpamFireWall' : ''), $sfw_die_page);

			if($this->test){
				$sfw_test_ip = isset($this->all_ips['sfw_test']['ip']) ? $this->all_ips['sfw_test']['ip'] : '';
				$sfw_test_status = isset($this->all_ips['sfw_test']['status']) ? $this->all_ips['sfw_test']['status'] : '';

				$sfw_die_page = str_replace('{REAL_IP__HEADER}', 'Real IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP__HEADER}', 'Test IP:', $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP}', $sfw_test_ip, $sfw_die_page);
				$sfw_die_page = str_replace('{REAL_IP}', (isset($this->all_ips['real']) && $this->all_ips['real']['ip']) ? $this->all_ips['real']['ip'] : '',     $sfw_die_page);
				$sfw_die_page = str_replace('{TEST_IP_BLOCKED}', $sfw_test_status == 1 ? 'Passed' : 'Blocked', $sfw_die_page);
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
					? $sfw_test_ip
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
					. var_export( \Cleantalk\ApbctUni\Helper::http__get_headers(), true)
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
			parent::sfw_die($api_key);
		}

	}
}
