<?php
require_once ('gb-config.php'); // Load GeniBase
require_once ('inc.php'); // Основной подключаемый файл-заплатка

html_header('Статистика');
?>
<p>
	<a href="/">« Вернуться к поиску</a>
</p>
<h1>Общая статистика по базе данных</h1>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
	google.load("visualization", "1", {packages:["corechart"]});
</script>
<?php
show_records_stat();

dic_stat('Распределение по&nbsp;вероисповеданию', 'Вероисповедание', 'religion');
dic_stat('Распределение по&nbsp;семейному положению', 'Семейное положение', 'marital');
dic_stat('Распределение по&nbsp;событиям', 'События', 'reason');

?>
<table class="stat">
	<caption>Распределение по&nbsp;воинским званиям</caption>
	<thead>
		<tr>
			<th>Воинское звание</th>
			<th>Записей</th>
		</tr>
	</thead>
	<tbody>
<?php
$even = 0;
$result = gbdb()->get_column('SELECT rank, COUNT(*) FROM ?_persons GROUP BY rank ORDER BY rank', array(), TRUE);
foreach ($result as $field => $cnt) {
    $even = 1 - $even;
    if (empty($field))
        $field = '(не указано)';
    print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($field) . "</td>\n\t<td class='align-right'>" . format_num($cnt) . "</td>\n</tr>";
}
?>
</tbody>
</table>

<table class="stat">
	<caption>Распределение по&nbsp;регионам Российской Империи</caption>
	<thead>
		<tr>
			<th>Губерния, Уезд</th>
			<th>Записей</th>
		</tr>
	</thead>
	<tbody>
<?php
$even = 0;
region_stat();
?>
</tbody>
</table>
<?php
html_footer();

function region_stat($parent_id = 0, $level = 1)
{
    global $even;
    
    $result = gbdb()->get_table('SELECT id, title, region_comment, region_cnt FROM ?_dic_regions' . ' WHERE parent_id = ?parent_id ORDER BY title', array(
        'parent_id' => $parent_id
    ));
    foreach ($result as $row) {
        $even = 1 - $even;
        print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region level_$level id_" . $row['id'] . "'>" . esc_html($row['title']) . (empty($row['region_comment']) ? '' : ' <span class="comment">' . esc_html($row['region_comment']) . '</span>') . "</td>\n\t<td class='align-right'>" . format_num($row['region_cnt']) . "</td>\n";
        
        region_stat($row['id'], $level + 1);
    }
}

function dic_stat($caption, $field_title, $field)
{
    static $chart_num = 0;
    
    $chart_num ++;
    ?>

<script type="text/javascript">
	$(document).ready(function(){
		var data = google.visualization.arrayToDataTable([
			['<?php print esc_js($field_title); ?>',  'Записей'],
<?php
    $result = gbdb()->get_column('SELECT ?#field, ?#field_cnt FROM ?@table' . ' WHERE ?#field_cnt != 0 ORDER BY ?#field', array(
        '@table' => "dic_{$field}s",
        '#field' => $field,
        '#field_cnt' => "{$field}_cnt"
    ), TRUE);
    foreach ($result as $fld => $cnt)
        print "\t\t\t['" . esc_js($fld) . "',  " . intval($cnt) . "],\n";
    ?>
		]);
		
		var options = {
			title:	'<?php print esc_js($caption); ?>',
			vAxis:	{minValue: 0,	title: 'Потери'},
			legend:	{position: 'none'},
		};
		
		var chart = new google.visualization.PieChart(document.getElementById('chart_<?php print $chart_num; ?>'));
		chart.draw(data, options);
	}
</script>
<div id="chart_<?php print $chart_num; ?>"
	style="width: 100%; height: 300px"></div>

<table class="stat">
	<caption><?php print $caption; ?></caption>
	<thead>
		<tr>
			<th><?php print $field_title; ?></th>
			<th>Записей</th>
		</tr>
	</thead>
	<tbody>
<?php
    $result = gbdb()->get_column('SELECT ?#field, ?#field_cnt FROM ?@table' . ' WHERE ?#field_cnt != 0 ORDER BY ?#field', array(
        '@table' => "dic_${field}s",
        '#field' => $field,
        '#field_cnt' => "{$field}_cnt"
    ), TRUE);
    $even = 0;
    foreach ($result as $field => $cnt) {
        $even = 1 - $even;
        print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($field) . "</td>\n\t<td class='align-right'>" . format_num($cnt) . "</td>\n</tr>";
    }
    ?>
</tbody>
</table>
<?php
}
