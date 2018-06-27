var key_check_timer = 0,
	value    = false,
	is_empty = false,
	is_email = false,
	is_key   = false,
	do_install = false;

/* Custom JS */
$(document).ready(function($) {
	
	/*---------- For Placeholder on IE9 and below -------------*/
	$('input, textarea').placeholder();
	
	/*----------- For icon rotation on input box foxus -------------------*/ 	
	$('.input-field').focus(function() {
  		$('.page-icon img').addClass('rotate-icon');
	});
	
	/*----------- For icon rotation on input box blur -------------------*/ 	
	$('.input-field').blur(function() {
  		$('.page-icon img').removeClass('rotate-icon');
	});

	// Checking and Highlighting access key onchange
	$('.input-field').on('input', function(){
		
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

	// Installation
	function install(){
		
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'install',
				key: $('.input-field').val().trim(),
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
		$('.input-field').addClass('loading');					
		
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'get_key',
				email: $('.input-field').val().trim(),
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
					$('.input-field').val(result.auth_key);
					$('.input-field').trigger('input');
				}
				$('.input-field').removeClass('loading');				
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

