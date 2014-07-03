<?php
require_once('functions.php');	// Общие функции системы
 
// define('DEBUG', 1);	// Признак режима отладки

// Считаем, сколько у нас каких записей
$cnt = (object) array();
//
$result = db_query('SELECT COUNT(*) FROM persons_raw WHERE status != "Draft"');
$row = mysql_fetch_array($result, MYSQL_NUM);
mysql_free_result($result);
$cnt->total = $row[0];
//
$result = db_query('SELECT COUNT(*) FROM persons_raw WHERE status = "Cant publish"');
$row = mysql_fetch_array($result, MYSQL_NUM);
mysql_free_result($result);
$cnt->cant_publish = $row[0];

// Делаем выборку записей для публикации
// $result = db_query('SELECT * FROM persons_raw WHERE status = "Cant publish" ORDER BY rank, reason LIMIT 1');
$result = db_query('SELECT * FROM persons_raw WHERE status = "Cant publish" ORDER BY RAND() LIMIT 1');
$row = mysql_fetch_array($result, MYSQL_ASSOC);
mysql_free_result($result);

if(defined('DEBUG'))	print "\n\n======================================\n";
if(defined('DEBUG'))	var_export($row);
$pub = prepublish($row, $have_trouble, $date_norm);
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);

// Если формализация сейчас прошла успешно …
if(!$have_trouble){
	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	db_query('REPLACE INTO persons (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
	db_query('UPDATE persons_raw SET status = "Published" WHERE id = ' . $row['id']);

	header('Location: ' . $_SERVER['PHP_SELF']);
	die();
}

// Делаем выборку справочников
$dic_religion = $dic_marital = $dic_source = array();
//
$dic_religion[0] = '(не определено)';
$result = db_query('SELECT id, religion FROM dic_religion ORDER BY religion');
while($r = mysql_fetch_array($result, MYSQL_NUM)){
	$dic_religion[$r[0]] = $r[1];
}
mysql_free_result($result);
//
$dic_marital[0] = '(не определено)';
$result = db_query('SELECT id, marital FROM dic_marital ORDER BY marital');
while($r = mysql_fetch_array($result, MYSQL_NUM)){
	$dic_marital[$r[0]] = $r[1];
}
mysql_free_result($result);
//
$dic_source[0] = '(не определено)';
$result = db_query('SELECT id, source FROM dic_source ORDER BY source');
while($r = mysql_fetch_array($result, MYSQL_NUM)){
	$dic_source[$r[0]] = $r[1];
}
mysql_free_result($result);

html_header();
print "<p class='aligncenter'>Всего неформализуемо " . format_num($cnt->cant_publish, ' запись', ' записи', ' записей') . " (" . round($cnt->cant_publish * 100 / $cnt->total, 2) . "%).</p>";

$fields = array(
	'surname'	=> 'Фамилия',
	'name'		=> 'Имя Отчество',
	'region_id'	=> 'Губерния, Уезд, Волость',
	'uyezd'		=> 'Уезд',
	'place'		=> 'Волость/Нас.пункт',
	'rank'		=> 'Воинское звание',
	'religion'	=> 'Вероисповедание',
	'marital'	=> 'Семейное положение',
	'reason'	=> 'Причина выбытия',
	'date'		=> 'Дата выбытия',
	'date_from'	=> 'Дата выбытия «с …»',
	'date_to'	=> 'Дата выбытия «по …»',
	'source'	=> 'Источник',
	'list_nr'	=> 'Источник: список №',
	'list_pg'	=> 'Источник: страница №',
	'comments'	=> 'Комментарии',
);
$dfields = explode(' ', 'surname name region_id place rank religion marital reason date list_nr list_pg uyezd');
$pfields = explode(' ', 'surname name region_id place rank religion_id marital_id reason date list_nr list_pg comments date_from date_to source_id');
?>
<p>Форма пока не работает — только смотрим… :)</p>
<table class="report"><tr>
	<td></td>
	<th>Исходные данные</th>
	<th>Формализованные данные</th>
</tr><?php
foreach($fields as $key => $def){
	print "<tr>\n";
	print "\t<th>$def</th>\n";
	if(!in_array($key, $dfields))
		print "\t<td></td>\n";
	else{
		print "\t<td>";
		print "<input type='text' name='r_$key' value='" . htmlspecialchars($row[$key]) . "' />";
		print "</td>\n";
	}
	if(in_array($key.'_id', $pfields))
		$key = $key.'_id';
	if(!in_array($key, $pfields))
		print "\t<td></td>";
	else{
		print "\t<td" . ($key == 'comments' || isset($pub[$key]) ? '' : ' class="trouble"') . ">";
		if($key == 'religion_id'){
			print "<select name='p_$key'>\n";
			foreach($dic_religion as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'marital_id'){
			print "<select name='p_$key'>\n";
			foreach($dic_marital as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'source_id'){
			print "<select name='p_$key'>\n";
			foreach($dic_source as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'date_from' || $key == 'date_to'){
			print "<input type='date' name='p_$key' value='" . htmlspecialchars($pub[$key]) . "' min='1914-07-28' max='1918-11-11'>";
		}elseif($key == 'comments'){
			print "<textarea name='p_$key' rows='7' cols='30'>" . htmlspecialchars($pub[$key]) . "</textarea>";
		}else{
			print "<input type='text' name='p_$key' value='" . htmlspecialchars($pub[$key]) . "' />";
			if($key == 'date')
				print " <small>«${date_norm}»</small>";
		}
		print "</td>\n";
	}
	print "</tr>";
}
?><tr>
	<td></td>
	<td><button>Сохранить исходные данные</button></td>
	<td><button>Сохранить формализованные данные</button></td>
</tr></table>
<?php

html_footer();
db_close();



function trim_text($text, $max_len = 70){
	$text = trim($text);
	if(mb_strlen($text) > $max_len)
		$text = preg_replace('/\s+\w*$/uS', '', mb_substr($text, 0, $max_len)) . '…';
	return $text;
}

?>