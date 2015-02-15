<?php
/**
 * Основной подключаемый файл системы.
 * 
 * Файл хранит некоторые основные функции, отвечает за её инициализацию и подключение всех
 * необходимых дополнительных модулей. 
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');

// Проверка версии PHP
if(version_compare(phpversion(), "5.3.0", "<"))	die('<b>ERROR:</b> PHP version 5.3+ needed!');



/***************************************************************************
 * Основной подключаемый файл системы
 */



// Запоминаем текущий каталог, как основу для всех подключаемых файлов системы
if(!defined('BASE_DIR'))	define('BASE_DIR',	dirname(dirname(__FILE__)));
// Константа для быстрого обращения к каталогу с подключаемыми файлами
if(!defined('GB_INC_DIR'))	define('GB_INC_DIR',	BASE_DIR . '/gb');

// Подключаем настройки системы
if(!defined('GB_TESTING_MODE')){	// … но не в режиме тестирования (т.к. в нём особые настройки уже загружены)
	if(!file_exists(BASE_DIR . '/gb-config.php'))	die('<b>ERROR:</b> Unable to find configuration file!');
	require_once(BASE_DIR . '/gb-config.php');
}

// Подключаем прочие файлы
require_once(GB_INC_DIR . '/text.php');
require_once(GB_INC_DIR . '/class.GB_DBase.php');
require_once(GB_INC_DIR . '/class.ww1_database.php');
require_once(GB_INC_DIR . '/class.ww1_records_set.php');

// Включение в режиме отладки полной отладочной информации
if(defined('GB_DEBUG')){
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
		$max_date = gbdb()->get_cell('SELECT MAX(update_datetime) FROM `persons_raw`');
		$tmp = date_diff(date_create($max_date), date_create('now'));
		$tmp = intval($tmp->format('%i'));
// var_export($tmp);
		if($tmp < 1)	return;
	}

	require_once(GB_INC_DIR . '/publish.php');	// Функции формализации данных

	// Делаем выборку записей для публикации
// 	$drafts = gbdb()->get_table('SELECT * FROM `persons_raw` WHERE `status` = "Draft" ORDER BY `list_pg`, `id` LIMIT ' . P_LIMIT);
	$drafts = gbdb()->get_table('SELECT * FROM `persons_raw` WHERE `status` = "Draft" ORDER BY RAND() LIMIT ' . P_LIMIT);
	
	// Нормирование данных
	foreach($drafts as $raw){
if(defined('GB_DEBUG'))	print "\n\n======================================\n";
if(defined('GB_DEBUG'))	var_export($row);
	$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('GB_DEBUG'))	var_export($have_trouble);
if(defined('GB_DEBUG'))	var_export($pub);
		// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
		if(!$have_trouble)
			gbdb()->set_row('persons', $pub, FALSE, GB_DBase::MODE_REPLACE);
		//
		gbdb()->set_row('persons_raw',
				array('status' => ($have_trouble ? 'Cant publish' : 'Published')),
				$raw['id']
		);
	}
}



/**
 * Periodically update calculated fields in database.
 */
