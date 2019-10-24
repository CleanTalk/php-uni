var key_check_timer = 0,
	value    = false,
	is_empty = false,
	is_email = false,
	is_key   = false,
	do_install = false;
	advan_config_show = false;

/* Custom JS */
$(document).ready(function($) {
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
		
		clearInterval(key_check_timer);
		
		var field = $(this);
		
		value = field.val().trim(),
		is_empty = value == '' ? true : false,
		is_email = value.search(/^\S+@\S+\.\S+$/) == 0 ? true : false,
		is_key   = value.search(/^[0-9a-zA-Z]*$/) == 0 ? true : false;	
		if(is_empty){
			field.css('box-shadow', '0 0 0.5px rgba(0,0,0,0.5)');
			field.css('border','0');
			$('.btn-setup').prop('disabled', true);
			return;
		}
		if(!is_key && !is_email){
			$('.btn-setup').prop('disabled', true);
			return;
		}
		
		if(is_email){
			$('.btn-setup').prop('disabled', false);
			return;
		}
		if(is_key && value.length > 7){
			key_check_timer = setTimeout(function(){
				field.addClass('loading');

				$.ajax({
					type: "POST",
					url: location.href,
					data: {
						action: 'key_validate',
						key: value,
						security: security,
					},
					success: function(result){
						result = $.parseJSON(result);
						if(result.valid == true){
							field.css('border', '1px solid #04B66B');
							field.css('box-shadow', '0 0 8px #04B66B');

							$('.btn-setup').prop('disabled', false);
							if(do_install)
								install();
						}else{
							field.css('box-shadow', '0 0 8px #F44336');			
							field.css('border', '1px solid #F44336');
							$('.btn-setup').prop('disabled', true);
							do_install = false;
							if(result.error){
								$('.alert-danger').show(300);
								$('#error-msg').text(result.error_string);
							}
						}
						field.prop('disabled', false);
						field.removeClass('loading');
						field.blur();
					},
					error: function(){
						do_install = false;
						$('.alert-danger').show(300);
						$('#error-msg').text("Checking key failed!");
					}
				});
			}, do_install ? 5 : 2000);						
		}

});	
	// Install button
	$('.btn-setup').on('click', function(event){
		if(is_email)
			get_key();
		
		if(!is_email && is_key)
			install();
	});
	$('#btn-login').on('click', function(event) {
		login();
	});
	$('#btn-save-settings').on('click', function(event) {
		save_settings();
	});	
	function save_settings() {
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'save_settings',
				ct_auth_key: $('input[name="ct_auth_key"]').val().trim(),
				ct_check_reg: $('input[name="ct_check_reg"]').val(),
				ct_check_without_email: $('input[name="ct_check_without_email"]').val(),
				ct_enable_sfw: $('input[name="ct_enable_sfw"]').val(),
			},
			success: function(result) {
				result = $.parseJSON(result);
				if (result.success) {
					$("body").overhang({
					  type: "success",
					  message: "Settings saved!",
					  duration: 3,
					  overlay: true,
					  closeConfirm: true
					});
				}
			},
			error: function(){
				
			}
		});		
	}
	function login() {
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'login',
				login: $('input[name="access_key_field_login"]').val().trim(),
				password: $('input[name="admin_password_key_field_login"]').val().trim(),
			},
			success: function(result) {
				result = $.parseJSON(result);
				if (result.passed) {
					document.location.reload(true);
				}
				else {
					$('.alert-danger').show(300);
					$('#error-msg').text('Incorrect login or password!');
				}
			},
			error: function(){
				
			}
		});
	}
	// Installation
	function install(){
		
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'install',
				key: $('input[name="access_key_field"]').val().trim(),
				additional_fields: $('#addition_scripts').val().trim(),
				admin_password : $('input[name="admin_password"]').val().trim(),
				security: security,
			},
			success: function(result){
				result = $.parseJSON(result);
				
				if(result.success){
					$('.alert-success').show(300);
					$('#setup-form').hide();	
					$('.setup-links').hide();		
					
				}else{
					do_install = false;
					$('.alert-danger').show(300);
					$('#error-msg').text(result.error);
				}
			},
			error: function(){
				do_install = false;
				$('.alert-danger').show(300);
				$('#error-msg').text('Installation error!');
				$('#setup-form').hide();
				$('.setup-links').hide();
			}
		});
	}

	// Getting access key
	function get_key(){
		$('input[name="access_key_field"]').addClass('loading');					
		
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'get_key',
				email: $('input[name="access_key_field"]').val().trim(),
				security: security,
			},
			success: function(result){
				result = $.parseJSON(result);
				if(result.error){
					$('.alert-danger').show(300);
					$('#error-msg').text(result.error_string);					
				}
				else if(result.exists){
					$('.alert-danger').show(300);
					$('#error-msg').text("This website already added!");					
				}else{
					do_install = true;
					$('input[name="access_key_field"]').val(result.auth_key);
					$('input[name="access_key_field"]').trigger('input');
				}
				$('input[name="access_key_field"]').removeClass('loading');				
			},
			error: function(){
				$('.alert-danger').show(300);
				$('#error-msg').text("Getting key error!");
				$('#setup-form').hide();
				$('.setup-links').hide();				
			}
		});
	}
	
	// Close alert
	$(".close").on('click', function(event) 
	{   
	    $(".alert-danger").hide(300);
	});

});

