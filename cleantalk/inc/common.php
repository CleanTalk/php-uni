<?php

define('APBCT_PLUGIN', 'uni');
define('APBCT_VERSION', '2.5.2');
define('APBCT_AGENT', APBCT_PLUGIN . '-' . str_replace( '.', '', APBCT_VERSION ) );
define('APBCT_USER_AGENT', 'Cleantalk-Antispam-Universal-Plugin/' . APBCT_VERSION);

function apbct_set_include_path(){
	set_include_path( CLEANTALK_ROOT );
}

function apbct_restore_include_path(){
	set_include_path( get_include_path() );
}

$ds = DIRECTORY_SEPARATOR;
define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_SITE_ROOT', realpath(__DIR__ . "$ds..$ds..$ds" ) . $ds );
define( 'CLEANTALK_ROOT', CLEANTALK_SITE_ROOT . 'cleantalk' . $ds );
define( 'CLEANTALK_LIB', CLEANTALK_ROOT . 'lib' . $ds );
define( 'CLEANTALK_INC', CLEANTALK_ROOT . 'inc' . $ds );
define( 'CLEANTALK_CONFIG_FILE', CLEANTALK_ROOT . 'config.php' );
define( 'CLEANTALK_CRON_FILE', CLEANTALK_ROOT . 'data' . DS . 'cron_data.php' );

apbct_set_include_path();
require_once CLEANTALK_LIB . 'ct_phpFix.php';
require_once CLEANTALK_LIB . 'autoloader.php';
require_once CLEANTALK_ROOT . 'config.php';

// Create empty error object
\Cleantalk\Common\Err::getInstance();
// Run scheduled tasks
$cron = new \Cleantalk\ApbctUni\Cron();
$cron->checkTasks();
if( ! empty( $cron->tasks_to_run ) )
	require_once CLEANTALK_ROOT . 'inc' . DS . 'cron_functions.php'; // File with cron wrappers
	$cron->runTasks();
unset( $cron );
