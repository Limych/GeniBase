<?php
/**
 * Функции работы текстовыми данными и форматирования информации.
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Date of last modification search keys making alghoritm.
 * @var string
 */
define('GB_METAKEYS_MAKE_DATE', '2015-03-04');	// YYYY-MM-DD

define('GB_MK_SURNAME',		 100);
define('GB_MK_MAIDEN_NAME',	 200);
define('GB_MK_GIVEN_NAME',	 300);
define('GB_MK_PATRONYMIC',	1000);
define('GB_MK_OTHER_NAME',	9900);
 
/**
 * Функция вычисления поисковых ключей слов.
 * 
 * @since	1.0.0
 * 
 * @param	array	$names	Associative array of names.
 * @return	array		Associative array of arrays of metakeys. All keys are upcased.
 */
function make_metakeys($names){
	foreach($names as $key => $val){
		if( !is_array($val))
			$names[$key] = preg_split('/[^\w\?\*]+/uS', $val, -1, PREG_SPLIT_NO_EMPTY);
	}
	
	/**
	 * Make meta keys for names.
	 * 
	 * @since	2.1.1
	 * 
	 * @param	array	$names	Associative array of arrays of names.
	 */
	$metakeys = GB_Hooks::apply_filters('make_metakeys', $names);

	foreach($metakeys as $key => $val)
		$metakeys[$key] = array_unique(array_map('mb_strtoupper', array_filter((array) $val)));

	return array_filter($metakeys);
}

/**
 * Функция вычисления фонетического ключа русского слова.
 * 
 * NB: Оригинальный алгоритм модифицирован для нужд данной поисковой системы.
 * 
 * @param	string|string[]	$word			Исходное слово или массив слов.
 * @param	boolean			$trim_surname	Признак, что передана фамилия. У фамилий сокращаются типичные окончания.
 * @return	string|string[]		Фонетический ключ слова или набор ключей для набора слов.
 */
function rus_metaphone($word, $trim_surname = false){
	// Если вместо строки передан массив, обработать каждое значение в отдельности
	// и вернуть результат в виде массива
	if( is_array($word)){
		foreach($word as $key => $val)
			$word[$key] = rus_metaphone($val, $trim_surname);
			return array_filter($word);
	}

	static $alf	= 'ОЕАИУЭЮЯПСТРКЛМНБВГДЖЗЙФХЦЧШЩЁЫ\?\*';	// алфавит кроме исключаемых букв
	static $cns1	= 'БЗДВГ';	// звонкие согласные
	static $cns2	= 'ПСТФК';	// глухие согласные
	static $cns3	= 'ПСТКБВГДЖЗФХЦЧШЩ';	// согласные, перед которыми звонкие оглушаются
	static $ch		= 'ОЮЕЭЯЁЫ';	// образец гласных
	static $ct		= 'АУИИАИИ';	// замена гласных
	static $ends	= array(	// Шаблоны для «сжатия» окончания наиболее распространённых фамилий
		'/ОВСК(?:И[ЙХ]|АЯ)$/uS'	=> '0',	// -овский, -овских, -овская
		'/ЕВСК(?:И[ЙХ]|АЯ)$/uS'	=> '1',	// -евский, -евских, -евская
		'/[ЕИ]?Н(?:ОК|КО(?:В|ВА)?)$/uS'
								=> '2',	// -енко, -енков, -енкова, -енок, -инко, -инков, -инкова, -инок, -нко, -нков, -нкова, -нок
		'/[ИЕ]?ЕВА?$/uS'		=> '3',	// -иев, -еев, -иева, -еева
		'/ИНА?$/uS'				=> '4',	// -ин, -ина
		'/[УЮ]К$/uS'			=> '5',	// -ук, -юк
		'/[ИЕ]К$/uS'			=> '6',	// -ик, -ек
		'/[ЫИ]Х$/uS'			=> '7',	// -ых, -их
		'/(?:[ЫИ]Й|АЯ)$/uS'		=> '8',	// -ый, -ий, -ая
		'/[ЕО]ВА?$/uS'			=> '9',	// -ов, -ев, -ова, -ева
	);
	static $ij		= array(	// Шаблоны для замены двубуквенных конструкций
		'/[ЙИ][ОЕ]/uS'				=> 'И',
		'/(?<=[АУИОЮЕЭЯЁЫ])Й/uS'	=> 'И',
	);
	$callback	= function($match) use ($cns1, $cns2){
		return strtr($match[1], $cns1, $cns2);
	};

	// Переводим в верхний регистр и оставляем только символы из $alf
	$word = mb_strtoupper($word, 'UTF-8');
	$word = preg_replace("/[^$alf]+/usS", '', $word);
	if( empty($word))	return $word;

	// Сжимаем парно идущие одинаковые буквы
	$word = preg_replace("/([^\?])\\1+/uS", '\\1', $word);

	// Сжимаем окончания фамилий, если это необходимо
	if( $trim_surname)	$word = preg_replace(array_keys($ends), array_values($ends), $word);

	// Оглушаем последний символ, если он - звонкий согласный
	$word = preg_replace_callback("/([$cns1])$/uS",	$callback, $word);

	// Сжимаем -йо-, -йе- и т.п.
	$word = preg_replace(array_keys($ij), array_values($ij), $word);
	
	// Оглушаем все гласные
	$word = strtr($word, $ch, $ct);

	// Оглушаем согласные перед согласными
	$word = preg_replace_callback("/([$cns1])(?=[$cns3])/uS", $callback, $word);

	// Повторно сжимаем парно идущие одинаковые буквы
	$word = preg_replace("/([^\?])\\1+/uS", '\\1', $word);

	return $word;
} // function rus_metaphone

