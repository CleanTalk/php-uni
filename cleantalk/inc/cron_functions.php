<?php

use Cleantalk\ApbctUni\SFW;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;

function apbct_sfw_update(){
	
	global $apikey, $spam_firewall;
	
	// SFW actions
	if( isset($apikey, $spam_firewall ) ){
		
		$sfw = new SFW();
		
		// Update SFW
		$result = $sfw->sfw_update( $apikey );
		if( ! Err::check() ){
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_last_update', time() );
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_entries', $result );
			
		}
	}
	
	return ! Err::check() ? true : false;
}

function apbct_sfw_send_logs(){
	
	global $apikey, $spam_firewall;
	
	// SFW actions
	if( isset($apikey, $spam_firewall ) ){
		
		$sfw = new SFW();
		
		// Send SFW logs
		$result = $sfw->logs__send( $apikey );
		
		if( ! empty( $result['error'] ) )
			Err::add( $result['error'] );
		
		if( ! Err::check() )
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_last_logs_send', time() );
	}
	
	return ! Err::check() ? true : false;
}

/**
 * Update latest version of plugin in config.php
 */
function apbct__plugin_get_latest_version() {
    $path_to_config = CLEANTALK_ROOT . 'config.php';
    global $antispam_activity_status;

    $updater = new \Cleantalk\Updater\Updater( CLEANTALK_ROOT );
    $latest_version = $updater->getLatestRelease();

    if (isset($latest_version['error'])){
        Err::add($latest_version['error']);
    } else {
        File::clean__variable($path_to_config, 'latest_version');
        File::inject__variable($path_to_config, 'latest_version', $latest_version);
    }

    if (! isset($antispam_activity_status)) {
        File::clean__variable($path_to_config, 'antispam_activity_status');
        File::inject__variable($path_to_config, 'antispam_activity_status', true);
    }
}