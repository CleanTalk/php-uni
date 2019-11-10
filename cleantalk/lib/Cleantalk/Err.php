<?php

namespace Cleantalk;

/**
 * Class Err
 * Uses singleton template.
 * Errors handling
 *
 * @package Cleantalk
 */
class Err{
	
	static $instance;
	public $errors = [];
	
	public function __construct(){}
	public function __wakeup(){}
	public function __clone(){}
	
	/**
	 * Constructor
	 */
	public static function get(){
		if (!isset(static::$instance)) {
			static::$instance = new static;
			static::$instance->init();
		}
		return static::$instance;
	}
	
	/**
	 * Alternative constructor
	 */
	private function init(){
	
	}
	
	/**
	 * Adds new error
	 *
	 */
	public static function add(){
		self::get()->errors[] = implode(': ', func_get_args());
		return self::$instance;
	}
	
	public function prepend( $string ){
		$this->errors[count($this->errors) - 1 ] = $string . ': ' . end( self::get()->errors );
	}
	
	public function append( $string ){
		$this->string = $string . ': ' . $this->string;
	}
	
	public function get_last( $output_style = 'string' ){
		$out = $out = end( self::$instance->errors );
		if($output_style == 'as_json')
			$out = json_encode( array('error' => end( self::$instance->errors ) ), true );
		return $out;
	}
	
	public function get_all( $output_style = 'string' ){
		$out = self::$instance->errors;
		if($output_style == 'as_json')
			$out = json_encode( self::$instance->errors, true );
		return $out;
	}
	
	public function has_errors(){
		return (bool)self::$instance->errors;
	}
}