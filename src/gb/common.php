<?php
// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');

// Проверка версии PHP
if(version_compare(phpversion(), "5.3.0", "<"))	die('<b>ERROR:</b> PHP version 5.3+ needed!');



/***************************************************************************
 * Основной подключаемый файл системы
 */



// Запоминаем текущий каталог, как основу для всех подключаемых файлов системы
define('BASE_DIR',	dirname(dirname(__FILE__)));
// Константа для быстрого обращения к каталогу с подключаемыми файлами
define('INC_DIR',	BASE_DIR . '/gb');

// Подключаем настройки системы
if(!file_exists(BASE_DIR . '/gb-config.php'))	die('<b>ERROR:</b> Unable to find configuration file!');
require_once(BASE_DIR . '/gb-config.php');

// Подключаем прочие файлы
require_once(INC_DIR . '/text.php');
require_once(INC_DIR . '/class.ww1_database.php');
require_once(INC_DIR . '/class.ww1_records_set.php');

// Включение в режиме отладки полной отладочной информации
if(defined('DEBUG')){
	error_reporting(E_ALL);	// Включить показ всех ошибок
	ini_set('display_errors', 'stdout');
}



// Базовые настройки системы
mb_internal_encoding('UTF-8');
//
setlocale(LC_ALL, 'ru_RU.utf8');
// bindtextdomain(WWI_TXTDOM, dirname(__FILE__) . '/lang');
// textdomain(WWI_TXTDOM);
// bind_textdomain_codeset(WWI_TXTDOM, 'UTF-8');



// Таблица сокращений регионов
static $region_short = array(
	' генерал-губернаторство'	=> ' ген.-губ.',
	' наместничество'	=> ' нам.',
	' губерния'	=> ' губ.',
	' область'	=> ' обл.',
	' уезд'	=> ' у.',
	' волость'	=> ' вол.',
	' округа'	=> ' окр.',
);



/**
 * Make non-negative integer value.
 * 
 * @param	mixed	$val	The scalar value being converted to non-negative integer.
 * @return	integer	Non-negative integer value.
 */
function absint($val) {
	return abs(intval($val));
}



/**
 * Periodically publish new data to main database.
 * 
 * @param	string	$force
 */
function publish_cron($force = false){
	if(!$force){
		$result = db_query('SELECT MAX(update_datetime) FROM `persons_raw`');
		$row = $result->fetch_array(MYSQL_NUM);
		$result->free();
		$tmp = date_diff(date_create($row[0]), date_create('now'));
		$tmp = intval($tmp->format('%i'));
// var_export($tmp);
		if($tmp < 1)	return;
	}

	require_once(INC_DIR . '/publish.php');	// Функции формализации данных

	// Делаем выборку записей для публикации
	$drafts = array();
	// $result = db_query('SELECT * FROM `persons_raw` WHERE `status` = "Draft" ORDER BY `list_pg`, `id` LIMIT ' . P_LIMIT);
	$result = db_query('SELECT * FROM `persons_raw` WHERE `status` = "Draft" ORDER BY RAND() LIMIT ' . P_LIMIT);
	while($row = $result->fetch_array(MYSQL_ASSOC)){
		$drafts[] = $row;
	}
	$result->free();

	// Нормирование данных
	foreach($drafts as $raw){
if(defined('DEBUG'))	print "\n\n======================================\n";
if(defined('DEBUG'))	var_export($row);
	$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);
		// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
		if(!$have_trouble){
			db_query('REPLACE INTO `persons` (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
		}
		db_query('UPDATE `persons_raw` SET `status` = "' . ($have_trouble ? 'Cant publish' : 'Published') . '" WHERE `id` = ' . $raw['id']);
	}
}



/**
 * Соединяемся с СУБД, выбираем базу данных
 */
function db_open($open = true){
	static $db = null;

	if(!empty($db) || !$open)	return $db;

	$db = new MySQLi(DB_HOST, DB_USER, DB_PWD, DB_BASE);
	if($db->connect_error)
		die('Ошибка подключения (' . $db->connect_errno . ') ' . $db->connect_error);

	// Проверка версии MySQL
	// if(version_compare($db->server_info, "5.0.0", "<"))	die('<b>ERROR:</b> MySQL version 5.0+ needed!');

	$db->set_charset('utf8');
	return $db;
}



/**
 * Экранирование специальных конструкций
 */
