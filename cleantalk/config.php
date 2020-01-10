<?php

//Settings
	$registrations_test = true;
	$general_postdata_test = false;
	$spam_firewall = true;

// Statistics
	$sfw_last_update = 0;
	$sfw_entries = 0;
	$sfw_last_logs_send = 0;

// Response language
$response_lang = 'en';

define('APBCT_PLUGIN', 'uni');
define('APBCT_VERSION', '2.1');
define('APBCT_AGENT', APBCT_PLUGIN . '-' . str_replace( '.', '', APBCT_VERSION ) );
define('APBCT_USER_AGENT', 'Cleantalk-Antispam-Universal-Plugin/' . APBCT_VERSION);