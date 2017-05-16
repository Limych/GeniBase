<?php

// ** Лимиты ** //
define('Q_LIMIT', 20); // Лимит числа строк на одной странице результатов поиска
define('P_LIMIT', 70); // Лимит числа единовременно публикуемых записей

define('OVERLOAD_BAN_TIME', 60); // На сколько минут блокируется нарушитель, вызвавший перегрузку системы
                                 
// ** Параметры обновления индексов ** //
define('IDX_EXPIRATION_DATE', "2015-02-12"); // YYYY-MM-DD Дата, созданные ранее которой индексы необходимо пересчитать
                                             
// ** Хранимый в базе период дат ** //
define('MIN_DATE', '1913-01-01');
define('MAX_DATE', '1920-12-31');

// Индексировать только результаты поиска, в которых не более заданного числа строк
define('MAX_RECORDS_INDEXATION', 2000);

// Подключаем языковый файл проекта
define('WW1_TXTDOM', '1914');
load_textdomain(WW1_TXTDOM, BASE_DIR . '/languages/' . get_locale() . '.mo');

// Подключаем основные файлы проекта
require_once (BASE_DIR . '/class.ww1-database.php');
require_once (BASE_DIR . '/class.ww1-records-set.php');

// Таблица сокращений регионов
static $region_short = array(
    ' генерал-губернаторство' => ' ген.-губ.',
    ' наместничество' => ' нам.',
    ' губерния' => ' губ.',
    ' область' => ' обл.',
    ' уезд' => ' у.',
    ' волость' => ' вол.',
    ' округа' => ' окр.'
);

/**
 * Вывод начальной части страницы
 *
 * @param string $title
 *            the page.
 * @param boolean $do_index
 *            restrict indexation of page.
 */
function html_header($title, $do_index = TRUE)
{
    gb_enqueue_style('styles', site_url('/css/styles.css'), array(
        'gb-core',
        'responsive-tables',
        'forms'
    ));
    
    $robots = $do_index ? 'All' : 'NoIndex,Follow';
    
    @header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"
	<?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, user-scalable=no" />
<meta name='robots' content='<?php print $robots; ?>' />

<title><?php echo esc_html($title); ?>&nbsp;- <?php esc_html_ex('The First World War, 1914&ndash;1918', 'HTML-page title', WW1_TXTDOM); ?></title>

<link rel="icon" type="image/vnd.microsoft.icon"
	href="<?php print site_url('/favicon.ico'); ?>" />
<link rel="shortcut icon" type="image/vnd.microsoft.icon"
	href="<?php print site_url('/favicon.ico'); ?>" />
<?php gb_head(); ?>
</head>
<body>
	<div id="adsense" style="position: absolute; left: -9999px;">&nbsp;</div>
	<div class="afs_ads" style="position: absolute; left: -9999px;">&nbsp;</div>
	<header>
		<div class='logo'>
			<a href="<?php print site_url(); ?>" tabindex="-2"><img
				src="<?php print site_url('/img/logo.jpg'); ?>" alt='' /></a>
		</div>
		<div class='title'>
			<h1><?php _ex('The First World War, <nobr>1914&ndash;1918</nobr>', 'Project title in header', WW1_TXTDOM);?></h1>
			<div>
				<a href="http://www.svrt.ru/" tabindex="-1"><?php _e('The project of Union of Revival of Pedigree Traditions', WW1_TXTDOM)?></a>
			</div>
		</div>
	</header>
<?php
    
    if (file_exists(BASE_DIR . '/info_msg.php')) {
        print "<section class='message'>";
        require_once (BASE_DIR . '/info_msg.php');
        print "</section>\n";
    }
}

/**
 * Вывод хвостовой части страницы
 */
function html_footer()
{
    ?>
<footer>
		<p style="text-align: center; margin-top: 3em" class="hide-on-print">
			<a href="<?php print site_url('/stat.php'); ?>"><?php _e('Statistic', WW1_TXTDOM);?></a>
			| <a href="<?php print site_url('/guestbook/'); ?>"><?php _e('Guestbook', WW1_TXTDOM);?></a>
			| <a href="http://forum.svrt.ru/index.php?showforum=127"
				target="_blank"><?php _e('Discussion about the project', WW1_TXTDOM);?></a>
			| <a href="<?php print site_url('/crew.php'); ?>"><?php _e('Project crew', WW1_TXTDOM);?></a>
		</p>
		<p class="copyright">
			<strong><?php _e('Please note:', WW1_TXTDOM);?></strong> <?php _e('This lists are placed in the public domain for non-commercial research only. The use of this lists for commercial purposes without the express consent of the lists owners and the project participants is prohibited.', WW1_TXTDOM);?></p>
<?php if( GB_DEBUG): ?>
	<p>
			<small>Statistic: <?php
        print(timer_stop(0, 3) . 's');
        if (function_exists('memory_get_usage'))
            print(' / ' . round(memory_get_usage() / 1024 / 1024, 2) . 'mb ');
        ?></small>
		</p>
<?php endif; ?>
</footer>
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
<?php gb_footer(); ?>
</body>
</html>
<?php
}

