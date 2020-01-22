<?php

namespace Cleantalk\ApbctUni;

use Cleantalk\Common\File;
use Cleantalk\Common\Err;

/**
 * Class Cron
 *
 * @package Cleantalk\Common
 */
class Cron extends \Cleantalk\Common\Cron
{
	// Option name with cron data
	const CRON_FILE = CLEANTALK_ROOT . 'data'. DS . 'cron_data.php';
	
	public static function getTasks(){
		require self::CRON_FILE;
		return $tasks;
	}
	
	// Save option with tasks
	public static function saveTasks( $tasks ){
		File::replace__variable( self::CRON_FILE, 'tasks', $tasks );
		return ! Err::check();
	}
}
