<?php
require_once('functions.php');	// Общие функции системы
 
// define('DEBUG', 1);	// Признак режима отладки

// Делаем выборку записей для публикации
// $result = db_query('SELECT * FROM persons_raw WHERE status = "Cant publish" ORDER BY rank, reason LIMIT 1');
$result = db_query('SELECT * FROM persons_raw WHERE ' . (!empty($_POST['id']) ? 'id = ' . intval($_POST['id']) : 'status = "Cant publish" ORDER BY RAND() LIMIT 1'));
$raw = $result->fetch_array(MYSQL_ASSOC);
$result->free();

if(defined('DEBUG'))	print "\n\n======================================\n";
if(defined('DEBUG'))	var_export($raw);
$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);

// Если режим правки данных…
if($_SERVER['REQUEST_METHOD'] == 'POST'){
	// Вычисляем вносимые изменения
	$mod = array_diff_assoc($_POST[$_POST['mode']], $$_POST['mode']);
if(defined('DEBUG'))	print "\n\n=== Edit ===================================\n";
if(defined('DEBUG'))	var_export($mod);
	if(!empty($mod)){
		switch($_POST['mode']){
		case 'raw':
			// Исправление исходных данных во всех похожих записях
			$db = db_open();
			$fix = $cond = array();
			$cond[]	= "`status` != 'Published'";
			foreach($mod as $key => $val){
				$fix[]	= "`$key` = '" . $db->escape_string($val) . "'";
				$cond[]	= "`$key` = '" . $db->escape_string($raw[$key]) . "'";
				$raw[$key] = $val;
			}
			db_query('UPDATE persons_raw SET ' . implode(', ', $fix) . ' WHERE ' . implode(' AND ', $cond));
			$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);
			break;
		case 'pub':
			// Исправление публикуемых данных только в текущей записи
			break;
		}
	}
}

// Если формализация сейчас прошла успешно …
if(!$have_trouble){
	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	db_query('REPLACE INTO persons (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
	db_query('UPDATE persons_raw SET status = "Published" WHERE id = ' . $raw['id']);

	header('Location: ' . $_SERVER['PHP_SELF']);
	die();
}

// Считаем, сколько у нас каких записей
$cnt = (object) array();
//
$result = db_query('SELECT COUNT(*) FROM persons_raw WHERE status != "Draft"');
$r = $result->fetch_array(MYSQL_NUM);
$result->free();
$cnt->total = $r[0];
//
$result = db_query('SELECT COUNT(*) FROM persons_raw WHERE status = "Cant publish"');
$r = $result->fetch_array(MYSQL_NUM);
$result->free();
$cnt->cant_publish = $r[0];

// Делаем выборку справочников
$dic_religion = $dic_marital = $dic_source = array();
//
$dic_religion[0] = '(не определено)';
$result = db_query('SELECT id, religion FROM dic_religion ORDER BY religion');
while($r = $result->fetch_array(MYSQL_NUM)){
	$dic_religion[$r[0]] = $r[1];
}
$result->free();
//
$dic_marital[0] = '(не определено)';
$result = db_query('SELECT id, marital FROM dic_marital ORDER BY marital');
while($r = $result->fetch_array(MYSQL_NUM)){
	$dic_marital[$r[0]] = $r[1];
}
$result->free();
//
$dic_source[0] = '(не определено)';
$result = db_query('SELECT id, source FROM dic_source ORDER BY source');
while($r = $result->fetch_array(MYSQL_NUM)){
	$dic_source[$r[0]] = $r[1];
}
$result->free();

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
<form method="post" class="editor">
<input type='hidden' name='id' value='<?php print $raw['id']?>' />
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
		print "<input type='text' name='raw[$key]' value='" . htmlspecialchars($raw[$key]) . "' />";
		print "</td>\n";
	}
	if(in_array($key.'_id', $pfields))
		$key = $key.'_id';
	if(!in_array($key, $pfields))
		print "\t<td></td>";
	else{
		print "\t<td" . ($key == 'comments' || isset($pub[$key]) ? '' : ' class="trouble"') . ">";
		if($key == 'religion_id'){
			print "<select name='pub[$key]'>\n";
			foreach($dic_religion as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'marital_id'){
			print "<select name='pub[$key]'>\n";
			foreach($dic_marital as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'source_id'){
			print "<select name='pub[$key]'>\n";
			foreach($dic_source as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'date_from' || $key == 'date_to'){
			print "<input type='date' name='pub[$key]' value='" . htmlspecialchars($pub[$key]) . "' min='1914-07-28' max='1918-11-11'>";
		}elseif($key == 'comments'){
			print "<textarea name='pub[$key]' rows='7' cols='30'>" . htmlspecialchars($pub[$key]) . "</textarea>";
		}else{
			print "<input type='text' name='pub[$key]' value='" . htmlspecialchars($pub[$key]) . "' />";
			if($key == 'date')
				print " <small>«${date_norm}»</small>";
		}
		print "</td>\n";
	}
	print "</tr>";
}
?><tr>
	<td></td>
	<td class="aligncenter"><button name="mode" value="raw">Изменить исходные данные</button><br/><small>(во всех подобных записях)</small></td>
	<td class="aligncenter"><button name="mode" value="pub">Изменить формализованные данные</button><br/><small>(только в текущей записи)</small></td>
</tr></table></form>
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