function ad()
{
    ?>
<section class="ad hide-on-print">
	<script async
		src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
	<!-- 1914 - Основной -->
	<ins class="adsbygoogle" style="display: block"
		data-ad-client="ca-pub-7764169370455068" data-ad-slot="5537165712"
		data-ad-format="auto"></ins>
	<script>
	(adsbygoogle = window.adsbygoogle || []).push({});
	</script>
</section>
<?php
}

/**
 * Periodically publish new data to main database.
 *
 * @param string $force            
 */
function publish_cron($force = false)
{
    if (! $force) {
        $max_date = gbdb()->get_cell('SELECT MAX(update_datetime) FROM ?_persons_raw');
        $tmp = date_diff(date_create($max_date), date_create('now'));
        $tmp = intval($tmp->format('%i'));
        // var_export($tmp); // TODO: Remove this?
        if ($tmp < 1)
            return;
    }
    
    require_once (BASE_DIR . '/edit/functions.publish.php'); // Функции формализации данных
                                                            
    // Делаем выборку записей для публикации
    $drafts = gbdb()->get_table('SELECT * FROM ?_persons_raw WHERE `status` = "Draft"' . ' ORDER BY RAND() LIMIT ' . P_LIMIT);
    
    // Нормирование данных
    foreach ($drafts as $raw) {
        if (GB_DEBUG)
            print "\n\n======================================\n";
        if (GB_DEBUG)
            var_export($row);
        $pub = prepublish($raw, $have_trouble, $date_norm);
        if (GB_DEBUG)
            var_export($have_trouble);
        if (GB_DEBUG)
            var_export($pub);
            // Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
        if (! $have_trouble)
            gbdb()->set_row('?_persons', $pub, FALSE, GB_DBase::MODE_REPLACE);
            //
        gbdb()->set_row('?_persons_raw', array(
            'status' => ($have_trouble ? 'Cant publish' : 'Published')
        ), $raw['id']);
    }
}

/**
 * Periodically update calculated fields in database.
 */
