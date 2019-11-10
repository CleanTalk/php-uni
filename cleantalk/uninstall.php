<?php

function cleantalk_is_error( $var ){
	return $var instanceof \Cleantalk\Err;
}

use Cleantalk\Err;

define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_ROOT_DIR', getcwd() . DS . '..' . DS );
define( 'CLEANTALK_DIR', CLEANTALK_ROOT_DIR . DS . 'cleantalk' . DS );

// Lib autoloader
require_once CLEANTALK_DIR . 'lib' . DS . 'ct_autoloader.php';

require_once CLEANTALK_DIR . 'ct_config.php';

foreach ($modified_files as $file_name){
	var_dump( $file_name );
	\Cleantalk\File::clean__tag( CLEANTALK_ROOT_DIR . $file_name, 'top_code' );
	\Cleantalk\File::clean__tag( CLEANTALK_ROOT_DIR . $file_name, 'bottom_code' );
	\Cleantalk\File::clean__tag( CLEANTALK_DIR . 'ct_config.php', 'installed_config' );
}

if(Err::get()->has_errors()){
	var_dump( Err::get()->get_all );
	die(Err::get()->get_all('as_json'));
}else{
	die(json_encode(array(
		'success' => true
	)));
}