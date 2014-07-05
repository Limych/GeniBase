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
	static $db = null;

	if(!empty($db) || !$open)	return $db;

	$db = new MySQLi('u62106.mysql.masterhost.ru', 'u62106', 'comendreoi3i')
		or die('Не удалось соединиться: ' . $db->error);
	$db->select_db('u62106_1914') or die('Не удалось выбрать базу данных');
	$db->set_charset('utf8');
	return $db;
}



/**
 * Отправляем запрос в СУБД
 */
function db_query($query){
	$db = db_open();
	$result = $db->query($query) or die('Запрос не удался: ' . $db->error);
	return $result;
}



/**
 * Выполняем обновление вычисляемых данных и закрываем соединение с СУБД
 */
function db_close(){
	$db = db_open();
	// Процедура постепенного обновления вычисляемых данных

	// Генерируем фонетические ключи
	$result = db_query('SELECT DISTINCT surname FROM persons WHERE surname_key = "" ORDER BY list_nr LIMIT 120');
	$tmp = array();
	while($row = $result->fetch_array(MYSQL_NUM)){
		$tmp[] = $row[0];
	}
	$result->free();
	shuffle($tmp);
	foreach(array_slice($tmp, 0, 12) as $surname){
		db_query('UPDATE persons SET surname_key = "' . $db->escape_string(rus_metaphone(strtok($surname, ' '), true)) . '" WHERE surname = "' . $db->escape_string($surname) . '"');
	}
	
	// Обновляем списки вложенных регионов, если это необходимо
	$result = db_query('SELECT id, parent_id FROM dic_region WHERE region_ids = ""');
	while($region = $result->fetch_object()){
		$result2 = db_query('SELECT region_ids FROM dic_region WHERE parent_id = ' . $region->id);
		$tmp = array();
		while($row = $result2->fetch_array(MYSQL_NUM)){
			$tmp[] = $row[0];
		}
		$result2->free();
		array_unshift($tmp, $region->id);
		db_query('UPDATE dic_region SET region_ids = "' . $db->escape_string(implode(',', $tmp)) . '" WHERE id = ' . $region->id);
		db_query('UPDATE dic_region SET region_ids = "" WHERE id = ' . $region->parent_id);
	}
	$result->free();
	
	// Обновляем полные наименования регионов, если это необходимо
	$result = db_query('SELECT id, parent_id, title FROM dic_region WHERE region = ""');
	while($region = $result->fetch_object()){
		$result2 = db_query('SELECT region FROM dic_region WHERE id = ' . $region->parent_id);
		$parent = $result2->fetch_object();
		$result2->free();
		$tmp = (empty($parent) ? '' : $parent->region . ', ') . $region->title;
		db_query('UPDATE dic_region SET region = "' . $db->escape_string($tmp) . '" WHERE id = ' . $region->id);
		db_query('UPDATE dic_region SET region = "" WHERE parent_id = ' . $region->id);
	}
	$result->free();
	
	// Обновляем статистику…
	//
	// … по регионам
	$result = db_query('SELECT id, region_ids FROM dic_region ORDER BY RAND() LIMIT 1');
	while($row = $result->fetch_object()){
		if(empty($row->region_ids))	$row->region_ids = $row->id;
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE region_id IN (' . $row->region_ids . ')');
		$cnt = $result2->fetch_array(MYSQL_NUM);
		$result2->free();
		db_query('UPDATE dic_region SET region_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	$result->free();
	//
	// … по религиям
	$result = db_query('SELECT id FROM dic_religion ORDER BY RAND() LIMIT 1');
	while($row = $result->fetch_object()){
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE religion_id = ' . $row->id);
		$cnt = $result2->fetch_array(MYSQL_NUM);
		$result2->free();
		db_query('UPDATE dic_religion SET religion_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	$result->free();
	//
	// … по семейным положениям
	$result = db_query('SELECT id FROM dic_marital ORDER BY RAND() LIMIT 1');
	while($row = $result->fetch_object()){
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE marital_id = ' . $row->id);
		$cnt = $result2->fetch_array(MYSQL_NUM);
		$result2->free();
		db_query('UPDATE dic_marital SET marital_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	$result->free();
	//
	// … по причинам выбытия
	$result = db_query('SELECT id FROM dic_reason ORDER BY RAND() LIMIT 1');
	while($row = $result->fetch_object()){
		$result2 = db_query('SELECT COUNT(*) FROM persons WHERE reason_id = ' . $row->id);
		$cnt = $result2->fetch_array(MYSQL_NUM);
		$result2->free();
		db_query('UPDATE dic_reason SET reason_cnt = ' . intval($cnt[0]) . ' WHERE id = ' . $row->id);
	}
	$result->free();

	// Закрываем соединение с СУБД
	$db->close();
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
	
	<link rel="stylesheet" type="text/css" href="/styles.css" />
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
 * Функция расширения поискового запроса по именам
 */
function extend_names($names){
	static $name_reductions = array(
		// ''		=> 'анис',
		// ''		=> 'арх',
		// ''		=> 'болесл',
		// ''		=> 'бор',
		// ''		=> 'гер',
		// ''		=> 'горд',
		// ''		=> 'дан',
		'дементий'		=> 'дем',
		'денис'		=> 'ден',
		'евдоким'		=> 'евд',
		'еремей'		=> 'ерем',
		'ефрем'		=> 'ефр',
		// ''		=> 'каз',
		// ''		=> 'кир',
		// ''		=> 'леон',
		// ''		=> 'мир',
		// ''		=> 'наз',
		// ''		=> 'нест',
		// ''		=> 'никл',
		// ''		=> 'онис',
		// ''		=> 'ос',
		// ''		=> 'род',
		// ''		=> 'стеф',
		// ''		=> 'тер',
		// ''		=> 'федос',
		// ''		=> 'феодос',
		// ''		=> 'янк',
		'абрам'			=> 'абр',					'александр'		=> 'ал-др ал-р',		'алексей'		=> 'ал-ей ал-сей алекс',
		'андрей'		=> 'анд андр',				'антон'			=> 'ант',				'арсений'		=> 'арс арсен',
		'артемий'		=> 'арт артем',				'афанасий'		=> 'аф афан афанас',	'бронислав'		=> 'брон бронисл',
		'валентин'		=> 'валент',				'василий'		=> 'вас васил',			'вацлав'		=> 'вацл',
		'викентий'		=> 'викент',				'виктор'		=> 'викт',				'вильгельм'		=> 'вильг',
		'владимир'		=> 'влад владим вл-р',		'владислав'		=> 'владисл',			'гавриил'		=> 'гавр',
		'генрих'		=> 'генр',					'георгий'		=> 'георг',				'герасим'		=> 'герас',
		'григорий'		=> 'гр григ григор',		'густав'		=> 'густ',				'давид'			=> 'дав',
		'давыд'			=> 'дав',					'дмитрий'		=> 'дм дмитр дмит',		'дорофей'		=> 'дороф',
		'евгений'		=> 'евг евген',				'евдоким'		=> 'евдок',				'евстафий'		=> 'евстаф',
		'евтихий'		=> 'евтих',					'егор'			=> 'ег',				'емельян'		=> 'емел',
		'ермолай'		=> 'ермол',					'ефим'			=> 'еф',				'захарий'		=> 'зах захар',
		'зиновий'		=> 'зинов',					'иван'			=> 'ив',				'игнатий'		=> 'игн игнат',
		'илларион'		=> 'иллар',					'иосиф'			=> 'иос',				'исидор'		=> 'исид',
		'казимир'		=> 'казим',					'кирилл'		=> 'кирил',				'кондратий'		=> 'кондр',
		'константин'	=> 'констан конст констант',	'корней'		=> 'корн',			'кузьма'		=> 'куз',
		'куприян'		=> 'купр',					'лаврентий'		=> 'лавр',				'леонтий'		=> 'леонт',
		'людвиг'		=> 'людв',					'макар'			=> 'мак',				'максим'		=> 'макс',
		'мартын'		=> 'март',					'матвей'		=> 'матв',				'мефодий'		=> 'мефод',
		'митрофан'		=> 'митр митроф',			'михаил'		=> 'мих',				'моисей'		=> 'моис',
		'никанор'		=> 'никан',					'никита'		=> 'ник',				'никифор'		=> 'никиф',
		'николай'		=> 'ник никол',				'онуфрий'		=> 'онуфр',				'павел'			=> 'пав',
		'пантелеймон'	=> 'пант пантел',			'пётр'			=> 'пет',				'платон'		=> 'плат',
		'поликарп'		=> 'полик',					'порфирий'		=> 'порф порфир',		'прокопий'		=> 'прок прокоп прокоф',
		'прокофий'		=> 'прок прокоп прокоф',	'прохор'		=> 'прох',				'роман'			=> 'ром',
		'савелий'		=> 'савел',					'семён'			=> 'сем',				'сергей'		=> 'серг',
		'сильвестр'		=> 'сильв',					'спиридон'		=> 'спир спирид',		'станислав'		=> 'стан станисл',
		'степан'		=> 'степ',					'терентий'		=> 'терент',			'тимофей'		=> 'тим тимоф',
		'тихон'			=> 'тих',					'трифон'		=> 'триф',				'трофим'		=> 'троф',
		'фёдор'			=> 'фед',					'федот'			=> 'фед',				'филимон'		=> 'фил филим',
		'филипп'		=> 'фил филип',				'фридрих'		=> 'фридр',				'харитон'		=> 'харит',
		'яков'			=> 'як',
	);
	static $patronimic_reductions = array(
		'абрамов'		=> 'абр абрам',
		'адамов'		=> 'адам',
		'акимов'		=> 'аким',
		'александров'	=> 'ал-др',
		'алексеев'		=> 'ал ал-ев алекс',
		'андреев'		=> 'анд андр',
		'антонов'		=> 'ант антон',
		'артемов'		=> 'артем',
		'архипов'		=> 'архип',
		'афанасьев'		=> 'аф афан',
		'борисов'		=> 'борис',
		'вадимов'		=> 'владим',
		'васильев'		=> 'вас васил',
		'викентьев'		=> 'викент',
		'викторов'		=> 'викт',
		'владимиров'		=> 'влад',
		'власов'		=> 'влас',
		'гаврилов'		=> 'гавр',
		'георгиев'	=> 'георг',
		'герасимов'		=> 'герас',
		'гордеев'		=> 'горд',
		'григорьев'		=> 'гр григ григор',
		'давидов'		=> 'дав давид',
		'давыдов'		=> 'дав',
		'данилов'		=> 'дан данил',
		'демидов'		=> 'дем демид',
		'демьянов'		=> 'дем демьян',
		'денисов'		=> 'денис',
		'дмитриев'		=> 'дм дмитр',
		'евгеньев'		=> 'ег',
		'евгениев'		=> 'ег',
		'евдокимов'		=> 'евдок',
		'евстафиев'		=> 'евстаф евстаф',
		'егоров'		=> 'егор',
		'емельянов'		=> 'емел',
		'еремеев'		=> 'ерем',
		'ефимов'		=> 'еф ефим',
		'ефремов'		=> 'ефр ефрем',
		'захаров'		=> 'зах захар',
		'иванов'		=> 'ив иван',
		'игнатьев'		=> 'игн игнат',
		'илларионов'		=> 'иллар',
		'ильин'		=> 'ил ильич',
		'ильич'		=> 'ил ильин',
		'иосифов'		=> 'иос иосиф',
		'исидоров'		=> 'исид',
		'казимиров'		=> 'казим',
		'карлов'		=> 'карл',
		'карпов'		=> 'карп',
		'кириллов'		=> 'кир кирил',
		'климов'		=> 'клим',
		'кондратьев'		=> 'кондр',
		'константинов'	=> 'конст',
		'корнеев'		=> 'корн',
		'кузьмин'		=> 'куз кузьм',
		'кузьмич'		=> 'куз кузьм',
		'лаврентиев'		=> 'лавр лавр',
		'леонов'		=> 'леон',
		'леонтьев'		=> 'леон леонт',
		'лукин'		=> 'лук лук',
		'лукьянов'		=> 'лукьян',
		'людвигов'		=> 'людв',
		'макаров'		=> 'макар',
		'максимов'		=> 'макс максим',
		'марков'		=> 'марк',
		'мартынов'		=> 'март',
		'матвеев'		=> 'матв',
		'миронов'		=> 'мирон',
		'митрофанов'		=> 'митр митроф',
		'михайлов'		=> 'мих михайл',
		'моисеев'		=> 'моис',
		'назаров'		=> 'назар',
		'наумов'		=> 'наум',
		'никит'		=> 'ник никит',
		'никифоров'	=> 'ник никиф',
		'николаев'		=> 'ник никол',
		'онисимов'		=> 'онис',
		'онуфриев'		=> 'онуфр',
		'осипов'		=> 'ос осип',
		'павлов'		=> 'пав павл',
		'пантелеев'		=> 'пант пантел',
		'пантелеймонов'		=> 'пант пантел',
		'петров'		=> 'петр',
		'платонов'		=> 'плат',
		'прокофьев'		=> 'прокоф',
		'прохоров'		=> 'прох',
		'романов'		=> 'ром роман',
		'савелиев'		=> 'сав савел',
		'савельев'		=> 'сав савел',
		'семёнов'		=> 'сем семен',
		'сергеев'		=> 'серг',
		'сидоров'		=> 'сидор',
		'спиридонов'		=> 'спир',
		'станиславов'		=> 'стан станисл',
		'степанов'		=> 'степ степан',
		'стефанов'		=> 'стеф',
		'тарасов'		=> 'тарас',
		'терентьев'		=> 'терент',
		'тимофеев'		=> 'тим тимоф',
		'тихонов'		=> 'тих тихон',
		'трофимов'		=> 'троф',
		'устинов'		=> 'устин',
		'фёдоров'		=> 'фед федор',
		'федосов'		=> 'федос',
		'федотов'		=> 'фед федот',
		'филимонов'		=> 'фил',
		'филиппов'		=> 'фил филипп филип',
		'фомин'		=> 'фом фомич',
		'фомич'		=> 'фом фомин',
		'францев'		=> 'франц',
		'фролов'		=> 'фрол',
		'харитонов'		=> 'харит',
		'яковлев'		=> 'як яков яковл',
		// ''		=> 'войц',
		// ''		=> 'лейб',
		// ''		=> 'прок',
		// ''		=> 'хаим',
		// ''		=> 'арх',
		// ''		=> 'тер',
		// ''		=> 'ян',
		// ''		=> 'исаак',
		// ''		=> 'пет',
		// ''		=> 'спирид',
		// ''		=> 'парф',
		// ''		=> 'гаврил',
		// ''		=> 'род',
		// ''		=> 'анис',
		// ''		=> 'евд',
		// ''		=> 'каз',
		// ''		=> 'юзеф',
		// ''		=> 'родион',
		// ''		=> 'лазар',
		// ''		=> 'ст',
		// ''		=> 'ал-дров',
		// ''		=> 'елис',
		// ''		=> 'сильв',
		// ''		=> 'сид',
		// ''		=> 'янкел',
		// ''		=> 'кирилл',
		// ''		=> 'гер',
		// ''		=> 'трофим',
		// ''		=> 'ермол',
		// ''		=> 'анан',
		// ''		=> 'полик',
		// ''		=> 'август',
		// ''		=> 'вик',
		// ''		=> 'савв',
		// ''		=> 'никл',
		// ''		=> 'мартын',
		// ''		=> 'ал-еев',
		// ''		=> 'самойл',
		// ''		=> 'викен',
		// ''		=> 'мир',
		// ''		=> 'фридр',
		// ''		=> 'яким',
		// ''		=> 'афанас',
		// ''		=> 'купр',
		// ''		=> 'мак',
		// ''		=> 'герш',
		// ''		=> 'арт',
		// ''		=> 'филим',
		// ''		=> 'авкс',
		// ''		=> 'тит',
		// ''		=> 'павлов',
		// ''		=> 'берк',
		// ''		=> 'дороф',
		// ''		=> 'янк',
		// ''		=> 'бор',
		// ''		=> 'зин',
		// ''		=> 'нест',
		// ''		=> 'феликс',
		// ''		=> 'ал-др',
		// ''		=> 'евст',
		// ''		=> 'андреев',
		// ''		=> 'триф',
		// ''		=> 'кон',
		// ''		=> 'евс',
		// ''		=> 'никон',
		// ''		=> 'фр',
		// ''		=> 'нестер',
		// ''		=> 'ероф',
		// ''		=> 'андриан',
		// ''		=> 'демент',
		// ''		=> 'мошк',
		// ''		=> 'евтих',
		// ''		=> 'самуил',
		// ''		=> 'ден',
		// ''		=> 'христ',
		// ''		=> 'арс',
		// ''		=> 'дмит',
		// ''		=> 'ал-дров',
		// ''		=> 'клем',
		// ''		=> 'зинов',
		// ''		=> 'порф',
		// ''		=> 'ульян',
		// ''		=> 'авг',
		// ''		=> 'федоров',
		// ''		=> 'прокоп',
		// ''		=> 'вильг',
		// ''		=> 'арсен',
		// ''		=> 'ал-еев',
		// ''		=> 'алексеев',
		// ''		=> 'ицк',
		// ''		=> 'алек',
		// ''		=> 'конон',
		// ''		=> 'агаф',
		// ''		=> 'валент',
		// ''		=> 'шлем',
		// ''		=> 'шмул',
		// ''		=> 'венед',
		// ''		=> 'уст',
		// ''		=> 'наз',
		// ''		=> 'хар',
		// ''		=> 'севаст',
		// ''		=> 'мефод',
		// ''		=> 'авраам',
		// ''		=> 'констант',
		// ''		=> 'терен',
		// ''		=> 'варф',
		// ''		=> 'евген',
		// ''		=> 'потап',
		// ''		=> 'васильев',
		// ''		=> 'кален',
		// ''		=> 'лаврент',
		// ''		=> 'якуб',
		// ''		=> 'тар',
		// ''		=> 'мовш',
		// ''		=> 'томаш',
		// ''		=> 'авксент',
		// ''		=> 'арон',
		// ''		=> 'давыд',
		// ''		=> 'готлиб',
		// ''		=> 'авер',
		// ''		=> 'кондрат',
		// ''		=> 'феодос',
		// ''		=> 'николаев',
		// ''		=> 'янов',
		// ''		=> 'сафр',
		// ''		=> 'варфол',
		// ''		=> 'виктор',
		// ''		=> 'людвиг',
		// ''		=> 'михайлов',
		// ''		=> 'войцех',
		// ''		=> 'емельян',
		// ''		=> 'сам',
		// ''		=> 'евсеев',
		// ''		=> 'гершк',
		// ''		=> 'меер',
		// ''		=> 'калин',
		// ''		=> 'панф',
		// ''		=> 'мат',
		// ''		=> 'калистр',
		// ''		=> 'евламп',
		// ''		=> 'исак',
	);

	$names = array_map('mb_strtolower', preg_split('/\s+/uS', $names));
	$have_name = false;
	foreach($names as $key => $n){
		$ext = array($n);
		if(preg_match('/\b\w+(вна|[вмт]ич|[мт]ична|ин|[ое]в(н?а)?)\b/uS', $n)){
			// Это отчество
			$n2 = preg_replace('/на$/uS', 'а', preg_replace('/ич$/uS', '', $n));
			if($n != $n2)
				$ext[] = $n2;
			if(isset($patronimic_reductions[$n]))
				$ext = array_merge($ext, explode(' ', $patronimic_reductions[$n]));
			if(isset($patronimic_reductions[$n2]))
				$ext = array_merge($ext, explode(' ', $patronimic_reductions[$n2]));

			$names[$key] = '[[:blank:]](' . implode('|', array_unique($ext)) . ')[[:>:]]';
		}elseif($have_name){
			// Это непонятно что
			if(isset($name_reductions[$n]))
				$ext = array_merge($ext, explode(' ', $name_reductions[$n]));
			if(isset($patronimic_reductions[$n]))
				$ext = array_merge($ext, explode(' ', $patronimic_reductions[$n]));

			$names[$key] = '[[:blank:]](' . implode('|', array_unique($ext)) . ')[[:>:]]';
		}else{
			// Это имя
			if(isset($name_reductions[$n]))
				$ext = array_merge($ext, explode(' ', $name_reductions[$n]));

			$names[$key] = '^(' . implode('|', array_unique($ext)) . ')[[:>:]]';
			$have_name = true;
		}
	}
	return $names;
} // function extend_name



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
		while($row = $sql_result->fetch_object())
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
	protected	$page;			// Текущая страница результатов
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
	var	$surname_ext	= false;
	var	$name_ext		= false;

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
			$this->surname_ext	= isset($_REQUEST['surname_ext']);
			$this->name_ext		= isset($_REQUEST['name_ext']);
		}	// if

		// Считаем, сколько всего записей в базе
		$query = 'SELECT COUNT(*) FROM persons';
		$result = db_query($query);
		$cnt = $result->fetch_array(MYSQL_NUM);
		$result->free();
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
			while($row = $result->fetch_array(MYSQL_NUM)){
				$dics['rank'][$row[0]] = $row[0];
			}
			$result->free();

			// Получаем список всех вариантов значений вероисповеданий
			$dics['religion'] = array();
			$result = db_query('SELECT * FROM dic_religion WHERE religion_cnt != 0 ORDER BY religion');
			while($row = $result->fetch_object()){
				$dics['religion'][$row->id] = $row->religion;
			}
			$result->free();

			// Получаем список всех вариантов значений семейных положений
			$dics['marital'] = array();
			$result = db_query('SELECT * FROM dic_marital WHERE marital_cnt != 0 ORDER BY marital');
			while($row = $result->fetch_object()){
				$dics['marital'][$row->id] = $row->marital;
			}
			$result->free();

			// Получаем список всех вариантов значений причин выбытия
			$dics['reason'] = array();
			$result = db_query('SELECT * FROM dic_reason WHERE reason_cnt != 0 ORDER BY reason');
			while($row = $result->fetch_object()){
				$dics['reason'][$row->id] = $row->reason;
			}
			$result->free();

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
				case 'surname':
					// Текстовые поля
					print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /><label><input type='checkbox' name='surname_ext' value='1'" . (!isset($_GET['surname_ext']) ? "" : " checked='checked'") . " />&nbsp;фонетический поиск по&nbsp;фамилиям</label></div></div>\n";
					break;
				case 'name':
					// Текстовые поля
					print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /><label><input type='checkbox' name='name_ext' value='1'" . (!isset($_GET['name_ext']) ? "" : " checked='checked'") . " />&nbsp;автоматическое расширение поиска</label></div></div>\n";
					break;
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
					print "\t<div class='field'><label for='q_$key'>$val:</label> c&nbsp;<input type='date' id='q_$key' name='date_from' value='" . htmlspecialchars($this->query['date_from']) . "' min='1914-07-28' max='1918-11-11' /> по&nbsp;<input type='date' name='date_to' value='" . htmlspecialchars($this->query['date_to']) . "' min='1914-07-28' max='1918-11-11' /></div>\n";
					break;
				default:
					// Текстовые поля
					print "\t<div class='field'><label for='q_$key'>$val:</label> <input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /></div>\n";
					break;
				}
			}
		}	// if
	}

	// Осуществление поиска и генерация класса результатов поиска
	function do_search(){
		$db = db_open();
		
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Формируем основной поисковый запрос в БД
			$w = array();
			foreach($this->query as $key => $val){
				if(empty($val))	continue;
				if(false != ($reg = preg_match('/[_%]/uS', $val)))
					$tmp = "`$key` LIKE '" . $db->escape_string($val) . "'";
				else{
					$tmp = "LOWER(`$key`) = '" . $db->escape_string($val) . "'";
					if($key == 'surname')
						$tmp = "($tmp OR `surname_key` = '" . $db->escape_string(rus_metaphone($val, true)) . "')";
					elseif($key == 'name')
						$tmp = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", extend_names($val)) . "'";
				}
				if($key == 'place'){
					$tmp = "($tmp OR ";
					if($reg)
						$tmp .= "`region` LIKE '" . $db->escape_string($val) . "'";
					else
						$tmp .= "MATCH (`region`) AGAINST ('" . $db->escape_string($val) . "' IN BOOLEAN MODE)";
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
					$tmp = '`date_to` >= STR_TO_DATE("' . $db->escape_string($val) . '", "%Y-%m-%d")';
				}elseif($key == 'date_to'){
					// Дата по
					$tmp = '`date_from` <= STR_TO_DATE("' . $db->escape_string($val) . '", "%Y-%m-%d")';
				}elseif(in_array($key, $nums)){
					// Числовые данные
					if(in_array($key, $ids))
						$key .= '_id';	// Модифицируем название поля
					if(!is_array($val))
						$val = preg_split('/\D+/uS', trim($val));
					$val = implode(', ', array_map('intval', $val));
					if(false === strchr($val, ','))
						$tmp = "`$key` = $val";	// Одиночное значение
					else
						$tmp = "`$key` IN ($val)";	// Множественное значение
				}else{
					// Текстовые данные…
					if(is_array($val)){
						// … в виде массива строк
						$tmp = array();
						foreach($val as $v)
							$tmp[] = "`$key` = '" . $db->escape_string($v) . "'";
						$tmp = '(' . implode(' OR ', $tmp) . ')';
					}else{
						// … в виде строки
						if(false != ($reg = preg_match('/[_%]/uS', $val)))
							$tmp = "`$key` LIKE '" . $db->escape_string($val) . "'";
						else{
							$tmp = "LOWER(`$key`) = '" . $db->escape_string($val) . "'";
							if($key == 'surname' && $this->surname_ext)
								$tmp = "($tmp OR `surname_key` = '" . $db->escape_string(rus_metaphone($val, true)) . "')";
							elseif($key == 'name' && $this->name_ext)
								$tmp = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", extend_names($val)) . "'";
						}
					}
				}
				$w[] = $tmp;
			}
			$w = implode(' AND ', $w);
		}

// var_export($w);
		// Считаем, сколько результатов найдено
		$query = 'SELECT COUNT(*) FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id WHERE ' . $w;
		$result = db_query($query);
		$cnt = $result->fetch_array(MYSQL_NUM);
		$result->free();
		
		// Запрашиваем текущую порцию результатов для вывода в таблицу
		$query = 'SELECT * FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id LEFT JOIN dic_religion ON dic_religion.id=persons.religion_id LEFT JOIN dic_marital ON dic_marital.id=persons.marital_id LEFT JOIN dic_reason ON dic_reason.id=persons.reason_id LEFT JOIN dic_source ON dic_source.id=persons.source_id WHERE ' . $w . ' ORDER BY surname, name LIMIT ' . (($this->page - 1) * Q_LIMIT) . ', ' . Q_LIMIT;
		$result = db_query($query);
		$report = new ww1_solders_set($this->page, $result, $cnt[0]);
		$result->free();

		return $report;
	}
}

?>