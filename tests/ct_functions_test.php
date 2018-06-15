<?php
require_once 'cleantalk/lib/ct_functions.php';

class ct_functions_test extends \PHPUnit\Framework\TestCase {

    public function test_apbct_get_fields_any()
    {
        $test_post_data = array();
        $test_post_data['nickname_field'] = 'Clean';
        $test_post_data['email_field'] = 's@cleantalk.org';
        $test_post_data['message_field'] = 'testmsg';
		$this->assertArraySubset(['nickname' => ['Clean']],apbct_get_fields_any($test_post_data));
		$this->assertArraySubset(['email' => ['s@cleantalk.org']],apbct_get_fields_any($test_post_data));
		$this->assertArraySubset(['message_field' => ['testmsg']],apbct_get_fields_any($test_post_data));
 	}
}