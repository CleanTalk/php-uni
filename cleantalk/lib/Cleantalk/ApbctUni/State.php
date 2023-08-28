<?php

namespace Cleantalk\ApbctUni;

class State extends \Cleantalk\ApbctUni\Storage
{
	use \Cleantalk\Templates\FluidInterface;
	use \Cleantalk\Templates\Singleton;

	public static $instance;

	public $default_settings = array();

	public $default_data = array();

	public $default_remote_calls = array(
		// Common
		'update_ct_firewall' => array('last_call' => 0, 'cooldown' => 0),
	);

	private $default_fw_stats = array(
		'entries'        => 0,
		'updating'       => false,
		'update_percent' => 0,
		'logs_sent_time' => 0,
		'last_update'    => 0,
	);
	
	private $default_plugin_meta = array();
	
	public function __construct( ...$options )
	{
		// Default options to get
		$options = $options ? $options : array('settings', 'data', 'remote_calls');

		if( self::$instance )
			return self::$instance;

		foreach($options as $option_name){

			$option = $this->get( $option_name );

			// @todo Check default option
			$def_option_name = 'default_' . $option_name;
			$option = is_array( $option )
				? array_merge( $this->$def_option_name, $option )
				: $this->$def_option_name;
			
			// Generating salt
			if($option_name === 'data'){

				// Generate during construction if unset
				if(empty($option['salt']))
					$option['salt'] = str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0');
				if(empty($option['security_key']))
					$option['security_key'] = md5( isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '' );

			}

			$this->$option_name = $this->convertToStorage( $option_name, $option );
		}

		self::$instance = $this;

	}

	/**
	 * Magic. Handles unexisting properties.
	 * Returns certain options. From the top level of State.
	 * for ->key returns settings->key
	 *      for ->* returns data->* if it's set
	 *          for every other occurrences pass call to Storage->__get()
	 *
	 * @param $name
	 *
	 * @return mixed|null
	 */
	public function __get( $name ) {
		return $name === 'key'
			? $this->settings->key
			: ( isset( $this->data->$name )
				? $this->data->$name
				: parent::__get( $name )
			);
	}
}
