<?php
// Запрещено непосредственное исполнение этого скрипта
if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	die('Direct execution forbidden!');



// Лимит числа строк в одном отчёте
define('Q_LIMIT',	20);



// Базовые настройки системы
mb_internal_encoding('UTF-8');
//
setlocale(LC_ALL, 'ru_RU.utf8');
bindtextdomain(WWI_TXTDOM, dirname(__FILE__) . '/lang');
textdomain(WWI_TXTDOM);
bind_textdomain_codeset(WWI_TXTDOM, 'UTF-8');



/**
 * Функция форматирования числа и вывода сопровождающего слова в правильном склонении
 */
function format_num($number, $tail_1 = Null, $tail_2 = Null, $tail_5 = Null){
	$formatted = preg_replace('/^(\d)\D(\d{3})$/uS', '$1$2', number_format($number, 0, ',', ' '));

// "Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n"
// "%10<=4 && (n%100<10 || n%100>=20)) ? 1 : 2);\n"
	if(!empty($tail_1)){
		if($tail_2 == Null)	$tail_2 = $tail_1;
		if($tail_5 == Null)	$tail_5 = $tail_2;

		$sng = intval($number) % 10;
		$dec = intval($number) % 100;
// var_export(array($sng, $dec));
		$formatted .= ($sng == 1 && $dec != 11) ? $tail_1 :
			($sng >= 2 && $sng <= 4 && ($dec < 10 || $dec > 20)) ? $tail_2 : $tail_5;
	}

	return $formatted;
}



/**
 * Функция вычисления фонетического ключа русского слова
 */
function rus_metaphone($word, $trim_surname = false){
// Второй вариант — пожалуй, лучший.
// Заменяет ЙО, ЙЕ и др.; неплохо оптимизирован.
//
// NB: Оригинальный алгоритм модифицирован для нужд данной поисковой системы

	$alf	= 'ОЕАИУЭЮЯПСТРКЛМНБВГДЖЗЙФХЦЧШЩЁЫ';	// алфавит кроме исключаемых букв
	$cns1	= 'БЗДВГ';	// звонкие согласные
	$cns2	= 'ПСТФК';	// глухие согласные
	$cns3	= 'ПСТКБВГДЖЗФХЦЧШЩ';	// согласные, перед которыми звонкие оглушаются
	$ch		= 'ОЮЕЭЯЁЫ';	// образец гласных
	$ct		= 'АУИИАИИ';	// замена гласных
	$ends	= array(	// Шаблоны для «сжатия» окончания наиболее распространённых фамилий
		'/ОВСК(?:ИЙ|АЯ)$/uS'	=> '0',	// -овский, -овская
		'/ЕВСК(?:ИЙ|АЯ)$/uS'	=> '1',	// -евский, -евская
		'/[ЕИ]?Н(?:ОК|КО(?:В|ВА)?)$/uS'
								=> '2',	// -енко, -енков, -енкова, -енок, -инко, -инков, -инкова, -инок, -нко, -нков, -нкова, -нок
		'/(?:[ИЕ]?Е|О)ВА?$/uS'	=> '3',	// -ов, -ев, -иев, -еев, -ова, -ева, -иева, -еева
		'/ИНА?$/uS'				=> '4',	// -ин, -ина
		'/[УЮ]К$/uS'			=> '5',	// -ук, -юк
		'/[ИЕ]К$/uS'			=> '6',	// -ик, -ек
		'/[ЫИ]Х$/uS'			=> '7',	// -ых, -их
		'/(?:[ЫИ]Й|АЯ)$/uS'		=> '8',	// -ый, -ий, -ая
	);
	$ij		= array(	// Шаблоны для замены двубуквенных конструкций однобуквенными
		'/[ЙИ]О/uS'	=> 'И',
		'/[ЙИ]Е/uS'	=> 'И',
	);
	$callback	= function($match) use ($cns1, $cns2){
		return strtr($match[1], $cns1, $cns2);
	};

	// Переводим в верхний регистр и оставляем только символы из $alf
	$word = mb_strtoupper($word, 'UTF-8');
	$word = preg_replace("/[^$alf]+/usS", '', $word);
	if(empty($word))	return $word;

	// Сжимаем парно идущие одинаковые буквы
	$word = preg_replace("/(.)\\1+/uS", '\\1', $word);

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
	$word = preg_replace("/(.)\\1+/uS", '\\1', $word);

	return $word;
} // function rus_metaphone



/**
 * Соединяемся с СУБД, выбираем базу данных
 */
function db_open($open = true){
	static $link = null;

	if(!empty($link) || !$open)	return $link;

	$link = mysql_connect('u62106.mysql.masterhost.ru', 'u62106', 'comendreoi3i')
		or die('Не удалось соединиться: ' . mysql_error());
	mysql_select_db('u62106_1914') or die('Не удалось выбрать базу данных');
	mysql_set_charset('utf8',$link);
	return $link;
}



/**
 * Отправляем запрос в СУБД
 */
function db_query($query){
	db_open();
	$result = mysql_query($query) or die('Запрос не удался: ' . mysql_error());
	return $result;
}



/**
 * Выполняем обновление вычисляемых данных и закрываем соединение с СУБД
 */
