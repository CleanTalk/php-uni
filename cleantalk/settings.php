<?php

use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;
use Cleantalk\ApbctUni\SFW;

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';
require_once 'inc' . DIRECTORY_SEPARATOR . 'admin.php';

session_start();

if( Server::is_post() && Post::get( 'action' ) ){
	
	// Brute force protection
	sleep(2);
    
    switch (Post::get('action')){
        
        case 'login':
            
            // If password is set in config
	        if(isset($password)){
		        if( ( Post::get( 'login' ) == $apikey || ( isset( $email ) && Post::get( 'login' ) == $email ) ) && hash( 'sha256', trim( Post::get( 'password' ) ) ) == $password ){
                    $_SESSION['authenticated'] = 'true';
                }else
                    Err::add('Incorrect login or password');
		        
            // No password is set. Check only login.
	        }elseif( ( Post::get( 'login' ) == $apikey ) ){
                $_SESSION['authenticated'] = 'true';
                
            // No match
	        }else
		        Err::add('Incorrect login');
	
	        Err::check() or die(json_encode(array('passed' => true)));
	        die(Err::check_and_output( 'as_json' ));
	        
	        break;
	
        case 'logout':
            session_destroy();
            unset($_SESSION['authenticated']);
	        die( json_encode( array( 'success' => true ) ) );
            break;
		        
        case 'save_settings':
            
            if( Post::get( 'security' ) === $security ){
	
	            $path_to_config = CLEANTALK_ROOT . 'config.php';
	            
                File::replace__variable( $path_to_config, 'apikey', Post::get( 'apikey' ) );
                File::replace__variable( $path_to_config, 'registrations_test', (bool)Post::get( 'registrations_test' ) );
                File::replace__variable( $path_to_config, 'general_postdata_test', (bool)Post::get( 'general_postdata_test' ) );
                File::replace__variable( $path_to_config, 'spam_firewall', (bool)Post::get( 'spam_firewall' ) );
                
                // SFW actions
	            if( Post::get( 'spam_firewall' ) && Post::get( 'apikey' ) ){
		            
		            $sfw = new SFW();
		            
		            // Update SFW
		            $result = $sfw->sfw_update( Post::get( 'apikey' ) );
		            if( ! Err::check() ){
		                File::replace__variable( $path_to_config, 'sfw_last_update', time() );
		                File::replace__variable( $path_to_config, 'sfw_entries', $result );
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
			
			    session_destroy();
			    unset($_SESSION['authenticated']);
		        uninstall();
			
			    Err::check() or die(json_encode(array('success' => true)));
			    die(Err::check_and_output( 'as_json' ));
			    
		    }else
			    die(Err::add('Forbidden')->get_last( 'as_json' ));
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
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="img/ct_logo.png">
        <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">
        
        <title>Universal Anti-Spam Plugin by CleanTalk</title>
        <!-- Bootstrap core CSS -->
        <link href="css/bootstrap.css" rel="stylesheet">
        <link href="css/overhang.min.css" rel="stylesheet">
        
        <!-- Custom styles -->
        <link href="css/setup-wizard.css" rel="stylesheet">
        
        <link href="css/animate-custom.css" rel="stylesheet">
    
    </head>
    <body class="fade-in">
        
        <!-- Login -->
        <?php if(empty($_SESSION["authenticated"]) || $_SESSION["authenticated"] != 'true') { ?>
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
                                 <input type="text" placeholder="Access key<?php if( isset( $email, $password ) ) echo ' or e-mail'; ?>" class="input-field" name="login" required/>
                                 
                                 <?php if( ! empty( $password ) ) : ?>
                                 <input type="password" placeholder="Password" class="input-field" name="password"/>
                                 <?php endif; ?>
                                 <button type="submit" name="action" value="login" class="btn btn-setup" id="btn-login">Login</button>
                                 <p>Don't know your access key? Get it <a href="https://cleantalk.org/my" target="_blank">here</a>.</p>
                            </form>
                            <?php else : ?>
                            <h4><p class="text-center">Please, <?php echo '<a href="' . Server::get( 'HOST_NAME' ) . '/cleantalk/install.php">setup</a>'; ?> plugin first!</p></h4>
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
        <div align="left" style="margin-top: -50px;"><a href="#" class="text-danger" id='btn-uninstall' >Uninstall</a></div>
        <div align="right" style="margin-top: -20px;"><a href="#" id='btn-logout'>Log out </a></div>
            <div class="row" style="margin-top: 50px;">
                <div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4">
                    <div class="page-icon animated bounceInDown">
                        <img  src="img/ct_logo.png" alt="Cleantalk logo" />
                    </div>
                    <div class="setup-logo">
                        <h3> - Universal Anti-Spam Plugin - </h3>
                    </div>
                </div>
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
                            <label for="check_reg">Check registrations</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="check_reg" name="registrations_test" <?php if (!empty($registrations_test)) echo "checked"; ?>>
                        </div>
                        <div class="form-group row">
                            <label for="check_all_post_data">Check data without email</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="check_without_email" name="general_postdata_test" <?php if (!empty($general_postdata_test)) echo "checked"; ?>>
                        </div>
                        <div class="form-group row">
                            <label for="swf_on">Enable SpamFireWall</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="enable_sfw" name="spam_firewall" <?php if (!empty($spam_firewall)) echo "checked"; ?>>
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
                </div>
            </div>
                <div class="wrapper wrapper__center">
                    <button type="submit" class="btn btn-setup mt-sm-2" id='btn-save-settings' style="display: inline">Save</button>
                    <img class="preloader" src="img/preloader.gif" style="display: none;">
                </div>
            </form>
        </div>
    <?php } ?>
        <!-- End Admin area box -->

        <footer class="container">

        </footer>
        
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/placeholder-shim.min.js"></script>
    <script src="js/common.js?v=2.0"></script>
    <script src="js/custom.js?v=2.0"></script>
    <script src="js/overhang.min.js"></script>
    <script type='text/javascript'>
        var security = '<?php echo $security ?>';
        var ajax_url = location.href;
    </script>

</body>