function db_update()
{
    // Skip updation for debug mode but not for testing mode
    // if( GB_DEBUG && !defined('GB_TESTING_MODE')) return; // TODO: Remove this?
    
    // Удаляем устаревшие записи из таблицы контроля нагрузки на систему
    gbdb()->query('DELETE FROM ?_load_check WHERE (`banned_to_datetime` IS NULL' . ' AND TIMESTAMPDIFF(HOUR, `first_request_datetime`, NOW()) > 3)' . ' OR `banned_to_datetime` < NOW()');
    
    // Удаляем поисковые ключи, которым не на что ссылаться
    gbdb()->query('DELETE FROM ?_idx_search_keys WHERE NOT EXISTS ( SELECT 1 FROM ?_persons ' . 'WHERE id = person_id )');
    
    // Генерируем поисковые ключи для фамилий
    // NB: В начале мы делаем выборку того, что НАДО СОХРАНИТЬ!
    $result = gbdb()->get_column('SELECT DISTINCT `surname` FROM ?_persons AS p' . ' WHERE NOT EXISTS (' . ' SELECT 1 FROM ?_idx_search_keys AS sk WHERE p.`id` = sk.`person_id`' . ' AND sk.`surname_key_type` != 0 AND p.`update_datetime` < sk.`update_datetime`' . ' AND sk.`update_datetime` > STR_TO_DATE(?exp, "%Y-%m-%d")' . ' ) ORDER BY `update_datetime` ASC LIMIT 15', array(
        'exp' => max(IDX_EXPIRATION_DATE, GB_METAKEYS_MAKE_DATE)
    ));
    foreach ($result as $surname) {
        // Delete old metakeys
        gbdb()->query('DELETE FROM sk USING ?_idx_search_keys AS sk' . ' INNER JOIN ?_persons AS p WHERE p.`surname` = ?surname AND p.`id` = sk.`person_id`', array(
            'surname' => $surname
        ));
        
        $names = preg_split('/[^\w\?\*]+/uS', $surname, - 1, PREG_SPLIT_NO_EMPTY);
        foreach ($names as $name) {
            $metakeys = make_metakeys(array(
                GB_MK_SURNAME => $name
            ));
            foreach ($metakeys as $type => $mks) {
                foreach ($mks as $key) {
                    if ($key == '')
                        continue;
                    
                    $mask = ! preg_match('/[?*]/uSs', $key) ? '' : GB_DBase::make_condition($key);
                    gbdb()->query('INSERT INTO ?_idx_search_keys (`person_id`, `surname_key`,' . ' `surname_key_type`, `surname_mask`) SELECT `id`, ?key, ?type,' . ' ?mask FROM ?_persons WHERE `surname` = ?surname', array(
                        'surname' => $surname,
                        'key' => $key,
                        'type' => $type,
                        'mask' => $mask
                    ));
                } // foreach
            } // foreach
        } // foreach
    } // foreach
      
    // Обновляем списки вложенных регионов, если это необходимо
    $result = gbdb()->get_column('SELECT `id`, `parent_id` FROM ?_dic_regions WHERE `region_ids` = ""', array(), TRUE);
    foreach ($result as $id => $parent_id) {
        $ids = gbdb()->get_cell('SELECT GROUP_CONCAT(`region_ids`) FROM ?_dic_regions WHERE `parent_id` = ?id', array(
            'id' => $id
        ));
        $ids = trim(preg_replace('/,,+/uS', ',', $ids), ',');
        gbdb()->set_row('?_dic_regions', array(
            'region_ids' => (empty($ids) ? $id : "$id,$ids")
        ), $id);
        gbdb()->set_row('?_dic_regions', array(
            'region_ids' => ''
        ), $parent_id);
    }
    
    // Обновляем полные наименования регионов, если это необходимо
    $result = gbdb()->get_table('SELECT `id`, `parent_id`, `title` FROM ?_dic_regions WHERE `region` = ""');
    foreach ($result as $row) {
        $parent_region = gbdb()->get_cell('SELECT `region` FROM ?_dic_regions WHERE `id` = ?parent_id', $row);
        global $region_short;
        $tmp = trim((empty($parent_region) || substr($parent_region, 0, 1) == '(' ? '' : "$parent_region, ") . (substr($row['title'], 0, 1) == '(' ? '' : strtr($row['title'], $region_short)), ', ');
        if ($tmp) {
            gbdb()->set_row('?_dic_regions', array(
                'region' => $tmp
            ), $row['id']);
            gbdb()->set_row('?_dic_regions', array(
                'region' => ''
            ), array(
                'parent_id' => $row['id']
            ));
        }
    }
    
    // Обновляем статистику…
    //
    // … по регионам
    $result = gbdb()->get_column('SELECT `id`, `region_ids` FROM ?_dic_regions' . ' ORDER BY `update_datetime` ASC LIMIT 7', array(), TRUE);
    foreach ($result as $id => $region_ids) {
        if (empty($region_ids))
            $region_ids = $id;
        $cnt = '';
        if (false !== strpos($region_ids, ',')) {
            // У региона есть вложенные регионы — просуммируем их статистику и прибавим к статистике региона
            $childs = gbdb()->get_cell('SELECT SUM(`region_cnt`) FROM ?_dic_regions' . ' WHERE `parent_id` = ?parent_id', array(
                'parent_id' => $id
            ));
            $cnt = $childs . ' + ';
        }
        gbdb()->query('UPDATE LOW_PRIORITY ?_dic_regions SET `region_cnt` = ' . $cnt . '( SELECT COUNT(*) FROM ?_persons WHERE `region_id` = ?region_ids ),' . ' `update_datetime` = NOW() WHERE `id` = ?id', array(
            'id' => $id,
            'region_ids' => $region_ids
        ));
    }
    //
    // … по религиям, семейным положениям, событиям
    foreach (explode(' ', 'religion marital reason') as $key) {
        $result = gbdb()->get_column('SELECT `id` FROM ?@table ORDER BY `update_datetime` ASC LIMIT 1', array(
            '@table' => "dic_{$key}s"
        ));
        foreach ($result as $row) {
            gbdb()->query('UPDATE LOW_PRIORITY ?@table SET ?#field_cnt =' . ' ( SELECT COUNT(*) FROM ?_persons WHERE ?#field_id = ?id ),' . ' `update_datetime` = NOW() WHERE `id` = ?id', array(
                '@table' => "dic_{$key}s",
                '#field_cnt' => "{$key}_cnt",
                '#field_id' => "{$key}_id",
                'id' => $row['id']
            ));
        } // foreach
    } // foreach
}
 // function db_update

