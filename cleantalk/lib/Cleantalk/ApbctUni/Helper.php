<?php

namespace Cleantalk\ApbctUni;

/**
 * Cleantalk's hepler class
 * 
 * Mostly contains request's wrappers.
 *
 * @version 2.4
 * @package Cleantalk
 * @subpackage Helper
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */

class Helper extends \Cleantalk\Common\Helper
{	
	public static function http__user_agent()
	{
		return defined( 'APBCT_USER_AGENT' ) ? APBCT_USER_AGENT : static::DEFAULT_USER_AGENT;
	}

	/**
	 * Pops line from the csv buffer and format it by map to array
	 *
	 * @param $csv
	 * @param array $map
	 *
	 * @return array|false
	 */
	public static function buffer__csv__pop_line_to_array(&$csv, $map = array(), $stripslashes = false)
	{
		$line = trim(static::buffer__csv__pop_line($csv));
		$line = strpos( $line, '\'' ) === 0
            ? str_getcsv( $line, ',', '\'' )
            : explode( ',', $line );
		
		if( $stripslashes ){
            $line = array_map( function( $elem ){
                    return stripslashes( $elem );
                },
                $line
            );
        }
		if( $map )
			$line = array_combine( $map, $line );
		
		return $line;
	}

	/**
	 * Pops line from buffer without formatting
	 *
	 * @param $csv
	 *
	 * @return false|string
	 */
	public static function buffer__csv__pop_line(&$csv)
	{
		$pos  = strpos( $csv, "\n" );
		$line = substr( $csv, 0, $pos );
		$csv  = substr_replace( $csv, '', 0, $pos + 1 );
		return $line;
	}
}