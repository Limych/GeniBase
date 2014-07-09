<?php
// Проверка версии PHP
if(version_compare(phpversion(), "5.3.0", "<"))	die('<b>ERROR:</b> PHP version 5.3+ needed!');

require_once('functions.php');	// Общие функции системы

$dbase = new ww1_database_solders(Q_SIMPLE);

html_header();
show_records_stat();
?>
<form action="<?php print $_SERVER['PHPH_SELF']?>#report">
	<h2>Поиск персоны</h2>
	<p class="small alignright"><a href="/extsearch.php">Расширенный поиск</a></p>
	<?php $dbase->search_form(); ?>
	<button type="submit">Искать</button>
	<a name="help"></a>
	<p class="nb">Система при поиске автоматически пытается расширить Ваш запрос с&nbsp;учётом возможных ошибок и&nbsp;сокращений в&nbsp;написании имён и&nbsp;фамилий.</p>
	<p class="nb"><strong>Обратите внимание:</strong> во&nbsp;времена Первой Мировой Войны не&nbsp;было современных республик и&nbsp;областей&nbsp;— были губернии и&nbsp;уезды Российской Империи, границы которых часто отличаются от&nbsp;границ современных территорий. Места жительства в&nbsp;системе указываются по&nbsp;состоянию на&nbsp;даты войны.</p>
	<p class="nb">Для поиска частей слов используйте метасимволы: «_» (подчёркивание)&nbsp;— заменяет один любой символ, «%» (процент)&nbsp;— заменяет любое <nobr>кол-во</nobr> любых символов.</p>
</form>
<?php
if($dbase->have_query){
	log_event();
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
<p style="text-align: center; margin-top: 3em;"><a href="/stat.php">Статистика</a> | <a href="/todo.php">ToDo-list</a> | <a href="http://forum.svrt.ru/index.php?showtopic=3936&view=getnewpost" target="_blank">Обсуждение сервиса</a> (<a href="http://forum.svrt.ru/index.php?showtopic=7343&view=getnewpost" target="_blank">техническое</a>) | <a href="crue.php">Команда проекта</a></p>
<?php

// Выводим ссылки для поисковых роботов на 12 последних результатов поиска
$db = db_open();
$stmt = $db->prepare('SELECT `query`, `url` FROM `logs` WHERE `query` != "" ORDER BY datetime DESC LIMIT 12');
$stmt->execute();
$stmt->bind_result($squery, $url);
$res = array();
while($stmt->fetch()){
	if(empty($squery))	$squery = '.';
	$res[] = "<a href='$url'>" . htmlspecialchars($squery) . "</a>";
}
$stmt->close();
print "<p class='lastq aligncenter'>Некоторые последние поисковые запросы в систему: " . implode(', ', $res) . "</p>\n";

html_footer();
db_close();
?>