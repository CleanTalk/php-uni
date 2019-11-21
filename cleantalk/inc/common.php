<?php

define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_SERVER_ROOT', $_SERVER['DOCUMENT_ROOT'] . DS );
define( 'CLEANTALK_ROOT', $_SERVER['DOCUMENT_ROOT'] . DS . 'cleantalk' . DS );

require_once CLEANTALK_ROOT . 'lib' . DS . 'autoloader.php';
require_once CLEANTALK_ROOT . 'config.php';

// Create empty error object
\Cleantalk\Common\Err::getInstance();