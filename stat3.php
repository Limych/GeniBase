<?php
require_once('functions.php');	// Общие функции системы

define('HIST_WIDTH',	600);



$result = db_query('SELECT MAX(list_nr) FROM `persons`');
$row = $result->fetch_array(MYSQL_NUM);
$result->free();
$max_list_nr = $row[0];
$result->free();

if(!isset($_REQUEST['ignore_per']))
	$_REQUEST['ignore_per'] = 40;

html_header();
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Специальная статистика дат по базе данных</h1>
<form>
<div>Номер списков для рассчёта: c <input type="number" name="list_from" value="<?php print $_REQUEST['list_from']; ?>" min="1" max="<?php print $max_list_nr; ?>" /> по <input type="number" name="list_to" value="<?php print $_REQUEST['list_to']; ?>" min="1" max="<?php print $max_list_nr; ?>" /></div>
<div>Игнорировать периоды длиннее <input type="number" name="ignore_per" value="<?php print $_REQUEST['ignore_per']; ?>" min="1" max="999" /> дней</div>
<button>Отправить</button>
</form>
<?php

if($_REQUEST['list_from'] && $_REQUEST['list_to']):
	$ignore_per = intval($_REQUEST['ignore_per']);
	
	// Получаем минимальное и максимальное значение дат
	$result = db_query('SELECT MIN(`date_from`), MAX(`date_to`) FROM `persons` WHERE `list_nr` >= ' . intval($_REQUEST['list_from']) . ' AND `list_nr` <= ' . intval($_REQUEST['list_to']));
	list($date_min, $date_max) = $result->fetch_array(MYSQL_NUM);
	$result->free();
	$date_min = date_create($date_min);

	$date = array();
	$tmp = date_diff($date_min, date_create($date_max));
	$tmp = $tmp->format('%a');
	for($i = 0; $i < $tmp; $i++)
		$date[$i] = 0;

	$cnt = 0;
	$result = db_query('SELECT `date_from`, `date_to` FROM `persons` WHERE `list_nr` >= ' . intval($_REQUEST['list_from']) . ' AND `list_nr` <= ' . intval($_REQUEST['list_to']));
	while($row = $result->fetch_array(MYSQL_NUM)){
		$cnt++;
		$tmp = date_diff($date_min, date_create($row[0]));
		$i = intval($tmp->format('%a'));
		$tmp = date_diff($date_min, date_create($row[1]));
		$tmp = intval($tmp->format('%a'));
		if($ignore_per && ($tmp - $i) >= $ignore_per)
			continue;
		for(; $i <= $tmp; $i++){
			$date[$i]++;
		}
	}
	$result->free();
	if($ignore_per){
		while(count($date) && !$date[0]){
			array_shift($date);
			$date_min->add(new DateInterval('P1D'));
		}
		while(count($date) && !$date[count($date)-1]){
			array_pop($date);
		}
	}
// var_export($date);
?>
<p>Всего в этот подсчёте участвует <?php print format_num($cnt, ' запись.', ' записи.', ' записей.')?></p>
<table class="stat">
	<caption>Частота встречаемости дат в списке</caption>
<thead><tr>
	<th>Дата</th>
	<th>Записей</th>
	<th></th>
</tr></thead><tbody>
<?php
$even = 0;
$max = max($date);
foreach($date as $key => $val){
	$even = 1-$even;
	$tmp = clone $date_min;
	$tmp->add(new DateInterval("P${key}D"));
	$hist = intval((HIST_WIDTH - 10) * $val / $max);
	if($val)	$hist += 10;
	print "<tr class='" . ($even ? 'even' : 'odd') . "'>\n\t<td>" . htmlspecialchars($tmp->format('d-M-Y')) . "</td>\n\t<td class='alignright'>" . $val . "</td>\n\t<td class='alignright'><div style='width: " . $hist . "px; background: blue'>&nbsp;</div></td>\n</tr>";
}
?>
</tbody></table>
<?php
endif;
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