<?php
require_once('gb/gb.php');	// Общие функции системы

$dbase = new ww1_database_solders(Q_EXTENDED);

$tmp = trim(get_request_attr('region') . ' ' . get_request_attr('place'));
$squery = get_request_attr('surname') . ' ' . get_request_attr('name') . (empty($tmp) ? '' : " ($tmp)");
$squery = trim($squery);

html_header('Поиск' . (empty($squery) ? 'персоны' : '"' . esc_html($squery) . '"'));
show_records_stat();
?>
<form action="<?php print $_SERVER['PHP_SELF']?>#report" class='responsive-form no-print'>
	<h2>Форма расширенного поиска</h2>
	<p class="small alignright"><a href="#help">Инструкция по использованию</a> | <a href="/">Упрощённый поиск</a></p>
	<div class='fields'><?php $dbase->search_form(); ?></div>
	<div class="buttons">
		<button class="search" type="submit">Искать</button>
		<button class="clearForm" type="button">Очистить</button>
	</div>
	<div id="help">
	<p class="nb">Фонетический поиск по фамилиям учитывает близость произношения разных звуков. Изначально списки были рукописными и часто писались «со слов», потому одна и та же фамилия может в списках быть записана очень по-разному.</p>
	<p class="nb">Расширение поиска по именам автоматически добавляет в результаты поиска наиболее часто встречающиеся варианты сокращённых записей имени и/или отчества. Неполные совпадения выводятся в&nbsp;конце списка и&nbsp;выделяются цветом.</p>
	<p class="nb">Во всех текстовых полях можно использовать метасимволы: «?» (вопрос)&nbsp;— заменяет один любой символ, «*» (звёздочка)&nbsp;— заменяет один и&nbsp;более любых символов. При использовании метасимволов фонетический поиск и расширение поиска не действуют.</p>
	<p class="nb">Если у&nbsp;вас нет русской клавиатуры, вы&nbsp;можете набирать текст в&nbsp;транслите&nbsp;&mdash; он&nbsp;будет автоматически перекодирован в&nbsp;русские буквы. <a href="/translit.php">Таблица перекодировки</a>.</p>
	<p class="nb">В списках можно выбирать по&nbsp;нескольку значений. Для этого кликайте мышью держа зажатой клавишу «Ctrl» («Command» для Mac).</p>
	<p class="nb">В полях «Страница источника» и&nbsp;«ID записи» можно перечислять по&nbsp;нескольку значений через запятую или пробел.</p>
	</div>
</form>
<?php
if($dbase->have_query){
	load_check();
	$report = $dbase->do_search();
	log_event($report->records_cnt);

	// Определяем, какие поля будут выводиться в поле краткой информации, а какие в подробной
	$brief_fields = array(
		'surname'	=> 'Фамилия',
		'name'		=> 'Имя Отчество',
	);
	$detailed_fields = array(
		'rank'		=> 'Воинское звание',
		'religion'	=> 'Вероисповедание',
		'marital'	=> 'Семейное положение',
		'region'	=> 'Губерния, Уезд, Волость',
		'place'		=> 'Волость/Нас.пункт',
		'reason'	=> 'Событие',
		'date'		=> 'Дата события',
		'military_unit'		=> 'Место службы',
		'place_of_event'	=> 'Место события',
		'estate_or_title'	=> 'Титул/сословие',
		'additional_info'	=> 'Доп. инф-ция',
		'birthdate'	=> 'Дата рождения',
		'source'	=> 'Источник',
		'comments'	=> '',
		'id'		=> 'ID записи',
	);
	$tmp = array();
	foreach(array_keys($detailed_fields) as $key){
		if(($key == 'date' && (!empty($dbase->query['date_from']) || !empty($dbase->query['date_to'])))
		|| !empty($dbase->query[$key]))
			$tmp[] = $key;
	}
	$pos = 0;
	if((count($tmp) < 2) && !in_array('region', $tmp)){
		array_splice($tmp, $pos++, 0, 'region');
	}
	if((count($tmp) < 2) && !in_array('place', $tmp)){
		if(in_array('region', $tmp))
			$tmp[] = 'place';
		else
			array_splice($tmp, $pos++, 0, 'place');
	}
	foreach($tmp as $key){
		$brief_fields[$key] = $detailed_fields[$key];
		unset($detailed_fields[$key]);
	}

	// Выводим результаты в html
	$report->show_report($brief_fields, $detailed_fields);
}

html_footer();
