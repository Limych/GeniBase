<?php
require_once('gb/common.php');	// Общие функции системы

define('HIST_WIDTH',	600);



$max_list_nr = gbdb()->get_cell('SELECT MAX(list_nr) FROM ?_persons');

if(!isset($_REQUEST['ignore_per']))
	$_REQUEST['ignore_per'] = 40;

html_header('Статистика');
?>
<p><a href="/">« Вернуться к поиску</a></p>
<h1>Специальная статистика дат по базе данных</h1>
<form>
<div>Номер списков для рассчёта: c <input type="number" name="list_from" value="<?php print $_REQUEST['list_from']; ?>" min="1" max="<?php print $max_list_nr; ?>" /> по <input type="number" name="list_to" value="<?php print $_REQUEST['list_to']; ?>" min="1" max="<?php print $max_list_nr; ?>" /></div>
<div>Игнорировать периоды длиннее <input type="number" name="ignore_per" value="<?php print $_REQUEST['ignore_per']; ?>" min="1" max="999" /> дней</div>
<button>Отправить</button>
</form>
<?php

if(get_request_attr('list_from') && get_request_attr('list_to')):
	$ignore_per = intval(get_request_attr('ignore_per', 0));
	
	$result = gbdb()->get_row('SELECT MIN(`date_from`), DATEDIFF(MAX(`date_to`), MIN(`date_from`)) AS `days`
			FROM ?_persons WHERE `date_from` AND `list_nr` >= ?list_from AND `list_nr` <= ?list_to',
			array(
				'list_from'	=> intval(get_request_attr('list_from')),
				'list_to'	=> intval(get_request_attr('list_to')),
			));
	list($date_min, $to) = array_values($result);
	$date = array();
	for($i = 0; $i < $to; $i++)
		$date[$i] = 0;

	$result = gbdb()->get_table('SELECT DATEDIFF(`date_from`, ?date_from) AS `first`,
			DATEDIFF(`date_to`, `date_from`) AS `days`, COUNT(*) AS `cnt` FROM ?_persons
			WHERE `date_from` AND `list_nr` >= ?list_from AND `list_nr` <= ?list_to
			GROUP BY `date_from`, `days` HAVING `days` <= ?days_ignore',
			array(
				'date_from'		=> $date_min,
				'list_from'		=> $_REQUEST['list_from'],
				'list_to'		=> $_REQUEST['list_to'],
				'days_ignore'	=> $ignore_per,
			));
	$cnt = 0;
	foreach ($result as $row){
		$i = intval($row['first']);
		$to = $i + intval($row['days']);
		$cnt += intval($row['cnt']);
		for(; $i <= $to; $i++)
			$date[$i] += intval($row['cnt']);
	}
	$date_min = date_create($date_min);
	if($ignore_per){
		while(count($date) && !$date[0]){
			array_shift($date);
			$date_min->add(new DateInterval('P1D'));
		}
		while(count($date) && !$date[count($date)-1])
			array_pop($date);
	}
// var_export($date);
?>
<p>Всего в этом подсчёте участвует <?php print format_num($cnt, ' запись.', ' записи.', ' записей.')?></p>
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



// Comparison function
function cmp($a, $b){
    if($a == $b){
        return 0;
    }
    return ($a < $b) ? 1 : -1;
}

?>