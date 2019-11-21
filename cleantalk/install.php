<?php

function cleantalk_is_error( $var ){
	return $var instanceof \Cleantalk\Err;
}

use Cleantalk\Err;

define( 'DS', DIRECTORY_SEPARATOR );
define( 'CLEANTALK_ROOT_DIR', getcwd() . DS );
define( 'CLEANTALK_DIR', CLEANTALK_ROOT_DIR . DS . 'cleantalk' . DS );

// Lib autoloader
require_once CLEANTALK_DIR . 'lib' . DS . 'ct_autoloader.php';


	// Validating key
	if(isset($_POST['action']) && $_POST['action'] == 'key_validate' && $_POST['security'] == md5($_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkBase/CleantalkAPI.php');
		require_once('cleantalk/lib/CleantalkAPI.php');
		$result = CleantalkAPI::method__notice_validate_key(
			$_POST['key'], 
			preg_replace('/http[s]?:\/\//', '', $_SERVER['SERVER_NAME'], 1)
		);
		die(json_encode($result));
	}
	
	// Gettings key
	if(isset($_POST['action']) && $_POST['action'] == 'get_key' && $_POST['security'] == md5($_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkBase/CleantalkAPI.php');
		require_once('cleantalk/lib/CleantalkAPI.php');
		$result = CleantalkAPI::method__get_api_key(
			'antispam',
			$_POST['email'],
			$_SERVER['SERVER_NAME'],
			'php-uni'
		);
		error_log( var_export( $result, true ));
		die(json_encode($result));
	}
	
	// Installation
	if(isset($_POST['action']) && $_POST['action'] == 'install' && $_POST['security'] == md5($_SERVER['SERVER_NAME'])){
			
		// Additions to INDEX.PHP
		
		$path_to_index = getcwd() . DS . 'index.php';	
		if(!file_exists($path_to_index)){
			die(json_encode(array('error' => 'Unable to find index.php in the ROOT directory.')));
		}

		// Parsing params
		if(preg_match('/^[a-z0-9]{1,20}$/', $_POST['key'], $matches)){
			$api_key = $matches[0];
		}else{
			die(json_encode(array('error' => 'Key is bad. Key is "'.$_POST['key'].'"')));
		}
		$files_to_mod = array('index.php');
		
		// Detecting CMS
		$index_file = file_get_contents($path_to_index);
		
		//X-Cart 4
		if (preg_match('/(xcart_4_.*?)/', $index_file))
			array_push($files_to_mod, "home.php","register.php","add_review.php","help.php");
		//osTicket
		if (preg_match('/osticket/i', $index_file))
			array_push($files_to_mod, "account.php", "open.php");
		//Additional scripts
		if (!empty($_POST['additional_fields'])) {
			$add_files = explode(",", $_POST['additional_fields']);
			if ($add_files && is_array($add_files)) {
				foreach ($add_files as $file)
					array_push($files_to_mod, $file);
			}
		}
		
		foreach ($files_to_mod as $file_name)
		{
		 
			$mod_file = file_get_contents(CLEANTALK_ROOT_DIR . $file_name);
			$php_open_tags  = preg_match_all("/(<\?)/", $mod_file);
			$php_close_tags = preg_match_all("/(\?>)/", $mod_file);
			$first_php_start = strpos($mod_file, '<?');
			
			$result = true;
			
			// Adding <?php to the start if it's not there
			if($first_php_start !== 0)
				$result = \Cleantalk\File::inject__code($file_name, "<?php\n?>\n", 'start');
			
			if( !cleantalk_is_error( $result ) ){
				
				// Adding ? > to the end if it's not there
				if($php_open_tags <= $php_close_tags)
					$result = \Cleantalk\File::inject__code($file_name, "\n<?php\n", 'end');
				
				if( !cleantalk_is_error( $result ) ){
			        
                    // Addition to the top of the script
				    $result = \Cleantalk\File::inject__code(
					    $file_name,
					    "\trequire_once( getcwd() . '/cleantalk/cleantalk.php');",
					    '(<\?php)|(<\?)',
					    'top_code'
				    );
					
					if( !cleantalk_is_error( $result ) ){
			         
				        // Addition to index.php Bottom (JavaScript test)
				        $result = \Cleantalk\File::inject__code(
					        $file_name,
					        "\tob_end_flush();\n"
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
		
	// Additions to CT_CONFIG.PHP
		
		$path_to_config = CLEANTALK_DIR . 'ct_config.php';
		
		// Backwards because inserting it step by step
        //
		\Cleantalk\File::inject__tag__end($path_to_config, 'installed_config');
		
            if (isset($_POST['admin_password']))
                \Cleantalk\File::inject__code($path_to_config, "\t\$admin_password = '" . hash('sha256',trim($_POST['admin_password'])) . "';");
            \Cleantalk\File::inject__code($path_to_config, "\t\$modified_files = " . var_export($files_to_mod, true) .";");
            \Cleantalk\File::inject__code($path_to_config, "\t\$auth_key = '$api_key';");
            
		\Cleantalk\File::inject__tag__start($path_to_config, 'installed_config');
		
	// Delete instllation file
		unlink(__FILE__);
        
        if(Err::get()->has_errors()){
	        die(Err::get()->get_all('as_json'));
        }else{
            die(json_encode(array(
                'success' => true
            )));
        }
        
		
	}	
?>
<!DOCTYPE html>
<html>
  <head>  	
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <link rel="shortcut icon" href="cleantalk/img/ct_logo.png"> 
    <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">

	<title>Universal Anti-Spam Plugin by CleanTalk</title>
    <!-- Bootstrap core CSS -->
    <link href="cleantalk/css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles -->
    <link href="cleantalk/css/setup-wizard.css" rel="stylesheet">   
    
    <link href="cleantalk/css/animate-custom.css" rel="stylesheet"> 
   
  </head>
    <body class="fade-in">
    	<!-- start setup wizard box -->
    	<div class="container" id="setup-block">
    		<div class="row">
			    <div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4">
			    	 
			       <div class="setup-box clearfix animated flipInY">
			       		<div class="page-icon animated bounceInDown">
			       			<img  src="cleantalk/img/ct_logo.png" alt="Cleantalk logo" />
			       		</div>
			        	<div class="setup-logo">
			        		<h3> - Universal Anti-Spam Plugin - </h3>
			        	</div> 
			        	<hr />
			        	<div class="setup-form">
			        		  <div class="alert alert-success alert-dismissible fade in" style="display:none; word-wrap: break-word;" role="alert">
							    <strong>Success!</strong> 
							    <br />
							    <p>Enter to your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
							    <br />
								<p>File ctsetup.php was deleted automatically. This page doesn't exists anymore.</p>
								<br />
								<p>You can manage your settings <a class="underlined" href="/cleantalk/ctsettings.php">here</a></p>
								<br />
								<p>You can test any form on your website by using special email stop_email@example.com. Every submit with this email will be blocked.</p>
							  </div>
			        		<!-- Start Error box -->
			        		<div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
								  <button type="button" class="close" > &times;</button>
								   <p id='error-msg'></p>
							</div> <!-- End Error box -->
			        		<form action = 'javascript:void(null);' method="post" id='setup-form'>
						   		 <input type="text" placeholder="Access key or e-mail" class="input-field" name="access_key_field" required/> 
						   		 	<p><button type="button" class="btn" id="show_more_btn" style="background-color:transparent">Advanced configuration <img  class ="show_more_icon" src="cleantalk/img/expand_more.png" alt="Show more" style="width:24px; height:24px;"/></button></p>
 							   		<div class ="advanced_conf">
							   		<p class="text-center">Set admin password</p>
									<input type="password" name="admin_password" class="input-field" placeholder="Password"> 							   			
 							   			<p><small>Additional scripts</small>&nbsp;<img data-toggle="tooltip" data-placement="top" title="Universal Anti-Spam plugin will write protection code to index.php file by default. If your contact or registration contact forms are located in different files/scripts, list them here separated by commas. Example: register.php, contact.php" src="/cleantalk/img/help_icon.png" style="width:10px; height:10px;"></p>
 							   			<input type="text" class="input-field" id="addition_scripts" style="height:25px; width:50%"/> 
 							   		</div>
						   		 <button type="submit" class="btn btn-setup" disabled>Install</button> 
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
			        	</div> 			        	
			       </div>			        
			    </div>
			</div>
            <div class="row">
                <div class="col-sm-12">
                    <p class="footer-text"><small>Please, check the extension for your CMS on our <a href="https://cleantalk.org/help/install" target="_blank">plugins page</a> before setup</small></p>
                    <p class="footer-text"><small>It is highly recommended to create a backup before installation</small></p>
                </div>
            </div>
    	</div>
      	<!-- End setup-wizard wizard box -->

     	<footer class="container">

        </footer>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script>window.jQuery || document.write('<script src="cleantalk/js/jquery-1.9.1.min.js"><\/script>')</script> 
        <script src="cleantalk/js/bootstrap.min.js"></script> 
        <script src="cleantalk/js/placeholder-shim.min.js"></script>        
        <script src="cleantalk/js/custom.js?v=13"></script>
		<script type='text/javascript'>
			var security = '<?php echo md5($_SERVER['SERVER_NAME']) ?>';
		</script>

    </body>
</html>
	