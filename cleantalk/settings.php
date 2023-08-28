<?php

require_once 'check_requirements.php';

use Cleantalk\Common\API;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;
use Cleantalk\ApbctUni\SFW;

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';
require_once 'inc' . DIRECTORY_SEPARATOR . 'admin.php';

if (!defined('CLEANTALK_URI')) {
    define('CLEANTALK_URI', preg_replace('/^(.*\/)(.*?.php)?/', '$1', Server::get('REQUEST_URI')));
}
$cookie_domain = Server::get('HTTP_HOST');
define( 'COOKIE_DOMAIN', $cookie_domain );

if( Server::is_post() && Post::get( 'action' ) ){

    // Brute force protection
    sleep(2);

    switch (Post::get('action')){

        case 'login':

            // If password is set in config
            if(isset($password)){
                if( ( Post::get( 'login' ) == $apikey || ( isset( $uni_email ) && Post::get( 'login' ) == $uni_email ) ) && hash( 'sha256', trim( Post::get( 'password' ) ) ) === $password ){
                    setcookie('authenticated', $security, time() + 86400 * 30, '/', COOKIE_DOMAIN, false, true);
                }else
                    Err::add('Incorrect login or password');

                // No password is set. Check only login.
            }elseif( ( Post::get( 'login' ) == $apikey ) ){
                setcookie('authenticated', $security, time() + 86400 * 30, '/', COOKIE_DOMAIN, false, true);

                // No match
            }else
                Err::add('Incorrect login');

            Err::check() or die(json_encode(array('passed' => true)));
            die(Err::check_and_output( 'as_json' ));

            break;

        case 'logout':
            setcookie('authenticated', 0, time()-86400, '/', COOKIE_DOMAIN, false, true);
            die( json_encode( array( 'success' => true ) ) );
            break;

        case 'save_settings':

            if( Post::get( 'security' ) === $security ){

                $path_to_config = CLEANTALK_ROOT . 'config.php';
                $apikey = Post::get( 'apikey' );
                global $account_name_ob;
                global $antispam_activity_status;

                /**
                 * Apikey validation
                 */
                if(!empty($apikey)) {
                    $result = API::method__notice_paid_till(
                        $apikey,
                        preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
                        'antispam'
                    );

                    if(
                           !empty($result)
                        && isset($result['valid']) && $result['valid'] === 0
                        && array_key_exists('account_name_ob', $result)
                        && ($result['account_name_ob'] === NULL || $result['account_name_ob'] !== $account_name_ob)
                    ) {
                        File::replace__variable( $path_to_config, 'apikey', '' );
                        die(Err::add('Error occurred while API key validating. Error: Testing is failed. Please check the Access key.')->get_last( 'as_json' ));
                    }
                } else {
                    die(Err::add('Please, enter the access key')->get_last( 'as_json' ));
                }

                if (! isset($antispam_activity_status) && Post::is_set( 'antispam_activity_status' )) {
                    File::clean__variable($path_to_config, 'antispam_activity_status');
                    File::inject__variable($path_to_config, 'antispam_activity_status', (bool)Post::get( 'antispam_activity_status' ));
                } else {
                    File::replace__variable( $path_to_config, 'antispam_activity_status', (bool)Post::get( 'antispam_activity_status' ) );
                }

                File::replace__variable( $path_to_config, 'apikey', $apikey );
                File::replace__variable( $path_to_config, 'registrations_test', (bool)Post::get( 'registrations_test' ) );
                File::replace__variable( $path_to_config, 'general_postdata_test', (bool)Post::get( 'general_postdata_test' ) );
                File::replace__variable( $path_to_config, 'spam_firewall', (bool)Post::get( 'spam_firewall' ) );
                File::replace__variable( $path_to_config, 'general_post_exclusion_usage', (bool)Post::get( 'general_post_exclusion_usage' ) );

                // SFW actions
                if( Post::get( 'spam_firewall' ) && $apikey ){

                    $sfw = new SFW();

                    // Update SFW
                    $result = $sfw->sfw_update( $apikey );
                    if( ! Err::check() ){
                        File::replace__variable( $path_to_config, 'sfw_last_update', time() );
                    }

                    // Send SFW logs
                    $result = $sfw->logs__send( Post::get( 'apikey' ) );
                    if( empty( $result['error'] ) && ! Err::check() )
                        File::replace__variable( $path_to_config, 'sfw_last_logs_send', time() );
                }

                Err::check() or die(json_encode(array('success' => true)));
                die(Err::check_and_output( 'as_json' ));

            }else
                die(Err::add('Forbidden')->get_last( 'as_json' ));
            break;

        case 'uninstall':

            if( Post::get( 'security' ) === $security ){

                setcookie('authenticated', 0, time()-86400, '/', COOKIE_DOMAIN, false, true);
                uninstall();

                Err::check() or die(json_encode(array('success' => true)));
                die(Err::check_and_output( 'as_json' ));

            }else
                die(Err::add('Forbidden')->get_last( 'as_json' ));
            break;

        case 'update':
            global $security;
            global $latest_version;

            $updater = new \Cleantalk\Updater\Updater( CLEANTALK_ROOT );
            $result = $updater->update(APBCT_VERSION, $latest_version);
            if( empty( $result['error'] ) ){
                File::clean__variable(CLEANTALK_CONFIG_FILE, 'latest_version');
                File::inject__variable(CLEANTALK_CONFIG_FILE, 'latest_version', $latest_version);
                File::clean__variable(CLEANTALK_CONFIG_FILE, 'antispam_activity_status');
				File::inject__variable(CLEANTALK_CONFIG_FILE, 'antispam_activity_status', true);
            }
            die(json_encode( $result, true ));
            break;

        default:
            die(Err::add('Unknown action')->get_last( 'as_json' ));
            break;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="img/ct_logo.png">
    <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">

    <title>Universal Anti-Spam Plugin by CleanTalk</title>

    <!-- CSS -->
    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.css" rel="stylesheet">

    <!-- Plugins-->
    <link href="css/overhang.min.css" rel="stylesheet">

    <!-- Custom styles -->
    <link href="css/setup-wizard.css" rel="stylesheet">

    <!-- Animation -->
    <link href="css/animate-custom.css" rel="stylesheet">

</head>
<body class="fade-in">

<!-- Login -->
<?php if( !isset($security) || \Cleantalk\Variables\Cookie::get( 'authenticated' ) !== $security ) { ?>
    <!-- start login wizard box -->
    <div class="container" id="setup-block">
        <div class="row">
            <div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4">

                <div class="setup-box clearfix animated flipInY">
                    <div class="page-icon animated bounceInDown">
                        <img  src="img/ct_logo.png" alt="Cleantalk logo" />
                    </div>
                    <div class="setup-logo">
                        <h3> - Universal Anti-Spam Plugin - </h3>
                    </div>
                    <hr />
                    <div class="setup-form">
                        <!-- Start Error box -->
                        <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
                            <button type="button" class="close" > &times;</button>
                            <p id='error-msg'></p>
                        </div>
                        <!-- End Error box -->
                        <?php if( ! empty( $is_installed ) ) : ?>
                            <form action = 'javascript:void(null);' method="post" id='login-form'>
                                <input type="text" placeholder="Access key<?php if( isset( $uni_email, $password ) ) echo ' or e-mail'; ?>" class="input-field" name="login" required/>

                                <?php if( ! empty( $password ) ) : ?>
                                    <input type="password" placeholder="Password" class="input-field" name="password"/>
                                <?php endif; ?>
                                <button type="submit" name="action" value="login" class="btn btn-setup" id="btn-login">Login</button>
                                <p>Don't know your access key? Get it <a href="https://cleantalk.org/my" target="_blank">here</a>.</p>
                            </form>
                        <?php else : ?>
                            <h4><p class="text-center">Please, <?php echo '<a href="' . CLEANTALK_URI . 'install.php">setup</a>'; ?> plugin first!</p></h4>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Settings -->
<?php } else { ?>
    <!-- End login-wizard wizard box -->
    <!-- Admin area box -->
    <div class="container" id="admin-block" style="margin-top: 80px;">

        <!-- Uninstall Logout buttons -->
        <div class="settings-links">
            <a style="float: right" href="#" id='btn-logout'>Log out </a>
        </div>

        <!-- Icon and title -->
        <div class="page-icon animated bounceInDown">
            <img  src="img/ct_logo.png" alt="Cleantalk logo" />
        </div>
        <div class="setup-logo">
            <h3> - Universal Anti-Spam Plugin - </h3>
        </div>

        <form action='javascript:void(null);' class="form-horizontal" role="form">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
                    <h4><p class="text-center">Settings</p></h4>
                    <hr>
                    <div class="col-sm-12">
                        <div class="form-group row">
                            <input class="form-control" type="text" placeholder="Access key" id="auth_key" name = "apikey" value =<?php if (isset($apikey)) echo $apikey; ?>>
                            <p>Account registered for email: <?php echo !empty($account_name_ob) ? $account_name_ob : 'unkonown';  ?></p>
                        </div>
                        <div class="form-group row">
                            <input type="checkbox" class="checkbox style-2 apbct_setting-checkbox" id="antispam_activity_status" name="antispam_activity_status" <?php if (!empty($antispam_activity_status)) echo "checked"; ?>>
                            <label for="antispam_activity_status" class="apbct_setting-checkbox--label">Enable Antispam</label>
                        </div>
                        <div class="form-group row">
                            <input type="checkbox" class="checkbox style-2 apbct_setting-checkbox" id="check_reg" name="registrations_test" <?php if (!empty($registrations_test)) echo "checked"; ?>>
                            <label for="check_reg" class="apbct_setting-checkbox--label">Check registrations</label>
                        </div>
                        <div class="form-group row">
                            <input type="checkbox" class="checkbox style-2 apbct_setting-checkbox" id="check_without_email" name="general_postdata_test" <?php if (!empty($general_postdata_test)) echo "checked"; ?>>
                            <label for="check_without_email" class="apbct_setting-checkbox--label">Check data without email</label>
                        </div>
                        <div class="form-group row">
                            <input type="checkbox" class="checkbox style-2 apbct_setting-checkbox" id="general_post_exclusion_usage" name="general_post_exclusion_usage" <?php if (!empty($general_post_exclusion_usage)) echo "checked"; ?>>
                            <label for="general_post_exclusion_usage" class="apbct_setting-checkbox--label">Exclude forms contain a service field</label>
                            <div id="exclusions-div" style="margin: 1% 2%; padding: 1%;border: 1px solid #CFCFCF;
                            display:
                            <?php echo $general_post_exclusion_usage ? 'inherit' : 'none' ?>
                            ">
                                <p>Add the tag below to the form that needs to be excluded. Set unique "id" attribute if you have several forms on the same page:</p>
                                <div id="exclusion-html" style="border: solid 1px; word-break: break-all; padding: 1%; background: #fff;">
                                <?php echo htmlspecialchars('<input id="any_id_1" name="ct_service_data" type="hidden" value="'. $exclusion_key .'">') ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="checkbox" class="checkbox style-2 apbct_setting-checkbox" id="enable_sfw" name="spam_firewall" <?php if (!empty($spam_firewall)) echo "checked"; ?>>
                            <label for="enable_sfw" class="apbct_setting-checkbox--label">Enable SpamFireWall</label>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
                    <h4><p class="text-center">Statistics</p></h4>
                    <hr>
                    <p>Check detailed statistics on <a href="https://cleantalk.org/my<?php echo !empty($user_token) ? '?cp_mode=antispam&user_token='.$user_token : ''; ?>" target="_blank">your Anti-Spam dashboard</a></p>
                    <p>Presumably CMS: <?php echo $detected_cms; ?></p>
                    <!--                    <p>Last spam check request to http://moderate3.cleantalk.org server was at Oct 07 2019 14:10:43.</p>-->
                    <!--                    <p>Average request time for past 7 days: 0.399 seconds.</p>-->
                    <p>SpamFireWall base contains <?php echo $sfw_entries; ?> entries.</p>
                    <p>SpamFireWall was updated: <?php echo $sfw_last_update ? date('M d Y H:i:s', $sfw_last_update) : 'never';?>.</p>
                    <p>SpamFireWall logs were sent: <?php echo $sfw_last_logs_send ? date('M d Y H:i:s', $sfw_last_logs_send) : 'never';?>.</p>
                    <p>Modified files:</p>
                    <?php foreach($modified_files as $file){;?>
                        <p>&nbsp; - <?php echo $file; ?></p>
                    <?php } ?>
                </div>
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-setup mt-sm-2" id='btn-save-settings' style="display: inline">Save</button>
                <img class="preloader" src="img/preloader.gif" style="display: none;">
            </div>
        </form>

        <?php
        /**
         * CsCart JS Snippet
         */
        apbct__cscart_js_snippet();

        /**
         * Plugin version section
         */
        apbct__plugin_update_message();
        ?>

    </div>

	<?php if( ! empty( $is_installed ) ) : ?>
        <footer class="container">
            <h5 style="text-align: center"><a href="#" style="color: inherit;" id='btn-uninstall' >Uninstall</a></h5>
        </footer>
	<?php endif; ?>
<?php } ?>
<!-- End Admin area box -->

<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/placeholder-shim.min.js"></script>
<script src="js/ct_ajax.js?v=<?php echo APBCT_VERSION; ?>"></script>
<script src="js/common.js?v=<?php echo APBCT_VERSION; ?>"></script>
<script src="js/custom.js?v=<?php echo APBCT_VERSION; ?>"></script>
<script src="js/overhang.min.js"></script>
<script type='text/javascript'>
    var security = '<?php if (isset($security)) echo $security ?>';
    var ajax_url = location.href;
</script>

</body>
