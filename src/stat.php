<?php
require_once('gb/gb.php');	// Общие функции системы

html_header('Статистика');
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Общая статистика по базе данных</h1>

<?php
show_records_stat();
?>

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

<table class="stat">
	<caption>Распределение по&nbsp;воинским званиям</caption>
<thead><tr>
	<th>Воинское звание</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = gbdb()->get_table('SELECT rank, COUNT(*) AS cnt FROM ?_persons GROUP BY rank ORDER BY rank');
foreach ($result as $row){
	$even = 1-$even;
	if(empty($row['rank']))
		$row['rank'] = '(не указано)';
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($row['rank']) . "</td>\n\t<td class='align-right'>" . format_num($row['cnt']) . "</td>\n</tr>";
}
?>
</tbody></table>

<table class="stat">
	<caption>Распределение по&nbsp;событиям</caption>
<thead><tr>
	<th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Тип&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
	<th>Название события</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = gbdb()->get_table('SELECT event_type, reason, SUM(reason_cnt) AS cnt FROM ?_dic_reason' .
		' GROUP BY event_type, reason ORDER BY 1,2');
foreach ($result as $row){
	$even = 1-$even;
	if(empty($row['event_type']))	$row['event_type'] = '(не указано)';
	if(empty($row['reason']))		$row['reason'] = '(не указано)';
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t"
				."<td>" . esc_html($row['event_type']) . "</td>\n\t"
				."<td>" . esc_html($row['reason']) . "</td>\n\t"
				."<td class='align-right'>" . format_num($row['cnt']) . "</td>\n"
			."</tr>";
}
?>
</tbody></table>

<?php
dic_stat('Распределение по&nbsp;вероисповеданию', 'Вероисповедание', 'religion');
dic_stat('Распределение по&nbsp;семейному положению', 'Семейное положение', 'marital');
?>


<?php
function region_stat($parent_id = 0, $level = 1){
	global $even;

	$result = gbdb()->get_table('SELECT id, title, region_comment, region_cnt FROM ?_dic_region' .
			' WHERE parent_id = ?parent_id ORDER BY title', array('parent_id' => $parent_id));
	foreach ($result as $row){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region level_$level id_" .
				$row['id'] . "'>" . esc_html($row['title']) .
				(empty($row['region_comment']) ? '' : ' <span class="comment">' .
						esc_html($row['region_comment']) . '</span>') .
				"</td>\n\t<td class='align-right'>" . format_num($row['region_cnt']) . "</td>\n";

		region_stat($row['id'], $level + 1);
	}
}

function dic_stat($caption, $field_title, $field){
?>

<table class="stat">
	<caption><?php print $caption; ?></caption>
<thead><tr>
	<th><?php print $field_title; ?></th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
	$result = gbdb()->get_column('SELECT ?#field, ?#field_cnt FROM ?@table' .
			' WHERE ?#field_cnt != 0 ORDER BY ?#field',
			array(
				'@table'		=> "dic_$field",
				'#field'		=> $field,
				'#field_cnt'	=> "{$field}_cnt",
			), TRUE);
	$even = 0;
	foreach ($result as $field => $cnt){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($field) .
				"</td>\n\t<td class='align-right'>" . format_num($cnt) . "</td>\n</tr>";
	}
?>
</tbody></table>
<?php
}
html_footer();
