<?php
require_once('../gb/common.php');	// Общие функции системы
require_once(GB_INC_DIR . '/publish.php');	// Функции формализации данных
 
// define('DEBUG', 1);	// Признак режима отладки



// Поддержка запросов данных через AJAX
if($_REQUEST['mode'] == 'get_data'){
	if(isset($_REQUEST['region_id'])){
		if(intval($_REQUEST['region_id']) < 1)	exit;

		$cur_id = intval($_REQUEST['region_id']);
		$html = array();

		$result = $db->get_column('SELECT id, title FROM dic_region WHERE parent_id = :id ORDER BY title',
				array('id' => $cur_id), TRUE);
		$tmp = array();
		foreach ($result as $id => $title)
			$tmp[] = "<option value='$id'>$title</option>";
		if($tmp)	$html[] = '<select>' . implode($tmp) . '</select>';

		do{
			$r = $db->get_column('SELECT parent_id FROM dic_region WHERE id = :id', array('id' => $cur_id));
			if(!$r)	exit;

			$result = $db->get_column('SELECT id, title FROM dic_region WHERE parent_id = :id ORDER BY title',
					array('id' => $cur_id), TRUE);
			$tmp = array();
			foreach ($result as $id => $title)
				$tmp[] = "<option value='$id'" . ($id != $cur_id ? "" : " selected='selected'") . ">$title</option>";

			array_unshift($html, '<select>' . implode($tmp) . '</select>');
			$cur_id = $r['parent_id'];
		} while($cur_id);

		$level = 0;
		foreach($html as $h){
			$level++;
			print "<div class='level_$level'>$h</div>";
		}
		exit;

	}elseif(isset($_REQUEST['source_id'])){
		if(intval($_REQUEST['source_id']) < 1)	exit;

		$r = $db->get_row('SELECT source, source_url, pg_correction FROM dic_source WHERE id = :id',
				array('id' => $_REQUEST['source_id']));
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
if(isset($_REQUEST['id']))
	$raw = $db->get_row('SELECT * FROM persons_raw WHERE id = :id', array('id' => $_REQUEST['id']));
else
	$raw = $db->get_row('SELECT * FROM persons_raw WHERE status = "Cant publish" ORDER BY RAND() LIMIT 1');

// Для отладки
if(defined('P_DEBUG'))	print "\n\n======================================\n";
if(defined('P_DEBUG'))	var_export($raw);
$pub = prepublish($raw, $have_trouble, $date_norm);
if(defined('P_DEBUG'))	var_export($have_trouble);
if(defined('P_DEBUG'))	var_export($pub);

// Если режим правки данных…
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mode'])){

	// Вычисляем вносимые изменения
	if(defined('P_DEBUG'))	print "\n\n=== Edit ===================================\n";
	$mod = array_diff_assoc($_POST[$_POST['mode']], $$_POST['mode']);
	if(defined('P_DEBUG'))	var_export($mod);
	
	switch($_POST['mode']){
	case 'raw':
		// Исправление исходных данных во всех похожих записях
		foreach($mod as $key => $val){
			$db->query('UPDATE `persons_raw` SET :#key = :new WHERE `status` != "Published" AND :#key = :old' .
					(!empty($_POST['raw_similar']) ? '' : ' AND id = :id'),
					array('#key' => $key, 'new' => $val, 'old' => $raw[$key], 'id' => $raw['id']));
			$raw[$key] = $val;
		}
		$pub = prepublish($raw, $have_trouble, $date_norm);
		break;
	case 'pub':
		// Исправление только текущей формализованной записи
		foreach($mod as $key => $val)
			$pub[$key] = $val;
		$pub = prepublish_make_data($pub, $have_trouble);
		break;
	}
if(defined('P_DEBUG'))	var_export($have_trouble);
if(defined('P_DEBUG'))	var_export($pub);
}

// Если формализация сейчас прошла успешно …
if(!$have_trouble){
	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	$db->set_row('persons', $pub, FALSE, GB_DBase::MODE_REPLACE);
	$db->set_row('persons_raw', array('status' => 'Published'), array('id' => $raw['id']));

	header('Location: ' . $_SERVER['PHP_SELF'] . '?rnd=' . rand());
	die();
}

// Считаем, сколько у нас каких записей
$cnt = (object) $db->get_row('SELECT COUNT(*) `total`
		, SUM(CASE WHEN `status` = "Draft"        THEN 1 ELSE 0 END) `draft`
		, SUM(CASE WHEN `status` = "Published"    THEN 1 ELSE 0 END) `published`
		, SUM(CASE WHEN `status` = "Cant publish" THEN 1 ELSE 0 END) `cant_publish`
		, SUM(CASE WHEN `status` = "Require edit" THEN 1 ELSE 0 END) `require_edit`
FROM `persons_raw`');

// Делаем выборку справочников
$dic_source = $dic_rank = array();
//
$dic_religion = $db->get_column('SELECT id, religion FROM dic_religion ORDER BY religion', array(), TRUE);
$dic_marital = $db->get_column('SELECT id, marital FROM dic_marital ORDER BY marital', array(), TRUE);
$dic_rank = $db->get_column('SELECT id, rank FROM dic_rank ORDER BY rank', array(), TRUE);
//
$dic_reason = $db->get_column('SELECT id, reason FROM dic_reason where event_type IN ("Потери", "Награждение") ORDER BY event_type, reason', array(), TRUE);
//
$result = $db->get_table('SELECT id, source, source_url, pg_correction FROM dic_source');
foreach ($result as $r){
	$dic_source        [$r['id']] = $r['source'];
	$dic_source_url    [$r['id']] = $r['source_url'];
	$dic_source_pg_corr[$r['id']] = $r['pg_correction'];
}
uasort($dic_source, function($a, $b){
	if(preg_match('/№(\d+)/uS', $a, $ma) && preg_match('/№(\d+)/uS', $b, $mb)){
		if(intval($ma[1]) == intval($mb[1]))
			return 0;
		return (intval($ma[1]) < intval($mb[1])) ? -1 : 1;
	}
	return strcmp($a, $b);
});



html_header();

print "<p class='aligncenter'>"
							."Опубликовано "        . format_num(   intval($cnt->published)+intval($cnt->require_edit),      ' запись', ' записи', ' записей')
                                                    . " (" . round((intval($cnt->published)+intval($cnt->require_edit)) * 100 / $cnt->total, 2) . "%), "
							."из которых требуют правки " . format_num($cnt->require_edit, ' ', ' ', ' ')
                                                    . " (" . round( $cnt->require_edit * 100 / $cnt->published, 2) . "%)). "
							."Неформализовано "     . format_num(  $cnt->cant_publish, ' запись', ' записи', ' записей')
                                                    . " (" . round($cnt->cant_publish * 100 / $cnt->total, 2) . "%)."
		. "</p>";

$fields = array(
	'surname'	=> 'Фамилия',
	'name'		=> 'Имя Отчество',
	'region_id'	=> 'Губерния, Уезд, Волость',
	'uyezd'		=> 'Уезд',
	'place'		=> 'Волость/Нас.пункт',
	'rank'		=> 'Воинское звание',
	'religion'	=> 'Вероисповедание',
	'marital'	=> 'Семейное положение',
	'reason'	=> 'Событие',
	'date'		=> 'Дата/период события:',
	'date_from'	=> ' начиная с …',
	'date_to'	=> ' заканчивая по …',
	'source'	=> 'Источник:',
	'list_nr'	=> ' № выпуска',
	'list_pg'	=> ' № страницы',
	'comments'	=> 'Комментарии',
);
$dfields = explode(' ', 'surname name region_id place rank religion marital reason date list_nr list_pg uyezd source_id');
$pfields = explode(' ', 'surname name region_id place rank religion_id marital_id reason_id date list_nr list_pg comments date_from date_to source_id');
?>
<p class='aligncenter'> <b>Аккуратнее с этой формой — отменить изменения НЕВОЗМОЖНО!</b></p>
<p class='aligncenter'> <b>Внимание! Временно работать с формой ЗАПРЕЩЕНО!!!</b></p>
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
			$('#source_link').load('<?php print $_SERVER['PHP_SELF'] ?>', {
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
		$('#regions').load('<?php print $_SERVER['PHP_SELF'] ?>', {
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

<div class="aligncenter">
	<select name="row_type">
		<option selected="selected" value="">Выводить неформализовавшиеся записи</option>
		<option                     value="">Выводить записи, требующие правки</option>
		</select>
</div>
<div class="aligncenter"><button>Пропустить эту запись</button></div>
<input type='hidden' name='id' value='<?php print $raw['id']?>' />
<table class="report"><tr>
	<td></td>
	<th>Исходные данные</th>
	<th>Формализованные данные</th>
</tr><?php
foreach($fields as $key => $def){
	print "<tr>\n";
	
	// вывод заголовков строк
	print "\t<th>$def</th>\n";
	
	//вывод столбца исходных данных
	if( in_array($key.'_id', $dfields)) $key = $key.'_id';
	if(!in_array($key      , $dfields)) print "\t<td></td>\n";
	else{
		print "\t<td>";
		if($key == 'source_id'){
			print "<input type='text' size=60 name='raw[$key]' value='" . htmlspecialchars($dic_source[$raw[$key]]) . "' />";
			print "<br />";
			if (!empty($dic_source_url[$raw[$key]])){
				$pg_raw = htmlspecialchars($raw[list_pg]); //list_pg
				$url_raw = str_replace('{pg}', $pg_raw + $dic_source_pg_corr[$raw[$key]] , $dic_source_url[$raw[$key]]);
				$text_raw = trim_text($dic_source[$raw[$key]], 40);
				print "<small>Ссылка на источник: «<a href='$url_raw' target='_blank'>$text_raw</a>», стр.$pg_raw</small>";
			}
			else
				print "<small>Ссылка на источник не указана</small>";
		}else{
			print "<input type='text' size=60 name='raw[$key]' value='" . htmlspecialchars($raw[$key]) . "' />";
		}
		print "</td>\n";
	}
	
	//вывод столбца формализованных данных
	if( in_array($key.'_id', $pfields)) $key = $key.'_id';
	if(!in_array($key      , $pfields))	print "\t<td></td>";
	else{
		print "\t<td" . ($key == 'comments' || isset($pub[$key]) ? '' : ' class="trouble"') . ">";
		if($key == 'rank'){
			print "<select id='$key' name='pub[$key]'>\n";
			foreach($dic_rank as $k => $d){
				print "\t\t<option value='$k'" . ($k != $pub[$key] ? "" : " selected='selected'") . ">" . htmlspecialchars(trim_text($d)) . "</option>\n";
			}
			print "</select>";
		}elseif($key == 'religion_id'){
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
			if($key == 'date'){
				print "<input id='$key' type='text' name='pub[$key]' value='" . htmlspecialchars($pub[$key], ENT_QUOTES) . "' />";
				print " <small>Машина это видит как «${date_norm}»</small>";
			}
			else
				print "<input id='$key' type='text' size=60 name='pub[$key]' value='" . htmlspecialchars($pub[$key], ENT_QUOTES) . "' />";
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



function trim_text($text, $max_len = 70){
	$text = trim($text);
	if(mb_strlen($text) > $max_len)
		$text = preg_replace('/\s+\w*$/uS', '', mb_substr($text, 0, $max_len)) . '…';
	return $text;
}
