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
	$result = db_query('SELECT DISTINCT surname FROM persons WHERE surname_key = "" ORDER BY list_nr LIMIT 500');
	$tmp = array();
	while($row = mysql_fetch_array($result, MYSQL_NUM)){
		$tmp[] = $row[0];
	}
	mysql_free_result($result);
	shuffle($tmp);
	foreach(array_slice($tmp, 0, 30) as $surname){
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
<p style="color: red; text-align: center">Система находится в состоянии разработки. Все правки пока делаются «по-живому». Возможна нестабильная работа.</p>
<h1>Первая Мировая война, 1914–1918&nbsp;гг.<br/>Алфавитные списки потерь нижних чинов</h1>
<?php
}



/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
</body></html>
<?php
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