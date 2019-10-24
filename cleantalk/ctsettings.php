<?php
// Config
require_once('ct_config.php');
session_start();
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_destroy();
    unset($_SESSION['authenticated']);
    header('location:ctsettings.php');
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'login') {
            $result['passed'] = isset($_POST['login']) && $_POST['login'] == $auth_key && isset($_POST['password']) && hash('sha256',trim($_POST['password'])) == $admin_password;
            if ($result['passed']) {
                $_SESSION['authenticated'] = 'true';
            }

            die(json_encode($result));       
        }
        if ($_POST['action'] == 'save_settings') {

            $new_settings = array(
                'auth_key' => isset($_POST['ct_auth_key']) ? $_POST['ct_auth_key'] : $auth_key,
                'check_reg' => (isset($_POST['ct_check_reg']) && $_POST['ct_check_reg'] == 'true') ? true : false,
                'check_all_post_data' => (isset($_POST['ct_check_without_email']) && $_POST['ct_check_without_email'] == 'true') ? true : false,
                'swf_on' => (isset($_POST['ct_enable_sfw']) && $_POST['ct_enable_sfw'] == 'true') ? true : false,
            );
            change_config_file_settings('ct_config.php', $new_settings);
            die(json_encode(array(
            'success' => true
            )));
        }        
    }
}
function change_config_file_settings ($filePath, $newSettings) {

    //TODO update settings
    $config = file_get_contents($filePath);

    foreach ($newSettings as $key => $value) {
        $updatedConfig = preg_replace("/\$".$key." = (.*?);/", "\$".$key." = ".$value.";", $config);
    }
    file_put_contents($filePath, $updatedConfig);
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
                            </div> <!-- End Error box -->
                            <?php if ($is_installed) : ?>
                            <form action = 'javascript:void(null);' method="post" id='login-form'>
                                 <input type="text" placeholder="Access key or e-mail" class="input-field" name="access_key_field_login" required/> 
                                 <input type="password" placeholder="Password" class="input-field" name="admin_password_key_field_login"/> 
                                 <button type="submit" class="btn btn-setup" id = 'btn-login' >Login</button> 
                            </form>
                            <?php else : ?>
                            <h4><p class="text-center">Please, setup plugin first!</p></h4>
                            <?php endif; ?>             
                        </div>                      
                   </div>                   
                </div>
            </div>
        </div>
    <?php } else { ?>
        <!-- End login-wizard wizard box -->
        <!-- Admin area box -->
        <div class="container" id="admin-block" style="margin-top: 65px;">
        <div align="right" style="margin-top: -50px;"><a href="?logout=true" onclick="return confirm('Are you sure you want to logout?');">Log out </a></div>
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
                            <input class="form-control" type="text" placeholder="Access key" id="auth_key" name = "ct_auth_key" value =<?php if (isset($auth_key)) echo $auth_key; ?>>
                        </div>                                            
                        <div class="form-group row">
                            <label for="ct_check_reg">Check registrations</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="check_reg" name="ct_check_reg" <?php if (isset($check_reg) && $check_reg == true) echo "checked"; ?>>
                        </div>                        
                        <div class="form-group row">
                            <label for="ct_check_without_email">Check data without email</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="check_without_email" name="ct_check_without_email" <?php if (isset($check_all_post_data) && $check_all_post_data == true) echo "checked"; ?>>
                        </div>
                        <div class="form-group row">
                            <label for="ct_enable_sfw">Enable SpamFireWall</label>
                            <input type="checkbox" class="checkbox style-2 pull-right" id="enable_sfw" name="ct_enable_sfw" <?php if (isset($swf_on) && $swf_on == true) echo "checked"; ?>>
                        </div>                        
                    </div>
                </div>
                <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
                    <h4><p class="text-center">Statistics</p></h4>
                    <hr>
                    <p>Check detailed statistics on <a href="//cleantalk.org/my" target="_blank">your Anti-Spam dashboard</a></p>
                    <p>Last spam check request to http://moderate3.cleantalk.org server was at Oct 07 2019 14:10:43.</p>
                    <p>Average request time for past 7 days: 0.399 seconds.</p>
                    <p>Last time SpamFireWall was triggered for unknown IP at unknown</p>
                    <p>SpamFireWall was updated Oct 08 2019 06:57:08. Now contains 6526 entries.</p>
                    <p>SpamFireWall sent unknown events at unknown.</p>
                    <p>There are no failed connections to server.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-setup mt-sm-2" id = 'btn-save-settings'>Save</button>
            </form>
        </div>
    <?php } ?>
        <!-- End Admin area box -->

        <footer class="container">

        </footer>

       <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
        <script>window.jQuery || document.write('<script src="js/jquery-1.9.1.min.js"><\/script>')</script> 
        <script src="js/bootstrap.min.js"></script> 
        <script src="js/placeholder-shim.min.js"></script>        
        <script src="js/custom.js?v=13"></script>
        <script src="js/overhang.min.js"></script>
        <script type='text/javascript'>
            var security = '<?php echo md5($_SERVER['SERVER_NAME']) ?>';
        </script>

    </body>