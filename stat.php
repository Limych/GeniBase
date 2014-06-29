<?php
require_once('functions.php');	// Общие функции системы

$result = db_query('SELECT COUNT(*) FROM persons');
$cnt = mysql_fetch_array($result, MYSQL_NUM);
mysql_free_result($result);

$result = db_query('SELECT COUNT(*) FROM persons_raw');
$cnt2 = mysql_fetch_array($result, MYSQL_NUM);
mysql_free_result($result);

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
$query = 'SELECT DISTINCT rank FROM persons WHERE rank != "" ORDER BY rank';
$result = db_query($query);
while($row = mysql_fetch_object($result)){
	$query = 'SELECT COUNT(*) FROM persons WHERE rank = "' . $row->rank . '"';
	$result2 = db_query($query);
	$cnt = mysql_fetch_array($result2, MYSQL_NUM);
	mysql_free_result($result2);
	
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->rank) . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n</tr>";
}
mysql_free_result($result);
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
$query = 'SELECT id, religion FROM dic_religion ORDER BY religion';
$result = db_query($query);
while($row = mysql_fetch_object($result)){
	$query = 'SELECT COUNT(*) FROM persons WHERE religion_id = ' . $row->id;
	$result2 = db_query($query);
	$cnt = mysql_fetch_array($result2, MYSQL_NUM);
	mysql_free_result($result2);
	if(empty($cnt[0]))	continue;
	
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->religion) . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n</tr>";
}
mysql_free_result($result);
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
$query = 'SELECT id, marital FROM dic_marital ORDER BY marital';
$result = db_query($query);
while($row = mysql_fetch_object($result)){
	$query = 'SELECT COUNT(*) FROM persons WHERE marital_id = ' . $row->id;
	$result2 = db_query($query);
	$cnt = mysql_fetch_array($result2, MYSQL_NUM);
	mysql_free_result($result2);
	
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->marital) . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n</tr>";
}
mysql_free_result($result);
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
$query = 'SELECT DISTINCT reason FROM persons WHERE reason != "" ORDER BY reason';
$result = db_query($query);
while($row = mysql_fetch_object($result)){
	$query = 'SELECT COUNT(*) FROM persons WHERE reason = "' . $row->reason . '"';
	$result2 = db_query($query);
	$cnt = mysql_fetch_array($result2, MYSQL_NUM);
	mysql_free_result($result2);
	
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($row->reason) . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n</tr>";
}
mysql_free_result($result);
?>
</tbody></table>
<?php
html_footer();
db_close();



function region_stat($parent_id = 0, $level = 1){
	global $even;

	$result = db_query('SELECT id, title, region_ids, region_comment FROM dic_region WHERE parent_id = ' . $parent_id . ' ORDER BY title');
	while($row = mysql_fetch_object($result)){
		if(empty($row->region_ids))	$row->region_ids = $row->id;
		$query = 'SELECT COUNT(*) FROM persons WHERE region_id IN (' . $row->region_ids . ')';
		$result2 = db_query($query);
		$cnt = mysql_fetch_array($result2, MYSQL_NUM);
		mysql_free_result($result2);

		$even = 1-$even;
		print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td class='region region_$level'>" . htmlspecialchars($row->title) . (empty($row->region_comment) ? '' : ' <span class="comment">' . htmlspecialchars($row->region_comment) . '</span>') . "</td>\n\t<td class='alignright'>" . format_num($cnt[0]) . "</td>\n";

		region_stat($row->id, $level + 1);
	}
	mysql_free_result($result);
}

?>