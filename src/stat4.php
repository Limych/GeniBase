<?php
require_once ('gb-config.php'); // Load GeniBase
require_once ('inc.php'); // Основной подключаемый файл-заплатка

html_header('Статистика');
?>
<p>
	<a href="/">« Вернуться к поиску</a>
</p>
<h1>Распределение потерь за весь период войны</h1>
<?php

$cnt = 0;
$hist = array_fill(0, 5 * 12, 0);
$result = gbdb()->get_table('SELECT `date_from`, `date_to`, COUNT(*) AS cnt FROM ?_persons' . ' GROUP BY `date_from`, `date_to`');
foreach ($result as $row) {
    $date1 = intval(preg_replace_callback('/^(\d+)-(\d+)-\d+$/uS', function ($m) {
        return (intval($m[1]) - 1914) * 12 + intval($m[2]) - 1;
    }, $row['date_from']));
    $date2 = intval(preg_replace_callback('/^(\d+)-(\d+)-\d+$/uS', function ($m) {
        return (intval($m[1]) - 1914) * 12 + intval($m[2]) - 1;
    }, $row['date_to']));
    if ($date1 != $date2)
        continue;
    
    $cnt += $row['cnt'];
    $hist[$date1] += $row['cnt'];
}
// var_export($hist);
?>
<p>Всего в этом подсчёте участвует <?php print format_num($cnt, ' запись.', ' записи.', ' записей.')?></p>

<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
	google.load("visualization", "1", {packages:["corechart"]});
	google.setOnLoadCallback(drawChart);

	function drawChart() {
		var data = google.visualization.arrayToDataTable([
			['Месяц/Год',  'Потери'],
<?php
$month = explode(' ', 'Янв Фев Мар Апр Май Июн Июл Авг Сен Окт Ноя Дек');

for ($i = 6; $i <= 58; $i ++) {
    $m = $month[$i % 12];
    $y = 1914 + intval($i / 12);
    print "\t\t\t['$m $y',  ${hist[$i]}],\n";
}
?>
		]);
		
		var options = {
			vAxis:	{minValue: 0,	title: 'Потери'},
			legend:	{position: 'none'},
		};
		
		var chart = new google.visualization.SteppedAreaChart(document.getElementById('chart_div'));
		chart.draw(data, options);
	}
</script>
<div id="chart_div" style="width: 100%; height: 500px"></div>
<?php
html_footer();