function db_escape($str, $escape_reg = false){
	// Если вместо строки передан массив, обработать каждое значение в отдельности и вернуть результат в виде массива
	if(is_array($str)){
		foreach($str as $key => $val)
			$str[$key] = db_escape($val, $escape_reg);
		return $str;
	}

	$db = db_open();
	$str = $db->escape_string($str);
	if($escape_reg)
		$str = preg_replace('/([.?*\\_%])/uS', '\\\1', $str);
	return $str;
}



/**
 * Формирование из строки с метасимволами регулярного выражения для поиска в системе
 */
function db_regex($str, $full_word = true){
	// Если вместо строки передан массив, обработать каждое значение в отдельности и вернуть результат в виде массива
	if(is_array($str)){
		foreach($str as $key => $val)
			$str[$key] = db_regex($val, $full_word);
		return $str;
	}

	$str = strtr($str, array(
		'ё'	=> '(е|ё)',
		'Ё'	=> '(Е|Ё)',
	));
	$str = preg_replace_callback('/(\?+|\*+)/uS', function ($matches){
		$ch = substr($matches[1], 0, 1);
		$len = strlen($matches[1]);
		// return '[[:alpha:]]' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}'));
		return '(..)' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}'));	// Костыли для учёта двухбайтной кодировки
	}, $str);
	if($full_word)
		$str = "[[:<:]]${str}[[:>:]]";
	return $str;
}



/**
 * Отправляем запрос в СУБД
 */
function db_query($query){
	$db = db_open();
	if(false === ($result = $db->query($query))){
		error_log('MySQL query error: ' . $db->error . ' Query: ' . $query);
		die('Запрос не удался: ' . $db->error . (!defined('SQL_DEBUG') ? '' : '<br/>Запрос: ' . $query));
	}
	return $result;
}



/**
 * Закрываем соединение с СУБД
 */
function db_close(){
	// Раз в 30 запусков делаем апдейт обновляемых данных
	if(rand(0, 30) == 0)	db_update();

	// Закрываем соединение с СУБД
	$db = db_open(false);
	if($db)		$db->close();
}



/**
 * Periodically update calculated fields in database.
 */
