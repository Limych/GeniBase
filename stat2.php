<?php
require_once('functions.php');	// Общие функции системы
html_header();
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Специальная статистика по базе данных</h1>
<?php

$name_reductions = $names = $patronymic_reductions = $patronymics = array();
$result = db_query('SELECT DISTINCT name FROM persons_raw');
while($row = mysql_fetch_array($result, MYSQL_NUM)){
	$row = preg_split('/\s+/uS', $row[0]);
	if(empty($row))	continue;
	$key = array_shift($row);
	if(false !== strpos($key, '.'))
		$name_reductions[$key] = 0;
	else
		$names[$key] = 0;
	foreach($row as $key){
		if(false !== strpos($key, '.'))
			$patronymic_reductions[$key] = 0;
		else
			$patronymics[$key] = 0;
	}
}
mysql_free_result($result);

foreach(array_keys($name_reductions) as $key){
	$result = db_query('SELECT COUNT(*) FROM persons_raw WHERE name = "' . $key . '" OR name LIKE "' . $key . ' %"');
	$row = mysql_fetch_array($result, MYSQL_NUM);
	mysql_free_result($result);
	$name_reductions[$key] = $row[0];
}
uasort($name_reductions, 'cmp');

// foreach(array_keys($names) as $key){
	// $result = db_query('SELECT COUNT(*) FROM persons_raw WHERE name = "' . $key . '" OR name LIKE "' . $key . ' %"');
	// $row = mysql_fetch_array($result, MYSQL_NUM);
	// mysql_free_result($result);
	// $names[$key] = $row[0];
// }

// foreach($patronymic_reductions as $key){
	// $result = db_query('SELECT COUNT(*) FROM persons_raw WHERE name LIKE "% ' . $key . '" OR name LIKE "% ' . $key . ' %"');
	// $row = mysql_fetch_array($result, MYSQL_NUM);
	// mysql_free_result($result);
	// $patronymic_reductions[$key] = $row[0];
// }

// foreach($patronymics as $key){
	// $result = db_query('SELECT COUNT(*) FROM persons_raw WHERE name LIKE "% ' . $key . '" OR name LIKE "% ' . $key . ' %"');
	// $row = mysql_fetch_array($result, MYSQL_NUM);
	// mysql_free_result($result);
	// $patronymics[$key] = $row[0];
// }
?>
<table class="stat">
	<caption>Наиболее часто встречающиеся сокращения имён</caption>
<thead><tr>
	<th>Сокращение</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
foreach($name_reductions as $key => $val){
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($key) . "</td>\n\t<td class='alignright'>" . $val . "</td>\n</tr>";
}
?>
</tbody></table>
<?php
html_footer();
db_close();



// Comparison function
function cmp($a, $b){
    if($a == $b){
        return 0;
    }
    return ($a < $b) ? 1 : -1;
}

?>