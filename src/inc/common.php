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
define('INC_DIR',	BASE_DIR . '/inc');

// Подключаем настройки системы
if(!file_exists(BASE_DIR . '/gb-config.php'))	die('<b>ERROR:</b> Unable to find configuration file!');
require_once(BASE_DIR . '/gb-config.php');

// Подключаем функции работы с текстовыми данными
require_once(INC_DIR . '/text.php');

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
 *
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
 * Выполняем периодическое обновление вычисляемых данных
 */
function db_update(){
	// Удаляем устаревшие записи из таблицы контроля нагрузки на систему
	db_query("DELETE FROM `load_check` WHERE (`banned_to_datetime` IS NULL AND TIMESTAMPDIFF(HOUR, `first_request_datetime`, NOW()) > 3) OR `banned_to_datetime` < NOW()");

	// Генерируем поисковые ключи для фамилий
	// $result = db_query('SELECT DISTINCT `surname` FROM `persons` WHERE `surname_key` = "" ORDER BY `update_datetime` ASC LIMIT 12');
	$result = db_query('SELECT DISTINCT `surname` FROM `persons` ORDER BY `update_datetime` ASC LIMIT 12');
	while($row = $result->fetch_array(MYSQL_NUM)){
		db_query('UPDATE `persons` SET `surname_key` = "' . db_escape(implode(' ', make_search_keys($row[0], false))) . '", `update_datetime` = NOW() WHERE `surname` = "' . db_escape($row[0]) . '"');
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

	<link rel="stylesheet" type="text/css" href="/styles.css" />
</head><body>
	<h1>Первая Мировая война, 1914–1918&nbsp;гг.<br/>Алфавитные списки потерь нижних чинов</h1>
<?php
}



/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
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
 * Функция вывода общей статистики о числе записей в системе
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
	public	$records_cnt;

	// Создание экземпляра класса и сохранение результатов поиска
	function __construct($page, $sql_result, $records_cnt = NULL){
		parent::__construct($page);
		
		$this->records = array();
		while($row = $sql_result->fetch_object()){
			if($row->religion == '(иное)'){
				$result = db_query('SELECT religion FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->religion = $tmp[0];
			}
			if($row->marital == '(иное)'){
				$result = db_query('SELECT marital FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->marital = $tmp[0];
			}
			if($row->reason == '(иное)'){
				$result = db_query('SELECT reason FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->reason = $tmp[0];
			}
			$this->records[] = $row;
		}

		$this->records_cnt = ($records_cnt !== NULL ? $records_cnt : count($this->records));
	}

	// Вывод результатов поиска в виде html-таблицы
	function show_report($brief_fields = NULL, $detailed_fields = array()){
		$max_pg = max(1, ceil($this->records_cnt / Q_LIMIT));
		if($this->page > $max_pg)	$this->page = $max_pg;

		$brief_fields_cnt = count($brief_fields);
?>
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
		$('body').keydown(function(e){
			if(e.ctrlKey && e.keyCode == 37){	// Ctrl+Left
				location.href = $('.paginator:first .prev').attr('href');
			}
			if(e.ctrlKey && e.keyCode == 39){	// Ctrl+Right
				location.href = $('.paginator:first .next').attr('href');
			}
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
			print "\t<tr class='brief" . ($even ? ' even' : ' odd') . " id_" . $row->id . "'>\n";
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
		if($num != 0):
			static $hints = array(
				'По клику на строке интересной Вам записи открывается дополнительная информация.',
				'По страницам результатов поиска можно перемещаться, используя клавиши <span class="kbdKey">Ctrl</span>+<span class="kbdKey">→</span> и <span class="kbdKey">Ctrl</span>+<span class="kbdKey">←</span>.',
				'Многие записи снабжены ссылками на электронные копии источников, по которым создавалась эта база данных.',
				'Обычно при поиске система автоматически ищет близкие по звучанию или написанию варианты фамилий. Все найденные варианты показываются в конце списка.',
			);
			shuffle($hints);
			print "<p class='nb aligncenter' style='margin-top: 3em'><strong>Обратите внимание:</strong> " . array_shift($hints) . "</p>";
		else:
?>
<div class="notfound"><p>Что делать, если ничего не&nbsp;найдено?</p>
<ol>
	<li>Попробовать разные близкие варианты написания имён, фамилий, мест.
		<div class="nb">Изначально списки писались от-руки в&nbsp;условиях войны и&nbsp;не&nbsp;всегда очень грамотными писарями. Во&nbsp;время их написания, набора в&nbsp;типографии и&nbsp;во&nbsp;время оцифровки их волонтёрами могли закрасться различные ошибки;</div></li>
	<li>Повторить поиск, исключив один из&nbsp;критериев.
		<div class="nb">Возможно, искомые Вами данные по&nbsp;какой-то причине занесены в&nbsp;систему не&nbsp;полностью;</div></li>
	<li>Подождать неделю-другую и повторить поиск.
		<div class="nb">Система постоянно пополняется новыми материалами и, возможно, необходимая Вам информация будет добавлена в&nbsp;неё через некоторое время.</div></li>
</ol></div>
<?php
		endif;
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
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
				$this->have_query |= !empty($this->query[$key]);
			}
		}else{
			// Расширенный режим поиска **************************************
			$dics = explode(' ', 'rank religion marital reason');
			foreach(explode(' ', 'surname name rank religion marital region place reason date_from date_to list_nr list_pg') as $key){
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
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
		$sort_by = 'surname';

/*** ↓↓↓ Удалить после августа 2014 ↓↓↓ *************************************************/
		// Проверка на старые метасимволы и выдача предупреждения
		foreach($this->query as $val){
			if(preg_match('/[_%]/uS', $val)){
				print "<p class='aligncenter' style='color: red'>Обратите внимание, что изменились метасимволы, которые обозначают неизвестную часть слова.</p>\n";
				break;
			}
		}
/*** ↑↑↑ Удалить после августа 2014 ↑↑↑ *************************************************/

		// SELECT t.* FROM test AS t WHERE t.fio LIKE REPLACE(REPLACE('Ко*лев', '?', '_'), '*', '%') UNION SELECT t.* FROM test AS t WHERE 'Ко*лев' LIKE REPLACE(REPLACE(t.fio, '?', '_'), '*', '%')
		
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Формируем основной поисковый запрос в БД
			$w = array();
			foreach($this->query as $key => $val){
				if(empty($val))	continue;
				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'name' && !$is_regex)
					$q = "LOWER(`name`) RLIKE '" . implode("' AND LOWER(`name`) RLIKE '", db_escape(expand_names($val))) . "'";
				else{
					if($key == 'place'){
						// Удаляем слова типа «губерния», «уезд» и т.п.
						global $region_short;
						$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
					}
					$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
					$q = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", db_escape(db_regex($val_a))) . "'";
					if($key == 'surname' && !$is_regex){
						$q = "($q OR `surname_key` RLIKE '" . implode("' AND `surname_key` RLIKE '", db_escape(db_regex(make_search_keys($val_a)))) . "')";
						$sort_by = '(surname = "' . db_escape($val) . '") DESC, surname_key';
					}
				}
				if($key == 'place'){
					$q = "LOWER(CONCAT_WS(',', `region`, `place`)) RLIKE '" . implode("' AND LOWER(CONCAT_WS(',', `region`, `place`)) RLIKE '", db_escape(db_regex($val_a))) . "'";
					// $q = "($q OR ";
					// if($is_regex)
						// $q .= "LOWER(`region`) RLIKE '" . implode("' OR LOWER(`region`) RLIKE '", db_escape(db_regex($val_a))) . "'";
					// else
						// $q .= "MATCH (`region`) AGAINST ('" . db_escape($val) . "' IN BOOLEAN MODE)";
					// $q .= ')';
				}
				$w[] = $q;
			}
			$w = implode(' AND ', $w);
		}else{
			// Расширенный режим поиска **************************************

			// Формируем основной поисковый запрос в БД
			$w = array();
			$nums = explode(' ', 'religion marital reason list_nr list_pg');	// Список полей, в которых передаются числовые данные
			$ids = explode(' ', 'religion marital reason');	// Список полей, в которых передаются идентификаторы
			foreach($this->query as $key=>$val){
				if(empty($val))	continue;
				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'date_from'){
					// Дата с
					$q = '`date_to` >= STR_TO_DATE("' . db_escape($val) . '", "%Y-%m-%d")';
				}elseif($key == 'date_to'){
					// Дата по
					$q = '`date_from` <= STR_TO_DATE("' . db_escape($val) . '", "%Y-%m-%d")';
				}elseif(in_array($key, $nums)){
					// Числовые данные
					if(in_array($key, $ids))
						$key .= '_id';	// Модифицируем название поля
					if(!is_array($val))
						$val = preg_split('/\D+/uS', trim($val));
					$val = implode(', ', array_map('intval', $val));
					if(false === strchr($val, ','))
						$q = "`$key` = $val";	// Одиночное значение
					else
						$q = "`$key` IN ($val)";	// Множественное значение
				}else{
					// Текстовые данные…
					if(is_array($val)){
						// … в виде массива строк
						$q = array();
						foreach($val as $v)
							$q[] = "`$key` = '" . db_escape($v) . "'";
						$q = '(' . implode(' OR ', $q) . ')';
					}else{
						// … в виде строки
						if($key == 'name' && !is_regex && $this->name_ext)
							$q = "LOWER(`name`) RLIKE '" . implode("' AND LOWER(`name`) RLIKE '", db_escape(expand_names($val))) . "'";
						else{
							if($key == 'region' || $key == 'place'){
								// Удаляем слова типа «губерния», «уезд» и т.п.
								global $region_short;
								$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
							}
							$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
							$q = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", db_escape(db_regex($val_a))) . "'";
							if($key == 'surname' && $this->surname_ext && !$is_regex){
								$q = "($q OR `surname_key` RLIKE '" . implode("' AND `surname_key` RLIKE '", db_escape(db_regex(make_search_keys($val_a)))) . "')";
								$sort_by = '(surname = "' . db_escape($val) . '") DESC, surname_key';
							}
						}
					}
				}
				$w[] = $q;
			}
			$w = implode(' AND ', $w);
		}

if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($w);	print "\n -->\n";	}
		// Считаем, сколько результатов найдено
		$query = 'SELECT COUNT(*) FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id WHERE ' . $w;
		$result = db_query($query);
		$cnt = $result->fetch_array(MYSQL_NUM);
		$result->free();
		
		// Запрашиваем текущую порцию результатов для вывода в таблицу
		$query = 'SELECT *, persons.id FROM persons LEFT JOIN dic_region ON dic_region.id=persons.region_id LEFT JOIN dic_religion ON dic_religion.id=persons.religion_id LEFT JOIN dic_marital ON dic_marital.id=persons.marital_id LEFT JOIN dic_reason ON dic_reason.id=persons.reason_id LEFT JOIN dic_source ON dic_source.id=persons.source_id WHERE ' . $w . ' ORDER BY ' . $sort_by . ' ASC, name ASC, region ASC, place ASC LIMIT ' . (($this->page - 1) * Q_LIMIT) . ', ' . Q_LIMIT;
		$result = db_query($query);
		$report = new ww1_solders_set($this->page, $result, $cnt[0]);
		$result->free();

		return $report;
	}
}

?>