function db_update(){
	// Удаляем устаревшие записи из таблицы контроля нагрузки на систему
	gbdb()->query('DELETE FROM `load_check` WHERE (`banned_to_datetime` IS NULL
			AND TIMESTAMPDIFF(HOUR, `first_request_datetime`, NOW()) > 3)
			OR `banned_to_datetime` < NOW()');

	// Генерируем поисковые ключи для фамилий
	$result = gbdb()->get_column('SELECT DISTINCT `surname` FROM `persons`
			WHERE NOT EXISTS (
				SELECT 1 FROM `idx_search_keys` WHERE `persons`.`id` = `idx_search_keys`.`person_id`
				AND (
					`idx_search_keys`.`update_datetime` > :exp
					OR `persons`.`update_datetime` < `idx_search_keys`.`update_datetime`
				)
			) ORDER BY `update_datetime` ASC LIMIT 15', array('exp' => IDX_EXPIRATION_DATE));
	foreach ($result as $row){
		gbdb()->query('DELETE FROM `idx_search_keys` USING `idx_search_keys` INNER JOIN `persons`
				WHERE `persons`.`surname` = :surname AND `persons`.`id` = `idx_search_keys`.`person_id`',
				array('surname' => $row[0]));
		
		$keys = make_search_keys($row[0], false);
		foreach ($keys as $key)
			gbdb()->query('INSERT IGNORE INTO `idx_search_keys` (`person_id`, `surname_key`)
					SELECT `id`, UPPER(:key) FROM `persons` WHERE `surname` = :surname',
					array('key' => $key, 'surname' => $row[0]));
	}
	
	// Обновляем списки вложенных регионов, если это необходимо
	$result = gbdb()->get_column('SELECT `id`, `parent_id` FROM `dic_region` WHERE `region_ids` = ""',
			array(), TRUE);
	foreach ($result as $id => $parent_id){
		$ids = gbdb()->get_cell('SELECT GROUP_CONCAT(`region_ids`) FROM `dic_region` WHERE `parent_id` = :id',
				array('id' => $id));
		$ids = trim(preg_replace('/,,+/uS', ',', $ids) ,',');
		gbdb()->set_row('dic_region', array('region_ids' => (empty($ids) ? $id : "$id,$ids")), $id);
		gbdb()->set_row('dic_region', array('region_ids' => ''), $parent_id);
	}
	
	// Обновляем полные наименования регионов, если это необходимо
	$result = gbdb()->get_table('SELECT `id`, `parent_id`, `title` FROM `dic_region` WHERE `region` = ""');
	foreach ($result as $row){
		$parent_region = gbdb()->get_cell('SELECT `region` FROM `dic_region` WHERE `id` = :parent_id', $row);
		global $region_short;
		$tmp = trim(
				(empty($parent_region) || substr($parent_region, 0, 1) == '('
						? '' : "$parent_region, ") .
				(substr($row['title'], 0, 1) == '('
						? '' : strtr($row['title'], $region_short)),
				', ');
		if($tmp){
			gbdb()->set_row('dic_region', array('region' => $tmp), $row['id']);
			gbdb()->set_row('dic_region', array('region' => ''), array('parent_id' => $row['id']));
		}
	}
	
	// Обновляем статистику…
	//
	// … по регионам
	$result = gbdb()->get_column('SELECT `id`, `region_ids` FROM `dic_region` ORDER BY `update_datetime` ASC LIMIT 7',
			array(), TRUE);
	foreach ($result as $id => $region_ids){
		if(empty($region_ids))	$region_ids = $id;
		$cnt = '';
		if(false !== strpos($region_ids, ',')){
			// У региона есть вложенные регионы — просуммируем их статистику и прибавим к статистике региона
			$childs = gbdb()->get_cell('SELECT SUM(`region_cnt`) FROM `dic_region` WHERE `parent_id` = :parent_id',
					array('parent_id' => $id));
			$cnt = $childs . ' + ';
		}
		gbdb()->query('UPDATE LOW_PRIORITY `dic_region` SET `region_cnt` = ' . $cnt .
				'( SELECT COUNT(*) FROM `persons` WHERE `region_id` = :region_ids ), `update_datetime` = NOW() WHERE `id` = :id',
				array('id' => $id, 'region_ids' => $region_ids));
	}
	//
	// … по религиям, семейным положениям, событиям
	foreach(explode(' ', 'religion marital reason') as $key){
		$result = gbdb()->get_column('SELECT `id` FROM :#table ORDER BY `update_datetime` ASC LIMIT 1',
				array('#table' => "dic_$key"));
		foreach($result as $row){
			gbdb()->get_column('UPDATE LOW_PRIORITY :#table SET :#field_cnt =
					( SELECT COUNT(*) FROM `persons` WHERE :#field_id = :id ),
					`update_datetime` = NOW() WHERE `id` = :id',
					array(
						'#table'		=> "dic_$key",
						'#field_cnt'	=> "{$key}_cnt",
						'#field_id'		=> "{$key}_id",
						'id'	=> $row['id'],
					));
		}	// foreach
	}	// foreach
}	// function db_update



/**
 * Вывод начальной части страницы
 * 
 * @param	string	$title	Title of page.
 */
function html_header($title){
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
		if(preg_match('/[_%]/uS', @$val)){
			print "\t<meta name='robots' content='noindex,nofollow' />\n";
			break;
		}
	}
/*** ↑↑↑ Удалить после августа 2014 ↑↑↑ *************************************************/
?>

	<title><?php echo $title; ?> - Первая мировая война, 1914–1918 гг. Алфавитные списки потерь нижних чинов</title>

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
	<!-- <h1>Первая мировая война, 1914–1918&nbsp;гг.<br/>Алфавитные списки потерь нижних чинов</h1> -->
	<!-- начало вставки -->
	<table align=center width=770 border=0 cellspacing=0 cellpadding=2>
		<tr>
			<td width=150>
				<a href=http://1914.svrt.ru/><img src="/img/logo03c.jpg" hspace=0 vspace=0></a>
			</td>
			<td align=center hspace=30 vspace=30
				<link rel="stylesheet" type="text/css" href="/styles.css" />
				<h2>Первая мировая война, 1914–1918 гг.<br/>Алфавитные списки потерь нижних чинов</h2>
				<a href=http://www.svrt.ru/>Проект Союза Возрождений Родословных Традиций (СВРТ)</a>
			</td>
		</tr>
	</table>
	<!-- окончание вставки -->	
<?php
}


/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
<!--
<p style="text-align: center; margin-top: 3em;"><a href="/news.php">Новости</a> | <a href="/stat.php">Статистика</a> | <a href="/guestbook/index.php">Гостевая</a>  | <a href="http://forum.svrt.ru/index.php?showforum=127" target="_blank">Форум</a> | <a href="crue.php">Команда</a></p>
-->
<p style="text-align: center; margin-top: 3em;"><a href="/stat.php">Статистика</a> | <a href="/guestbook/index.php">Гостевая книга</a>  | <a href="/todo.php">ToDo-list</a> | <a href="http://forum.svrt.ru/index.php?showtopic=3936&view=getnewpost" target="_blank">Обсуждение сервиса</a> (<a href="http://forum.svrt.ru/index.php?showtopic=7343&view=getnewpost" target="_blank">техническое</a>) | <a href="crue.php">Команда проекта</a></p>
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
	$cnt	= gbdb()->get_cell('SELECT COUNT(*) FROM persons');
	$cnt2	= gbdb()->get_cell('SELECT COUNT(*) FROM persons_raw');
	//
	$txt = format_num($cnt, ' запись.', ' записи.', ' записей.');
	if($cnt != $cnt2)
		$txt = format_num($cnt2, ' запись.', ' записи.', ' записей.') . ' Из них сейчас доступны для поиска ' . $txt;
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

	if(gbdb()->get_cell('SELECT 1 FROM `logs` WHERE `url` = :url AND `datetime` >= NOW() - INTERVAL 1 HOUR',
			array('url' => $url)))
		return;

	$tmp = trim($_REQUEST['region'] . ' ' . $_REQUEST['place']);
	$squery = trim($_REQUEST['surname'] . ' ' . $_REQUEST['name'] . (empty($tmp) ? '' : " ($tmp)"));

	gbdb()->set_row('logs', array(
		'query' => $squery,
		'url'	=> $url,
		'records_found'	=> $records_found,
	), FALSE, GB_DBase::MODE_INSERT);
}



