<?php

namespace Cleantalk\Common;

/**
 * Class File
 * Gather functions that works with files.
 * All methods are static.
 *
 * @package Cleantalk
 */
class File{
	
	/**
	 * Removes content from file in tag
	 * Tags example:
	 * //Cleantalk/TAG_NAME/start
	 * //Cleantalk/TAG_NAME/end
	 *
	 * @param string $file_path
	 * @param string $tag Tag name (Not whole tag. Only tag name.)
	 *
	 * @return bool|Err
	 */
	public static function clean__tag( $file_path, $tag ){
		$pattern = '\s*' . self::tag__php__start( $tag ) . '[\S\s]*?' . self::tag__php__end( $tag );
		$pattern = \Cleantalk\Common\Helper::convert_to_regexp( $pattern );
		return \Cleantalk\Common\Helper::is_regexp( $pattern )
			? self::clean__pattern( $file_path, $pattern )
			: Err::add( __CLASS__, __FUNCTION__, 'Pattern wrong', $pattern );
	}
	
	/**
	 * Removes variable from file
	 *
	 * @param string $file_path
	 * @param string $variable Variable name (without "$")
	 *
	 * @return bool|Err
	 */
	public static function clean__variable( $file_path, $variable ){
		$pattern = '\s*$' . $variable . '\s?=[\S\s]*?;';
		$pattern = \Cleantalk\Common\Helper::convert_to_regexp( $pattern );
		return \Cleantalk\Common\Helper::is_regexp( $pattern )
			? self::clean__pattern( $file_path, $pattern )
			: Err::add( __CLASS__, __FUNCTION__, 'Pattern wrong', $pattern );
	}

    /**
     * Clear all file content
     *
     * @param $file_path
     *
     *
     * @return false|int
     * @since version
     */
    public static function clean__all( $file_path )
    {
        return file_put_contents($file_path, '<?php');
    }

	/**
	 * Delete given pattern from file.
	Works only for first collision.
	 *
	 * @param string $file_path
	 * @param string $pattern RegExp
	 *
	 * @return bool| /Cleantalk/Err
	 */
	public static function clean__pattern( $file_path, $pattern ){
		
		if( is_file( $file_path ) || is_writable( $file_path ) ){
			
			$file_content = file_get_contents( $file_path );
			
			if( $file_content ){
				
				// Cleaning up
				$new_content = preg_replace( '/' . $pattern . '/', '', $file_content, 1 );
				$result = $new_content !== null ? true : false;
				
				if($result){
					if( file_put_contents( $file_path, $new_content, LOCK_EX ) ){
						return true;
					}else
						return Err::add(__CLASS__, __FUNCTION__, 'Write error'); // Cannot write new content to template PHP file
				}else
					return Err::add(__CLASS__, __FUNCTION__, 'Replacement fail'); // Can't read from template PHP file
			}else
				return Err::add(__CLASS__, __FUNCTION__, 'Read fail'); // Can't read from template PHP file
		}else
			return Err::add(__CLASS__, __FUNCTION__, 'No file'); // No template PHP file
	}
	
	public static function replace__variable( $file_path, $variable, $value ){
		$injection = "\n\t\$$variable = " . var_export( $value, true ) . ";";
		$needle = '\s*\$' . $variable . '\s?=[\S\s]*?;';
		static::replace__code( $file_path, $injection, $needle );
	}

	public static function get__variable($file_path, $variable)
	{
		if (!is_file($file_path)) {
			return Err::add(__CLASS__, __FUNCTION__, 'File not found', $file_path); // No PHP file
		}

		if (!is_writable($file_path)) {
			return Err::add(__CLASS__, __FUNCTION__, 'No right to write in file'); // No PHP file
		}

		$file_content = file_get_contents($file_path);
		$value_start = strpos($file_content, '$' . $variable . ' = ');
		$value_end = strpos($file_content, ';', $value_start);
		$value = substr($file_content, $value_start + strlen($variable) + 4, $value_end - $value_start - strlen($variable) - 4);
		$value = trim($value, " \t\n\r\0\x0B'\"\'");

		return $value;
	}
	
	public static function replace__code( $file_path, $injection, $needle ){
		
		if( is_file( $file_path ) ){
			
			if( is_writable( $file_path ) ){
				
				$file_content = file_get_contents( $file_path );
				
				if( $file_content ){
					
					$new_content = preg_replace("/$needle/", $injection, $file_content, 1);
					$result = $new_content !== null ? true : false;
					
					if($result){
						if( $bytes = file_put_contents( $file_path, $new_content, LOCK_EX ) ){
							return $bytes;
						}else
							return Err::add(__CLASS__, __FUNCTION__, 'Write error'); // Cannot write new content to template PHP file
					}else
						return Err::add(__CLASS__, __FUNCTION__, 'Replacement fail'); // Can't read from template PHP file
				}else
					return Err::add(__CLASS__, __FUNCTION__, 'Read fail'); // Can't read from template PHP file
			}else
				return Err::add(__CLASS__, __FUNCTION__, 'No right to write in file'); // No PHP file
		}else
			return Err::add(__CLASS__, __FUNCTION__, 'File not found', $file_path); // No PHP file
	}
	
