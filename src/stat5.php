<?php
require_once('gb/common.php');	// Общие функции системы

html_header('Статистика');
?>
<p><a href="/">« Вернуться к поиску</a></p>
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
<thead><tr>
	<th>Воинское звание</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = $db->get_table('SELECT rank, COUNT(*) AS cnt FROM persons GROUP BY rank ORDER BY rank');
foreach ($result as $row){
	$even = 1-$even;
	if(empty($row['rank']))
		$row['rank'] = '(не указано)';
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row['rank']) . "</td>\n\t<td class='alignright'>" . format_num($row['cnt']) . "</td>\n</tr>";
}
?>
</tbody></table>

<table class="stat">
	<caption>Распределение по&nbsp;регионам Российской Империи</caption>
<thead><tr>
	<th>Губерния, Уезд</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
region_stat();
?>
</tbody></table>
<?php
html_footer();



function region_stat($parent_id = 0, $level = 1){
	global $even, $db;

	$result = $db->get_table('SELECT id, title, region_comment, region_cnt FROM dic_region
			WHERE parent_id = :parent_id ORDER BY title',
			array(
				'parent_id'	=> $parent_id,
			));
	foreach ($result as $row){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region level_$level id_" .
				$row['id'] . "'>" . htmlspecialchars($row['title']) .
				(empty($row['region_comment']) ? '' : ' <span class="comment">' .
						htmlspecialchars($row['region_comment']) . '</span>') .
				"</td>\n\t<td class='alignright'>" . format_num($row['region_cnt']) . "</td>\n";

		region_stat($row['id'], $level + 1);
	}
	$result->free();
}

function dic_stat($caption, $field_title, $field){
	global $db;
	static	$chart_num = 0;
	
	$chart_num++;
?>

<script type="text/javascript">
	$(document).ready(function(){
		var data = google.visualization.arrayToDataTable([
			['<?php print htmlspecialchars($field_title); ?>',  'Записей'],
			<?php
				$result = $db->get_table('SELECT :field AS field, :field_cnt AS cnt FROM :#table WHERE cnt != 0 ORDER BY field',
						array(
							'#table'	=> "dic_$field",
							'field'		=> $field,
							'field_cnt'	=> "{$field}_cnt",
						));
				foreach ($result as $row)
					print "\t\t\t['" . htmlspecialchars($row['field']) . "',  " . $row['cnt'] . "],\n";
			?>
		]);
		
		var options = {
			title:	'<?php print htmlspecialchars($caption); ?>',
			vAxis:	{minValue: 0,	title: 'Потери'},
			legend:	{position: 'none'},
		};
		
		var chart = new google.visualization.PieChart(document.getElementById('chart_<?php print $chart_num; ?>'));
		chart.draw(data, options);
	}
</script>
<div id="chart_<?php print $chart_num; ?>" style="width: 100%; height: 300px"></div>

<table class="stat">
	<caption><?php print $caption; ?></caption>
<thead><tr>
	<th><?php print $field_title; ?></th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
	$result = $db->get_table('SELECT :field AS field, :field_cnt AS cnt FROM :#table
			WHERE cnt != 0 ORDER BY field',
			array(
				'#table'	=> "dic_${field}",
				'field'		=> $field,
				'field_cnt'	=> "{$field}_cnt",
			));
	$even = 0;
	foreach($result as $row){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row['field']) .
				"</td>\n\t<td class='alignright'>" . format_num($row['cnt']) . "</td>\n</tr>";
	}
?>
</tbody></table>
<?php
}
