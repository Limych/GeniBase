<?php
// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');

/***************************************************************************
 * Функции работы с текстовыми данными
 */



/**
 * Функция форматирования числа и вывода сопровождающего слова в правильном склонении
 */
function format_num($number, $tail_1 = Null, $tail_2 = Null, $tail_5 = Null){
	$formatted = preg_replace('/^(\d)\D(\d{3})$/uS', '$1$2', number_format($number, 0, ',', ' '));

	if(!empty($tail_1)){
		if($tail_2 == Null)	$tail_2 = $tail_1;
		if($tail_5 == Null)	$tail_5 = $tail_2;

		$sng = intval($number) % 10;
		$dec = intval($number) % 100 - $sng;
		$formatted .=
			($dec == 10 ? $tail_5 :
			($sng == 1 ? $tail_1 :
			($sng >= 2 && $sng <= 4 ? $tail_2 : $tail_5)));
	}

	return $formatted;
}	// function format_num



/**
 * Функция вычисления поисковых ключей слов.
 * 
 * @param	string|string[]	$text		Текст, для которого необходимо вычислить поисковые ключи.
 * @param	boolean			$make_regex	Признак, что надо создавать поисковое регулярное выражение, вместо простого текста.
 */
function make_search_keys($text, $make_regex = true){
	if(!is_array($text))
		$text = preg_split('/[^\w\?\*]+/uS', $text, -1, PREG_SPLIT_NO_EMPTY);

	foreach($text as $key => $word){
		$word = array_merge(
			(array) rus_metaphone($word, true),
			(array) rus_metascript(mb_ucfirst($word))
		);
// print "<!-- "; var_export($word); print " -->";
		$word = array_filter(
			$word,
			function ($val){
				return mb_strlen($val) >= 2;
			}
		);
// print "<!-- "; var_export($word); print " -->";
		$text[$key] = (!$make_regex ? implode(' ', $word) : '(' . implode('|', $word) . ')');
	}

	return $text;
}



/**
 * Функция вычисления письменного ключа русского слова.
 * 
 * @param	string|string[]	$word	Исходное слово или массив слов.
 * @return	string|string[]		Письменный ключ слова или набор ключей для набора слов.
 */
function rus_metascript($word){
	// Если вместо строки передан массив, обработать каждое значение в отдельности и вернуть результат в виде массива
	if(is_array($word)){
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
	// Если вместо строки передан массив, обработать каждое значение в отдельности и вернуть результат в виде массива
	if(is_array($word)){
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
	if(empty($word))	return $word;

	// Сжимаем парно идущие одинаковые буквы
	$word = preg_replace("/([^\?])\\1+/uS", '\\1', $word);

	// Сжимаем окончания фамилий, если это необходимо
	if($trim_surname)	$word = preg_replace(array_keys($ends), array_values($ends), $word);

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



/**
 * Функция перевода первой буквы в верхний регистр.
 * 
 * @param	string	$text	Исходный текст.
 * @return	string		Изменённый текст.
 */
function mb_ucfirst($text){
	return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}



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
		if(preg_match('/[а-яА-Я]/uS', $text[$i]))
			$text[$i] = preg_replace('/[ъЪ]$/uS', '', strtr($text[$i], $alf));
	}
	return implode($text);
}



/**
 * Функция расширения поискового запроса по именам
 */
function expand_names($names){
	$names = array_map('mb_strtolower', preg_split('/\s+/uS', strtr($names, array('ё'	=> 'е', 'Ё'	=> 'Е'))));
	$have_name = false;
	foreach($names as $key => $n){
		$exp = array($n);
		if(preg_match('/\b\w+(вна|[вмт]ич|[мт]ична|ин|[ое]в(н?а)?)\b/uS', $n)){
			// Это отчество
			$n2 = preg_replace('/на$/uS', 'а', preg_replace('/ич$/uS', '', $n));
			if($n != $n2)
				$exp[] = $n2;

			$result = db_query('SELECT `expand` FROM `dic_names` WHERE `key` IN ("' . implode('", "', array_map('db_escape', $exp)) . '") AND `is_patronimic` = 1');
			while($tmp = $result->fetch_array(MYSQL_NUM)){
				$exp = array_merge($exp, explode(' ', $tmp[0]));
			}
			$result->free();

			$names[$key] = '[[:blank:]](' . implode('|', array_unique($exp)) . ')[[:>:]]';
		}elseif(!$have_name){
			// Это имя
			$result = db_query('SELECT `expand` FROM `dic_names` WHERE `key` = "' . db_escape($n) . '" AND `is_patronimic` = 0');
			while($tmp = $result->fetch_array(MYSQL_NUM)){
				$exp = array_merge($exp, explode(' ', $tmp[0]));
			}
			$result->free();

			$names[$key] = '^(' . implode('|', array_unique($exp)) . ')[[:>:]]';
			$have_name = true;
		}else{
			// Это непонятно что
			$result = db_query('SELECT `expand` FROM `dic_names` WHERE `key` = "' . db_escape($n) . '"');
			while($tmp = $result->fetch_array(MYSQL_NUM)){
				$exp = array_merge($exp, explode(' ', $tmp[0]));
			}
			$result->free();

			$names[$key] = '[[:<:]](' . implode('|', array_unique($exp)) . ')[[:>:]]';
		}
	}
// print "<!-- "; var_export($names); print " -->";
	return $names;
} // function expand_names

?>