/**
 * Функция вывода общей статистики о числе записей в системе.
 */
function show_records_stat()
{
    global $dbase;
    
    $cnt = is_object($dbase) ? $dbase->records_cnt : gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons');
    $cnt2 = gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons_raw');
    
    if ($cnt == $cnt2) {
        $out = sprintf(_n('Currently the database contains %s record.', 'Currently the database contains %s records.', $cnt, WW1_TXTDOM), number_format_i18n($cnt));
    } else {
        $out = sprintf(_n('Currently the database contains %s record.', 'Currently the database contains %s records.', $cnt2, WW1_TXTDOM), number_format_i18n($cnt2)) . sprintf(_n(' Of these, %s record is now available for search.', ' Of these, %s records is now available for search.', $cnt, WW1_TXTDOM), number_format_i18n($cnt));
    }
    print "<p class='align-center'>$out</p>\n";
}

/**
 * ******************************************************************************
 * Функции протоколирования использования системы
 */

/**
 * Функция добавления в логи информации о текущем запросе.
 *
 * @param integer $records_found            
 */
function log_event($records_found = 0)
{
    // Skip logging if debug mode
    if (GB_DEBUG)
        return;
        
        // Удаляем пустые параметры
    $url = preg_replace_callback('/(?<=\?)(.*)(?=\#|$)/uS', function ($matches) {
        return implode('&', array_filter(preg_split('/&/uS', $matches[1]), function ($val) {
            return ! preg_match('/^(pg=\d+|.*=)$/uS', $val);
        }));
    }, $_SERVER['REQUEST_URI']);
    
    if (gbdb()->get_cell('SELECT 1 FROM ?_logs WHERE `url` = ?url AND `datetime` >= NOW() - INTERVAL 3 HOUR', array(
        'url' => $url
    )))
        return;
    
    $tmp = trim(get_request_attr('region') . ' ' . get_request_attr('place'));
    $squery = trim(get_request_attr('surname') . ' ' . get_request_attr('name') . (empty($tmp) ? '' : " ($tmp)"));
    
    global $gb_timer;
    $timetotal = microtime(true) - $gb_timer['start'];
    gbdb()->set_row('?_logs', array(
        'query' => $squery,
        'uid' => is_bot_user(FALSE) ? '' : GB_User::hash(),
        'is_robot' => is_bot_user(FALSE),
        'url' => $url,
        'records_found' => $records_found,
        'duration' => $timetotal
    ), FALSE, GB_DBase::MODE_INSERT);
}

/**
 * Автоматическая проверка на предмет перенагрузки системы.
 *
 * В случае выявления перенагрузки, функция не возвращает ничего, а исполнение скрипта прерывается.
 */
function load_check()
{
    $row = gbdb()->get_row('SELECT' . ' TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) AS `period_in_sec`,' . ' CEIL(TIMESTAMPDIFF(SECOND, `first_request_datetime`, NOW()) / `requests_counter`) AS `speed`,' . ' `banned_to_datetime` >= NOW() AS `banned`' . ' FROM ?_load_check WHERE `ip` = ?ip', array(
        'ip' => $_SERVER["REMOTE_ADDR"]
    ));
    
    if (! $row) {
        // Первый заход пользователя
        gbdb()->set_row('?_load_check', array(
            'ip' => $_SERVER["REMOTE_ADDR"]
        ), FALSE, GB_DBase::MODE_INSERT);
    } elseif ($row['banned'] || (($row['speed'] < 3) && ($row['period_in_sec'] > 30))) {
        // Пользователь проштрафился
        gbdb()->query('UPDATE ?_load_check SET `banned_to_datetime` = TIMESTAMPADD(MINUTE, ?ban, NOW())' . ' WHERE `ip` = ?ip', array(
            'ip' => $_SERVER["REMOTE_ADDR"],
            'ban' => OVERLOAD_BAN_TIME
        ));
        
        @header('Retry-After: 600000');
        $title = 'Доступ приостановлен';
        $message = __('You are overload the database and have been blocked for some time. Please, take a break...', WW1_TXTDOM);
        gb_die($message, $title, 'response=503');
    } else {
        // Очередной заход пользователя
        gbdb()->query('UPDATE ?_load_check SET `requests_counter` = `requests_counter` + 1 WHERE `ip` = ?ip', array(
            'ip' => $_SERVER["REMOTE_ADDR"]
        ));
    }
}

