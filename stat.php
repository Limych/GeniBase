<?php
require_once('functions.php');	// Общие функции системы

html_header();
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Общая статистика по базе данных</h1>
<?php
show_records_stat();

dic_stat('Распределение по&nbsp;вероисповеданию', 'Вероисповедание', 'religion');
dic_stat('Распределение по&nbsp;семейному положению', 'Семейное положение', 'marital');
dic_stat('Распределение по&nbsp;причине выбытия', 'Причина выбытия', 'reason');

?>
<table class="stat">
	<caption>Распределение по&nbsp;воинским званиям</caption>
<thead><tr>
	<th>Воинское звание</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
//							0		1
$result = db_query('SELECT rank, COUNT(*) FROM persons GROUP BY rank ORDER BY rank');
while($row = $result->fetch_array(MYSQLI_NUM)){
	$even = 1-$even;
	if(empty($row[0]))
		$row[0] = '(не указано)';
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row[0]) . "</td>\n\t<td class='alignright'>" . format_num($row[1]) . "</td>\n</tr>";
}
$result->free();
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
db_close();



function region_stat($parent_id = 0, $level = 1){
	global $even;

	$result = db_query('SELECT id, title, region_comment, region_cnt FROM dic_region WHERE parent_id = ' . $parent_id . ' ORDER BY title');
	while($row = $result->fetch_object()){
		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region region_$level id_" . $row->id . "'>" . htmlspecialchars($row->title) . (empty($row->region_comment) ? '' : ' <span class="comment">' . htmlspecialchars($row->region_comment) . '</span>') . "</td>\n\t<td class='alignright'>" . format_num($row->region_cnt) . "</td>\n";

		region_stat($row->id, $level + 1);
	}
	$result->free();
}

// function hierarhical_stat($field, $parent_id = 0, $level = 1, $tfield = NULL){
	// global $even;

	// if(empty($tfield))	$tfield = $field;
	// $result = db_query("SELECT id, $tfield, ${field}_comment, ${field}_cnt FROM dic_${field} WHERE parent_id = $parent_id ORDER BY $tfield");
	// while($row = $result->fetch_array(MYSQL_ASSOC)){
		// $even = 1-$even;
		// print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region region_$level id_${row[id]}'>" . htmlspecialchars($row[$tfield]) . (empty($row[$tfield.'_comment']) ? '' : ' <span class="comment">' . htmlspecialchars($row[$tfield.'_comment']) . '</span>') . "</td>\n\t<td class='alignright'>" . format_num($row[$tfield.'_cnt']) . "</td>\n";

		// hierarhical_stat($field, $row->id, $level + 1, $tfield);
	// }
	// $result->free();
// }

function dic_stat($caption, $field_title, $field){
?>

<table class="stat">
	<caption><?php print $caption; ?></caption>
<thead><tr>
	<th><?php print $field_title; ?></th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
//							0			1
$result = db_query("SELECT ${field}, ${field}_cnt FROM dic_${field} WHERE ${field}_cnt != 0 ORDER BY ${field}");
while($row = $result->fetch_array(MYSQLI_NUM)){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row[0]) . "</td>\n\t<td class='alignright'>" . format_num($row[1]) . "</td>\n</tr>";
}
$result->free();
?>
</tbody></table>
<?php
}

?>