<?php

namespace Cleantalk\Variables;

/**
 * Class Cookie
 * Safety handler for $_COOKIE
 *
 * @usage \Cleantalk\Variables\Cookie::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Cookie extends SuperGlobalVariables{
	
	static $instance;

    /**
     * Getting visible fields collection
     * @return array
     *
     * @since version
     */
    public static function getVisibleFields()
    {
        // Visible fields processing
        // Get from separated native cookies and convert it to collection
        $visible_fields_cookies_array = array_filter($_COOKIE, static function ($key) {
            return strpos($key, 'apbct_visible_fields_') !== false;
        }, ARRAY_FILTER_USE_KEY);
        $visible_fields_collection = array();
        foreach ( $visible_fields_cookies_array as $visible_fields_key => $visible_fields_value ) {
            $prepared_key = str_replace('apbct_visible_fields_', '', $visible_fields_key);
            $prepared_value = json_decode(str_replace('\\', '', $visible_fields_value), true);
            $visible_fields_collection[$prepared_key] = $prepared_value;
        }
        return $visible_fields_collection;
    }

    /**
	 * Gets given $_COOKIE variable and save it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		
		// Return from memory. From $this->variables
		if(isset(static::$instance->variables[$name]))
			return static::$instance->variables[$name];
		
		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_COOKIE, $name );
		
		if( empty( $value ) )
			$value = isset( $_COOKIE[ $name ] ) ? $_COOKIE[ $name ]	: '';
			
		return $value;
	}
}