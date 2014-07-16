<?php
require_once('../functions.php');	// Общие функции системы
require_once('../publisher.php');	// Функции формализации данных
 
// define('DEBUG', 1);	// Признак режима отладки



// Поддержка запросов данных через AJAX
if($_REQUEST['mode'] == 'get_data'){
	if(isset($_REQUEST['region_id'])){
		if(intval($_REQUEST['region_id']) < 1)	exit;

		$cur_id = intval($_REQUEST['region_id']);
		$html = array();

		$result = db_query('SELECT id, title FROM dic_region WHERE parent_id = ' . $cur_id . ' ORDER BY title');
		$tmp = array();
		while($r = $result->fetch_array(MYSQL_ASSOC)){
			$tmp[] = "<option value='${r[id]}'>${r[title]}</option>";
		}
		$result->free();
		if($tmp)	$html[] = "<select>" . implode($tmp) . "</select>";

		do{
			$result = db_query('SELECT parent_id FROM dic_region WHERE id = ' . $cur_id);
			$r = $result->fetch_array(MYSQL_ASSOC);
			$result->free();
			if(!$r)		exit;

			$result = db_query('SELECT id, title FROM dic_region WHERE parent_id = ' . $r['parent_id'] . ' ORDER BY title');
			$tmp = array();
			while($r2 = $result->fetch_array(MYSQL_ASSOC)){
				$tmp[] = "<option value='${r2[id]}'" . ($r2['id'] != $cur_id ? "" : " selected='selected'") . ">${r2[title]}</option>";
			}
			$result->free();

			array_unshift($html, "<select>" . implode($tmp) . "</select>");
			$cur_id = $r['parent_id'];
		}while($cur_id);

		$level = 0;
		foreach($html as $h){
			$level++;
			print "<div class='level_$level'>$h</div>";
		}
		exit;

	}elseif(isset($_REQUEST['source_id'])){
		if(intval($_REQUEST['source_id']) < 1)	exit;

		$result = db_query('SELECT source, source_url, pg_correction FROM dic_source WHERE id = ' . intval($_REQUEST['source_id']));
		$r = $result->fetch_array(MYSQL_ASSOC);
		$result->free();
		if(!$r || empty($r['source_url']))	exit;

		$pg = intval($_REQUEST['list_pg']);
		$url = str_replace('{pg}', $pg + $r['pg_correction'], $r['source_url']);
		$text = trim_text($r['source'], 40);
		print "<small>Ссылка на источник: «<a href='$url' target='_blank'>$text</a>», стр.$pg</small>";
		exit;
	}
	exit;
}



// Делаем выборку записей для публикации
// $result = db_query('SELECT * FROM persons_raw WHERE status = "Cant publish" ORDER BY rank, reason LIMIT 1');
$result = db_query('SELECT * FROM persons_raw WHERE ' . (!empty($_POST['id']) && isset($_POST['mode']) ? 'id = ' . intval($_POST['id']) : 'status = "Cant publish" ORDER BY RAND() LIMIT 1'));
$raw = $result->fetch_array(MYSQL_ASSOC);
$result->free();

if(defined('DEBUG'))	print "\n\n======================================\n";
if(defined('DEBUG'))	var_export($raw);
$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);

// Если режим правки данных…
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mode'])){
	// Вычисляем вносимые изменения
if(defined('DEBUG'))	print "\n\n=== Edit ===================================\n";
	$mod = array_diff_assoc($_POST[$_POST['mode']], $$_POST['mode']);
if(defined('DEBUG'))	var_export($mod);
	switch($_POST['mode']){
	case 'raw':
		// Исправление исходных данных во всех похожих записях
		$db = db_open();
		foreach($mod as $key => $val){
			if(!empty($_POST['raw_similar']))
				db_query("UPDATE `persons_raw` SET `$key` = '" . $db->escape_string($val) . "' WHERE `status` != 'Published' AND `$key` = '" . $db->escape_string($raw[$key]) . "'");
			$raw[$key] = $val;
		}
		$pub = prepublish($raw, $have_trouble, $date_norm);
		break;
	case 'pub':
		// Исправление только текущей формализованной записи
		foreach($mod as $key => $val){
			$pub[$key] = $val;
		}
		$pub = prepublish_make_data($pub, $have_trouble);
		break;
	}
if(defined('DEBUG'))	var_export($have_trouble);
if(defined('DEBUG'))	var_export($pub);
}

