var key_check_timer = 0,
	value    = false,
	is_empty = false,
	is_email = false,
	is_key   = false,
	key_valid = false,
	do_install = false;
is_password       = false,
	advan_config_show = false,
	email = null,
	user_token = null,
	account_name_ob = null;

/* Custom JS */
jQuery(document).ready(function($) {

	$( ".advanced_conf" ).hide();
	$('.show_more_icon').css('transform','rotate(' + 90 + 'deg)');

	/*---------- For Placeholder on IE9 and below -------------*/
	$('input, textarea').placeholder();

	/*----------- For icon rotation on input box foxus -------------------*/
	$('input[name="access_key_field"]').focus(function() {
		$('.page-icon img').addClass('rotate-icon');
	});

	/*----------- For icon rotation on input box blur -------------------*/
	$('input[name="access_key_field"]').blur(function() {
		$('.page-icon img').removeClass('rotate-icon');
	});

	$('#show_more_btn').click(function(){
		if (!advan_config_show) {
			$('.show_more_icon').css('transform','rotate(' + 0 + 'deg)');
			advan_config_show = true;
			$( ".advanced_conf" ).show();
		}
		else {
			$('.show_more_icon').css('transform','rotate(' + 90 + 'deg)');
			advan_config_show = false;
			$( ".advanced_conf" ).hide();

		}

	});

	// Checking and Highlighting access key onchange
	$('input[name="access_key_field"]').on('input', function(){

		clearTimeout(key_check_timer);

		var field = $(this);
		var value = field.val().trim();

		is_key   = value.search(/^[0-9a-zA-Z]*$/) === 0;

		if(is_key && value.length > 7){
			key_check_timer = setTimeout(function(){
				// field.addClass('loading');
				key_validate( value, field );
			}, do_install ? 5 : 2000);
		}
	});

	// Checking and Highlighting access key onchange
	$('input[name="email_field"]').on('input', function(){

		is_email = $(this).val().trim().search(/^\S+@\S+\.\S+$/) === 0;

		validate_installation();
	});

	// Checking and Highlighting access key onchange
	$('input[name="admin_password"]').on('input', function(){

		var field = $(this),
			value = $(this).val();

		is_password =  value.length >= 4  && value.search(/^[^\s]*$/) === 0;

		validate_installation();

		if( is_password ){
			$('#password_requirements').hide();
			field.css('border', '1px solid #04B66B');
			field.css('box-shadow', '0 0 8px #04B66B');
		}else{
			$('#password_requirements').show();
			field.css('box-shadow', '0 0 8px #F44336');
			field.css('border', '1px solid #F44336');
		}
	});

	// Install button
	$('#btn-setup').on('click', function(event){
		if( ! key_valid )
			get_key();
		else
			install();
	});

	//set the block with special tag in dependence of post_exclusion_usage statement
	$('#general_post_exclusion_usage').on('click', function(event) {
		let state = 'none'
		if ($('#general_post_exclusion_usage').prop('checked')){
			state = 'inherit'
		}
		$('#exclusions-div').css('display',state)
	});


	$('#btn-login').on('click', function(event) {
		login();
	});

	$("#btn-logout").on('click', function(event){
		if(confirm('Are you sure you want to logout?'))
			logout();
	});

	$('#btn-save-settings').on('click', function(event) {
		save_settings();
	});

	$('#serve_run_cron_sfw_update').on('click', function(event) {
		serve_run_cron_sfw_update();
	});

	$('#serve_run_cron_sfw_send_logs').on('click', function(event) {
		serve_run_cron_sfw_send_logs();
	});

	$("#btn-uninstall").on('click', function(event){
		if(confirm('Are you sure you want to uninstall the plugin?'))
			uninstall();
	});

	// click for update plugin
	$("#btn-update").on('click', function(event){
		update();
	});

	// Close alert
	$(".close").on('click', function(event){
		$(".alert-danger").hide(300);
	});

	function validate_installation(){

		$('.btn-setup').prop(
			'disabled',
			! ( is_email && is_password )
		);

	}

	function get_key(){

		let field = $('input[name="access_key_field"]');
		let email = $('input[name="email_field"]');

		ct_AJAX(
			{
				action: 'get_key',
				email: email.val().trim(),
				security: security,
			},
			{
				callback: function(result, data, params, obj) {
					if(result.auth_key){
						do_install = true;
						field.val(result.auth_key);
						field.trigger('input');
						email = result.email ? result.email : null;
					}else{
						$('#setup-form').hide();
						$('.setup-links').hide();
					}
				},
				spinner: function(){field.toggleClass('loading')}
			}
		);
	}

	function key_validate( value, field ){
		ct_AJAX(
			{
				action: 'key_validate',
				key: value,
			},
			{
				callback: function(result, data, params, obj){
					if(result.valid){

						key_valid = true;

						field.css('border', '1px solid #04B66B');
						field.css('box-shadow', '0 0 8px #04B66B');

						$('.btn-setup').prop('disabled', false);

						user_token = result.user_token ? result.user_token : null;
						account_name_ob = result.account_name_ob ? result.account_name_ob : null;

						if(do_install)
							install();
					}else{
						field.css('box-shadow', '0 0 8px #F44336');
						field.css('border', '1px solid #F44336');
						$('.btn-setup').prop('disabled', true);
						do_install = false;
					}
					field.prop('disabled', false);
					field.blur();
				},
				spinner: function(){ field.toggleClass('loading') }
			}
		);
	}

	function install(){
		ct_AJAX(
			{
				action: 'install',
				key: $('input[name="access_key_field"]').val().trim(),
				additional_fields: $('#addition_scripts').val().trim(),
				modify_index: +$('#input__modify_index').is( ":checked" ),
				admin_password : $('input[name="admin_password"]').val().trim(),
				email: $('input[name="email_field"]').val().trim(),
				user_token: user_token,
				account_name_ob: account_name_ob,
			},
			{
				callback: function(result, data, params, obj) {
					if(result.success){
						jQuery('.alert-danger').hide(300);
						$('.alert-success').show(300);
						$('#setup-form').hide();
						$('.setup-links').hide();
					}else{
						do_install = false;
					}
				},
			}
		);
	}

	function login() {
		var login = $('input[name="login"]');
		var password = $('input[name="password"]').length
			? $('input[name="password"]').val().trim()
			: null;
		ct_AJAX(
			{
				action: 'login',
				login: login.val().trim(),
				password: password,
			},
			{
				callback: function(result, data, params, obj) {
					if (result.passed)
						location.reload();
				},
				spinner: function(){ login.toggleClass('loading') }
			}
		);
	}

	function logout() {
		ct_AJAX(
			{
				action: 'logout',
			},
			{
				callback: function(result, data, params, obj) {
					if (result.success)
						location.reload();
				},
			}
		);
	}

	function save_settings(){
		ct_AJAX(
			{
				action: 'save_settings',
				apikey: $('input[name="apikey"]').val().trim(),
                antispam_activity_status: $('#antispam_activity_status').is(':checked') ? 1 : 0,
				registrations_test: $('#check_reg').is(':checked') ? 1 : 0,
				general_postdata_test: $('#check_without_email').is(':checked') ? 1 : 0,
				spam_firewall: $('#enable_sfw').is(':checked') ? 1 : 0,
				general_post_exclusion_usage: $('#general_post_exclusion_usage').is(':checked') ? 1 : 0,
			},
			{
				callback: function(result, data, params, obj) {
					if (result.success) {
						$("body").overhang({
							type: "success",
							message: "Settings saved! Page will be updated in 3 seconds.",
							duration: 3,
							overlay: true,
							// closeConfirm: true,
							easing: 'linear'
						});
						setTimeout(function(){ location.reload(); }, 3000 );
					}
				},
				spinner: $('#btn-save-settings+.preloader'),
				button: $('#btn-save-settings'),
				error_handler: function(result, data, params, obj){
					$("body").overhang({
						type: "error",
						message: 'Error: ' + result.error,
						duration: 43200,
						overlay: true,
						closeConfirm: true,
						easing: 'linear'
					});
				}
			}
		);
	}

	function serve_run_cron_sfw_update(){
		console.log('serve_run_cron_sfw_update')
		ct_AJAX(
			{
				action: 'serve_run_cron_sfw_update',
			},
			{
				callback: function(result, data, params, obj) {
					console.log(result)
					if(result.success){
						alert('OK');
					}
				},
			}
		);
	}

	function serve_run_cron_sfw_send_logs(){
		console.log('serve_run_cron_sfw_send_logs')
		ct_AJAX(
			{
				action: 'serve_run_cron_sfw_send_logs',
			},
			{
				callback: function(result, data, params, obj) {
					console.log(result)
					if(result.success){
						alert('OK');
					}
				},
			}
		);
	}

	function uninstall(){
		ct_AJAX(
			{
				action: 'uninstall',
			},
			{
				callback: function(result, data, params, obj) {
					if(result.success){
						location.reload();
					}
				},
			}
		);

	}

	function update(){
		ctAJAX({
			data: { action: 'update'},
			button: $('#btn-update'),
			spinner: $('#btn-update+.ajax-preloader'),
			successCallback: function(result, data, params, obj) {
				if (result.success) {
					$("body").overhang({
						type: "success",
						message: "Update was successful",
						duration: 3,
						overlay: true,
						easing: 'linear'
					});
				}
			},
			errorOutput: function( msg ) {
				$("body").overhang({
					type: "error",
					message: 'Error during update: ' + msg,
					duration: 43200,
					overlay: true,
					closeConfirm: true,
					easing: 'linear'
				})
			},
		});
	}

});
