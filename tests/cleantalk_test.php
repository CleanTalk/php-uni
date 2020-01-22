<?php
require_once 'cleantalk/lib/Cleantalk.php';

class cleantalk_test extends \PHPUnit\Framework\TestCase {

    public function test_httpPing()
    {
    	$ct = new Cleantalk();
		$this->assertInternalType("int",$ct->httpPing("https://cleantalk.org/"));
		$this->assertGreaterThan(0, $ct->httpPing("https://cleantalk.org/"));	
 	}
    public function test_is_JSON()
    {
    	$ct = new Cleantalk();
		$isJson = '{
		"name":"John",
		"age":30,
		"cars":[ "Ford", "BMW", "Fiat" ]
		}';
		$notJson = "simple_str";
		$this->assertTrue($ct->is_JSON($isJson));
		$this->assertFalse($ct->is_JSON($notJson));	
 	} 	
}