var langs = {
	EN: {
		header_start:      {type: 'txt', text: 'Greetings, lets get started!'},
		header_lang:       {type: 'txt', text: 'Please, select your language:'},
		header_setup:      {type: 'txt', text: 'Enter Access key or Email:'},
		header_reg:        {type: 'txt', text: 'You can get access in two ways:'},
		field_key:         {type: 'plh', text: 'Access key'},
		field_email:       {type: 'plh', text: 'Enter email'},
		and:               {type: 'txt', text: 'and'},
		button_key_auto:   {type: 'txt', text: 'Get key automatically'},
		or:                {type: 'txt', text: 'or'},
		key_manual:        {type: 'val', text: 'Register manually'},
		button_setup:      {type: 'txt', text: 'Start setup'},
		button_next:       {type: 'val', text: 'Next'},
		button_back:       {type: 'val', text: 'Back'},
		header_success:    {type: 'txt', text: 'Сongratulations!'},
		header_success2:   {type: 'txt', text: 'Setup is complete!'},		
	},
	RU: {
		header_start:      {type: 'txt', text: 'Здравствуйте, давайте начнем!'},
		header_lang:       {type: 'txt', text: 'Пожалуйста, выберете язык:'},
		header_setup:      {type: 'txt', text: 'Введите ключ досутпа или E-mail:'},
		header_reg:        {type: 'txt', text: 'Вы можете получить ключ двумя путями:'},
		field_key:         {type: 'plh', text: 'Ключ доступа'},
		field_email:       {type: 'plh', text: 'Введите e-mail'},
		and:               {type: 'txt', text: 'и'},
		button_key_auto:   {type: 'txt', text: 'Получите ключ автоматически'},
		or:                {type: 'txt', text: 'или'},
		key_manual:        {type: 'val', text: 'Зарегистрируйтесь вручную'},
		button_setup:      {type: 'txt', text: 'Начать установку'},
		button_next:       {type: 'val', text: 'Дальше'},
		button_back:       {type: 'val', text: 'Назад'},
		header_success:    {type: 'txt', text: 'Поздравляем!'},
		header_success2:   {type: 'txt', text: 'Установка прошла успешно!'},
	}
};

$( function(){
		
	// Language selection
	$('select[name=language]').on('click', 'option', function(){
		change_language(this.getAttribute('value'));
	});
	
	// Default language
	change_language('EN');
	
});

function change_language(lang){
	
	lang = langs[lang];
	
	var telems = $('.lang'),
		telem;
	
	for(key in lang){
		telem = telems.filter('.'+key);
		if(lang[key].type == 'val')
			telem.val(lang[key].text);
		if(lang[key].type == 'txt')
			telem.text(lang[key].text);
		if(lang[key].type == 'plh')
			telem.attr('placeholder', lang[key].text);
	}
}