	public static function inject__tag__start( $file_path, $tag ){
		self::inject__code( $file_path, self::tag__php__start( $tag ) );
	}
	
	public static function inject__tag__end( $file_path, $tag ){
		self::inject__code( $file_path, self::tag__php__end( $tag ) );
	}
	
	public static function inject__variable( $file_path, $variable, $value, $compact = false ){
		$value = var_export( $value, true );
		$value = $compact ? preg_replace( '/\s*/', '', $value ) : $value;
		self::inject__code( $file_path, "\$$variable = $value;" );
	}
	
	public static function inject__code( $file_path, $injection, $needle = '<\?php', $tag = null ){
		
		if( is_file( $file_path ) ){
			
			if( is_writable( $file_path ) ){
				
				$file_content = file_get_contents( $file_path );
				
				if( $file_content ){
					
					$replacement = $tag
						? self::tag__php__start( $tag ) . PHP_EOL . $injection . PHP_EOL . self::tag__php__end( $tag )
						: $injection;
					
					switch ($needle){
						case 'start':
							$new_content = $replacement . $file_content;
							break;
						case 'end':
							$new_content = $file_content . $replacement;
							break;
						default:
							$new_content = preg_replace("/$needle/", "$0" . PHP_EOL . $replacement, $file_content, 1);
					}
					
					$result = $new_content !== null && $new_content != $file_content ? true : false;
					if($result){
						if( file_put_contents( $file_path, $new_content, LOCK_EX ) ){
							return true;
						}else
							return Err::add(__CLASS__, __FUNCTION__, 'Write error'); // Cannot write new content to template PHP file
					}else
						return Err::add(__CLASS__, __FUNCTION__, 'Replacement fail'); // Can't read from template PHP file
				}else
					return Err::add(__CLASS__, __FUNCTION__, 'Read fail'); // Can't read from template PHP file
			}else
				return Err::add(__CLASS__, __FUNCTION__, 'No right to write in file'); // No PHP file
		}else
			return Err::add(__CLASS__, __FUNCTION__, 'File not found', $file_path); // No PHP file
	}
	
	public static function tag__php__start( $tag ){
		return "\n//Cleantalk/$tag/start";
	}
	
	public static function tag__php__end( $tag ){
		return "\n//Cleantalk/$tag/end";
	}

    /**
     * Recursive
     * Copying folder or file
     *
     * @param string $from Path of the folder to copy
     * @param string $to Path to copy in
     * @param array $exceptions
     *
     * @return bool
     */
    public static function copy( $from, $to, $exceptions = array() ){

        $out = true;

        if( is_dir( $from ) ){

            if( ! is_dir( $to ) && ! mkdir( $to ) ){
                error_log( var_export( $to, true ) );
                return false;
            }
            $dd = dir( $from );
            while( ( $entry = $dd->read() ) !== false ){
                if( ! in_array( $entry, array_merge( $exceptions, array( '.', '..' ) ) ) )
                    if( ! self::copy( $from . DS . $entry, $to . DS . $entry, $exceptions ) ){
                        $out = false;
                        break;
                    }
            }
            $dd->close();

        }else
            $out = copy( $from, $to );

        return $out;
    }

    /**
     * Recursive
     * Deleting folder or file
     *
     * @param string $path Path of the folder to delete
     * @param array $exceptions
     *
     * @return bool
     */
    public static function delete( $path, $exceptions = array() ){

        $out = true;

        // Not exists
        if( ! file_exists( $path ) )
            return $out;

        // Directory
        elseif( is_dir( $path ) ){
            $dd = dir( $path );
            while( ( $entry = $dd->read() ) !== false ){
                if( ! in_array( $entry, array_merge( $exceptions, array( '.', '..' ) ) ) ){
                    if( ! self::delete( $path . DS . $entry ) ){
                        $out = false;
                        break;
                    }
                }
            }
            $dd->close();

            if( self::isFolderEmpty( $path ) )
                $out = rmdir( $path );

            // File
        }else
            $out = unlink( $path );

        return $out;
    }

    /**
     * Checks if the folder is empty or not
     *
     * @param $path
     *
     * @return bool
     */
    public static function isFolderEmpty( $path ){
        return ! count( glob( $path . '/*' ) );
    }
}