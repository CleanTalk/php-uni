<?php

define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_SERVER_ROOT', $_SERVER['DOCUMENT_ROOT'] . DS );
define( 'CLEANTALK_ROOT', $_SERVER['DOCUMENT_ROOT'] . DS . 'cleantalk' . DS );
define( 'CLEANTALK_CONFIG_FILE', CLEANTALK_ROOT . 'config.php' );
define( 'CLEANTALK_CRON_FILE', CLEANTALK_ROOT . 'data' . DS . 'cron_data.php' );

require_once CLEANTALK_ROOT . 'lib' . DS . 'autoloader.php';
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