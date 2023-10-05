<?php

namespace Cleantalk\ApbctUni;

use Cleantalk\ApbctUni\SFW;
use Cleantalk\Variables\Get;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;

class RemoteCalls
{
	const COOLDOWN = 10;

	public static function check()
	{
		return Get::is_set('apbct_remote_call_token', 'apbct_remote_call_action', 'plugin_name')
			&&
			in_array(Get::get('plugin_name'), array('uni', 'apbct'));
	}

	public static function perform()
	{
		$path_to_config = CLEANTALK_ROOT . 'config.php';
		$apikey = File::get__variable($path_to_config, 'apikey');

		$ct = State::getInstance();

		$action = strtolower(Get::get('apbct_remote_call_action'));
		$token  = strtolower(Get::get('apbct_remote_call_token'));
		if (!isset($ct->remote_calls->$action)) {
			Err::add('UNKNOWN_ACTION');
			die(Err::check_and_output('as_json'));
		}

		$cooldown = isset($ct->remote_calls->$action->cooldown)
			? $ct->remote_calls->$action->cooldown
			: self::COOLDOWN;
		$pass_cooldown = Helper::ip__get(array('real')) === $_SERVER['SERVER_ADDR'];
		if (!(time() - $ct->remote_calls->$action->last_call >= $cooldown || $pass_cooldown)) {
			Err::add('TOO_MANY_ATTEMPTS');
			die(Err::check_and_output('as_json'));
		}

		$ct->remote_calls->$action->last_call = time();
		$ct->remote_calls->save();

		if ($token != strtolower(md5($apikey))) {
			Err::add('WRONG_TOKEN');
			die(Err::check_and_output('as_json'));
		}

		$action = 'action__'.$action;
		if (!method_exists('\Cleantalk\ApbctUni\RemoteCalls', $action)) {
			Err::add('UNKNOWN_ACTION_METHOD');
			die(Err::check_and_output('as_json'));
		}

		sleep( (int) Get::get('delay') ); // Delay before perform action;
		$out = RemoteCalls::$action();

		die(Err::check() ? Err::check_and_output( 'as_json' ) : json_encode($out));
	}

	public static function action__update_ct_firewall()
	{
		$path_to_config = CLEANTALK_ROOT . 'config.php';
		$apikey = File::get__variable($path_to_config, 'apikey');

		$sfw = new SFW();
		$result = $sfw->sfw_update($apikey);
		
		die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
	}
}