function db_close(){
	// Процедура постепенного обновления вычисляемых данных

	// Генерируем фонетические ключи
	$result = db_query('SELECT DISTINCT surname FROM persons WHERE surname_key = "" ORDER BY list_nr LIMIT 120');
	$tmp = array();
	while($row = mysql_fetch_array($result, MYSQL_NUM)){
		$tmp[] = $row[0];
	}
	mysql_free_result($result);
	shuffle($tmp);
	foreach(array_slice($tmp, 0, 12) as $surname){
		db_query('UPDATE persons SET surname_key = "' . mysql_escape_string(rus_metaphone(strtok($surname, ' '), true)) . '" WHERE surname = "' . mysql_escape_string($surname) . '"');
	}
	
	// Обновляем списки вложенных регионов, если это необходимо
	$result = db_query('SELECT id, parent_id FROM dic_region WHERE region_ids = ""');
	while($region = mysql_fetch_object($result)){
		$result2 = db_query('SELECT region_ids FROM dic_region WHERE parent_id = ' . $region->id);
		$tmp = array();
		while($row = mysql_fetch_array($result2, MYSQL_NUM)){
			$tmp[] = $row[0];
		}
		mysql_free_result($result2);
		array_unshift($tmp, $region->id);
		db_query('UPDATE dic_region SET region_ids = "' . mysql_escape_string(implode(',', $tmp)) . '" WHERE id = ' . $region->id);
		db_query('UPDATE dic_region SET region_ids = "" WHERE id = ' . $region->parent_id);
	}
	mysql_free_result($result);
	
	// Обновляем полные наименования регионов, если это необходимо
	$result = db_query('SELECT id, parent_id, title FROM dic_region WHERE region = ""');
	while($region = mysql_fetch_object($result)){
		$result2 = db_query('SELECT region FROM dic_region WHERE id = ' . $region->parent_id);
		$parent = mysql_fetch_object($result2);
		mysql_free_result($result2);
		$tmp = (empty($parent) ? '' : $parent->region . ', ') . $region->title;
		db_query('UPDATE dic_region SET region = "' . mysql_escape_string($tmp) . '" WHERE id = ' . $region->id);
		db_query('UPDATE dic_region SET region = "" WHERE parent_id = ' . $region->id);
	}
	mysql_free_result($result);
	
	// Обновляем статистику…
	//
	// … по регионам
	$result = db_query('SELECT id, region_ids FROM dic_region ORDER BY RAND() LIMIT 1');
	while($row = mysql_fetch_object($result)){
		if(empty($row->region_ids))	$row->region_ids = $row->id;
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE region_id IN (' . $row->region_ids . ')');
		$cnt = mysql_fetch_array($result2, MYSQL_NUM);
		mysql_free_result($result2);
		db_query('UPDATE dic_region SET region_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	mysql_free_result($result);
	//
	// … по религиям
	$result = db_query('SELECT id FROM dic_religion ORDER BY RAND() LIMIT 1');
	while($row = mysql_fetch_object($result)){
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE religion_id = ' . $row->id);
		$cnt = mysql_fetch_array($result2, MYSQL_NUM);
		mysql_free_result($result2);
		db_query('UPDATE dic_religion SET religion_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	mysql_free_result($result);
	//
	// … по семейным положениям
	$result = db_query('SELECT id FROM dic_marital ORDER BY RAND() LIMIT 1');
	while($row = mysql_fetch_object($result)){
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE marital_id = ' . $row->id);
		$cnt = mysql_fetch_array($result2, MYSQL_NUM);
		mysql_free_result($result2);
		db_query('UPDATE dic_marital SET marital_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	mysql_free_result($result);

	// Закрываем соединение с СУБД
	mysql_close(db_open());
}



/**
 * Вывод начальной части страницы
 */
function html_header(){
	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru"><head>
	<meta charset="UTF-8" />
	
	<link rel="stylesheet" type="text/css" href="styles.css" />
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6/jquery.min.js" type="text/javascript"></script>
</head><body>
<p style="color: red; text-align: center">Система находится в состоянии разработки. Все правки пока делаются «по-живому». Возможна нестабильная работа. Вся информация пока загружается в тестовом режиме: возможны любые неточности и пробелы в предоставляемых результатах поиска — обязательно перепроверяйте информацию по текстовым спискам и/или архивным копиям списков потерь.</p>
<h1>Первая Мировая война, 1914–1918&nbsp;гг.<br/>Алфавитные списки потерь нижних чинов</h1>
<?php
}



/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
<p class="copyright">Обработанные списки размещаются в свободном доступе только для некоммерческих исследований. Использование обработанных списков в коммерческих целях не может быть осуществлено без согласия правообладателя источника информации, СВРТ и участников проекта, осуществлявших обработку и систематизацию списков.</p>
</body></html>
<?php
flush();
}



/**
 * Функция формирования блока ссылок для перемещения между страницами
 */
function paginator($pg, $max_pg){
	$pag = array();
	$url1 = preg_replace('/&pg=\d+/S', '', $_SERVER['REQUEST_URI']);
	$url = $url1 . '&pg=';
	
	if($pg > 1)	$pag[] = '<a href="' . ($pg == 2 ? $url1 : $url . ($pg-1)) . '#report" class="prev">←</a>';
	if($pg > 1)	$pag[] = '<a href="' . $url1 . '#report">1</a>';

	if($pg > 12)	$pag[] = '<span>…</span>';
	if($pg > 11)	$pag[] = '<a href="' . $url . ($pg-10) . '#report">' . ($pg-10) . '</a>';
	
	if($pg == 8)	$pag[] = '<a href="' . $url . '2#report">2</a>';
	elseif($pg > 8)	$pag[] = '<span>…</span>';
	for($i = max($pg-5, 2); $i < $pg; $i++){
		$pag[] = '<a href="' . $url . $i . '#report">' . $i . '</a>';
	}

	$pag[] = '<span class="current">' . $pg . '</span>';

	for($i = $pg+1; $i < min($pg+5, $max_pg); $i++){
		$pag[] = '<a href="' . $url . $i . '#report">' . $i . '</a>';
	}
	if($pg == $max_pg-6)	$pag[] = '<a href="' . $url . ($max_pg-1) . '#report">' . ($max_pg-1) . '</a>';
	elseif($pg < $max_pg-6)	$pag[] = '<span>…</span>';

	if($pg < $max_pg-10)	$pag[] = '<a href="' . $url . ($pg+10) . '#report">' . ($pg+10) . '</a>';
	if($pg < $max_pg-11)	$pag[] = '<span>…</span>';
	
	if($pg < $max_pg)	$pag[] = '<a href="' . $url . $max_pg . '#report">' . $max_pg . '</a>';
	if($pg < $max_pg)	$pag[] = '<a href="' . $url . ($pg+1) . '#report" class="next">→</a>';
	return '<div class="paginator">' . implode(' ', $pag) . '</div>';
}



/**
 * Функция перевода первой буквы в верхний регистр
 */
function mb_ucfirst($text){
	return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}



/**
 * Функция нормирования русского текста
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
	
	return strtr($text, $alf);
}



/**
 * Функция нормирования исходных данных
 */
function prepublish($row, &$have_trouble, &$date_norm){
	static	$str_fields = array('surname', 'name', 'rank', 'religion', 'marital', 'uyezd', 'reason');

	foreach($str_fields as $key){
		// Убираем концевые пробелы и сокращаем множественные пробелы
		$row[$key] = trim(preg_replace('/\s\s+/uS', ' ', $row[$key]));

		// Конвертируем старо-русские буквы в современные
		$row[$key] = fix_russian($row[$key]);

		// Правим регистр букв в текстах
		if(($key == 'surname') || ($key == 'name')){
			// Первые буквы каждого слова в верхний регистр
			$row[$key] = preg_replace_callback('/\b\w+(?:-\w+)\b/uS', function ($matches){
				return mb_ucfirst($matches[0]);
			}, $row[$key]);
		}else{
			// Первую букву в верхний регистр
			$row[$key] = mb_ucfirst($row[$key]);
		}
	}
	
	// Расшифровываем сокращения имён
	// static $names = array(
		// 'Ив.'		=> 'Иван',			'Вас.'		=> 'Василий',		'Мих.'		=> 'Михаил',
		// 'Григ.'		=> 'Григорий',		'Никол.'	=> 'Николай',		'Степ.'		=> 'Степан',
		// 'Андр.'		=> 'Андрей',		'Пав.'		=> 'Павел',			'Дм.'		=> 'Дмитрий',
		// 'Сем.'		=> 'Семён',			'Як.'		=> 'Яков',			'Афан.'		=> 'Афанасий',
		// 'Фед.'		=> 'Фёдор',			'Серг.'		=> 'Сергей',		'Васил.'	=> 'Василий',
		// 'Дмитр.'	=> 'Дмитрий',		'Гавр.'		=> 'Гавриил',		'Макс.'		=> 'Максим',
		// 'Конст.'	=> 'Константин',	'Дан.'		=> 'Даниил',		'Троф.'		=> 'Трофим',
		// 'Георг.'	=> 'Георгий',		'Гр.'		=> 'Григорий',		'Никиф.'	=> 'Никифор',
		// 'Тимоф.'	=> 'Тимофей',		'Тим.'		=> 'Тимофей',		'Емел.'		=> 'Емельян',
		// 'Матв.'		=> 'Матвей',		'Владим.'	=> 'Владимир',		'Ант.'		=> 'Антон',
		// 'Анд.'		=> 'Андрей',		'Филип.'	=> 'Филипп',		'Григор.'	=> 'Григорий',
		// 'Куз.'		=> 'Кузьма',		'Игн.'		=> 'Игнатий',		'Стеф.'		=> 'Стефан',
		// 'Прок.'		=> 'Прокопий',		'Ром.'		=> 'Роман',			'Ник.'		=> 'Николай',
		// 'Зах.'		=> 'Захарий',		'Иос.'		=> 'Иосиф',			'Леонт.'	=> 'Леонтий',
		// 'Герас.'	=> 'Герасим',		'Иллар.'	=> 'Илларион',		'Кирил.'	=> 'Кирилл',
		// 'Митроф.'	=> 'Митрофан',		'Кондр.'	=> 'Кондратий',		'Моис.'		=> 'Моисей',
		// 'Лавр.'		=> 'Лаврентий',		'Констант.'	=> 'Константин',	'Тих.'		=> 'Тихон',
		// 'Пантел.'	=> 'Пантелеймон',	'Евдок.'	=> 'Евдоким',		'Еф.'		=> 'Ефим',
		// 'Харит.'	=> 'Харитон',		'Игнат.'	=> 'Игнатий',		'Прокоф.'	=> 'Прокопий',
		// 'Терент.'	=> 'Терентий',		'Спирид.'	=> 'Спиридон',		'Дав.'		=> 'Давыд Давид',
		// 'Исид.'		=> 'Исидор',		'Викт.'		=> 'Виктор',		'Ег.'		=> '',
		// 'Прох.'		=> 'Прохор',		'Аф.'		=> 'Афанасий',		'Митр.'		=> 'Митрофан',
		// 'Артем.'	=> 'Артемий',		'Федор.'	=> '',				'Станисл.'	=> 'Станислав',
		// 'Порф.'		=> 'Порфирий',		'Стан.'		=> 'Станислав',		'Пант.'		=> 'Пантелеймон',
		// 'Евстаф.'	=> 'Евстафий',		'Спир.'		=> 'Спиридон',		'Абр.'		=> 'Абрам',
		// 'Ефр.'		=> '',				'Прокоп.'	=> 'Прокопий',		'Род.'		=> 'Родион',
		// 'Федос.'	=> '',				'Фил.'		=> 'Филипп',		'Плат.'		=> 'Платон',
		// 'Захар.'	=> 'Захарий',		'Триф.'		=> 'Трифон',		'Ал-др.'	=> 'Александр',
		// 'Афанас.'	=> 'Афанасий',		'Влад.'		=> 'Владимир',		'Елис.'		=> 'Елисей',
		// 'Порфир.'	=> 'Порфирий',		'Онис.'		=> '',				'Савел.'	=> 'Савелий',
		// 'Евтих.'	=> '',
		// 'Арс.'	=> '',
		// 'Корн.'	=> '',
		// 'Феодос.'	=> '',
		// 'Алекс.'	=> '',
		// 'Пет.'	=> '',
		// 'Арсен.'	=> '',
		// 'Дороф.'	=> '',
		// 'Mиx.'	=> '',
		// 'Тер.'	=> '',
		// 'Ден.'	=> '',
		// 'Полик.'	=> '',
		// 'Март.'	=> '',
		// 'Анис.'	=> '',
		// 'Петр.'	=> '',
		// 'Никл.'	=> '',
		// 'Бор.'	=> '',
		// 'Нест.'	=> '',
		// 'Арх.'	=> '',
		// 'Мак.'	=> '',
		// 'Терен.'	=> '',
		// 'Фридр.'	=> '',
		// 'Горд.'	=> '',
		// 'Онуфр.'	=> '',
		// 'Владисл.'	=> '',
		// 'Харл.'	=> '',
		// 'Кир.'	=> '',
		// 'Ерем.'	=> '',
		// 'Анан.'	=> '',
		// 'Никит.'	=> '',
		// 'Евген.'	=> '',
		// 'Викент.'	=> '',
		// 'Зинов.'	=> '',
		// 'Феод.'	=> '',
		// 'Ероф.'	=> '',
		// 'Лаврент.'	=> '',
		// 'Дмит.'	=> '',
		// 'Янк.'	=> '',
		// 'Генр.'	=> '',
		// 'Феокт.'	=> '',
		// 'Харламп.'	=> '',
		// 'Мефод.'	=> '',
		// 'Лаз.'	=> '',
		// 'Филим.'	=> '',
		// 'Тар.'	=> '',
		// 'Авр.'	=> '',
		// 'Иван.'	=> '',
		// 'Макар.'	=> '',
		// 'Дем.'	=> '',
		// 'Дионис.'	=> '',
		// 'Гер.'	=> '',
		// 'Леон.'	=> '',
		// 'Христ.'	=> '',
		// 'Болесл.'	=> '',
		// 'Ерм.'	=> '',
		// 'Пантелейм.'	=> '',
		// 'Антон.'	=> '',
		// 'Купр.'	=> '',
		// 'Казим.'	=> '',
		// 'Авкс.'	=> '',
		// 'Наз.'	=> '',
		// 'Иосиф.'	=> '',
		// 'Констан.'	=> '',
		// 'Демент.'	=> '',
		// 'Менд.'	=> '',
	// );

	// Расшифровываем вероисповедания
	static $religions = array(
		''		=> 0,
		// Православное
		'прав'	=> 1,
		'правосл'	=> 1,
		// Иудейское
		'иуд'	=> 2,
		'иудей'	=> 2,
		// Старообрядческое
		'стар'	=> 3,
		'раск'	=> 3,
		'старовер'	=> 3,
		'староверъ'	=> 3,
		// Магометанское
		'маг'	=> 4,
		'магом'	=> 4,
		'магомет'	=> 4,
		'магометанин'	=> 4,
		'магометанинъ'	=> 4,
		// Евангелическо-лютеранское
		'е лют'	=> 5,
		'ев лют'	=> 5,
		'евг'	=> 5,
		'еванг'	=> 5,
		'лют'	=> 5,
		'лютер'	=> 5,
		'лютеранин'	=> 5,
		'лютеранинъ'	=> 5,
		// Римско-католическое
		'р кат'	=> 8,
		'р катол'	=> 8,
		'кат'	=> 6,
		'катол'	=> 6,
		'католик'	=> 6,
		'католикъ'	=> 6,
		// Армянско-григорианское
		'ар гр'	=> 7,
		'ар григор'	=> 7,
		'григ'	=> 7,
		// Субботники
		'субботн'	=> 8,
		// Караимское
		'караим'	=> 9,
		// Баптистское
		'бабт'	=> 10,
		'бапт'	=> 10,
		// Молоканское
		'мол'	=> 11,
		'молок'	=> 11,
		'молоканин'	=> 11,
		'молоканинъ'	=> 11,
		// Сектантское
		'сект'	=> 12,
		'сектант'	=> 12,
		'сектантъ'	=> 12,
		// Реформаторское
		'реф'	=> 13,
		// Языческое
		'языч'	=> 14,
		'язычн'	=> 14,
		'язычник'	=> 14,
		'язычникъ'	=> 14,
		// Единоверское
		'един'	=> 15,
		'единов'	=> 15,
		// Протестантское
		'протест'	=> 16,
	);
	$tmp = trim(preg_replace('/\W+/uS', ' ', mb_strtolower($row['religion'])));
// if(defined('P_DEBUG'))	var_export($tmp);
	if(isset($religions[$tmp]))
		$row['religion_id'] = $religions[$tmp];

	// Расшифровываем семейные положения
	static $maritals = array(
		''		=> 0,
		// Женатые
		'ж'	=> 1,
		'жен'	=> 1,
		'женат'	=> 1,
		'женатъ'	=> 1,
		// Холостые
		'х'	=> 2,
		'хол'	=> 2,
		'холост'	=> 2,
		'холостъ'	=> 2,
		// Вдовые
		'вд'	=> 3,
		'вдв'	=> 3,
		'вдов'	=> 3,
		'вдовъ'	=> 3,
		'вдовец'	=> 3,
	);
	$tmp = trim(preg_replace('/\W+/uS', ' ', mb_strtolower($row['marital'])));
// if(defined('P_DEBUG'))	var_export($tmp);
	if(isset($maritals[$tmp]))
		$row['marital_id'] = $maritals[$tmp];

	// Расшифровываем источники
	if(empty($row['list_nr'])){
		$row['source_id'] = 0;
	}else{
		$result = db_query('SELECT id FROM dic_source WHERE source LIKE "Именной список №' . $row['list_nr'] . ' %"');
		$res = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		if($res)
			$row['source_id'] = $res[0];
		else{
			db_query('INSERT INTO dic_source (source) VALUES ("Именной список №' . $row['list_nr'] . ' убитым, раненым и без вести пропавшим нижним чинам.")');
			$row['source_id'] = mysql_insert_id();
		}
	}
	
	// Уточняем региональную привязку
	if(!empty($row['uyezd'])){
		$result = db_query('SELECT id FROM dic_region WHERE parent_id = ' . $row['region_id'] . ' AND title LIKE "' . mysql_escape_string($row['uyezd']) . ' %"');
		$res = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		if($res)
			$row['region_id'] = $res[0];
		else{
			db_query('INSERT INTO dic_region (parent_id, title) VALUES (' . $row['region_id'] . ', "' . mysql_escape_string($row['uyezd']) . ' ")');
			$row['region_id'] = mysql_insert_id();
		}
	}

	// Расшифровываем даты
	if(empty($row['date'])){
		$row['date_from']	= '1914-07-28';
		$row['date_to']		= '1918-11-11';
	}else{
		$date_norm = $row['date'];
		// Переводим все буквы в строчные, обрезаем концевые пробелы и корректируем русские буквы
		$date_norm = fix_russian(trim(mb_strtolower($date_norm)));

		// Переводим текстовые названия месяцев в числовые
		static $date_fixes = array(
			'/(?<=^|[\d\W])янв(?=[\d\W]|$)/uS'		=> '.01.',
			'/(?<=^|[\d\W])января(?=[\d\W]|$)/uS'	=> '.01.',
			'/(?<=^|[\d\W])фев(?=[\d\W]|$)/uS'		=> '.02.',
			'/(?<=^|[\d\W])фвр(?=[\d\W]|$)/uS'		=> '.02.',
			'/(?<=^|[\d\W])февраля(?=[\d\W]|$)/uS'	=> '.02.',
			'/(?<=^|[\d\W])мрт(?=[\d\W]|$)/uS'		=> '.03.',
			'/(?<=^|[\d\W])мар(?=[\d\W]|$)/uS'		=> '.03.',
			'/(?<=^|[\d\W])марта?(?=[\d\W]|$)/uS'	=> '.03.',
			'/(?<=^|[\d\W])апр(?=[\d\W]|$)/uS'		=> '.04.',
			'/(?<=^|[\d\W])апреля(?=[\d\W]|$)/uS'	=> '.04.',
			'/(?<=^|[\d\W])мая(?=[\d\W]|$)/uS'		=> '.05.',
			'/(?<=^|[\d\W])июн(?=[\d\W]|$)/uS'		=> '.06.',
			'/(?<=^|[\d\W])июня(?=[\d\W]|$)/uS'		=> '.06.',
			'/(?<=^|[\d\W])июл(?=[\d\W]|$)/uS'		=> '.07.',
			'/(?<=^|[\d\W])июля(?=[\d\W]|$)/uS'		=> '.07.',
			'/(?<=^|[\d\W])ав(?=[\d\W]|$)/uS'		=> '.08.',
			'/(?<=^|[\d\W])авг(?=[\d\W]|$)/uS'		=> '.08.',
			'/(?<=^|[\d\W])августа?(?=[\d\W]|$)/uS'	=> '.08.',
			'/(?<=^|[\d\W])сен(?=[\d\W]|$)/uS'		=> '.09.',
			'/(?<=^|[\d\W])снт(?=[\d\W]|$)/uS'		=> '.09.',
			'/(?<=^|[\d\W])сент(?=[\d\W]|$)/uS'		=> '.09.',
			'/(?<=^|[\d\W])сентября(?=[\d\W]|$)/uS'	=> '.09.',
			'/(?<=^|[\d\W])окт(?=[\d\W]|$)/uS'		=> '.10.',
			'/(?<=^|[\d\W])октября(?=[\d\W]|$)/uS'	=> '.10.',
			'/(?<=^|[\d\W])нбр(?=[\d\W]|$)/uS'		=> '.11.',
			'/(?<=^|[\d\W])ноя(?=[\d\W]|$)/uS'		=> '.11.',
			'/(?<=^|[\d\W])нояб(?=[\d\W]|$)/uS'		=> '.11.',
			'/(?<=^|[\d\W])ноября(?=[\d\W]|$)/uS'	=> '.11.',
			'/(?<=^|[\d\W])дек(?=[\d\W]|$)/uS'		=> '.12.',
			'/(?<=^|[\d\W])дкб(?=[\d\W]|$)/uS'		=> '.12.',
			'/(?<=^|[\d\W])декабря(?=[\d\W]|$)/uS'	=> '.12.',
		);
		$date_norm = preg_replace(array_keys($date_fixes), array_values($date_fixes), $date_norm);
		
		// Убираем префикс «с »
		$date_norm = preg_replace('/^съ?/uS', '', $date_norm);
		// Заменяем частички « по » и « на » на дефис
		$date_norm = preg_replace('/(?<=[\d\W])(?:по|на)(?=[\d\W])/uS', '-', $date_norm);
		// Заменяем частички « и » и « или » на запятую
		$date_norm = preg_replace('/(?<=[\d\W])и(ли)?(?=[\d\W])/uS', ',', $date_norm);
		// Убираем окончание «г.» или «года»
		$date_norm = preg_replace('/г\w*\.?\s*$/uS', '', $date_norm);

		// Убираем все пробелы
		$date_norm = preg_replace('/\s+/uS', '', $date_norm);
		// Сокращаем несколько точек или дефисов в один
		$date_norm = preg_replace('/([\.\-]){2,}/uS', '\\1', $date_norm);

		// Шаблоны для поиска дат
		static	$reg_date_left	= "(\d\d?)(?:[\.\/](?:(\d\d?)(?:[\.\/]((?:\d\d)?\d\d)?)?)?)?";	// дд[.мм[.[гг]гг]]
		static	$reg_date_right	= "(?:(?:(?:(\d\d?)?[\.\/])?(\d\d?))?[\.\/])?(\d\d\d\d)";	// [[дд.]мм.]гггг

		// Запись «1914/15»
		if($date_norm == '1914/15'){
			$matches = array('', '28', '07', '1914', '31', '12', '1915');

		// Простая запись дд.мм.гггг или мм.гггг или гггг
		}elseif(preg_match("/^$reg_date_left$/uS", $date_norm, $matches)
		|| preg_match("/^$reg_date_right$/uS", $date_norm, $matches)){
			if(empty($matches[3]))	$matches[3] = ($matches[2] >= 7 ? 1914 : 1915);
			$matches[4] = $matches[1];
			$matches[5] = $matches[2];
			$matches[6] = $matches[3];

		// Периодическая запись дд-дд.мм.гггг или дд.мм-дд.мм.гггг или дд.мм.гггг-дд.мм.гггг
		}elseif(preg_match("/^$reg_date_left-$reg_date_left$/uS", $date_norm, $matches)
		|| preg_match("/^$reg_date_left-$reg_date_right$/uS", $date_norm, $matches)){
			if(empty($matches[6]))	$matches[6] = ($matches[5] >= 7 ? 1914 : 1915);
			if(empty($matches[2]))	$matches[2] = $matches[5];
			if(empty($matches[3]))	$matches[3] = $matches[6];

		// Списочная запись дд,дд,дд.мм.гггг
		}elseif(preg_match("/^(\d\d?),(?:\d\d?,)*$reg_date_left$/uS", $date_norm, $matches)
		|| preg_match("/^(\d\d?),(?:\d\d?,)*$reg_date_right$/uS", $date_norm, $matches)){
			if(empty($matches[4]))	$matches[4] = ($matches[3] >= 7 ? 1914 : 1915);
			array_splice($matches, 2, 0, array($matches[3], $matches[4]));
		}

		// Нормализуем данные и формируем новые поля
		if(!empty($matches)){
			static $last_days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

			if($matches[3] < 100)	$matches[3] += 1900;
			if(empty($matches[2]))	$matches[2] = ($matches[3] == 1914 ? '07' : '01');
			if(empty($matches[1]))	$matches[1] = (($matches[3] == 1914) && ($matches[2] == 7) ? '28' : '01');;
			$row['date_from']	= implode('-', array($matches[3], $matches[2], $matches[1]));
			if($matches[6] < 100)	$matches[6] += 1900;
			if(empty($matches[5]))	$matches[5] = ($matches[5] == 1918 ? '11' : '12');
			if(empty($matches[4]))	$matches[4] = (($matches[6] == 1918) && ($matches[5] == 11) ? '11' : $last_days[intval($matches[5])-1]);
			$row['date_to']	= implode('-', array($matches[6], $matches[5], $matches[4]));
			
			if(($row['date_from'] < '1914-07-28') || ($row['date_from'] > '1918-11-11')
			|| ($row['date_to'] < '1914-07-28') || ($row['date_to'] > '1918-11-11')){
				unset($row['date_from']);
				unset($row['date_to']);
			}
		}
// var_export($matches);
	}

	// Собираем данные для занесения в основную таблицу
if(defined('P_DEBUG'))	var_export($row);
	$have_trouble = false;
	$pub = array();
	foreach(explode(' ', 'id surname name rank religion_id marital_id region_id place reason date date_from date_to source_id list_nr list_pg') as $key){
		if(isset($row[$key]))	
			$pub[$key] = $row[$key];
		else
			$have_trouble = true;
	}
	return $pub;
}	// function prepublish



/********************************************************************************
 * Функции работы с языками
 */



/********************************************************************************
 * Абстрактный класс хранения результатов поиска
 */
abstract class ww1_records_set {
	protected	$page;			// Текущая страница результатов

	// Создание экземпляра класса
	function __construct($page){
		$this->page = $page;
	}

	// Вывод результатов поиска в виде html-таблицы
	abstract function show_report();
}



/********************************************************************************
 * Класс хранения результатов поиска по спискам погибших
 */
class ww1_solders_set extends ww1_records_set{
	protected	$records;
	protected	$records_cnt;

	// Создание экземпляра класса и сохранение результатов поиска
	function __construct($page, $sql_result, $records_cnt = NULL){
		parent::__construct($page);
		
		$this->records = array();
		while($row = mysql_fetch_object($sql_result))
			$this->records[] = $row;

		$this->records_cnt = ($records_cnt !== NULL ? $records_cnt : count($this->records));
	}

	// Вывод результатов поиска в виде html-таблицы
	function show_report($brief_fields = NULL, $detailed_fields = array()){
		$max_pg = max(1, ceil($this->records_cnt / Q_LIMIT));
		if($this->page > $max_pg)	$this->page = $max_pg;

		$brief_fields_cnt = count($brief_fields);
?>
<a name="report"></a>
<p class="aligncenter">Всего найдено <?php print format_num($this->records_cnt, ' запись.', ' записи.', ' записей.')?></p>
<?php
		if(false !== ($show_detailed = !empty($detailed_fields))){
?>
<script type="text/javascript">
	$(document).ready(function(){
		$(".report tr.detailed").hide();
		$(".report tr.brief").click(function(){
			$(this).next("tr").toggle();
			$(this).find(".arrow").toggleClass("up");
		});
	});
</script>
<?php
		}	// if($show_detailed)

		// Формируем пагинатор
		$pag = paginator($this->page, $max_pg);
		print $pag;	// Вывод пагинатора
?>
<table class="report"><thead>
	<tr>
		<th>№ <nobr>п/п</nobr></th>
<?php
		foreach(array_values($brief_fields) as $val){
			print "\t\t<th>" . htmlspecialchars($val) . "</th>\n";
		}
		if($show_detailed)
			print "\t\t<th></th>\n";
?>
	</tr>
</thead><tbody>
<?php
		$even = 0;
		$num = ($this->page - 1) * Q_LIMIT;
		foreach($this->records as $row){
			$even = 1-$even;
			print "\t<tr class='brief" . ($even ? ' even' : ' odd') . "'>\n";
			print "\t\t<td class='alignright'>" . (++$num) . "</td>\n";
			foreach(array_keys($brief_fields) as $key){
				print "\t\t<td>" . htmlspecialchars($row->$key) . "</td>\n";
			}
			if($show_detailed){
				print "\t\t<td><div class='arrow'></div></td>\n";
?>
	</tr><tr class='detailed'>
		<td></td>
		<td class='detailed' colspan="<?php print $brief_fields_cnt+1 ?>">
			<table>
<?php
				foreach($detailed_fields as $key => $val){
					$text = htmlspecialchars($row->$key);
					if($key == 'source'){
						if(!empty($row->source_url)){
							$text = '<a href="' . str_replace('{pg}', (int) $row->list_pg + (int) $row->pg_correction, $row->source_url) . '" target="_blank">«' . $text . '»</a>, стр.' . $row->list_pg;
						}else{
							$text = '«' . $text . '», стр.' . $row->list_pg;
						}
					}
					print "\t\t\t\t<tr>\n";
					if($key == 'comments'){
						print "\t\t\t\t\t<td colspan='2' class='comments'>" . $row->$key . "</td>\n";
					}else{
						print "\t\t\t\t\t<th>" . htmlspecialchars($val) . ":</th>\n";
						print "\t\t\t\t\t<td>" . $text . "</td>\n";
					}
					print "\t\t\t\t</tr>\n";
				}
?>
			</table>
		</td>
	</tr>
<?php
			}	// if($show_detailed)
		}	// foreach($this->records)
		if($num == 0):
?>
	<tr>
		<td colspan="<?php print $brief_fields_cnt+2 ?>" style="text-align: center">Ничего не найдено</td>
	</tr>
<?php
		endif;
?>
</tbody></table>
<?php
		print $pag;	// Вывод пагинатора
	}
}



/********************************************************************************
 * Абстрактный класс работы с базой данных
 */
define('Q_SIMPLE',		0);	// Простой режим поиска
define('Q_EXTENDED',	1);	// Расширенный режим поиска
//
abstract class ww1_database {
	protected	$query_mode;	// Режим поиска
	public		$query;			// Набор условий поиска
	protected	$page;			// Текущая страницы результатов
	public		$have_query;	// Признак наличия данных для запроса
	public		$records_cnt;	// Общее число записей в базе

	// Создание экземпляра класса
	function __construct($qmode = Q_SIMPLE){
		$this->query_mode = $qmode;
		$this->query = array();
		$this->have_query = false;
		$this->records_cnt = 0;

		$this->page = intval($_REQUEST['pg']);
		if($this->page < 1)	$this->page = 1;
	}

	// Генерация html-формы поиска
	abstract function search_form();

	// Осуществление поиска и генерация класса результатов поиска
	abstract function do_search();
}



/********************************************************************************
 * Класс работы с базой данных нижних чинов
 */
class ww1_database_solders extends ww1_database {
	// Создание экземпляра класса
	function __construct($qmode = Q_SIMPLE){
		parent::__construct($qmode);

		if($qmode == Q_SIMPLE){
			// Простой режим поиска ******************************************
			foreach(explode(' ', 'surname name place') as $key){
				$this->query[$key] = $_REQUEST[$key];
				$this->have_query |= !empty($this->query[$key]);
			}
		}else{
			// Расширенный режим поиска **************************************
			$dics = explode(' ', 'rank religion marital reason');
			foreach(explode(' ', 'surname name rank religion marital region place reason date_from date_to list_nr list_pg') as $key){
				$this->query[$key] = $_REQUEST[$key];
				if(in_array($key, $dics) && !is_array($this->query[$key]))
					$this->query[$key] = array();
				$this->have_query |= !empty($this->query[$key]);
			}
		}	// if

		// Считаем, сколько всего записей в базе
		$query = 'SELECT COUNT(*) FROM persons';
		$result = db_query($query);
		$cnt = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		$this->records_cnt = intval($cnt[0]);
	}

	// Генерация html-формы поиска
	function search_form(){
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Выводим html-поля
			$fields = array(
				'surname'	=> 'Фамилия',
				'name'		=> 'Имя-отчество',
				'place'		=> 'Место жительства',
			);
			foreach($this->query as $key => $val){
				print "\t<div class='field'><label for='q_$key'>${fields[$key]}:</label> <input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($val) . "'></div>\n";
			}
		}else{
			// Расширенный режим поиска **************************************

			$dics = array();

			// Получаем список всех вариантов значений воиских званий
			$dics['rank'] = array();
			$result = db_query('SELECT DISTINCT rank FROM persons WHERE rank != "" ORDER BY rank');
			while($row = mysql_fetch_array($result, MYSQL_NUM)){
				$dics['rank'][$row[0]] = $row[0];
			}
			mysql_free_result($result);

			// Получаем список всех вариантов значений вероисповеданий
			$dics['religion'] = array();
			$result = db_query('SELECT * FROM dic_religion ORDER BY religion');
			while($row = mysql_fetch_object($result)){
				$dics['religion'][$row->id] = $row->religion;
			}
			mysql_free_result($result);

			// Получаем список всех вариантов значений семейных положений
			$dics['marital'] = array();
			$result = db_query('SELECT * FROM dic_marital ORDER BY marital');
			while($row = mysql_fetch_object($result)){
				$dics['marital'][$row->id] = $row->marital;
			}
			mysql_free_result($result);

			// Получаем список всех вариантов значений причин выбытия
			$dics['reason'] = array();
			$result = db_query('SELECT DISTINCT reason FROM persons WHERE reason != "" ORDER BY reason');
			while($row = mysql_fetch_array($result, MYSQL_NUM)){
				$dics['reason'][$row[0]] = $row[0];
			}
			mysql_free_result($result);

			// Выводим html-поля
			$fields = array(
				'surname'	=> 'Фамилия',
				'name'		=> 'Имя-отчество',
				'rank'		=> 'Воинское звание',
				'religion'	=> 'Вероисповедание',
				'marital'	=> 'Семейное положение',
				'region'	=> 'Губерния, уезд, волость',
				'place'		=> 'Волость/Нас.пункт',
				'reason'	=> 'Причина выбытия',
				'date'		=> 'Дата выбытия',
				'list_nr'	=> 'Номер списка',
				'list_pg'	=> 'Страница списка',
			);
			foreach($fields as $key => $val){
				switch($key){
				case 'rank':
				case 'religion':
				case 'marital':
				case 'reason':
					// Списковые поля
					print "\t<div class='field'><label for='q_$key'>$val:</label> <select id='q_$key' name='${key}[]' multiple='multiple' size='5'>\n";
					foreach($dics[$key] as $k => $v){
						print "\t\t<option value='" . htmlspecialchars($k) . "'" . (is_array($this->query[$key]) && in_array($k, $this->query[$key]) ? " selected='selected'" : '') . ">" . htmlspecialchars($v) . "</option>\n";
					}
					print "</select></div>\n";
					break;
				case 'date':
					// Поля дат
					print "\t<div class='field'><label for='q_$key'>$val:</label> c&nbsp;<input type='date' id='q_$key' name='date_from' value='" . htmlspecialchars($this->query['date_from']) . "' min='1914-07-28' max='1918-11-11'> по&nbsp;<input type='date' name='date_to' value='" . htmlspecialchars($this->query['date_to']) . "' min='1914-07-28' max='1918-11-11'></div>\n";
					break;
				default:
					// Текстовые поля
					print "\t<div class='field'><label for='q_$key'>$val:</label> <input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "'></div>\n";
					break;
				}
			}
		}	// if
	}

	// Осуществление поиска и генерация класса результатов поиска
	function do_search(){
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Формируем основной поисковый запрос в БД
			$w = array();
			foreach($this->query as $key => $val){
				if(empty($val))	continue;
				if(false != ($reg = preg_match('/[_%]/uS', $val)))
					$tmp = $key . ' LIKE "' . mysql_escape_string($val) . '"';
				else{
					$tmp = 'MATCH (' . $key . ') AGAINST ("' . mysql_escape_string($val) . '" IN BOOLEAN MODE)';
					if($key == 'surname')
						$tmp = '(' . $tmp . ' OR surname_key = "' . mysql_escape_string(rus_metaphone($val, true)) . '")';
				}
				if($key == 'place'){
					$tmp = '(' . $tmp . ' OR ';
					if($reg)
						$tmp .= 'region LIKE "' . mysql_escape_string($val) . '"';
					else
						$tmp .= 'MATCH (region) AGAINST ("' . mysql_escape_string($val) . '" IN BOOLEAN MODE)';
					$tmp .= ')';
				}
				$w[] = $tmp;
			}
			$w = implode(' AND ', $w);
		}else{
			// Расширенный режим поиска **************************************

			// Формируем основной поисковый запрос в БД
			$w = array();
			$nums = explode(' ', 'religion marital list_nr list_pg');	// Список полей, в которых передаются числовые данные
			$ids = explode(' ', 'religion marital');	// Список полей, в которых передаются идентификаторы
			foreach($this->query as $key=>$val){
				if(empty($val))	continue;
				if($key == 'date_from'){
					// Дата с
					$tmp = 'date_to >= STR_TO_DATE("' . mysql_escape_string($val) . '", "%Y-%m-%d")';
				}elseif($key == 'date_to'){
					// Дата по
					$tmp = 'date_from <= STR_TO_DATE("' . mysql_escape_string($val) . '", "%Y-%m-%d")';
				}elseif(!in_array($key, $nums)){
					// Текстовые данные
					if(!is_array($val))
						$tmp = $key . ' LIKE "' . mysql_escape_string($val) . '"';
					else{
						$tmp = array();
						foreach($val as $v)
							$tmp[] = $key . ' = "' . mysql_escape_string($v) . '"';
						$tmp = '(' . implode(' OR ', $tmp) . ')';
					}
				}else{
					// Числовые данные
					if(in_array($key, $ids))
						$key .= '_id';	// Модифицируем название поля
					if(!is_array($val))
						$val = preg_split('/\D+/uS', trim($val));
					$val = implode(', ', array_map('intval', $val));
					if(false === strchr($val, ','))
						$tmp = $key . ' = ' . intval($val);	// Одиночное значение
					else
						$tmp = $key . ' IN (' . $val . ')';	// Множественное значение
				}
				$w[] = $tmp;
			}
			$w = implode(' AND ', $w);
		}

		// Считаем, сколько результатов найдено
		$query = 'SELECT COUNT(*) FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id WHERE ' . $w;
		$result = db_query($query);
		$cnt = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		
		// Запрашиваем текущую порцию результатов для вывода в таблицу
		$query = 'SELECT * FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id LEFT JOIN dic_religion ON dic_religion.id=persons.religion_id LEFT JOIN dic_marital ON dic_marital.id=persons.marital_id LEFT JOIN dic_source ON dic_source.id=persons.source_id WHERE ' . $w . ' ORDER BY surname, name LIMIT ' . (($this->page - 1) * Q_LIMIT) . ', ' . Q_LIMIT;
		$result = db_query($query);
		$report = new ww1_solders_set($this->page, $result, $cnt[0]);
		mysql_free_result($result);

		return $report;
	}
}

?>