function db_update(){
	// Удаляем устаревшие записи из таблицы контроля нагрузки на систему
	db_query("DELETE FROM `load_check` WHERE (`banned_to_datetime` IS NULL AND TIMESTAMPDIFF(HOUR, `first_request_datetime`, NOW()) > 3) OR `banned_to_datetime` < NOW()");

	// Генерируем поисковые ключи для фамилий
	// $result = db_query('SELECT DISTINCT `surname` FROM `persons` WHERE `surname_key` = "" ORDER BY `update_datetime` ASC LIMIT 12');
	$result = db_query('SELECT DISTINCT `surname` FROM `persons` ORDER BY `update_datetime` ASC LIMIT 12');
	while($row = $result->fetch_array(MYSQL_NUM)){
		$tmp = implode(' ', make_search_keys($row[0], false));
		// Обновление в основной таблице (старое)
		db_query('UPDATE `persons` SET `surname_key` = "' . db_escape($tmp) . '", `update_datetime` = NOW() WHERE `surname` = "' . db_escape($row[0]) . '"');

		// Обновление в отдельной таблице (новое)
		db_query('DELETE FROM `idx_surname_keys` USING `idx_surname_keys` INNER JOIN `persons` WHERE `persons`.`surname` = "' . db_escape($row[0]) . '" AND `persons`.`id` = `idx_surname_keys`.`person_id`');
		foreach (explode(' ', $tmp) as $key) {
			db_query('INSERT INTO `idx_surname_keys` (`person_id`, `surname_key`) SELECT `id`, "' . db_escape($key) . '" FROM `persons` WHERE `surname` = "' . db_escape($row[0]) . '"');
		}
	}
	$result->free();
	
	// Обновляем списки вложенных регионов, если это необходимо
	$result = db_query('SELECT `id`, `parent_id` FROM `dic_region` WHERE `region_ids` = ""');
	while($region = $result->fetch_object()){
		$result2 = db_query('SELECT GROUP_CONCAT(`region_ids`) FROM `dic_region` WHERE `parent_id` = ' . $region->id);
		$ids = $result2->fetch_array(MYSQL_NUM);
		$result2->free();
		$ids = trim(preg_replace('/,,+/uS', ',', $ids[0]) ,',');
		db_query('UPDATE `dic_region` SET `region_ids` = "' . $region->id . (empty($ids) ? '' : ',' . $ids) . '" WHERE `id` = ' . $region->id);
		db_query('UPDATE `dic_region` SET `region_ids` = "" WHERE `id` = ' . $region->parent_id);
	}
	$result->free();
	
	// Обновляем полные наименования регионов, если это необходимо
	$result = db_query('SELECT `id`, `parent_id`, `title` FROM `dic_region` WHERE `region` = ""');
	while($region = $result->fetch_object()){
		$result2 = db_query('SELECT `region` FROM `dic_region` WHERE `id` = ' . $region->parent_id);
		$parent = $result2->fetch_object();
		$result2->free();
		global $region_short;
		$tmp = trim((empty($parent) || substr($parent->region, 0, 1) == '(' ? '' : $parent->region . ', ') . (substr($region->title, 0, 1) == '(' ? '' : strtr($region->title, $region_short)), ', ');
		if($tmp){
			db_query('UPDATE `dic_region` SET `region` = "' . db_escape($tmp) . '" WHERE `id` = ' . $region->id);
			db_query('UPDATE `dic_region` SET `region` = "" WHERE `parent_id` = ' . $region->id);
		}
	}
	$result->free();
	
	// Обновляем статистику…
	//
	// … по регионам
	$result = db_query('SELECT `id`, `region_ids` FROM `dic_region` ORDER BY `update_datetime` ASC LIMIT 12');
	while($row = $result->fetch_object()){
		if(empty($row->region_ids))	$row->region_ids = $row->id;
		$cnt = '';
		if(false !== strpos($row->region_ids, ',')){
			// У региона есть вложенные регионы — просуммируем их статистику и добавим к статистике региона
			$result2 = db_query('SELECT SUM(`region_cnt`) FROM `dic_region` WHERE `parent_id` = ' . $row->id);
			$childs = $result2->fetch_array(MYSQL_NUM);
			$result2->free();
			$cnt = $childs[0] . ' + ';
		}
		db_query('UPDATE `dic_region` SET `region_cnt` = ' . $cnt . '( SELECT COUNT(*) FROM `persons` WHERE `region_id` = ' . intval($row->region_ids) . ' ), `update_datetime` = NOW() WHERE `id` = ' . $row->id);
	}
	$result->free();
	//
	// … по религиям, семейным положениям, причинам выбытия
	foreach(explode(' ', 'religion marital reason') as $key){
		$result = db_query("SELECT `id` FROM `dic_${key}` ORDER BY `update_datetime` ASC LIMIT 1");
		while($row = $result->fetch_array(MYSQL_NUM)){
			db_query("UPDATE `dic_${key}` SET `${key}_cnt` = ( SELECT COUNT(*) FROM `persons` WHERE `${key}_id` = ${row[0]} ), `update_datetime` = NOW() WHERE `id` = ${row[0]}");
		}
		$result->free();
	}
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
	<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php
/*** ↓↓↓ Удалить после августа 2014 ↓↓↓ *************************************************/
	// Проверка на старые метасимволы и выдача предупреждения
	global $dbase;
	foreach($dbase->query as $val){
		if(preg_match('/[_%]/uS', $val)){
			print "\t<meta name='robots' content='noindex,nofollow' />\n";
			break;
		}
	}
/*** ↑↑↑ Удалить после августа 2014 ↑↑↑ *************************************************/

	$tmp = trim($_REQUEST['region'] . ' ' . $_REQUEST['place']);
	$squery = $_REQUEST['surname'] . ' ' . $_REQUEST['name'] . (empty($tmp) ? '' : " ($tmp)");
	$squery = trim($squery);
?>
	<title>Поиск <?php echo (empty($squery) ? 'персоны' : '"' . htmlspecialchars($squery) . '"'); ?> - Первая Мировая война, 1914–1918 гг. Алфавитные списки потерь нижних чинов</title>

	<link rel="stylesheet" type="text/css" href="/styles.css" />
</head><body>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			$('.clearForm').on('click', function (){
				f_el = $(this).parents('form');
				f_el.find(':input').not(':button, :submit, :reset, :hidden, :checkbox, :radio').val('');
				f_el.find(':checkbox, :radio').prop('checked', false);
			});
		});
	</script>
	<h1>Первая Мировая война, 1914–1918&nbsp;гг.<br/>Алфавитные списки потерь нижних чинов</h1>
<?php
}



