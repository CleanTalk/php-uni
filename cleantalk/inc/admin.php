<?php

use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Post;
use Cleantalk\ApbctUni\Cron;

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

function install( $files, $api_key, $cms, $exclusions ){
	
	foreach ($files as $file){
		
		$file = CLEANTALK_SERVER_ROOT . $file;
		$file_content = file_get_contents( $file );
		$php_open_tags  = preg_match_all("/(<\?)/", $file_content);
		$php_close_tags = preg_match_all("/(\?>)/", $file_content);
		$first_php_start = strpos($file_content, '<?');
		
		// Adding <?php to the start if it's not there
		if($first_php_start !== 0)
			File::inject__code($file, "<?php\n?>\n", 'start');
		
		if( ! Err::check() ){
			
			// Adding ? > to the end if it's not there
			if($php_open_tags <= $php_close_tags)
				File::inject__code($file, "\n<?php\n", 'end');
			
			if( ! Err::check() ){
				
				// Addition to the top of the script
				File::inject__code(
					$file,
					"\trequire_once( getcwd() . '/cleantalk/cleantalk.php');",
					'(<\?php)|(<\?)',
					'top_code'
				);
				
				if( ! Err::check() ){
					
					// Addition to index.php Bottom (JavaScript test)
					File::inject__code(
						$file,
						"\tob_end_flush();\n"
						."\tif(isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){\n"
						."\t\tdie();\n"
						."\t}",
						'end',
						'bottom_code'
					);
					
				}
			}
		}
	}
	
	// Clean config
	if( ! Err::check() )
		uninstall();
	
	// Install settings in cofig if everything is ok
	if( ! Err::check() )
		install_config( $files, $api_key, $cms, $exclusions );
	
	// Set cron tasks
	if( ! Err::check() )
		install_cron();
}

function install_config( $modified_files, $api_key, $cms, $exclusions ){
	
	$path_to_config = CLEANTALK_ROOT . 'config.php';
	$salt = str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0');
	// Attention. Backwards order because inserting it step by step
	
	if( Post::get( 'admin_password' ) )
		File::inject__variable( $path_to_config, 'password', hash( 'sha256', trim( Post::get( 'admin_password' ) ) ) );
	if( Post::get( 'email' ) )
		File::inject__variable( $path_to_config, 'email', trim( Post::get( 'email' ) ) );
	if( Post::get( 'user_token' ) )
		File::inject__variable( $path_to_config, 'user_token', trim( Post::get( 'user_token' ) ) );
	if( Post::get( 'account_name_ob' ) )
		File::inject__variable( $path_to_config, 'account_name_ob', trim( Post::get( 'account_name_ob' ) ) );
	File::inject__variable( $path_to_config, 'salt', $salt );
	File::inject__variable( $path_to_config, 'security', hash( 'sha256', '0(o_O)0' . $salt ) );
	File::inject__variable( $path_to_config, 'modified_files', $modified_files, true );
	if( $exclusions )
		File::inject__variable( $path_to_config, 'exclusions', $exclusions, true );
	File::inject__variable( $path_to_config, 'apikey', $api_key );
	File::inject__variable( $path_to_config, 'detected_cms', $cms );
	File::inject__variable( $path_to_config, 'is_installed', true );
}

function install_cron(){
	Cron::addTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 );
	Cron::addTask( 'sfw_send_logs', 'apbct_sfw_send_logs', 3600 );
}

function uninstall( $files = array() ){
	
	global $modified_files;
	
	// Clean files from config.php
	$files = empty($files) && isset($modified_files)
		? $modified_files
		: $files;
	
	$path_to_config = CLEANTALK_ROOT . 'config.php';
	File::clean__variable( $path_to_config, 'security' );
	File::clean__variable( $path_to_config, 'password' );
	File::clean__variable( $path_to_config, 'salt' );
	File::clean__variable( $path_to_config, 'apikey' );
	File::clean__variable( $path_to_config, 'email' );
	File::clean__variable( $path_to_config, 'user_token' );
	File::clean__variable( $path_to_config, 'account_name_ob' );
	File::clean__variable( $path_to_config, 'detected_cms' );
	File::clean__variable( $path_to_config, 'admin_password' );
	File::clean__variable( $path_to_config, 'modified_files' );
	File::clean__variable( $path_to_config, 'exclusions' );
	File::clean__variable( $path_to_config, 'is_installed' );
	
	// Restore deafult settings
	File::replace__variable( $path_to_config, 'sfw_last_update', 0 );
	File::replace__variable( $path_to_config, 'sfw_last_logs_send', 0 );
	File::replace__variable( $path_to_config, 'sfw_entries', 0 );
	File::replace__variable( $path_to_config, 'registrations_test', true );
	File::replace__variable( $path_to_config, 'general_postdata_test', false );
	File::replace__variable( $path_to_config, 'spam_firewall', true );
	
	// Deleting cron tasks
	File::replace__variable( CLEANTALK_CRON_FILE, 'tasks', array() );
	
	// Deleting SFW nets
	File::clean__variable( CLEANTALK_ROOT . 'data' . DS . 'sfw_nets.php', 'sfw_nets' );
	
	if(isset($files)){
		foreach ( $files as $file ){
			File::clean__tag( CLEANTALK_SERVER_ROOT . $file, 'top_code' );
			File::clean__tag( CLEANTALK_SERVER_ROOT . $file, 'bottom_code' );
		}
	}
	
	return ! Err::check();
}

function detect_cms( $path_to_index, $out = 'Unknown' ){
	
	// Detecting CMS
	$index_file = file_get_contents( $path_to_index );
	
	//X-Cart 4
	if (preg_match('/(xcart_4_.*?)/', $index_file))
		$out = 'X-Cart 4';
	//osTicket
	if (preg_match('/osticket/i', $index_file))
		$out = 'osTicket';
	// PrestaShop
	if (preg_match('/(PrestaShop.*?)/', $index_file))
		$out = 'PrestaShop';
	// Question2Answer
	if (preg_match('/(Question2Answer.*?)/', $index_file))
		$out = 'Question2Answer';
	
	return $out;
}