function get_request_attr($var, $default = '')
{
    return isset($_REQUEST[$var]) ? $_REQUEST[$var] : $default;
}

/**
 * Функция форматирования числа и вывода сопровождающего слова в правильном склонении
 * 
 * @deprecated
 *
 */
function format_num($number, $tail_1 = Null, $tail_2 = Null, $tail_5 = Null)
{
    _deprecated_function('2.0.0', '_n');
    
    $formatted = number_format_i18n($number);
    
    if (! empty($tail_1)) {
        if ($tail_2 == Null)
            $tail_2 = $tail_1;
        if ($tail_5 == Null)
            $tail_5 = $tail_2;
        
        $sng = intval($number) % 10;
        $dec = intval($number) % 100 - $sng;
        $formatted .= ($dec == 10 ? $tail_5 : ($sng == 1 ? $tail_1 : ($sng >= 2 && $sng <= 4 ? $tail_2 : $tail_5)));
    }
    
    return $formatted;
}
 // function format_num()

/**
 * Функция расширения поискового запроса по именам
 */
function expand_names($names)
{
    $names = preg_split('/\s+/uS', strtr(mb_strtoupper($names), 'Ё', 'Е'));
    $have_name = false;
    foreach ($names as $key => $n) {
        $exp = array(
            $n
        );
        if (preg_match('/\b\w+(ВНА|[ВМТ]ИЧ|[МТ]ИЧНА|ИН|[ОЕ]В(Н?А)?)\b/uS', $n) && ! in_array($n, array(
            'ВАЛЕНТИН',
            'КОНСТАНТИН'
        ))) {
            // Это отчество
            $n2 = preg_replace('/НА$/uS', 'А', preg_replace('/ИЧ$/uS', '', $n));
            if ($n != $n2)
                $exp[] = $n2;
            $n3 = preg_replace('/А$/uS', 'НА', $n2);
            if ($n != $n3)
                $exp[] = $n3;
            $n3 = $n2 . 'ИЧ';
            if ($n != $n3)
                $exp[] = $n3;
            
            $result = gbdb()->get_column('SELECT `expand` FROM ?_dic_names WHERE `key` IN (?keys)' . ' AND `is_patronimic` = 1', array(
                'keys' => $exp
            ));
            foreach ($result as $tmp)
                $exp = array_merge($exp, explode(' ', $tmp));
            
            $names[$key] = '[[:blank:]](' . implode('|', array_unique($exp)) . ')[[:>:]]';
        } elseif (! $have_name) {
            // Это имя
            $result = gbdb()->get_column('SELECT `expand` FROM ?_dic_names WHERE `key` = ?key' . ' AND `is_patronimic` = 0', array(
                'key' => $n
            ));
            foreach ($result as $tmp)
                $exp = array_merge($exp, explode(' ', $tmp));
            
            $names[$key] = '^(' . implode('|', array_unique($exp)) . ')[[:>:]]';
            $have_name = true;
        } else {
            // Это непонятно что
            $result = gbdb()->get_column('SELECT `expand` FROM ?_dic_names WHERE `key` = ?key', array(
                'key' => $n
            ));
            foreach ($result as $tmp)
                $exp = array_merge($exp, explode(' ', $tmp));
            
            $names[$key] = '[[:<:]](' . implode('|', array_unique($exp)) . ')[[:>:]]';
        }
    }
    return $names;
}
 // function expand_names()
function simplify_query($query)
{
    // Если вместо строки передан массив, обработать каждое значение в отдельности
    // и вернуть результат в виде массива
    if (is_array($query))
        return array_map(__FUNCTION__, $query);
    
    $query = preg_replace('/(\*+)(\?+)/uSs', '$2$1', $query);
    $query = preg_replace_callback('/\*{2,}/uSs', function ($m) {
        return str_repeat('?', strlen($m[0]) - 1) . '*';
    }, $query);
    $query = preg_replace('/^\*$/uSs', '', $query);
	return $query;
}