define('GB_MK_KEY_PHONE',	10);
/**
 * Make metaphone word keys for names.
 *
 * @since	2.1.1
 *
 * @param array $metakeys	Associative array of names.
 * @return array	Associative array of names and metakeys.
*/
function gb_metaphone($metakeys){
	$names = array(GB_MK_SURNAME, GB_MK_MAIDEN_NAME, GB_MK_GIVEN_NAME, GB_MK_PATRONYMIC,
			GB_MK_OTHER_NAME);
	foreach($names as $nk){
		if( !empty($metakeys[$nk]))
			$metakeys[$nk + GB_MK_KEY_PHONE] = rus_metaphone($metakeys[$nk],
					($nk === GB_MK_SURNAME));
	}
	return $metakeys;
}
GB_Hooks::add_filter('make_metakeys', 'gb_metaphone');

/**
 * Функция вычисления письменного ключа русского слова.
 *
 * @param	string|string[]	$word	Исходное слово или массив слов.
 * @return	string|string[]		Письменный ключ слова или набор ключей для набора слов.
 */
function rus_metascript($word){
	// Если вместо строки передан массив, обработать каждое значение в отдельности
	// и вернуть результат в виде массива
	if( is_array($word)){
		foreach($word as $key => $val)
			$word[$key] = rus_metascript($val);
		return array_filter($word);
	}

	static $subs = array(
			// Непропечатки печатного текста
			// Заглавные буквы
			'/Д/uS'	=> 'Л',
			'/О/uS'	=> 'С',
			'/[ТП]/uS'	=> 'Г',
			'/[ЧК]/uS'	=> 'Н',
			'/Щ/uS'	=> 'Ш',
			'/Ъ/uS'	=> 'Ь',
			'/Й/uS'	=> 'И',
			// Строчные буквы
			'/д/uS'	=> 'л',
			'/о/uS'	=> 'с',
			'/[тп]/uS'	=> 'г',
			'/[чк]/uS'	=> 'н',
			'/щ/uS'	=> 'ш',
			'/ъ/uS'	=> 'ь',
			'/й/uS'	=> 'и',
			'/ы/uS'	=> 'м',
			'/в/uS'	=> 'з',
	);
	$word = preg_replace(array_keys($subs), array_values($subs), $word);
	return $word;
}

define('GB_MK_KEY_SCRIPT',	20);
/**
 * Make metascript word keys for names.
 *
 * @since	2.1.1
 *
 * @param array $metakeys	Associative array of names.
 * @return array	Associative array of names and metakeys.
*/
function gb_metascript($metakeys){
	$names = array(GB_MK_SURNAME, GB_MK_MAIDEN_NAME, GB_MK_GIVEN_NAME, GB_MK_PATRONYMIC,
			GB_MK_OTHER_NAME);
	foreach($names as $nk){
		if( !empty($metakeys[$nk]))
			$metakeys[$nk + GB_MK_KEY_SCRIPT] = rus_metascript($metakeys[$nk]);
	}
	return $metakeys;
}
GB_Hooks::add_filter('make_metakeys', 'gb_metascript');



/**
 * Функция нормирования русского текста.
 * 
 * @param	string	$text	Исходный текст для нормирования.
 * @return	string		Нормированный текст. 
 */
