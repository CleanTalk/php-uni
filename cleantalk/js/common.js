function sendAJAX(data, params, obj){

	// Default params
	var button        = params.button        || null;
	var field         = params.field        || null;
	var spinner       = params.spinner       || null;
	var progressbar   = params.progressbar   || null;
	var callback      = params.callback      || null;
	var error_handler = params.error_handler || null;
	var notJson       = params.notJson       || null;
	var timeout       = params.timeout       || 15000;
	obj               = obj                  || null;

	// Button and spinner
	if(button)  { button.attr('disabled', 'disabled'); button.css('cursor', 'not-allowed'); }  // Disable button
	if(spinner && typeof spinner == 'function') spinner();                                     // Show spinner
	if(spinner && typeof spinner == 'object') 	jQuery(spinner).css('display', 'inline');      // Show spinner

	data.security = security; // Adding security code

	jQuery.ajax({
		type: "POST",
		url: ajax_url,
		data: data,
		success: function(result){

			console.log(result);
			console.log(button);

			if(button){ button.removeAttr('disabled'); button.css('cursor', 'pointer'); }         // Enable button
			if(spinner && typeof spinner == 'function') spinner();                                // Hide spinner
			if(spinner && typeof spinner == 'object') 	jQuery(spinner).css('display', 'none'); // Hide spinner
			if(!notJson) result = JSON.parse(result);                                             // Parse answer
			if(!notJson && result.error){                                                         // Show error

				setTimeout(function(){
						if(progressbar) progressbar.fadeOut('slow');
					}, 1000
				);

				let error = error_handler
					? error_handler(result, data, params, obj)
					: function(){
						jQuery('.alert-danger').show(300);
						jQuery('#error-msg').text(result.error);
					};
				error();

			}else{
				if(callback)
					callback(result, data, params, obj);
			}
		},
		error: function(jqXHR, textStatus, errorThrown){
			if(button){ button.removeAttr('disabled'); button.css('cursor', 'pointer'); }
			if(spinner && typeof spinner == 'function') spinner();
			if(spinner && typeof spinner == 'object') 	jQuery(spinner).css('display', 'none');
			console.log('SPBC_AJAX_ERROR');
			console.log(jqXHR);
			console.log(textStatus);
			console.log(errorThrown);
			if(errorThrown)
				alert(errorThrown);
		},
		timeout: timeout,
	});
}