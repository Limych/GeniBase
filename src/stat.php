<?php
require_once('gb/common.php');	// Общие функции системы

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
	<caption>Распределение по&nbsp;событиям</caption>
<thead><tr>
	<th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Тип&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
	<th>Название события</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = $db->get_table('SELECT event_type, reason, SUM(reason_cnt) AS cnt FROM dic_reason
		GROUP BY event_type, reason ORDER BY 1,2');
foreach ($result as $row){
	$even = 1-$even;
	if(empty($row['event_type']))	$row['event_type'] = '(не указано)';
	if(empty($row['reason']))		$row['reason'] = '(не указано)';
	if(strlen(htmlspecialchars($row['reason'])) > 100){
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t"
				."<td>" .             htmlspecialchars($row['event_type']) .              "</td>\n\t"
				."<td>" . '<small>' . htmlspecialchars($row['reason']) . '</small>' . "</td>\n\t"
				."<td class='alignright'>" . format_num($row['cnt']) . "</td>\n"
			."</tr>";
	}else{
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t"
				."<td>" . htmlspecialchars($row['event_type']) . "</td>\n\t"
				."<td>" . htmlspecialchars($row['reason']) . "</td>\n\t"
				."<td class='alignright'>" . format_num($row['cnt']) . "</td>\n"
			."</tr>";
	}
}
?>
</tbody></table>

<?php
dic_stat('Распределение по&nbsp;вероисповеданию', 'Вероисповедание', 'religion');
dic_stat('Распределение по&nbsp;семейному положению', 'Семейное положение', 'marital');
?>


<?php
function region_stat($parent_id = 0, $level = 1){
	global $even, $db;

	$result = $db->get_table('SELECT id, title, region_comment, region_cnt FROM dic_region
			WHERE parent_id = :parent_id ORDER BY title',
			array('parent_id' => $parent_id));
	foreach ($result as $row){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region level_$level id_" .
				$row->id . "'>" . htmlspecialchars($row['title']) .
				(empty($row['region_comment']) ? '' : ' <span class="comment">' .
						htmlspecialchars($row['region_comment']) . '</span>') .
				"</td>\n\t<td class='alignright'>" . format_num($row['region_cnt']) . "</td>\n";

		region_stat($row['id'], $level + 1);
	}
}

function dic_stat($caption, $field_title, $field){
	global $db;
?>

<table class="stat">
	<caption><?php print $caption; ?></caption>
<thead><tr>
	<th><?php print $field_title; ?></th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = $db->get_table('SELECT :#field AS field, :#field_cnt AS cnt FROM :#table WHERE field != 0 ORDER BY field',
		array(
			'#table'		=> "dic_$field",
			'#field'		=> $field,
			'#field_cnt'	=> $field . '_cnt',
		));
foreach ($result as $row){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row[]) . "</td>\n\t<td class='alignright'>" . format_num($row[1]) . "</td>\n</tr>";
}
$result->free();
?>
</tbody></table>
<?php
}
html_footer();
