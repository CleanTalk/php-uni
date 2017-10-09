var key_check_timer = 0,
	value    = false,
	is_empty = false,
	is_email = false,
	is_key   = false,
	do_install = false;

$( function(){
		
	// Next button
	$('.button_next').on('click', function(){
		$('.button_back').show();
		$('.current_content')
			.hide()
			.removeClass('current_content')
			.next()
				.show()
				.addClass('current_content');
		if($('.current_content').hasClass('content_form'))
			$(this).hide();
	});
	
	// Back button
	$('.button_back').on('click', function(){
		$('.button_next').show();
		$('.current_content')
			.hide()
			.removeClass('current_content')
			.prev()
				.show()
				.addClass('current_content');
		if($('.current_content').hasClass('content_language'))
			$(this).hide();
	});
	
	// Checking and Highlighting access key onchange
	$('.field_key').on('input', function(){
		
		clearInterval(key_check_timer);
		
		var field = $(this);
		
		value = field.val().trim(),
		is_empty = value == '' ? true : false,
		is_email = value.search(/^\S+@\S+\.\S+$/) == 0 ? true : false,
		is_key   = value.search(/^[0-9a-zA-Z]*$/) == 0 ? true : false;
			
		if(is_empty){
			field.css('border-bottom', '3px solid #ddd');
			$('.button_setup').prop('disabled', true);
			$('.field_status').hide();
			return;
		}
		if(!is_key && !is_email){
			field.css('border-bottom', '3px solid red');
			$('.field_status').hide();
			$('.invalid_mail').show();
			$('.button_setup')
				.text('Register and start setup')
			return;
		}
		
		if(is_email){
			field.css('border-bottom', '3px solid green');
			$('.field_status').hide();
			$('.valid_mail').show();
			$('.valid_mail2').show();
			$('.button_setup')
				.text('Register and start setup')
				.prop('disabled', false);
			return;
		}
				
		if(is_key && value.length > 7){
			
			$('.button_setup').text('Start setup');
			
			if(!do_install){
				$('.timer').stop(true, true);
				$('.timer').animate({
					width: 300,
					left: -2,
				},
				2000,
				function(){
					$(this).css({left: -151, width: 0})
				});
			}
			
			key_check_timer = setTimeout(function(){
				
				$('.field_status').hide();
				field
					.prop('disabled', true);
					// .css('border-bottom', '3px solid #ddd');
				$('.field_key_preloader').css('visibility','visible')

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
							field.css('border-bottom', '3px solid green');
							$('.button_setup').prop('disabled', false);
							$('.valid_key').show();
							$('#footer').hide();
							if(do_install)
								install();
						}else{
							field.css('border-bottom', '3px solid red');
							$('.button_setup').prop('disabled', true);
							$('.invalid_key').show();
							do_install = false;
							if(result.error)
								alert(result.error_string);
						}
						field.prop('disabled', false);
						$('.field_key_preloader').css('visibility','hidden')
					},
					error: function(){
						do_install = false;
						alert('Checking key failed');
					}
				});
			}, do_install ? 5 : 2000);
		}
	});
	
	// Install
	$('.button_setup ').on('click', function(event){
			
		if(is_email)
			get_key();
		
		if(!is_email && is_key)
			install();
	});
	
	// Getting access key
	function get_key(){
							
		$('.field_key_preloader').css('visibility','visible')
		
		$.ajax({
			type: "POST",
			url: location.href,
			data: {
				action: 'get_key',
				email: $('.field_key').val().trim(),
				security: security,
			},
			success: function(result){
				result = $.parseJSON(result);
				if(result.error)
					alert(result.error_string);
				else if(result.exists){
					alert('This website already added');
				}else{
					do_install = true;
					$('.field_key').val(result.auth_key);
					$('.field_key').trigger('input');
				}
				$('.field_key_preloader').css('visibility','hidden')				
			},
			error: function(){
				alert('Getting key error');
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
				key: $('.field_key').val(),
				security: security,
			},
			success: function(result){
				
				result = $.parseJSON(result);
				
				if(result.success){
										
					$('.button_back').hide();
					$('.current_content')
						.hide()
						.removeClass('current_content')
						.next()
							.show()
							.addClass('current_content');
					
				}else{
					do_install = false;
					alert('Installer throws a error: ' + result.error);
				}
			},
			error: function(){
				do_install = false;
				alert('Istallation error');
			}
		});
	}	
});