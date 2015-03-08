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



/***************************************************************************
 * Основной подключаемый файл системы
 */

// Запоминаем родительский каталог, как основу для всех подключаемых файлов системы
if(!defined('BASE_DIR'))
	define('BASE_DIR',	dirname(dirname(__FILE__)));	// no trailing slash, full paths only

// Константы для быстрого обращения к каталогам с подключаемыми файлами
if(!defined('GB_INC_DIR'))
	define('GB_INC_DIR',	BASE_DIR . '/gb');
if(!defined('GB_LANG_DIR'))
	define('GB_LANG_DIR',	GB_INC_DIR . '/languages');

// Запоминаем текущий каталог, как корень сайта
if(!defined('BASE_URL'))
	define('BASE_URL', '//' . $_SERVER['HTTP_HOST'] . substr(BASE_DIR, strlen($_SERVER[ 'DOCUMENT_ROOT' ])));	// no trailing slash

// Подключаем настройки системы
if(!defined('GB_TESTING_MODE')){	// … но не в режиме тестирования (т.к. в нём особые настройки уже загружены)
	if(!file_exists(BASE_DIR . '/gb-config.php'))
		die('<b>ERROR:</b> Unable to find configuration file!');
	require_once(BASE_DIR . '/gb-config.php');
}

// Базовые настройки системы
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, array('ru_RU.utf8', 'ru_RU.UTF-8'));

// Include files required for initialization.
require_once(GB_INC_DIR . '/version.php');
require_once(GB_INC_DIR . '/load.php');
require_once(GB_INC_DIR . '/default-constants.php');

// Set initial default constants including GB_MEMORY_LIMIT, GB_MAX_MEMORY_LIMIT, GB_DEBUG
gb_initial_constants();

// Check for the required PHP version and for the MySQL extension or a database drop-in.
gb_check_php_mysql_versions();

// Turn register_globals off.
gb_unregister_GLOBALS();

// Standardize $_SERVER variables across setups.
gb_fix_server_vars();

// Check if we have received a request due to missing favicon.ico
gb_favicon_request();

// Check if we're in maintenance mode.
gb_maintenance();

// Start loading timer.
timer_start();

// Check if we're in GB_DEBUG mode.
gb_debug_mode();

// Load early GeniBase files.
require_once(GB_INC_DIR . '/pomo/mo.php');
require_once(GB_INC_DIR . '/functions.php');
require_once(GB_INC_DIR . '/class.gb-dbase.php');
require_once(GB_INC_DIR . '/general-template.php');

// Load the L10n library.
require_once(GB_INC_DIR . '/l10n.php');

// Load most of GeniBase.
require_once(GB_INC_DIR . '/kses.php');
require_once(GB_INC_DIR . '/formatting.php');
require_once(GB_INC_DIR . '/script-loader.php');
require_once(GB_INC_DIR . '/text.php');
require_once(GB_INC_DIR . '/class.ww1-database.php');
require_once(GB_INC_DIR . '/class.ww1-records-set.php');

// Define constants that rely on the API to obtain the default value.
gb_plugin_constants();

// Load default scripts and styles
// TODO: Remove after enabling actions
gb_default_styles();
gb_default_scripts();

// Load the default text localization domain.
load_default_textdomain();

$locale = get_locale();
$locale_file = GB_LANG_DIR . "/$locale.php";
if((0 === validate_file($locale)) && is_readable($locale_file))
	require($locale_file);
unset($locale_file);

// Pull in locale data after loading text domain.
require_once(GB_INC_DIR . '/locale.php');

/**
 * GeniBase Locale object for loading locale domain date and various strings.
 * @global object $gb_locale
 * @since 2.0.0
*/
$GLOBALS['gb_locale'] = new GB_Locale();



// Таблица сокращений регионов
static $region_short = array(
		' генерал-губернаторство'	=> ' ген.-губ.',
		' наместничество'	=> ' нам.',
		' губерния'			=> ' губ.',
		' область'			=> ' обл.',
		' уезд'				=> ' у.',
		' волость'			=> ' вол.',
		' округа'			=> ' окр.',
		' гмина'			=> ' гм.',
);



