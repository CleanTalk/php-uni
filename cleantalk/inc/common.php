<?php

define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_SITE_ROOT', realpath(__DIR__ . DS . '..' . DS . '..' . DS ) . DS );
define( 'CLEANTALK_ROOT', CLEANTALK_SITE_ROOT . 'cleantalk' . DS );
define( 'CLEANTALK_LIB', CLEANTALK_ROOT . 'lib' . DS );
define( 'CLEANTALK_INC', CLEANTALK_ROOT . 'inc' . DS );
define( 'CLEANTALK_CONFIG_FILE', CLEANTALK_ROOT . 'config.php' );
define( 'CLEANTALK_CRON_FILE', CLEANTALK_ROOT . 'data' . DS . 'cron_data.php' );

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