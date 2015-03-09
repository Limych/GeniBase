<?php
require_once('gb/gb.php');	// Общие функции системы

$dbase = new ww1_database_solders(Q_SIMPLE);

$tmp = trim(get_request_attr('region') . ' ' . get_request_attr('place'));
$squery = get_request_attr('surname') . ' ' . get_request_attr('name') . (empty($tmp) ? '' : " ($tmp)");
$squery = trim($squery);

$report = null;
if($dbase->have_query){
	load_check();
	$report = $dbase->do_search();
	log_event($report->records_cnt);
}

html_header('Поиск ' . (empty($squery) ? 'персоны' : '"' . esc_html($squery) . '"'),
		($report && $report->records_cnt > 0 && $report->records_cnt <= MAX_RECORDS_INDEXATION));
show_records_stat();
?>
<form action="<?php print $_SERVER['PHP_SELF']?>#report" class='responsive-form no-print'>
	<h2>Поиск персоны</h2>
	<p class="small alignright"><a href="/extsearch.php">Расширенный поиск</a></p>
	<div class='fields'><?php $dbase->search_form(); ?></div>
	<div class="buttons">
		<button class="search" type="submit">Искать</button>
	</div>
	<div id="help">
	<p class="nb">Система при поиске автоматически пытается расширить Ваш запрос с&nbsp;учётом возможных ошибок и&nbsp;сокращений в&nbsp;написании имён и&nbsp;фамилий. Неполные совпадения выводятся в&nbsp;конце списка и&nbsp;выделяются цветом.</p>
	<p class="nb"><strong>Обратите внимание:</strong> во&nbsp;времена Первой мировой войны не&nbsp;было современных республик и&nbsp;областей&nbsp;— были губернии и&nbsp;уезды Российской Империи, границы которых часто отличаются от&nbsp;границ современных территорий. Места жительства в&nbsp;системе указываются по&nbsp;состоянию на&nbsp;даты войны.</p>
	<p class="nb">Если у&nbsp;вас нет русской клавиатуры, вы&nbsp;можете набирать текст в&nbsp;транслите&nbsp;&mdash; он&nbsp;будет автоматически перекодирован в&nbsp;русские буквы. <a href="/translit.php">Таблица перекодировки</a>.</p>
	</div>
</form>
<?php
if($dbase->have_query){
	// Упрощаем результаты для пользователя
	foreach (array_keys($report->records) as $key){
		$report->records[$key]['place'] = trim($report->records[$key]['region'] . ', ' .
				$report->records[$key]['place'], ', ');
		unset($report->records[$key]['region']);
	}

	// Выводим результаты в html
	$brief_fields = array(
		'surname'	=> 'Фамилия',
		'name'		=> 'Имя Отчество',
		'place'		=> 'Губерния, Уезд, Волость, Нас.пункт',
	);
	$detailed_fields = array(
		'rank'		=> 'Воинское звание',
		'religion'	=> 'Вероисповедание',
		'marital'	=> 'Семейное положение',
		'reason'	=> 'Событие',
		'date'		=> 'Дата события',
		'military_unit'		=> 'Место службы',
		'place_of_event'	=> 'Место события',
		'estate_or_title'	=> 'Титул/сословие',
		'additional_info'	=> 'Доп. инф-ция',
		'birthdate'	=> 'Дата рождения',
		'source'	=> 'Источник',
		'comments'	=> '',
	);
	$report->show_report($brief_fields, $detailed_fields);
}

// Выводим ссылки для поисковых роботов на 12 последних результатов поиска
$res = gbdb()->get_table('SELECT `query`, `url` FROM ?_logs WHERE `query` != "" AND `is_robot` = 0' .
		' AND `records_found` AND `datetime` >= NOW() - INTERVAL 3 HOUR');
shuffle($res);
$res = array_slice($res, 0, 12);
foreach ($res as $key => $row){
	if(empty($row['query']))	$row['query'] = '.';
	$res[$key] = "<a href='$row[url]'>" . esc_html($row['query']) . "</a>";
}
if($res)	print "<p class='lastq aligncenter no-print'>Некоторые последние поисковые запросы в систему: " . implode(', ', $res) . "</p>\n";

html_footer();
