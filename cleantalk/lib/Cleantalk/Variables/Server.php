<?php

namespace Cleantalk\Variables;

/**
 * Class Server
 * Wrapper to safely get $_SERVER variables
 *
 * @usage \Cleantalk\Variables\Server::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Server extends SuperGlobalVariables{
	
	static $instance;
	
	/**
	 * Gets given $_SERVER variable and save it to memory
	 *
	 * @param string $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		
		// Return from memory. From $this->server
		if(isset(static::$instance->variable[$name]))
			return static::$instance->variable[$name];
		
		$name = strtoupper( $name );
		
		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_SERVER, $name );
		
		if( empty( $value ) )
			$value = isset( $_SERVER[ $name ] ) ? $_SERVER[ $name ]	: '';
		
		// Convert to upper case for REQUEST_METHOD
		if( in_array( $name, array( 'REQUEST_METHOD' ) ) )
			$value = strtoupper( $value );
		
		// Convert HTML chars for HTTP_USER_AGENT, HTTP_USER_AGENT, SERVER_NAME
		if( in_array( $name, array( 'HTTP_USER_AGENT', 'HTTP_USER_AGENT', 'SERVER_NAME' ) ) )
			$value = htmlspecialchars( $value );
		
		// Remember for thurther calls
		static::getInstance()->remember_variable( $name, $value );
		
		return $value;
	}
	
	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $string needle
	 *
	 * @return bool|int
	 */
	public static function in_uri( $string ){
		return self::has_string( 'REQUEST_URI', $string );
	}
	
	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $string needle
	 *
	 * @return bool|int
	 */
	public static function in_referer( $string ){
		return self::has_string( 'HTTP_REFERER', $string );
	}
	
	/**
	 * Checks if $_SERVER['REQUEST_URI'] contains string
	 *
	 * @param string $string needle
	 *
	 * @return bool|int
	 */
	public static function is_post(){
		return self::get( 'REQUEST_METHOD' ) === 'POST';
	}

	/**
	 * Returns domain name
	 *
	 * @return string|int
	 */
	public static function get_domain()
	{
		preg_match('@\.(\S+)\/?$@', self::get('HTTP_HOST'), $matches);
		return isset($matches[1]) ? $matches[1] : false;
	}
}