/**
 * Автоматическая проверка на предмет перенагрузки системы.
 * 
 * В случае выявления перенагрузки, функция не возвращает ничего, а исполнение скрипта прерывается.
 */
function load_check(){
	$row = gbdb()->get_cell('SELECT
			CEIL(TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW())) AS `period_in_sec`,
			CEIL(TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) / `requests_counter`) AS `speed`,
			`banned_to_datetime` >= NOW() AS `banned` 
			FROM `load_check` WHERE `ip` = :ip', array('ip' => $_SERVER["REMOTE_ADDR"]));

// print "<!-- "; var_export($row); print " -->";
	if(FALSE === $row){
		// Первый заход пользователя
		gbdb()->set_row('load_check', array('ip' => $_SERVER["REMOTE_ADDR"]), FALSE, GB_DBase::MODE_INSERT);

	}elseif($row['banned'] || (($row['speed'] < 3) && ($row['period_in_sec'] > 30))){
		// Пользователь проштрафился
		gbdb()->query('UPDATE `load_check` SET `banned_to_datetime` = TIMESTAMPADD(MINUTE, :ban, NOW())
				WHERE `ip` = :ip',
				array('ip' => $_SERVER["REMOTE_ADDR"], 'ban' => OVERLOAD_BAN_TIME));
		print "<div style='color: red; margin: 3em; font-width: bold; text-align: center'>Вы перегружаете систему и были заблокированы на некоторое время. Сделайте перерыв…</div>";
		die();

	}else{
		// Очередной заход пользователя
		gbdb()->query('UPDATE `load_check` SET `requests_counter` = `requests_counter` + 1 WHERE `ip` = :ip',
				array('ip' => $_SERVER["REMOTE_ADDR"]));
	}
}
