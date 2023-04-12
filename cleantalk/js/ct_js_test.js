var ct_date = new Date(), 
	ctTimeMs = new Date().getTime(),
	ctMouseEventTimerFlag = true, //Reading interval flag
	ctMouseData = [],
	ctMouseDataCounter = 0;

function ctSetCookie(c_name, value) {
	var ctSecure = location.protocol === 'https:' ? '; secure' : '';
	document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/; samesite=lax" + ctSecure;
}

var ctStart = function(){
	ctSetCookie("apbct_checkjs", apbct_checkjs_val);
	ctSetCookie("apbct_timezone", ct_date.getTimezoneOffset()/60*(-1));
	ctSetCookie("apbct_ps_timestamp", Math.floor(new Date().getTime()/1000));
	ctSetCookie("apbct_visible_fields", 0);
	ctSetCookie("apbct_visible_fields_count", 0);

	setTimeout(function(){

		var visible_fields_collection = {};
		var processedForms = [];

		for(var i = 0, host = '', action = ''; i < document.forms.length; i++){
			var form = document.forms[i];

			//Exclusion for forms
			if (
				form.method.toString().toLowerCase() === 'get' ||
				form.classList.contains('slp_search_form') || //StoreLocatorPlus form
				form.parentElement.classList.contains('mec-booking') ||
				form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
				(form.id && form.id === 'caspioform') || //Caspio Form
				(form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
				(form.classList && form.classList.contains('give-form')) || // GiveWP
				(form.id && form.id === 'ult-forgot-password-form') || //ult forgot password
				(form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
				(form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
				(form.id && form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
				(form.name && form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1)  || // WP Time Slots Booking Form
				form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
				(form.name && form.name.toString().indexOf('tribe-bar-form') !== -1)  // The Events Calendar
			) {
				continue;
			}

			if( ! apbct_visible_fields_already_collected( processedForms, form ) ) {
				visible_fields_collection[i] = apbct_collect_visible_fields( form );
				processedForms.push( apbct_get_form_details( form ) );
			}

			form.onsubmit_prev = form.onsubmit;

			form.ctFormIndex = i;
			form.onsubmit = function (event) {

				var visible_fields = {};
				visible_fields[0] = apbct_collect_visible_fields(this);
				apbct_visible_fields_set_cookie( visible_fields, event.target.ctFormIndex );

				// Call previous submit action
				if (event.target.onsubmit_prev instanceof Function) {
					setTimeout(function () {
						event.target.onsubmit_prev.call(event.target, event);
					}, 500);
				}
			};

			apbct_visible_fields_set_cookie( visible_fields_collection );

			if( typeof(form.action) == 'string' ){
			
				action = document.forms[i].action;

				// No home URL in address and http or https is in address
				if( action.indexOf(location.host) === -1 && ( action.indexOf('http://') !== -1 || action.indexOf('https://') !== -1 ) ){
					
					tmp  = action.split('//');
					tmp  = tmp[1].split('/');
					host = tmp[0].toLowerCase();
					last = tmp[tmp.length-1].toLowerCase();
				
					if( host != location.hostname.toLowerCase() || (last != 'index.php' && last.indexOf('.php') != -1)){
						var ct_action = document.createElement("input");
						ct_action.name='ct_action';
						ct_action.value=action;
						ct_action.type='hidden';
						document.forms[i].appendChild(ct_action);
						
						var ct_method = document.createElement("input");
						ct_method.name='ct_method';
						ct_method.value=document.forms[i].method;
						ct_method.type='hidden';
						document.forms[i].appendChild(ct_method);
											
						document.forms[i].method = 'POST';
						
						if (!window.location.origin){
							window.location.origin = window.location.protocol + "//" + window.location.hostname;
						}
						document.forms[i].action = window.location.origin;
					}
				}
			}
		}
	}, 1000);
};

//Writing first key press timestamp
var ctFunctionFirstKey = function(event){
	var KeyTimestamp = Math.floor(new Date().getTime()/1000);
	ctSetCookie("apbct_fkp_timestamp", KeyTimestamp);
	ctKeyStopStopListening();
};

//Reading interval
var ctMouseReadInterval = setInterval(function(){
	ctMouseEventTimerFlag = true;
}, 150);
	
//Writting interval
var ctMouseWriteDataInterval = setInterval(function(){
	ctSetCookie("apbct_pointer_data", JSON.stringify(ctMouseData));
}, 1200);

//Logging mouse position each 150 ms
var ctFunctionMouseMove = function(event){
	if(ctMouseEventTimerFlag == true){
		
		ctMouseData.push([
			Math.round(event.pageY),
			Math.round(event.pageX),
			Math.round(new Date().getTime() - ctTimeMs)
		]);
		
		ctMouseDataCounter++;
		ctMouseEventTimerFlag = false;
		if(ctMouseDataCounter >= 100){
			ctMouseStopData();
		}
	}
};

//Stop mouse observing function
function ctMouseStopData(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousemove", ctFunctionMouseMove);
	}else{
		window.detachEvent("onmousemove", ctFunctionMouseMove);
	}
	clearInterval(ctMouseReadInterval);
	clearInterval(ctMouseWriteDataInterval);				
}

//Stop key listening function
function ctKeyStopStopListening(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousedown", ctFunctionFirstKey);
		window.removeEventListener("keydown", ctFunctionFirstKey);
	}else{
		window.detachEvent("mousedown", ctFunctionFirstKey);
		window.detachEvent("keydown", ctFunctionFirstKey);
	}
}


function apbct_collect_visible_fields( form ) {

	// Get only fields
	var inputs = [],
		inputs_visible = '',
		inputs_visible_count = 0,
		inputs_invisible = '',
		inputs_invisible_count = 0,
		inputs_with_duplicate_names = [];

	for(var key in form.elements){
		if(!isNaN(+key))
			inputs[key] = form.elements[key];
	}

	// Filter fields
	inputs = inputs.filter(function(elem){

		// Filter already added fields
		if( inputs_with_duplicate_names.indexOf( elem.getAttribute('name') ) !== -1 ){
			return false;
		}
		// Filter inputs with same names for type == radio
		if( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute("type") )){
			inputs_with_duplicate_names.push( elem.getAttribute('name') );
			return false;
		}
		return true;
	});

	// Visible fields
	inputs.forEach(function(elem, i, elements){
		// Unnecessary fields
		if(
			elem.getAttribute("type")         === "submit" || // type == submit
			elem.getAttribute('name')         === null     ||
			elem.getAttribute('name')         === 'ct_checkjs'
		) {
			return;
		}
		// Invisible fields
		if(
			getComputedStyle(elem).display    === "none" ||   // hidden
			getComputedStyle(elem).visibility === "hidden" || // hidden
			getComputedStyle(elem).opacity    === "0" ||      // hidden
			elem.getAttribute("type")         === "hidden" // type == hidden
		) {
			if( elem.classList.contains("wp-editor-area") ) {
				inputs_visible += " " + elem.getAttribute("name");
				inputs_visible_count++;
			} else {
				inputs_invisible += " " + elem.getAttribute("name");
				inputs_invisible_count++;
			}
		}
		// Visible fields
		else {
			inputs_visible += " " + elem.getAttribute("name");
			inputs_visible_count++;
		}

	});

	inputs_invisible = inputs_invisible.trim();
	inputs_visible = inputs_visible.trim();

	return {
		visible_fields : inputs_visible,
		visible_fields_count : inputs_visible_count,
		invisible_fields : inputs_invisible,
		invisible_fields_count : inputs_invisible_count,
	}

}

function apbct_visible_fields_set_cookie( visible_fields_collection, form_id ) {

	var collection = typeof visible_fields_collection === 'object' && visible_fields_collection !== null ?  visible_fields_collection : {};

	for ( var i in collection ) {
		if ( i > 10 ) {
			// Do not generate more than 10 cookies
			return;
		}
		var collectionIndex = form_id !== undefined ? form_id : i;
		ctSetCookie("apbct_visible_fields_" + collectionIndex, JSON.stringify( collection[i] ) );
	}
}

function apbct_visible_fields_already_collected( formsProcessed, form ) {

	if ( formsProcessed.length > 0 && form.elements.length > 0 ) {

		var formMethod      = form.method;
		var formAction      = form.action;
		var formFieldsCount = form.elements.length;
		var formInputs      = [];

		// Getting only input elements from HTMLFormControlsCollection and putting these into the simple array.
		for( var key in form.elements ){
			if( ! isNaN( +key ) ) {
				formInputs[key] = form.elements[key];
			}
		}

		for ( var i = 0; i < formsProcessed.length; i++ ) {
			// The form with the same METHOD has not processed.
			if ( formsProcessed[i].method !== formMethod ) {
				return false;
			}
			// The form with the same ACTION has not processed.
			if ( formsProcessed[i].action !== formAction ) {
				// @ToDo actions often are different in the similar forms
				//return false;
			}
			// The form with the same FIELDS COUNT has not processed.
			if ( formsProcessed[i].fields_count !== formFieldsCount ) {
				return false;
			}

			// Compare every form fields by their TYPE and NAME
			var fieldsNames = formsProcessed[i].fields_names;
			for ( var field in fieldsNames ) {
				var res = formInputs.filter(function(item, index, array){
					var fieldName = item.name;
					var fieldType = item.type;
					if( fieldsNames[field].fieldName === fieldName && fieldsNames[field].fieldType === fieldType ) {
						return true;
					}
				});
				if( res.length > 0  ) {
					return true;
				}
			}
		}

		return false;
	}

	return false;
}

function apbct_get_form_details( form ) {

	if( form.elements.length > 0 ) {

		var fieldsNames = {};

		// Collecting fields and their names
		var inputs = form.elements;
		for (i = 0; i < inputs.length; i++) {
			var fieldName = inputs[i].name;
			var fieldType = inputs[i].type;
			fieldsNames[i] = {
				fieldName : fieldName,
				fieldType : fieldType,
			}
		}

		return {
			'method' : form.method,
			'action' : form.action,
			'fields_count' : form.elements.length,
			'fields_names' : fieldsNames,
		};
	}

	return false;
}

if(typeof window.addEventListener == "function"){
	document.addEventListener("DOMContentLoaded", ctStart);
	window.addEventListener("mousemove", ctFunctionMouseMove);
	window.addEventListener("mousedown", ctFunctionFirstKey);
	window.addEventListener("keydown", ctFunctionFirstKey);
}else{
	document.attachEvent("DOMContentLoaded", ctStart);
	window.attachEvent("onmousemove", ctFunctionMouseMove);
	window.attachEvent("mousedown", ctFunctionFirstKey);
	window.attachEvent("keydown", ctFunctionFirstKey);
}