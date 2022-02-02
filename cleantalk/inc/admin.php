<?php

use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Post;
use Cleantalk\ApbctUni\Cron;

require_once 'common.php';

function install( $files, $api_key, $cms, $exclusions ){

	foreach ($files as $file){

		$file_content = file_get_contents( $file );
		$php_open_tags  = preg_match_all("/(<\?)/", $file_content);
		$php_close_tags = preg_match_all("/(\?>)/", $file_content);
		$first_php_start = strpos($file_content, '<?');

		// Adding <?php to the start if it's not there
		if($first_php_start !== 0)
			File::inject__code($file, "<?php\n?>\n", 'start');

		if( ! Err::check() ){

			// Adding ? > to the end if it's not there
			if($php_open_tags <= $php_close_tags)
				File::inject__code($file, "\n<?php\n", 'end');

			if( ! Err::check() ){

				// Addition to the top of the script
				File::inject__code(
					$file,
					"\trequire_once( '" . CLEANTALK_SITE_ROOT . "cleantalk/cleantalk.php');",
					'(<\?php)|(<\?)',
					'top_code'
				);

				if( ! Err::check() ){

					// Addition to index.php Bottom (JavaScript test)
					File::inject__code(
						$file,
						"\t\nif(ob_get_contents()){\nob_end_flush();\n}\n"
						."\tif(isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){\n"
						."\t\tdie();\n"
						."\t}",
						'end',
						'bottom_code'
					);

				}
			}
		}
	}

	// Clean config
	if( ! Err::check() )
		uninstall();

	// Install settings in cofig if everything is ok
	if( ! Err::check() )
		install_config( $files, $api_key, $cms, $exclusions );

	// Set cron tasks
	if( ! Err::check() )
		install_cron();
}

function install_config( $modified_files, $api_key, $cms, $exclusions ){

    $path_to_config = CLEANTALK_ROOT . 'config.php';
    $apbct_salt = str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0');
    // Attention. Backwards order because inserting it step by step

    $pass = 'NO PASS';
    $uni_email = '';

    if( Post::get( 'admin_password' ) ) {
        $pass = trim( Post::get( 'admin_password' ) );
        File::inject__variable( $path_to_config, 'password', hash( 'sha256', trim( Post::get( 'admin_password' ) ) ) );
    }

    if( Post::get( 'email' ) ) {
        $uni_email = trim( Post::get( 'email' ) );
        File::inject__variable( $path_to_config, 'uni_email', trim( Post::get( 'email' ) ) );
    }

    if( Post::get( 'user_token' ) )
        File::inject__variable( $path_to_config, 'user_token', trim( Post::get( 'user_token' ) ) );
    if( Post::get( 'account_name_ob' ) )
        File::inject__variable( $path_to_config, 'account_name_ob', trim( Post::get( 'account_name_ob' ) ) );

    if($uni_email) {
        $host = $_SERVER['HTTP_HOST'] ?: 'Your Site';
        $to = $uni_email;
        $login = $uni_email;
        $subject = 'Universal Anti-Spam Plugin settings for ' . $host;
        $message = "Hi,<br><br>
                Your credentials to get access to settings of Universal Anti-Spam Plugin by CleanTalk are bellow,<br><br>
                Login: $login<br>
                Access key: $api_key <br>
                Password: $pass <br>
                Settings URL: https://$host/cleantalk/settings.php <br>
                Dashboard: https://cleantalk.org/my/?cp_mode=antispam <br><br>
                --<br>
                With regards,<br>
                CleanTalk team.";

        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        // Sending email
        mail(
            $to,
            $subject,
            $message,
            $headers
        );
    }

    File::inject__variable( $path_to_config, 'salt', $apbct_salt );
    File::inject__variable( $path_to_config, 'security', hash( 'sha256', '0(o_O)0' . $apbct_salt ) );
    File::inject__variable( $path_to_config, 'modified_files', $modified_files, true );
    if( $exclusions )
        File::inject__variable( $path_to_config, 'exclusions', $exclusions, true );
    File::inject__variable( $path_to_config, 'apikey', $api_key );
    File::inject__variable( $path_to_config, 'detected_cms', $cms );
    File::inject__variable( $path_to_config, 'is_installed', true );
}

function install_cron(){
	Cron::addTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 );
	Cron::addTask( 'sfw_send_logs', 'apbct_sfw_send_logs', 3600 );
    Cron::addTask( 'plugin_get_latest_version', 'apbct__plugin_get_latest_version', 86400 );
}

