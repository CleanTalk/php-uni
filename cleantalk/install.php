<?php

require_once 'check_requirements.php';

use Cleantalk\Common\Err;
use Cleantalk\Common\API;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

if(version_compare( phpversion(), '5.6', '>=' )){
    require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';
    require_once 'inc' . DIRECTORY_SEPARATOR . 'admin.php';
}

define( 'CLEANTALK_URI', preg_replace( '/^(.*\/)(.*?.php)?/', '$1',  Server::get('REQUEST_URI') ) );

if( version_compare( phpversion(), '5.6', '>=' ) && empty( $is_installed ) ){

    // Validating key
    if( Post::get( 'action' ) == 'key_validate' && Post::get( 'security' ) == md5( Server::get( 'SERVER_NAME' ) ) ){

        $result = API::method__notice_paid_till(
            Post::get( 'key' ),
            preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
            'antispam'
        );

        // $result['error'] = 'some';
        if( ! empty( $result['error'] ) ){
            $result['error'] = 'Checking key failed: ' . $result['error'];
        }

        die( json_encode( $result ) );
    }

    // Gettings key
    if( Post::get( 'action' ) == 'get_key' && Post::get( 'security' ) == md5( Server::get( 'SERVER_NAME' ) ) ){

        $result = API::method__get_api_key(
            'antispam',
            Post::get( 'email' ),
            Server::get( 'SERVER_NAME' ),
            'php-uni'
        );

        $result['email'] = Post::get( 'email' );

        if( ! empty( $result['account_exists'] ) ){
            $result['error'] = 'Please, get the access key from <a href="https://cleantalk.org/my/?cp_mode=antispam"> CleanTalk Control Panel</a> and insert it in the Access Key field';
        }
        if( ! empty( $result['error'] ) ){
            $result['error'] = 'Getting key error: ' . $result['error'];
        }


        die( json_encode( $result ) );
    }

    // Installation
    if( Post::get( 'action' ) == 'install' && Post::get( 'security' ) == md5( Server::get( 'SERVER_NAME' ) ) ){

        // Parsing key
        if( preg_match( '/^[a-z0-9]{1,20}$/', Post::get( 'key' ), $matches ) ){

            $api_key = $matches[0];
            $cms     = detect_cms( CLEANTALK_SITE_ROOT . 'index.php' );

            // Add index.php to files for modification if exists
            $files_to_mod = array();

            if( 'phpBB' == $cms ) {
                $files_to_mod[] = 'ucp.php';
                $files_to_mod[] = 'posting.php';
                $files_to_mod[] = 'memberlist.php';
            }

            //moodle CMS integration
            if( 'moodle' === $cms ) {
                $files_to_mod[] = 'login/signup.php';
            }

            if( Post::get( 'modify_index' ) )
                $files_to_mod[] = 'index.php';

            //Additional scripts to modify
            if( Post::get( 'additional_fields' ) ){
                // Merging
                $additional_files = explode( ",", Post::get( 'additional_fields' ) );
                $files_to_mod     = array_unique( array_merge( $files_to_mod, $additional_files ) );
            }

            Err::check() && die( Err::get_last( 'as_json' ) );

            if( !empty($files_to_mod) ){

                // Determine file to install Cleantalk script
                $exclusions = array();

                // Adding files to $files_to_mod depends from cms installed
                switch ( $cms ){
                    case 'X-Cart 4':
                        array_push( $files_to_mod, "home.php", "register.php", "add_review.php", "help.php" );
                        break;
                    case 'osTicket':
                        array_push( $files_to_mod, "account.php", "open.php" );
                        break;
                    case 'PrestaShop':
                        // array_push( $files_to_mod, "account.php", "open.php" );
                        $exclusions['submitLogin'] = '1';
                        break;
                    case 'Question2Answer':
                        $exclusions['dologin'] = '1';
                        break;
                    case 'DLE':
                        array_push( $files_to_mod, "engine/ajax/controller.php" );
                        break;
                }

                install( $files_to_mod, $api_key, $cms, $exclusions );

            }else
                Err::add( 'No files to modify or all files for script paths are unavailable' );
        }else
            Err::add( 'Key is bad. Key is "' . Post::get( 'key' ) . '"' );

        // Check for errors and output result
        $out = Err::check()
            ? Err::get_last( 'string' )
            : array( 'success' => true );

        die( json_encode( $out ) );
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
    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles -->
    <link href="css/setup-wizard.css" rel="stylesheet">

    <link href="css/animate-custom.css" rel="stylesheet">

</head>
<body class="fade-in">
<!-- start setup wizard box -->
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

                    <!-- Already installed. Settings link -->
                    <?php if( ! empty( $is_installed ) ) : ?>
                        <h4><p class="text-center">The plugin is already installed. You could enter the settings <?php echo '<a href="' . Server::get( 'HOST_NAME' ) . '/cleantalk/settings.php">here</a>'; ?> .</p></h4>

                        <!-- Installation form -->
                    <?php else : ?>
                        <div class="alert alert-success alert-dismissible fade in" style="display:none; word-wrap: break-word;" role="alert">
                            <strong style="text-align: center; display: block;">Success!</strong>
                            <br />
                            <p>Enter your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
                            <br />
                            <p>You can manage settings here: <a class="underlined" href="settings.php"><?php echo CLEANTALK_URI . 'settings.php'; ?></a></p>
                            <br />
                            <p>This location will be no longer accessible until the plugin is installed.</p>
                            <br />
                            <p>You can test any form on your website by using special email stop_email@example.com. Any submit with this email will be blocked.</p>
                        </div>
                        <!-- Start Error box -->
                        <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
                            <button type="button" class="close" > &times;</button>
                            <p id='error-msg'></p>
                        </div> <!-- End Error box -->
                        <form action = 'javascript:void(null);' method="post" id='setup-form'>
                            <p class="text-center">Please, enter your e-mail and password to protect Universal Anti-Spam Plugin dashboard</></p>
                            <div style="text-align: center">
                                <input type="email" placeholder="E-mail" class="input-field" name="email_field" required style="display: inline;"/>
                                <img class="preloader" src="img/preloader.gif" style="display: none;" alt="">
                            </div>
                            <input type="password" name="admin_password" class="input-field" placeholder="New password">
                            <p class="text-center --hide" id='password_requirements'><small>Password requirements are 4 symbols minimum, and no spaces.</small></p>

                            <p><button type="button" class="btn" id="show_more_btn" style="background-color:transparent">Advanced configuration (optional) <img  class ="show_more_icon" src="img/expand_more.png" alt="Show more" style="width:24px; height:24px;"/></button></p>
                            <div class ="advanced_conf">
                                <div style="text-align: center">
                                    <p class="text-center"><small>If you already have an access key, you could use it here</small></p>
                                    <input type="text" placeholder="Access key" class="input-field" name="access_key_field"  style="display: inline;"/>
                                    <img class="preloader" src="img/preloader.gif" style="display: none;">
                                </div>
                                <p><small>Additional scripts</small>&nbsp;<img data-toggle="tooltip" data-placement="top" title="Universal Anti-Spam plugin will write protection code to index.php file by default. If your contact or registration contact forms are located in different files/scripts, list them here separated by commas. Example: register.php, contact.php" src="img/help_icon.png" style="width:10px; height:10px;"></p>
                                <input type="text" class="input-field" id="addition_scripts"/>
                                <p><small><label for="input__modify_index" style="font-weight: normal;">Modify "index.php" script?</label></small>
                                    <img data-toggle="tooltip" data-placement="top" title="Universal Anti-Spam plugin will write protection code to index.php file by default. If you don't want to modify it, uncheck this." src="img/help_icon.png" style="width:10px; height:10px;"></p></p>
                                <input type="checkbox" name="modify_index" value="1" checked class="" id="input__modify_index">
                            </div>
                            <button type="submit" class="btn btn-setup" id="btn-setup" disabled>Install</button>
                        </form>
                        <div class="setup-links">

                            <a href="https://cleantalk.org/publicoffer" target="_blank">
                                License agreement
                            </a>
                            <br />
                            <a href="https://cleantalk.org/register?platform=php-uni&website=<?php echo $_SERVER['SERVER_NAME']; ?>" target="_blank">
                                Don't have an account? <strong>Create here!</strong>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <p class="footer-text"><small>Please, check the extension for your CMS on our <a href="https://cleantalk.org/help/install" target="_blank" style="text-decoration: underline;">plugins page</a> before setup</small></p>
            <p class="footer-text"><small>It is highly recommended to create a backup before installation</small></p>
        </div>
    </div>
</div>
<!-- End setup-wizard wizard box -->

<footer class="container">

</footer>

<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/placeholder-shim.min.js"></script>
<script src="js/common.js?v=<?php echo APBCT_VERSION; ?>>"></script>
<script src="js/custom.js?v=<?php echo APBCT_VERSION; ?>"></script>
<script type='text/javascript'>
    var security = '<?php echo md5( Server::get( 'SERVER_NAME' ) ) ?>';
    var ajax_url = location.href;
</script>

</body>
</html>
