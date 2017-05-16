<?php
require_once ('gb-config.php'); // Load GeniBase
require_once ('inc.php'); // Основной подключаемый файл-заплатка

$dbase = new ww1_database_solders(Q_EXTENDED);

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
	<h2><?php _e('Advanced search person', WW1_TXTDOM); ?></h2>
	<p class="small align-right">
		<a href="#help"><?php _e('Search instruction', WW1_TXTDOM)?></a> | <a
			href="/"><?php _e('Simple search', WW1_TXTDOM)?></a>
	</p>
	<?php $dbase->search_form(); ?>
	<div class="buttons">
		<button class="search" type="submit"><?php _ex('Search', 'Button name', WW1_TXTDOM)?></button>
	</div>
	<div id="help">
		<p class="nb"><?php _e('The searching by surname process can be automatically expanded with using the similar surnames. The lists of persons were initially handwritten, and written as it was heard, therefore the same surname in the lists can be put differently.', WW1_TXTDOM);?></p>
		<p class="nb"><?php _e('The searching by name process can be automatically expanded with using the name common abbreviations and shortcuts. Partial matches are displayed at the end of the list and highlighted by color.', WW1_TXTDOM);?></p>
		<p class="nb"><?php _e('In every text fields you can use wildcard characters: “?” (question)&nbsp;&mdash; matches any single character, “*” (asterisk)&nbsp;&mdash; matches a one or more characters of any kind. If you use wildcard characters, automatic expanding of the search does not work.', WW1_TXTDOM);?></p>
		<p class="nb"><?php printf(__('If you do not have Russian keyboard, you can type text in transliteration&nbsp;&mdash; it will be automatically encoded in Russian letters. <a href="%s">See the conversion table.</a>', WW1_TXTDOM), site_url('/translit.php'));?></p>
		<p class="nb"><?php _e('In the lists you can select or deselect several values. To do this, click the mouse while holding down “Ctrl” key (“Command” key for Mac).', WW1_TXTDOM);?></p>
		<p class="nb"><?php _e('In the fields “Source number”, “Source page” and “Record ID” you can list several values separated by commas or spaces.', WW1_TXTDOM);?></p>
	</div>
</form>
</section>
<?php

if (! $dbase->have_query)
    ad();
else {
    // Определяем, какие поля будут выводиться в поле краткой информации, а какие в подробной
    $brief_fields = array(
        'surname' => _x('Surname', 'Field name', WW1_TXTDOM),
        'name' => _x('Other names', 'Field name', WW1_TXTDOM)
    );
    $detailed_fields = array(
        'rank' => _x('Military rank', 'Field name', WW1_TXTDOM),
        'religion' => _x('Religion', 'Field name', WW1_TXTDOM),
        'marital' => _x('Marital status', 'Field name', WW1_TXTDOM),
        'region' => _x('Province, Uezd, Volost', 'Field name', WW1_TXTDOM),
        'place' => _x('Volost/Place', 'Field name', WW1_TXTDOM),
        'reason' => _x('Event', 'Field name', WW1_TXTDOM),
        'date' => _x('Event date', 'Field name', WW1_TXTDOM),
        'military_unit' => _x('Military unit', 'Field name', WW1_TXTDOM),
        'place_of_event' => _x('Place of event', 'Field name', WW1_TXTDOM),
        'estate_or_title' => _x('Title/Class', 'Field name', WW1_TXTDOM),
        'additional_info' => _x('Additional info', 'Field name', WW1_TXTDOM),
        'birthdate' => _x('Birthdate', 'Field name', WW1_TXTDOM),
        'source' => _x('Source', 'Field name', WW1_TXTDOM),
        'id' => _x('Record ID', 'Field name', WW1_TXTDOM),
        'comments' => ''
    ) // Place it always last
;
    $tmp = array();
    foreach (array_keys($detailed_fields) as $key) {
        if (($key == 'date' && (! empty($dbase->query['date_from']) || ! empty($dbase->query['date_to']))) || ! empty($dbase->query[$key]))
            $tmp[] = $key;
    }
    $pos = 0;
    if ((count($tmp) < 2) && ! in_array('region', $tmp)) {
        array_splice($tmp, $pos ++, 0, 'region');
    }
    if ((count($tmp) < 2) && ! in_array('place', $tmp)) {
        if (in_array('region', $tmp))
            $tmp[] = 'place';
        else
            array_splice($tmp, $pos ++, 0, 'place');
    }
    foreach ($tmp as $key) {
        $brief_fields[$key] = $detailed_fields[$key];
        unset($detailed_fields[$key]);
    }
    
    // Выводим результаты в html
    print "<section id='report'>\n";
    ad();
    $report->show_report($brief_fields, $detailed_fields);
    print "</section>\n";
}

html_footer();
