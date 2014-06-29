<?php
require_once('functions.php');	// Общие функции системы

$dbase = new ww1_database_solders(Q_SIMPLE);

html_header();
?>
<p style="text-align: center">На данный момент в базе <?php print format_num($dbase->records_cnt, ' запись.', ' записи.', ' записей.')?></p>
<form action="<?php print $_SERVER['PHPH_SELF']?>#report">
	<h2>Поиск персоны</h2>
	<p class="small alignright"><a href="/extsearch.php">Расширенный поиск</a></p>
	<?php $dbase->search_form(); ?>
	<button type="submit">Искать</button>
	<a name="help"></a>
	<p class="nb">Во всех полях можно использовать метасимволы: "_" (подчёркивание) — заменяет один любой символ, "%" (процент) — заменяет любое кол-во любых символов.</p>
</form>
<?php
if($dbase->have_query){
	$report = $dbase->do_search();

	// Выводим результаты в html
	$brief_fields = array(
		'surname'	=> 'Фамилия',
		'name'		=> 'Имя Отчество',
		'region'	=> 'Губерния, Уезд, Волость',
		'place'		=> 'Волость/Нас.пункт',
	);
	$detailed_fields = array(
		'rank'		=> 'Воинское звание',
		'religion'	=> 'Вероисповедание',
		'marital'	=> 'Семейное положение',
		'reason'	=> 'Причина выбытия',
		'date'		=> 'Дата выбытия',
		'source'	=> 'Источник',
		'comments'	=> '',
	);
	$report->show_report($brief_fields, $detailed_fields);
}
?>
<p style="text-align: center"><a href="/stat.php">Статистика</a> | <a href="/todo.php">ToDo-list</a> | <a href="http://forum.svrt.ru/index.php?showtopic=3936&view=getnewpost" target="_blank">Обсуждение сервиса</a> (<a href="http://forum.svrt.ru/index.php?showtopic=7343&view=getnewpost" target="_blank">техническое</a>)</p>
<?php
html_footer();
require_once('publisher.php');	// Внесение новых данных в систему
db_close();
?>