function fix_russian($text){
	static $alf = array(
		// Старо-русские буквы
		'ѣ'	=> 'Е',		'Ѣ'	=> 'е',
		'Ѵ'	=> 'И',		'ѵ'	=> 'и',
		'І'	=> 'И',		'і'	=> 'и',
		'Ѳ'	=> 'Ф',		'ѳ'	=> 'ф',
		"\u1029"	=> 'З',		"\u1109"	=> 'з',	// Зело

		// «Подделки» под русские буквы
		'I'	=> 'И',		'i'	=> 'и',
		'İ'	=> 'И',		'i'	=> 'и',
		'V'	=> 'И',		'v'	=> 'и',
		'S'	=> 'З',		's'	=> 'з',
		// латиница → кириллица
		'A'	=> 'А',		'a'	=> 'а',
		'B'	=> 'В',		'b'	=> 'в',
		'E'	=> 'Е',		'e'	=> 'е',
		'K'	=> 'К',		'k'	=> 'к',
		'M'	=> 'М',		'm'	=> 'м',
		'H'	=> 'Н',		'h'	=> 'н',
						'n'	=> 'п',
		'O'	=> 'О',		'o'	=> 'о',
		'P'	=> 'Р',		'p'	=> 'р',
		'C'	=> 'С',		'c'	=> 'с',
		'T'	=> 'Т',		't'	=> 'т',
		'Y'	=> 'У',		'y'	=> 'у',
		'X'	=> 'Х',		'x'	=> 'х',
	);
	
		// Сжимаем множественные звёздочки
		$text = preg_replace("/\*{2,}/uS", '*', $text);
		
	$text = preg_split('/(\W+)/uS', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	for($i = 0; $i < count($text); $i += 2){
		if( preg_match('/[а-яА-Я]/uS', $text[$i]))
			$text[$i] = preg_replace('/[ъЪ]$/uS', '', strtr($text[$i], $alf));
	}
	return implode($text);
}





/**
 * Check if only latin characters are in text.
 * 
 * @param	string	$text	Text to check for latin characters.
 * @return	boolean		Result of check.
 */
function is_translit($text) {
	return is_string($text) && preg_match('/[a-z]/uiS', $text);
}



/**
 * Convert transliterated text to russian (UTF-8).
 * 
 * Function uses GOST 16876-71 transliteration table.
 * 
 * @param	string	$text	Transliterated russian text.
 * @return	string|string[]		Converted text or translation table if $text was set to NULL.
 */
function translit2rus($text) {
	static $tr	= array(
		// Capital letters
		'A'		=> 'А',		'B'		=> 'Б',		'V'		=> 'В',		'G'		=> 'Г',		'D'		=> 'Д',
		'E'		=> 'Е',		'Jo'	=> 'Ё',		'Zh'	=> 'Ж',		'Z'		=> 'З',		'I'		=> 'И',
		'Jj'	=> 'Й',		'K'		=> 'К',		'L'		=> 'Л',		'M'		=> 'М',		'N'		=> 'Н',
		'O'		=> 'О',		'P'		=> 'П',		'R'		=> 'Р',		'S'		=> 'С',		'T'		=> 'Т',
		'U'		=> 'У',		'F'		=> 'Ф',		'Kh'	=> 'Х',		'C'		=> 'Ц',		'Ch'	=> 'Ч',
		'Sh'	=> 'Ш',		'Shh'	=> 'Щ',		'"'		=> 'Ъ',		'Y'		=> 'Ы',		'\''	=> 'Ь',
		'Eh'	=> 'Э',		'Ju'	=> 'Ю',		'Ja'	=> 'Я',

		// Lowercase letters
		'a'		=> 'а',		'b'		=> 'б',		'v'		=> 'в',		'g'		=> 'г',		'd'		=> 'д',
		'e'		=> 'е',		'jo'	=> 'ё',		'zh'	=> 'ж',		'z'		=> 'з',		'i'		=> 'и',
		'jj'	=> 'й',		'k'		=> 'к',		'l'		=> 'л',		'm'		=> 'м',		'n'		=> 'н',
		'o'		=> 'о',		'p'		=> 'п',		'r'		=> 'р',		's'		=> 'с',		't'		=> 'т',
		'u'		=> 'у',		'f'		=> 'ф',		'kh'	=> 'х',		'c'		=> 'ц',		'ch'	=> 'ч',
		'sh'	=> 'ш',		'shh'	=> 'щ',		'"'		=> 'ъ',		'y'		=> 'ы',		'\''	=> 'ь',
		'eh'	=> 'э',		'ju'	=> 'ю',		'ja'	=> 'я',

		// Additional (non GOST) pairs
		'J'		=> 'Й',		'j'		=> 'й',
		'Yo'	=> 'Ё',		'yo'	=> 'ё',
		'X'		=> 'Кс',	'x'		=> 'кс',
		'H'		=> 'Х',		'h'		=> 'х',
		'Sch'	=> 'Щ',		'sch'	=> 'щ',
		'Yu'	=> 'Ю',		'yu'	=> 'ю',
		'Ya'	=> 'Я',		'ya'	=> 'я',
	);

	if( $text === null)
		return $tr;

	return strtr($text, $tr);
} // function
