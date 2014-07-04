<?php
require_once('functions.php');	// Общие функции системы

$result = db_query('SELECT COUNT(*) FROM persons');
$cnt = $result->fetch_array(MYSQL_NUM);
$result->free();

$result = db_query('SELECT COUNT(*) FROM persons_raw');
$cnt2 = $result->fetch_array(MYSQL_NUM);
$result->free();

$txt = format_num($cnt[0], ' запись.', ' записи.', ' записей.');
if($cnt[0] != $cnt2[0]){
	$txt = format_num($cnt2[0], ' запись.', ' записи.', ' записей.') . ' Из них сейчас доступны для поиска ' . $txt;
}

html_header();
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Общая статистика по базе данных</h1>
<p>На данный момент в базе содержится <?php print $txt?></p>
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
$result = db_query('SELECT DISTINCT rank FROM persons WHERE rank != "" ORDER BY rank');
while($row = $result->fetch_object()){
	$result2 = db_query('SELECT COUNT(*) FROM persons WHERE rank = "' . $row->rank . '"');
	$cnt = $result2->fetch_array(MYSQL_NUM);
	$result2->free();
	
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->rank) . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n</tr>";
}
$result->free();
?>
</tbody></table>

<table class="stat">
	<caption>Распределение по&nbsp;вероисповеданию</caption>
<thead><tr>
	<th>Вероисповедание</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = db_query('SELECT id, religion, religion_cnt FROM dic_religion ORDER BY religion');
while($row = $result->fetch_object()){
	if(empty($row->religion_cnt))	continue;
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->religion) . "</td>\n\t<td class='alignright'>" . format_num($row->religion_cnt) . "</td>\n</tr>";
}
$result->free();
?>
</tbody></table>

<table class="stat">
	<caption>Распределение по&nbsp;семейному положению</caption>
<thead><tr>
	<th>Семейное положение</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = db_query('SELECT id, marital, marital_cnt FROM dic_marital ORDER BY marital');
while($row = $result->fetch_object()){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->marital) . "</td>\n\t<td class='alignright'>" . format_num($row->marital_cnt) . "</td>\n</tr>";
}
$result->free();
?>
</tbody></table>

<table class="stat">
	<caption>Распределение по&nbsp;причине выбытия</caption>
<thead><tr>
	<th>Причина выбытия</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
$result = db_query('SELECT id, reason, reason_cnt FROM dic_reason WHERE reason_cnt != 0 ORDER BY reason');
while($row = $result->fetch_object()){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->reason) . "</td>\n\t<td class='alignright'>" . format_num($row->reason_cnt) . "</td>\n</tr>";
}
$result->free();
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
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region region_$level'>" . htmlspecialchars($row->title) . (empty($row->region_comment) ? '' : ' <span class="comment">' . htmlspecialchars($row->region_comment) . '</span>') . "</td>\n\t<td class='alignright'>" . format_num($row->region_cnt) . "</td>\n";

		region_stat($row->id, $level + 1);
	}
	$result->free();
}

?>