<?php

use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Post;
use Cleantalk\ApbctUni\Cron;

require_once 'common.php';

function install( $files, $api_key, $cms, $exclusions ){
    if( $files ){
        $tmp = array();
        foreach ( $files as $file_to_mod ){

            // Check for absolute paths
            if(
                preg_match( '/^[\/\\\\].*/', $file_to_mod) || // Root for *nix systems
                preg_match( '/^[A-Za-z]:\/.*/', $file_to_mod)     // Root for windows systems
            ){
                Err::add( 'File paths should be relative' );
                break;
            }

            // Check for .. upper directory access
            if(
                preg_match( '/^\.\.[\/\\\\].*/', $file_to_mod) // Access to upper levels
            ){
                Err::add( 'Script for modification should be in the current folder or lower. You can not access upper leveled folders.' );
                break;
            }

            $file = CLEANTALK_SITE_ROOT . trim( $file_to_mod );
            if( file_exists($file) )
                $tmp[] = $file;
        }
        $files = $tmp;
    }

	foreach ($files as $file){

		$file_content = file_get_contents( $file );
		$php_open_tags  = preg_match_all("/(<\?)/", $file_content);
		$php_close_tags = preg_match_all("/(\?>)/", $file_content);
		$first_php_start = strpos($file_content, '<?');
        $contains_namespace_declaration = strpos($file_content, 'namespace');
        $contains_declare_declaration = strpos($file_content, 'declare');

		// Adding <?php to the start if it's not there
		if($first_php_start !== 0)
			File::inject__code($file, "<?php\n?>\n", 'start');

		if( ! Err::check() ){

			// Adding ? > to the end if it's not there
			if($php_open_tags <= $php_close_tags)
				File::inject__code($file, "\n<?php\n", 'end');

			if( ! Err::check() ){

                if( $contains_namespace_declaration !== false ) {
                    $needle = 'namespace\s?[a-zA-Z_\\x80-\\xff\\x5c][a-zA-Z0-9\\x80-\\xff\\x5c]*\s*;';
                } elseif ( $contains_declare_declaration !== false ) {
                    $needle = 'declare\s*\({1}.*\){1};';
                } else {
                    $needle = '(<\?php)|(<\?)';
                }

                // Addition to the top of the script
                File::inject__code(
                    $file,
                    "\trequire_once( '" . CLEANTALK_SITE_ROOT . "cleantalk/cleantalk.php');",
                    $needle,
                    'top_code'
                );

				if( ! Err::check() ){

					// Addition to index.php Bottom (JavaScript test)
					File::inject__code(
						$file,
						"\tif(ob_get_contents()){\n"
                        . "\t\tif (defined('__OSC_LOADED__') && function_exists('apbct_osc_restart_csrf_service')) {\n"
                        . "\t\tapbct_osc_restart_csrf_service();\n"
                        . "\t\t} else {\n"
                        . "\t\t\tob_end_flush();\n"
                        . "\t\t}\n"
                        . "\t}\n"
						. "\tif(isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){\n"
						. "\t\tdie();\n"
						. "\t}",
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
    File::inject__variable( $path_to_config, 'modified_files', $modified_files, false, true );
    if( $exclusions )
        File::inject__variable( $path_to_config, 'exclusions', $exclusions, false, true );
    File::inject__variable( $path_to_config, 'apikey', $api_key );
    File::inject__variable( $path_to_config, 'exclusion_key', md5($api_key) );
    File::inject__variable( $path_to_config, 'detected_cms', $cms );
    File::inject__variable( $path_to_config, 'is_installed', true );
}

function install_cron(){
    /** @var \Cleantalk\Custom\Cron\Cron $cron_class */
    $cron_class = \Cleantalk\Common\Mloader\Mloader::get('Cron');
    $cron = new $cron_class();
    $cron->checkCronData();
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
    File::clean__variable( $path_to_config, 'exclusion_key' );
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
    File::replace__variable( $path_to_config, 'service_field_in_post_exclusion_enabled', false );

	// Deleting cron tasks
	File::replace__variable( CLEANTALK_CRON_FILE, 'tasks', array() );
    File::replace__variable( CLEANTALK_CRON_FILE, 'cleantalk_cron', array(), true );
    File::replace__variable( CLEANTALK_CRON_FILE, 'cleantalk_cron_last_start', 0 );
    File::replace__variable( CLEANTALK_CRON_FILE, 'cleantalk_cron_pid', 0 );

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
        //moodle moodle
        if ( preg_match('/(moodle.*?)/', $index_file) ) {
            $out = 'moodle';
        }
		// OpenMage
        if ( preg_match('/(OpenMage.*?)/', $index_file) ) {
            $out = 'OpenMage';
        }
        // vBulletin
        if ( preg_match('/(vBulletin.*?)/', $index_file) ) {
            $out = 'vBulletin';
        }
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

/**
 * @return string
 */
function apbct__prepare_form_sign_exclusions_textarea()
{
    global $form_post_signs_exclusions_set;

    if (!is_array($form_post_signs_exclusions_set)) {
        $form_post_signs_exclusions_set = array();
    }

    $hint_text = 'Regular expression. If the form contains any of these signs in POST array keys or in value of "action" key, the whole form submission is excluded from spam checking.';
    $link_learn_more = 'https://cleantalk.org/help/exclusion-by-form-signs?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=uni_hint_exclusions__form_signs&utm_campaign=uni_links';

    $template = '
        <p>%s</br><span style="%s"><a href="%s" target="_blank">Learn more</a></span></p>
        <textarea id="form_signs_exclusions-textarea" name="form_signs_exclusions-textarea" style="%s">%s</textarea>
    ';
    $signs = '';
    foreach ( $form_post_signs_exclusions_set as $sign) {
        if (is_string($sign)) {
            $signs .= $sign . "\r\n";
        }
    }

    $style_textarea = 'word-break: break-all; padding: 1%; background: #fff; width: 100%';
    $style_span = 'display: flex; justify-content: flex-end; margin: 1%';

    return sprintf($template, $hint_text, $style_span, $link_learn_more, $style_textarea, htmlspecialchars($signs));
}

function apbct__prepare_service_field_exclusion_layout()
{
    global $exclusion_key;

    if (!empty($exclusion_key)) {
        $service_field = htmlspecialchars('<input id="any_id_1" name="ct_service_data" type="hidden" value="'. $exclusion_key .'">');
    } else {
        $service_field = 'Error! Can not gain exclusion key.';
    }

    $hint_text = 'Regular expression. If the form contains any of these signs in POST array keys or in value of "action" key, the whole form submission is excluded from spam checking.';
    $style = 'border: solid 1px; word-break: break-all; padding: 1%; background: #fff;';

    $template = '
        <p>%s</p>
        <div id="exclusion-html" style="%s">
        %s
        </div>
    ';

    return $exclusion_key ? sprintf($template, $hint_text, $style, $service_field) : $service_field;
}
/**
 * Sanitize and validate exclusions.
 * Explode given string by commas and trim each string.
 * Cut first 20 entities if more than 20 given. Remove duplicates.
 * Skip element if it's empty. Validate entity as URL. Cut first 128 chars if more than 128 given
 *
 * Return false if exclusion is bad
 * Return sanitized string if all is ok
 *
 * @param string $exclusions
 * @param bool $regexp
 *
 * @return bool|string|array
 */
function apbct_settings__sanitize__exclusions($exclusions, $return_array = false, $regexp = true, $urls = false)
{
    if ( ! is_string($exclusions) ) {
        return false;
    }

    $result = array();
    $type   = 0;

    if ( ! empty($exclusions) ) {
        if ( strpos($exclusions, "\r\n") !== false ) {
            $exclusions = explode("\r\n", $exclusions);
            $type       = 2;
        } elseif ( strpos($exclusions, "\n") !== false ) {
            $exclusions = explode("\n", $exclusions);
            $type       = 1;
        } else {
            $exclusions = explode(',', $exclusions);
        }
        //Drop duplicates first (before cut)
        $exclusions = array_unique($exclusions);
        //Take first 20 exclusions entities
        $exclusions = array_slice($exclusions, 0, 20);
        //Sanitizing
        foreach ($exclusions as $exclusion) {
            //Cut exclusion if more than 128 symbols gained
            $sanitized_exclusion = substr($exclusion, 0, 128);
            $sanitized_exclusion = trim($sanitized_exclusion);

            if ( ! empty($sanitized_exclusion) ) {
                if ( $regexp ) {
                    if ( @preg_match('/' . $exclusion . '/', '') === false) {
                        return false;
                    }
                } elseif ( $urls ) {
                    if (
                        ( strpos($exclusion, 'http://') !== false || strpos($exclusion, 'https://') !== false ) &&
                        filter_var($exclusion, FILTER_VALIDATE_URL)
                    ) {
                        return false;
                    }
                }
                $result[] = $sanitized_exclusion;
            }
        }
    }
    if ($return_array) {
        return $result;
    }
    switch ( $type ) {
        case 0:
        default:
            return implode(',', $result);
        case 1:
            return implode("\n", $result);
        case 2:
            return implode("\r\n", $result);
    }
}