// Если формализация сейчас прошла успешно …
if(!$have_trouble){
	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	db_query('REPLACE INTO persons (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
	db_query('UPDATE persons_raw SET status = "Published" WHERE id = ' . $raw['id']);

	header('Location: ' . $_SERVER['PHP_SELF'] . '?rnd=' . rand());
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
$dic_religion = $dic_marital = $dic_source = $dic_reason = array();
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
$result = db_query('SELECT id, source FROM dic_source');
while($r = $result->fetch_array(MYSQL_NUM)){
	$dic_source[$r[0]] = $r[1];
}
$result->free();
uasort($dic_source, function($a, $b){
	if(preg_match('/№(\d+)/uS', $a, $ma) && preg_match('/№(\d+)/uS', $b, $mb)){
		if(intval($ma[1]) == intval($mb[1]))
			return 0;
		return (intval($ma[1]) < intval($mb[1])) ? -1 : 1;
	}
	return strcmp($a, $b);
});
//
$dic_reason[0] = '(не определено)';
$result = db_query('SELECT id, reason FROM dic_reason ORDER BY reason');
while($r = $result->fetch_array(MYSQL_NUM)){
	$dic_reason[$r[0]] = $r[1];
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
$pfields = explode(' ', 'surname name region_id place rank religion_id marital_id reason_id date list_nr list_pg comments date_from date_to source_id');
?>
<p>Аккуратнее с этой формой — отменить изменения НЕВОЗМОЖНО!</p>
<script type="text/javascript">
	$(function(){
		$('form').on('reset', function(){
			el = $('form *.modifyed').removeClass('modifyed');
			$('#regions').empty();
			setTimeout("el.trigger('change');load_region(region_id)", 100);
		});
		$('input, textarea').on('keyup change', function(){
			$(this).toggleClass('modifyed', $(this).val() != this.defaultValue);
		});
		$('select').on('keyup change', function(){
			$(this).toggleClass('modifyed', $(this).find('option:selected').val() != $(this).find('option[selected]').val());
		});
		$('#source_id').on('change', function(){
			$('#source_link').load('<?php print SELF_URL ?>', {
				mode: 'get_data',
				source_id: $('#source_id').val(),
				list_pg: $('#list_pg').val()
			});
		});
		$('#list_pg').on('keyup change', function(){
			$('#source_id').trigger('change');
		});
		
		region_id = $('#region_id').val();
		$('#region_id').after('<div id="regions"></div>').remove();
		$('#regions').before('<input id="region_id" type="hidden" name="region_id" value="' + region_id + '" />');
		load_region(region_id);

		$('input, #source_id').trigger('change');
	});

	function load_region(region){
		$('#region_id').val(region);
		$('#regions').load('<?php print SELF_URL ?>', {
			mode: 'get_data',
			region_id: region
		}, function(){
console.log(region_id);
console.log($('#region_id').val());
			$('#regions select').toggleClass('modifyed', region_id != $('#region_id').prop('defaultValue')).on('change', function(){
				load_region($(this).find('option:selected').val());
			});
		});
	}
</script>
<form method="post" class="editor">
<div class="alignright"><button>Пропустить эту запись</button></div>
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
			print "<select id='$key' name='pub[$key]'>\n";
			foreach($dic_religion as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'marital_id'){
			print "<select id='$key' name='pub[$key]'>\n";
			foreach($dic_marital as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'source_id'){
			print "<select id='$key' name='pub[$key]'>\n";
			foreach($dic_source as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select><div id='source_link'></div>";
		}elseif($key == 'reason_id'){
			print "<select id='$key' name='pub[$key]'>\n";
			foreach($dic_reason as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'date_from' || $key == 'date_to'){
			print "<input id='$key' type='date' name='pub[$key]' value='" . htmlspecialchars($pub[$key]) . "' min='1914-07-28' max='1918-11-11'>";
		}elseif($key == 'comments'){
			print "<textarea id='$key' name='pub[$key]' rows='7' cols='30'>" . htmlspecialchars($pub[$key]) . "</textarea>";
		}else{
			print "<input id='$key' type='text' name='pub[$key]' value='" . htmlspecialchars($pub[$key]) . "' />";
			if($key == 'date')
				print " <small>Машина это видит как «${date_norm}»</small>";
		}
		print "</td>\n";
	}
	print "</tr>";
}
?><tr>
	<td class="aligncenter"><button id="reset" type="reset">Сброс изменений</button></td>
	<td class="aligncenter">
		<small><label><input type="checkbox" name="raw_similar" value="1" checked="checked" /> применить ко всем подобным записям</label></small><br/>
		<button name="mode" value="raw">Изменить исходные данные</button>
	</td>
	<td class="aligncenter">
		<button name="mode" value="pub">Изменить формализованные данные</button><br/>
		<small>(только в текущей записи)</small>
	</td>
</tr></table>
<p class="nb">Причина невозможности автоматической обработки всегда выделена красным фоном. Но это вовсе не значит, что править надо именно её.</p>
<p class="nb">Если видим ошибку в тексте, править лучше в исходных данных (слева), т.к. это применится ко всем таким же случаям. Если же случай явно разовый, то проще исправить его в формализованных данных (справа).</p>
<p class="nb">Даты лучше вообще всегда стараться править только слева. Машина ждёт указание даты в порядке день, месяц, год и в промежутке с 01.авг.1914 (дата объявления войны России) по 11.ноя.1918 (дата окончания войны). Разделители частей даты — точки «.» и/или пробелы.</p>
<p class="nb">Графа «Губерния, Уезд, Волость» пока показывается в виде внутренних идентификаторов регионов — их лучше пока всегда оставлять «как есть».</p>
<p class="nb">Если изменения получились пригодными к публикации, машина покажет следующую «плохую» запись. Иначе же на экране останется та же самая запись (с выделением красным фоном проблемного места).</p>
</form>
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