/**
 * Periodically publish new data to main database.
 * 
 * @param	string	$force
 */
function publish_cron($force = false){
	if(!$force){
		$max_date = gbdb()->get_cell('SELECT MAX(update_datetime) FROM ?_persons_raw');
		$tmp = date_diff(date_create($max_date), date_create('now'));
		$tmp = intval($tmp->format('%i'));
// var_export($tmp);	// TODO: Remove me?
		if($tmp < 1)	return;
	}

	require_once(GB_INC_DIR . '/publish.php');	// Функции формализации данных

	// Делаем выборку записей для публикации
	$drafts = gbdb()->get_table('SELECT * FROM ?_persons_raw WHERE `status` = "Draft"' .
			' ORDER BY RAND() LIMIT ' . P_LIMIT);
	
	// Нормирование данных
	foreach($drafts as $raw){
if(GB_DEBUG)	print "\n\n======================================\n";
if(GB_DEBUG)	var_export($row);
	$pub = prepublish($raw, $have_trouble, $date_norm);
if(GB_DEBUG)	var_export($have_trouble);
if(GB_DEBUG)	var_export($pub);
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
	// Skip updation for debug mode but not for testing mode
// 	if(GB_DEBUG && !defined('GB_TESTING_MODE'))	return;

	// Удаляем устаревшие записи из таблицы контроля нагрузки на систему
	gbdb()->query('DELETE FROM ?_load_check WHERE (`banned_to_datetime` IS NULL' .
			' AND TIMESTAMPDIFF(HOUR, `first_request_datetime`, NOW()) > 3)' .
			' OR `banned_to_datetime` < NOW()');

	// Генерируем поисковые ключи для фамилий
	static $search_key_types = array(
			''				=> 1,
			'metaphone'		=> 101,
			'metascript'	=> 102,
	);
		// Отбираем любые фамилии, кроме тех, у которых индекс ненулевого типа
		// создан позже времени модификации как самой записи, так и алгоритма
		$result = gbdb()->get_column('SELECT DISTINCT `surname` FROM ?_persons AS p' .
			' WHERE NOT EXISTS (' .
				' SELECT 1 FROM ?_idx_search_keys AS sk WHERE p.`id` = sk.`person_id`' .
				' AND sk.`surname_key_type` != 0' .
			    ' AND sk.`update_datetime` > p.`update_datetime`' .
				' AND sk.`update_datetime` > STR_TO_DATE(?exp, "%Y-%m-%d")' .
			' ) ORDER BY `update_datetime` ASC LIMIT 15',
			array('exp' => max(IDX_EXPIRATION_DATE, GB_SEARCH_KEYS_MAKE_DATE)) );
	foreach ($result as $surname){
		// Удаляем устаревшие(!) или нулевого типа ключи для записей с отобранными фамилиям
		gbdb()->query(
				'DELETE FROM sk USING ?_idx_search_keys AS sk' .
				' INNER JOIN ?_persons AS p' .
				' WHERE p.`surname` = ?surname AND p.`id` = sk.`person_id`' .
				' AND (   (sk.`surname_key_type` = 0)' .
				     ' or (sk.`update_datetime` < p.`update_datetime`)' .
				     ' or (sk.`update_datetime` < STR_TO_DATE(?exp, "%Y-%m-%d")))',
				array('surname' => $surname, 'exp' => max(IDX_EXPIRATION_DATE, GB_SEARCH_KEYS_MAKE_DATE) ));
		
		// Создаём ключи для записей с отобранными фамилиями их не имеющих
		$keys = make_search_keys_assoc($surname);
		foreach ($keys as $key){
			foreach ($key as $type => $vals){
				foreach((array) $vals as $v){
					$v = mb_strtoupper($v);
					$mask = !preg_match('/[?*]/uSs', $v) ? '' : GB_DBase::make_condition($v);
					gbdb()->query('INSERT INTO ?_idx_search_keys (`person_id`, `surname_key`,' .
							' `surname_key_type`, `surname_mask`) SELECT p.`id`, ?key, ?type,' .
							' ?mask FROM ?_persons AS p WHERE p.`surname` = ?surname' .
							' AND NOT EXISTS (SELECT 1 FROM ?_idx_search_keys AS sk WHERE p.`id` = sk.`person_id`)',
							array(
									'surname'	=> $surname,
									'key'		=> $v,
									'type'		=> $search_key_types[$type],
									'mask'		=> $mask,
							));
				}
			}
		}
	}
	
	// Обновляем списки вложенных регионов, если это необходимо
	$result = gbdb()->get_column('SELECT `id`, `parent_id` FROM ?_dic_region WHERE `region_ids` = ""',
			array(), TRUE);
	foreach ($result as $id => $parent_id){
		$ids = gbdb()->get_cell('SELECT GROUP_CONCAT(`region_ids`) FROM ?_dic_region WHERE `parent_id` = ?id',
				array('id' => $id));
		$ids = trim(preg_replace('/,,+/uS', ',', $ids) ,',');
		gbdb()->set_row('?_dic_region', array('region_ids' => (empty($ids) ? $id : "$id,$ids")), $id);
		gbdb()->set_row('?_dic_region', array('region_ids' => ''), $parent_id);
	}
	
	// Обновляем полные наименования регионов, если это необходимо
	$result = gbdb()->get_table('SELECT `id`, `parent_id`, `title` FROM ?_dic_region WHERE `region` = ""');
	foreach ($result as $row){
		$parent_region = gbdb()->get_cell('SELECT `region` FROM ?_dic_region WHERE `id` = ?parent_id', $row);
		global $region_short;
		$tmp = trim(
				(empty($parent_region) || substr($parent_region, 0, 1) == '('
						? '' : "$parent_region, ") .
				(substr($row['title'], 0, 1) == '('
						? '' : strtr($row['title'], $region_short)),
				', ');
		if($tmp){
			gbdb()->set_row('?_dic_region', array('region' => $tmp), $row['id']);
			gbdb()->set_row('?_dic_region', array('region' => ''), array('parent_id' => $row['id']));
		}
	}
	
	// Обновляем статистику…
	//
	// … по регионам
	$result = gbdb()->get_column('SELECT `id`, `region_ids` FROM ?_dic_region' .
			' ORDER BY `update_datetime` ASC LIMIT 7', array(), TRUE);
	foreach ($result as $id => $region_ids){
		if(empty($region_ids))	$region_ids = $id;
		$cnt = '';
		if(false !== strpos($region_ids, ',')){
			// У региона есть вложенные регионы — просуммируем их статистику и прибавим к статистике региона
			$childs = gbdb()->get_cell('SELECT SUM(`region_cnt`) FROM ?_dic_region' .
					' WHERE `parent_id` = ?parent_id', array('parent_id' => $id));
			$cnt = $childs . ' + ';
		}
		gbdb()->query('UPDATE LOW_PRIORITY ?_dic_region SET `region_cnt` = ' . $cnt .
				'( SELECT COUNT(*) FROM ?_persons WHERE `region_id` = ?region_ids ),' .
				' `update_datetime` = NOW() WHERE `id` = ?id',
				array('id' => $id, 'region_ids' => $region_ids));
	}
	//
	// … по религиям, семейным положениям, событиям
	foreach(explode(' ', 'religion marital reason') as $key){
		$result = gbdb()->get_column('SELECT `id` FROM ?@table ORDER BY `update_datetime` ASC LIMIT 1',
				array('@table' => "dic_$key"));
		foreach($result as $row){
			gbdb()->query('UPDATE LOW_PRIORITY ?@table SET ?#field_cnt =' .
					' ( SELECT COUNT(*) FROM ?_persons WHERE ?#field_id = ?id ),' .
					' `update_datetime` = NOW() WHERE `id` = ?id',
					array(
						'@table'		=> "dic_$key",
						'#field_cnt'	=> "{$key}_cnt",
						'#field_id'		=> "{$key}_id",
						'id'	=> $row['id'],
					));
		}	// foreach
	}	// foreach
}	// function db_update





/**
 * Функция вывода общей статистики о числе записей в системе.
 */
function show_records_stat(){
	global $dbase;
	
	$cnt	= is_object($dbase)
				? $dbase->records_cnt
				: gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons');
	$cnt2	= gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons_raw');
	//
	// TODO: gettext
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
	// Skip logging if debug mode
	if(GB_DEBUG)	return;

	// Удаляем пустые параметры
	$url = preg_replace_callback('/(?<=\?)(.*)(?=\#|$)/uS', function($matches){
		return implode('&', array_filter(preg_split('/&/uS', $matches[1]), function($val){
			return !preg_match('/^(pg=\d+|.*=)$/uS', $val);
		}));
	}, $_SERVER['REQUEST_URI']);

	if(gbdb()->get_cell('SELECT 1 FROM ?_logs WHERE `url` = ?url AND `datetime` >= NOW() - INTERVAL 1 HOUR',
			array('url' => $url)))
		return;

	$tmp = trim(get_request_attr('region') . ' ' . get_request_attr('place'));
	$squery = trim(get_request_attr('surname') . ' ' . get_request_attr('name') . (empty($tmp) ? '' : " ($tmp)"));

	gbdb()->set_row('?_logs', array(
		'query'		=> $squery,
		'is_robot'	=> is_bot_user(FALSE),
		'url'		=> $url,
		'records_found'	=> $records_found,
		'duration'	=> timer_stop(),
	), FALSE, GB_DBase::MODE_INSERT);
}



/**
 * Автоматическая проверка на предмет перенагрузки системы.
 * 
 * В случае выявления перенагрузки, функция не возвращает ничего, а исполнение скрипта прерывается.
 */
function load_check(){
	$row = gbdb()->get_row('SELECT' .
			' TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) AS `period_in_sec`,' .
			' CEIL(TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) / `requests_counter`) AS `speed`,' .
			' `banned_to_datetime` >= NOW() AS `banned`' .
			' FROM ?_load_check WHERE `ip` = ?ip',
			array('ip'		=> $_SERVER["REMOTE_ADDR"]));

	if(!$row){
		// Первый заход пользователя
		gbdb()->set_row('?_load_check', array('ip' => $_SERVER["REMOTE_ADDR"]), FALSE, GB_DBase::MODE_INSERT);

	}elseif($row['banned'] || (($row['speed'] < 3) && ($row['period_in_sec'] > 30))){
		// Пользователь проштрафился
		gbdb()->query('UPDATE ?_load_check SET `banned_to_datetime` = TIMESTAMPADD(MINUTE, ?ban, NOW())' .
				' WHERE `ip` = ?ip',
				array('ip' => $_SERVER["REMOTE_ADDR"], 'ban' => OVERLOAD_BAN_TIME));

		$protocol = $_SERVER["SERVER_PROTOCOL"];
		if('HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol)	$protocol = 'HTTP/1.0';
		@header("$protocol 503 Service Unavailable", true, 503);
		@header('Retry-After: 600000');
		html_header('Доступ приостановлен', FALSE);
		// TODO: gettext
		print "<div style='color: red; margin: 3em; font-width: bold; text-align: center'>Вы перегружаете систему и были заблокированы на некоторое время. Сделайте перерыв…</div>";
		html_footer();
		die();

	}else{
		// Очередной заход пользователя
		gbdb()->query('UPDATE ?_load_check SET `requests_counter` = `requests_counter` + 1 WHERE `ip` = ?ip',
				array('ip' => $_SERVER["REMOTE_ADDR"]));
	}
}



function get_request_attr($var, $default = ''){
	return isset($_REQUEST[$var]) ? $_REQUEST[$var] : $default;
}
