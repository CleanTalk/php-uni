<?php
	
	// Validating key
	if(isset($_POST['action']) && $_POST['action'] == 'key_validate' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkHelper.php');
		$result = CleantalkHelper::noticeValidateKey($_POST['key']);
		die(json_encode($result));
	}
	
	// Gettings key
	if(isset($_POST['action']) && $_POST['action'] == 'get_key' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkHelper.php');
		$result = CleantalkHelper::getApiKey($_POST['email'], $_SERVER['SERVER_NAME'], 'php-uni');
		die(json_encode($result));
	}
	
	// Installation
	if(isset($_POST['action']) && $_POST['action'] == 'install' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
		
	// Additions to INDEX.PHP
	
		$path_to_index = __DIR__ . '/index.php';
		
		if(!file_exists($path_to_index)){
			die(json_encode(array(
				'error' => 'Unable to find index.php in the ROOT directory.',
			)));
		}
		
		$index_file = file_get_contents($path_to_index);
		
		$php_open_tags  = preg_match_all("/(<\?)/", $index_file);
		$php_close_tags = preg_match_all("/(\?>)/", $index_file);
		
		$file_lenght     = strlen($index_file);
		$first_php_start = strpos($index_file, '<?');
		$first_php_end   = strpos($index_file, '?>');
		$last_php_end    = strrpos($index_file, '?>');
		
		// Adding <?php to the strat if it's not there
		if($first_php_start !== 0)
			$index_file = "<?php\n\t\n\t\n?>".$index_file;
		
		// Adding ? > to the end if it's not there
		if($php_open_tags > $php_close_tags)
			$index_file = $index_file."\n\t\n?>";
		
		// Addition to index.php Top
		$top_code_addition = "//Cleantalk\n\trequire_once(__DIR__ . '/cleantalk/cleantalk.php');";
		$index_file = preg_replace('/(<\?php)|(<\?)/', "<?php\n\t\n\t" . $top_code_addition, $index_file, 1);
		
		// Addition to index.php Bottom (JavaScript test)
		$bottom_code_addition = "\n"."<script>\n\tvar apbct_checkjs_val = '<? echo \$apbct_checkjs_val; ?>';\n</script>"."\n".'<script src="cleantalk/js/js_test.js"></script>';
		$index_file = $index_file.$bottom_code_addition;
		
		$fd = fopen($path_to_index, 'w') or die("Unable to open index.php");
		fwrite($fd, $index_file);
		fclose($fd);
		
	// Additions to CONFIG.PHP
	
		$path_to_config = __DIR__ . '/cleantalk/config.php';
		$code_addition  = "//Auth key";
		$code_addition .= "\n\t\$auth_key = '{$_POST['key']}';";
		
		$file_content = file_get_contents($path_to_config);
		$file_content = preg_replace('/(<\?php)|(<\?)/', "<?php\n\t\n\t" . $code_addition, $file_content, 1);
		
		$fd = fopen($path_to_config, 'w') or die('Unable to open config.php');
		fwrite($fd, $file_content);
		fclose($fd);
		
	// Delete instllation file
		unlink(__FILE__);
	
		die(json_encode(array(
			'success' => true
		)));
		
	}
	
