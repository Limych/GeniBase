<?php
require_once('gb/common.php');	// Общие функции системы



html_header('Статистика');
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Распределение потерь за весь период войны</h1>
<?php

$cnt = 0;
$hist = array_fill(0, 5 * 12, 0);
$result = db_query('SELECT `date_from`, `date_to`, COUNT(*) FROM `persons` GROUP BY `date_from`, `date_to`');
while($row = $result->fetch_array(MYSQL_NUM)){
	$date1 = intval(preg_replace_callback('/^(\d+)-(\d+)-\d+$/uS', function($m){
		return (intval($m[1]) - 1914) * 12 + intval($m[2]) - 1;
	}, $row[0]));
	$date2 = intval(preg_replace_callback('/^(\d+)-(\d+)-\d+$/uS', function($m){
		return (intval($m[1]) - 1914) * 12 + intval($m[2]) - 1;
	}, $row[1]));
	if($date1 != $date2)	continue;

	$cnt += $row[2];
	$hist[$date1] += $row[2];
}
$result->free();
// var_export($hist);
?>
<p>Всего в этом подсчёте участвует <?php print format_num($cnt, ' запись.', ' записи.', ' записей.')?></p>
<?php
$max = max($hist);
$month = explode(' ', 'Янв Фев Мар Апр Май Июн Июл Авг Сен Окт Ноя Дек');
$years = explode(' ', 'red orange green lightblue blue purple');

print "<div style='height: 20em'>\n";
for($i = 7; $i <= 58; $i++){
	$m = $month[$i % 12];
	$y = 1914 + intval($i / 12);
	$tmp = strtr(round(98 * $hist[$i] / $max + ($hist[$i] ? 2 : 0), 2), ',', '.');
	print "<span style='height: $tmp%; background: " . $years[intval($i / 12)] . "; display: inline-block; width: 1.92%; vertical-align: text-bottom;' title='$m $y: ${hist[$i]}'>&nbsp;</span>";
}
print "</div>\n";

$tmp = array();
for($y = 1914; $y <= 1918; $y++){
	$tmp[] = "<span style='background: " . $years[$y - 1914] . "; padding: 0.25em 1em'>$y</span>";
}
print "<p>" . implode(' ', $tmp) . "</p>";

html_footer();
db_close();

?>