function uninstall( $files = array() ){

	global $modified_files;

	// Clean files from config.php
	$files = empty($files) && isset($modified_files)
		? $modified_files
		: $files;

	$path_to_config = CLEANTALK_ROOT . 'config.php';
	File::clean__variable( $path_to_config, 'security' );
	File::clean__variable( $path_to_config, 'password' );
	File::clean__variable( $path_to_config, 'salt' );
	File::clean__variable( $path_to_config, 'apikey' );
	File::clean__variable( $path_to_config, 'uni_email' );
	File::clean__variable( $path_to_config, 'user_token' );
	File::clean__variable( $path_to_config, 'account_name_ob' );
	File::clean__variable( $path_to_config, 'detected_cms' );
	File::clean__variable( $path_to_config, 'admin_password' );
	File::clean__variable( $path_to_config, 'modified_files' );
	File::clean__variable( $path_to_config, 'exclusions' );
	File::clean__variable( $path_to_config, 'is_installed' );

	// Restore deafult settings
	File::replace__variable( $path_to_config, 'sfw_last_update', 0 );
	File::replace__variable( $path_to_config, 'sfw_last_logs_send', 0 );
	File::replace__variable( $path_to_config, 'sfw_entries', 0 );
    File::replace__variable( $path_to_config, 'antispam_activity_status', true );
	File::replace__variable( $path_to_config, 'registrations_test', true );
	File::replace__variable( $path_to_config, 'general_postdata_test', false );
	File::replace__variable( $path_to_config, 'spam_firewall', true );

	// Deleting cron tasks
	File::replace__variable( CLEANTALK_CRON_FILE, 'tasks', array() );

	// Deleting SFW nets
	File::clean__variable( CLEANTALK_ROOT . 'data' . DS . 'sfw_nets.php', 'sfw_nets' );

	if(isset($files)){
		foreach ( $files as $file ){
			File::clean__tag( $file, 'top_code' );
			File::clean__tag( $file, 'bottom_code' );
		}
	}

	return ! Err::check();
}

function detect_cms( $path_to_index, $out = 'Unknown' ){

	if( is_file($path_to_index) ){

		// Detecting CMS
		$index_file = file_get_contents( $path_to_index );

		//X-Cart 4
		if (preg_match('/(xcart_4_.*?)/', $index_file))
			$out = 'X-Cart 4';
		//osTicket
		if (preg_match('/osticket/i', $index_file))
			$out = 'osTicket';
		// PrestaShop
		if (preg_match('/(PrestaShop.*?)/', $index_file))
			$out = 'PrestaShop';
		// Question2Answer
		if (preg_match('/(Question2Answer.*?)/', $index_file))
			$out = 'Question2Answer';
		// FormTools
		if (preg_match('/(use\sFormTools.*?)/', $index_file))
			$out = 'FormTools';
		// SimplaCMS
		if (preg_match('/(Simpla CMS.*?)/', $index_file))
			$out = 'Simpla CMS';
        // phpBB
        if (preg_match('/(phpBB.*?)/', $index_file))
            $out = 'phpBB';
        if ( strpos( $index_file, '/wa-config/' ) && strpos( $index_file, 'waSystem::getInstance' ) )
            $out = 'ShopScript';
        if (preg_match('/(DATALIFEENGINE.*?)/', $index_file))
            $out = 'DLE';
        // CsCart
        if (preg_match('/(Kalynyak.*?)/', $index_file))
            $out = 'cscart';
    }

	return $out;
}

/**
 * Checking for a new version of the plugin and and showing the corresponding message
 */
function apbct__plugin_update_message() {
    global $latest_version;

    if (!$latest_version) {
        $latest_version = APBCT_VERSION;
    }

    if( version_compare( APBCT_VERSION, $latest_version ) === -1 ){
        echo '<p class="text-center">There is a newer version. Update to the latest ' . $latest_version . '</p>';
        echo '<p class="text-center"><button id="btn-update" form="none" class="btn btn-setup" value="">Update</button><img class="ajax-preloader" src="img/preloader.gif"></p>';
    }elseif( version_compare( APBCT_VERSION, $latest_version ) === 1 ){
        echo '<p class="text-center">You are using a higher version than the latest version '. APBCT_VERSION . '</p>';
    }else{
        echo '<p class="text-center">You are using the latest version '. APBCT_VERSION . '</p>';
    }
}

/**
 * Print Block with CSCart Js Snippet
 */
function apbct__cscart_js_snippet() {
    global $apikey, $apbct_salt, $detected_cms;
    
    // Only for CsCart
    if ($detected_cms != 'cscart') return;
    
    $apbct_checkjs_hash = apbct_checkjs_hash($apikey, $apbct_salt);
    ?>
    
    <div class="highlight">
        <h4>Add this code to all pages of the site (use the basic template). Detailed instruction is <a href="https://blog.cleantalk.org/protecting-cs-cart-website-from-spam/">here</a></h4>
        <pre tabindex="0" class="chroma">
            <code class="language-html" data-lang="html">
                &lt;script&gt;var apbct_checkjs_val="<?= $apbct_checkjs_hash; ?>";&lt;/script&gt;
                &lt;script src="/cleantalk/js/ct_js_test.js"&gt;&lt;/script&gt;
                &lt;script src="/cleantalk/js/ct_js_test.js"&gt;&lt;/script&gt;
            </code>
        </pre>
    </div>

    <?php
}
