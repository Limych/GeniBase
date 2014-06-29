<?php
require_once('functions.php');	// Общие функции системы

$dbase = new ww1_database_solders(Q_EXTENDED);

html_header();
?>
<form action="<?php print $_SERVER['PHPH_SELF']?>#report">
	<h2>Форма расширенного поиска</h2>
	<p class="small alignright"><a href="#help">Инструкция по использованию</a> | <a href="/">Упрощённый поиск</a></p>
	<?php $dbase->search_form(); ?>
	<button type="submit">Искать</button>
	<a name="help"></a>
	<p class="nb">Во всех полях можно использовать метасимволы: «_» (подчёркивание)&nbsp;— заменяет один любой символ, «%» (процент)&nbsp;— заменяет любое кол-во любых символов.</p>
	<p class="nb">В списках можно выбирать по нескольку значений. Для этого кликайте мышью держа зажатой клавишу «Ctrl» («Command» для Mac).</p>
	<p class="nb">В полях «Номер списка» и «Страница списка» можно перечислять по&nbsp;нескольку значений через запятую или пробел.</p>
</form>
<?php
if($dbase->have_query){
	$report = $dbase->do_search();

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
		'reason'	=> 'Причина выбытия',
		'date'		=> 'Дата выбытия',
		'source'	=> 'Источник',
		'comments'	=> '',
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
db_close();
?>