<?php
require_once ('gb-load.php'); // Load GeniBase
require_once ('inc.php'); // Основной подключаемый файл-заплатка

$dbase = new ww1_database_solders(Q_SIMPLE);

$tmp = trim(get_request_attr('region') . ' ' . get_request_attr('place'));
$squery = get_request_attr('surname') . ' ' . get_request_attr('name') . (empty($tmp) ? '' : " ($tmp)");
$squery = trim($squery);

$report = null;
if ($dbase->have_query) {
    load_check();
    $report = $dbase->do_search();
    log_event($report->records_cnt);
}

if (empty($squery))
    $title = __('Search person', WW1_TXTDOM);
else
    $title = sprintf(__('Search “%s”', WW1_TXTDOM), $squery);
html_header($title, ($report && $report->records_cnt > 0 && $report->records_cnt <= MAX_RECORDS_INDEXATION));
print "<section id='search-form'>\n";
show_records_stat();
?>
<form action="<?php print $_SERVER['PHP_SELF']?>#report"
	class='responsive-form hide-on-print'>
	<h2><?php _e('Search person', WW1_TXTDOM); ?></h2>
	<p class="small align-right">
		<a href="/extsearch.php"><?php _e('Advanced search', WW1_TXTDOM)?></a>
	</p>
	<?php $dbase->search_form(); ?>
	<div class="buttons">
		<button class="search" type="submit"><?php _ex('Search', 'Button name', WW1_TXTDOM)?></button>
	</div>
	<div id="help">
		<p class="nb"><?php _e('The system automatically trying to expand your request, in view of possible errors in spelling and abbreviations of names. Partial matches are displayed at the end of the list and highlighted by color.', WW1_TXTDOM);?></p>
		<p class="nb"><?php _e('<strong>Please note:</strong> during the First World War there were no modern republics and regions&nbsp;&mdash; were provinces and districts of the Russian Empire, the boundaries of which are often different from the boundaries of the modern territores. Place names are indicated as of the date of the war.', WW1_TXTDOM);?></p>
		<p class="nb"><?php printf(__('If you do not have Russian keyboard, you can type text in transliteration&nbsp;&mdash; it will be automatically encoded in Russian letters. <a href="%s">See the conversion table.</a>', WW1_TXTDOM), site_url('/translit.php'));?></p>
	</div>
</form>
</section>
<?php

if (! $dbase->have_query)
    ad();
else {
    // Упрощаем результаты для пользователя
    foreach (array_keys($report->records) as $key) {
        $report->records[$key]['place'] = trim($report->records[$key]['region'] . ', ' . $report->records[$key]['place'], ', ');
        unset($report->records[$key]['region']);
    }
    
    // Выводим результаты в html
    $brief_fields = array(
        'surname' => _x('Surname', 'Field name', WW1_TXTDOM),
        'name' => _x('Other names', 'Field name', WW1_TXTDOM),
        'place' => _x('Province, Uezd, Volost, Place', 'Field name', WW1_TXTDOM)
    );
    $detailed_fields = array(
        'rank' => _x('Military rank', 'Field name', WW1_TXTDOM),
        'religion' => _x('Religion', 'Field name', WW1_TXTDOM),
        'marital' => _x('Marital status', 'Field name', WW1_TXTDOM),
        'reason' => _x('Event', 'Field name', WW1_TXTDOM),
        'date' => _x('Event date', 'Field name', WW1_TXTDOM),
        'military_unit' => _x('Military unit', 'Field name', WW1_TXTDOM),
        'place_of_event' => _x('Place of event', 'Field name', WW1_TXTDOM),
        'estate_or_title' => _x('Title/Class', 'Field name', WW1_TXTDOM),
        'additional_info' => _x('Additional info', 'Field name', WW1_TXTDOM),
        'birthdate' => _x('Birthdate', 'Field name', WW1_TXTDOM),
        'source' => _x('Source', 'Field name', WW1_TXTDOM),
        'comments' => ''
    );
    
    // Выводим результаты в html
    print "<section id='report'>\n";
    ad();
    $report->show_report($brief_fields, $detailed_fields);
    print "</section>\n";
}

// Выводим ссылки для поисковых роботов на 12 последних результатов поиска
$res = gbdb()->get_table('SELECT `query`, `url` FROM ?_logs WHERE `query` != "" AND `is_robot` = 0' . ' AND `records_found` AND `datetime` >= NOW() - INTERVAL 3 HOUR');
if ($res) {
    shuffle($res);
    $res = array_slice($res, 0, 12);
    foreach ($res as $key => $row) {
        if (empty($row['query']))
            $row['query'] = '.';
        $url = esc_attr($row['url'] . '#report');
        $title = esc_html($row['query']);
        $res[$key] = "<a href='$url'>$title</a>";
    }
    print "<p class='lastq align-center hide-on-print'>" . __('Some last search queries:', WW1_TXTDOM) . ' ' . implode(', ', $res) . "</p>\n";
}

html_footer();
