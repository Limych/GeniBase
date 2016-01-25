<?php
require_once('gb-config.php');	// Load GeniBase
require_once('inc.php');	// Основной подключаемый файл-заплатка

define('MIN_NAMES_CNT', 50);

html_header('Статистика');
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Специальная статистика по базе данных</h1>
<?php

$name_reductions = $names = $patronymic_reductions = $patronymics = array();
$result = gbdb()->get_table('SELECT LOWER(name) AS name, COUNT(*) AS cnt FROM ?_persons_raw GROUP BY name');
foreach($result as $row){
	$row['name'] = array_map('mb_ucfirst', preg_split('/\s+/uS', $row['name']));
	if( empty($row['name']))
		continue;
	$key = array_shift($row['name']);
	if( false !== strpos($key, '.')
	|| false !== strpos($key, '-')){
		if( !isset($name_reductions[$key]))	$name_reductions[$key] = 0;
		$name_reductions[$key] += $row['cnt'];
	}else{
		if( !isset($names[$key]))	$names[$key] = 0;
		$names[$key] += $row['cnt'];
	}
	foreach($row['name'] as $key){
		if( false !== strpos($key, '.')
		|| false !== strpos($key, '-')){
			if( !isset($patronymic_reductions[$key]))	$patronymic_reductions[$key] = 0;
			$patronymic_reductions[$key] += $row['cnt'];
		}else{
			if( !isset($patronymics[$key]))	$patronymics[$key] = 0;
			$patronymics[$key] += $row['cnt'];
		}
	}
}
$result->free();
uasort($names, 'cmp');
uasort($name_reductions, 'cmp');
uasort($patronymics, 'cmp');
uasort($patronymic_reductions, 'cmp');

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
	if( $val < MIN_NAMES_CNT)	break;
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($key) . "</td>\n\t<td class='align-right'>" . $val . "</td>\n</tr>";
}
?>
</tbody></table>

<table class="stat">
	<caption>Наиболее часто встречающиеся сокращения отчеств</caption>
<thead><tr>
	<th>Сокращение</th>
	<th>Записей</th>
</tr></thead><tbody>
<?php
$even = 0;
foreach($patronymic_reductions as $key => $val){
	if( $val < MIN_NAMES_CNT)	break;
	$even = 1-$even;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . esc_html($key) . "</td>\n\t<td class='align-right'>" . $val . "</td>\n</tr>";
}
?>
</tbody></table>
<?php
html_footer();



// Comparison function
function cmp($a, $b){
    if( $a == $b){
        return 0;
    }
    return ($a < $b) ? 1 : -1;
}

?>