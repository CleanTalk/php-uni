<?php if( version_compare( phpversion(), '5.6', '<' ) ) : ?>
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
				    <!-- Check requirements -->
				    <h4><p class="text-center">PHP version is <?php echo phpversion(); ?></p></h4>
				    <h4><p class="text-center">The plugin requires version 5.6 or higher.</p></h4>
				    <h4><p class="text-center">Please, contact your hosting provider to update it.</p></h4>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
<?php
    die();
    endif;