?>
<html>
	<head>
		<script type='text/javascript' src="cleantalk/js/jquery-3.2.1.min.js"></script>
		<script type='text/javascript' src="cleantalk/js/lang.js"></script>
		<script type='text/javascript' src="cleantalk/js/setup.js"></script>
		
		<script type='text/javascript'>
			var security = '<?php echo md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME']) ?>';
		</script>
		
		<link rel="stylesheet" type="text/css" href="cleantalk/css/reset.css" />
		<link rel="stylesheet" type="text/css" href="cleantalk/css/setup.css" />
	</head>
	<body>
	
		<div id="main_wrapper">
			<header>
				<img id="logo" src="cleantalk/images/big-logo.png" />
				<h3 class="margin0">Universal Anti-Spam plugin for WebSites by CleanTalk</h3>
				<!-- h1 class="margin0">CleanTalk</h1-->
				<br /><br /><br /><br /><br />
			</header>
			<div id="content_wrapper">
			
				<!-- Language selection -->
				<div class="content content_language hide">
					<h2 class="lang header_start separator_text">Greetings, lets get started!</h2>
					<hr class="separator" style="background: #2ea2cc; max-width: 600px;"/>
					<h3 class="lang header_lang">Please, select your language:</h3>
					<select name="language" size="5">
						<option value="RU">Русский</option>
						<option value="EN">English</option>
					</select>
				</div>
				
				<!-- installation -->
				<div class="content content_form current_content">
						
					<h2 class="lang header_setup separator_text">Enter Access key or Email</h2>
					<hr class="separator" style="background: #2ea2cc; max-width: 600px;"/>
					
					<br />
					
					<div style="position: relative; height: 60px;">
						<div class="timer"></div>
						<input type="text" placeholder="Email or Access Key" class="field field_key lang" />
						<img src="cleantalk/images/preloader.gif" class="field_key_preloader preloader invisible" />
						<p style="font-size:12px;" class="lang field_status key_status invalid_key hide">Invalid access key</p>
						<p style="font-size:12px;" class="lang field_status key_status valid_key hide">Valid access key</p>
						<p style="font-size:12px;" class="lang field_status mail_status invalid_mail hide">Invalid email</p>
						<p style="font-size:12px;" class="lang field_status mail_status valid_mail hide">Valid email</p>
					</div>
					
					<div style="position: relative; height: 80px;">
						<button type="button" name="install" class="lang button button_setup button_key_auto" disabled>Start setup</button>
						<br />
						<p class="inline light_gray font_small lang by_pressing" style="margin: 3px;">by pressing this button you accept</p>
						<a href="https://cleantalk.org/publicoffer" target="_blank" class="inline light_gray font_small lang underlined license_agreement">License Agreement</a>
						<p style="font-size:12px; margin-top: 10px;" class="lang field_status mail_status valid_mail2 hide">Email will be used for registration</p>
					</div>
					
					<h3 class="lang or separator_text">or</h3>
					<hr class="separator" style="width: 40%; max-width: 300px;"/>
					<br />
					<a href="https://cleantalk.org/register?platform=php-uni&website=<?php echo $_SERVER['SERVER_NAME']; ?>" target="_blank" class="lang button_key_manual underlined">Register manually</a>
					
				</div>
				
				<!-- Success -->
				<div class="content content_language current_content hide">
					<br />
					<br />
					<h2 class="lang header_success">Сongratulations! Setup is complete!</h2>
					<br />
					<p>Enter to your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
					<br />
					<p>File ctsetup.php was deleted automatically. This page doesn't exists anymore.</p>
					<br />
					<p>You can test any form on your website by using special email stop_email@example.com. Every submit with this email will be blocked.</p>
				</div>
				
			</div>
			
			<input type="button" value="Back" class="hide center lang button button_back" style="display: none;"/>
			<input type="button" value="Next" class="hide center lang button button_next"/>
			
		</div>
		
		<!-- Footer -->
		<div id="footer">
			<p class="lang inline footer_notice">Attention!</p>
			<p class="lang inline footer_notice2">Before setup the CleanTalk please check the extension for your CMS on our</p>
			<a href="https://cleantalk.org/help/install" target="_blank" class="inline underlined lang plugins_page">plugins page.</a>
			<p class="lang inline footer_notice2">If you found the extension, please use it to setup CleanTalk, otherwise continue setup with the Wizard.</p>
			<br /><br />
			<p class="lang inline footer_notice_backup">It is highly recommended to create a backup before installation.</p>
			<br /><br />
			<p class="underlined light_gray hide"><a href="https://cleantalk.org/publicoffer" target="_blank" class="lang license_agreement">License Agreement</a></p>
		</div>
	</body>

</html>