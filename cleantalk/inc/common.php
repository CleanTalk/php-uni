<?php

use Cleantalk\ApbctUni\RemoteCalls;
use Cleantalk\Variables\Server;

define('APBCT_PLUGIN', 'uni');
define('APBCT_VERSION', '2.8.0');
define('APBCT_AGENT', APBCT_PLUGIN . '-' . str_replace( '.', '', APBCT_VERSION ) );
define('APBCT_USER_AGENT', 'Cleantalk-Antispam-Universal-Plugin/' . APBCT_VERSION);
define('APBCT_INITIAL_INCLUDE_PATH', get_include_path());

function apbct_set_include_path()
{
    defined('APBCT_INCLUDE_PATH_ON_FIRST_SET_CALL') or define('APBCT_INCLUDE_PATH_ON_FIRST_SET_CALL', get_include_path());
    set_include_path(CLEANTALK_ROOT);
}

function apbct_restore_include_path()
{
    set_include_path(get_include_path());
    if ( get_include_path() === CLEANTALK_ROOT ) {
        if ( defined(APBCT_INCLUDE_PATH_ON_FIRST_SET_CALL) ) {
            set_include_path(APBCT_INCLUDE_PATH_ON_FIRST_SET_CALL);
        } else {
            set_include_path(APBCT_INITIAL_INCLUDE_PATH);
        }
    }
}

$ds = DIRECTORY_SEPARATOR;
if ( ! defined('DS') ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
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

define('CT_URI', 'http://' . Server::get('HTTP_HOST') . preg_replace( '/^(\/.*?\/).*/', '$1', parse_url(Server::get('REQUEST_URI'), PHP_URL_PATH)));
$result = parse_url(Server::get('REQUEST_URI'));
define('CT_AJAX_URI', isset($result['path']) ? $result['path'] : '/cleantalk/cleantalk.php');

// Create empty error object
\Cleantalk\Common\Err::getInstance();
// Run scheduled tasks
require_once CLEANTALK_ROOT . 'inc' . DS . 'cron_functions.php';
/** @var \Cleantalk\Custom\Cron\Cron $cron_class */
$cron_class = \Cleantalk\Common\Mloader\Mloader::get('Cron');
$cron = new $cron_class();
$cron->checkCronData();
$tasks_to_run = $cron->checkTasks();
if (!empty( $tasks_to_run ) &&
    (
        !defined('DOING_CRON') ||
        (defined('DOING_CRON') && DOING_CRON !== true)
    )
) {
    $cron->runTasks($tasks_to_run);
}
unset( $cron );

/**
 * Generate value for checking JS
 */
function apbct_checkjs_hash($apikey, $salt) {
    return hash('sha256', $apikey . $salt);
}

RemoteCalls::check() && RemoteCalls::perform();