/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
<p class="copyright"><strong>Обратите внимание:</strong> Обработанные списки размещаются в свободном доступе только для некоммерческих исследований. Использование обработанных списков в коммерческих целях запрещено без получения Вами явного согласия правообладателя источника информации, СВРТ и участников проекта, осуществлявших обработку и систематизацию списков.</p>
<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

	ga('create', 'UA-289367-8', 'auto');
	ga('require', 'displayfeatures');
	ga('require', 'linkid', 'linkid.js');
	ga('send', 'pageview');
</script>
</body></html>
<?php
}



/**
 * Функция формирования блока ссылок для перемещения между страницами.
 * 
 * @param	integer	$pg
 * @param	integer	$max_pg
 * @return	string
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
 * Функция вывода общей статистики о числе записей в системе.
 */
function show_records_stat(){
	$result = db_query('SELECT COUNT(*) FROM persons');
	$cnt = $result->fetch_array(MYSQL_NUM);
	$result->free();
	//
	$result = db_query('SELECT COUNT(*) FROM persons_raw');
	$cnt2 = $result->fetch_array(MYSQL_NUM);
	$result->free();
	//
	$txt = format_num($cnt[0], ' запись.', ' записи.', ' записей.');
	if($cnt[0] != $cnt2[0]){
		$txt = format_num($cnt2[0], ' запись.', ' записи.', ' записей.') . ' Из них сейчас доступны для поиска ' . $txt;
	}
	print "<p class='aligncenter'>На данный момент в базе содержится $txt</p>\n";
}



/********************************************************************************
 * Функции протоколирования использования системы
 */

/**
 * Функция добавления в логи информации о текущем запросе.
 *  
 * @param	integer	$records_found
 */
function log_event($records_found = 0){
	// Удаляем пустые параметры
	$url = preg_replace_callback('/(?<=\?)(.*)(?=\#|$)/uS', function($matches){
		return implode('&', array_filter(preg_split('/&/uS', $matches[1]), function($val){
			return !preg_match('/^(pg=\d+|.*=)$/uS', $val);
		}));
	}, $_SERVER['REQUEST_URI']);

	$db = db_open();
	$stmt = $db->prepare('SELECT 1 FROM `logs` WHERE `url` = ? AND `datetime` >= NOW() - INTERVAL 1 HOUR');
	$stmt->bind_param("s", $url);
	$stmt->execute();
	$res = $stmt->fetch();
	$stmt->close();
	if($res)	return;

	$tmp = trim($_REQUEST['region'] . ' ' . $_REQUEST['place']);
	$squery = $_REQUEST['surname'] . ' ' . $_REQUEST['name'] . (empty($tmp) ? '' : " ($tmp)");
	$squery = trim($squery);

	$stmt = $db->prepare('INSERT `logs` (`query`, `url`, `records_found`) VALUES (?, ?, ?)');
	$stmt->bind_param("ssi", $squery, $url, $records_found);
	$stmt->execute();
	$stmt->close();
}



/**
 * Автоматическая проверка на предмет перенагрузки системы.
 * 
 * В случае выявления перенагрузки, функция не возвращает ничего, а исполнение скрипта прерывается.
 */
function load_check(){
	$ip = '"' . db_escape($_SERVER["REMOTE_ADDR"]) . '"';

	$result = db_query("SELECT CEIL(TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) / `requests_counter`) AS `speed`, `banned_to_datetime` >= NOW() AS `banned` FROM `load_check` WHERE `ip` = $ip");
	$row = $result->fetch_object();
	$result->free();

// print "<!-- "; var_export($row); print " -->";
	if(null === $row){
		// Первый заход пользователя
		db_query("INSERT INTO `load_check` (`ip`) VALUES ($ip)");
	}elseif($row->banned || $row->speed < 3){
		// Пользователь проштрафился
		db_query("UPDATE `load_check` SET `banned_to_datetime` = TIMESTAMPADD(MINUTE, " . OVERLOAD_BAN_TIME . ", NOW()) WHERE `ip` = $ip");
		print "<div style='color: red; margin: 3em; font-width: bold; text-align: center'>Вы перегружаете систему и были заблокированы на некоторое время. Сделайте перерыв…</div>";
		die();
	}else{
		// Очередной заход пользователя
		db_query("UPDATE `load_check` SET `requests_counter` = `requests_counter` + 1 WHERE `ip` = $ip");
	}
}
