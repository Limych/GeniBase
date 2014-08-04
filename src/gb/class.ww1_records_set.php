<?php
// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/********************************************************************************
 * Абстрактный класс хранения результатов поиска
*/
abstract class ww1_records_set {
	protected	$page;			// Текущая страница результатов

	// Создание экземпляра класса
	function __construct($page){
		$this->page = $page;
	}

	// Вывод результатов поиска в виде html-таблицы
	abstract function show_report();
}



/********************************************************************************
 * Класс хранения результатов поиска по спискам погибших
*/
class ww1_solders_set extends ww1_records_set{
	protected	$records;
	public	$records_cnt;

	// Создание экземпляра класса и сохранение результатов поиска
	function __construct($page, $sql_result, $records_cnt = NULL){
		parent::__construct($page);

		$this->records = array();
		while($row = $sql_result->fetch_object()){
			if($row->religion == '(иное)'){
				$result = db_query('SELECT religion FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->religion = $tmp[0];
			}
			if($row->marital == '(иное)'){
				$result = db_query('SELECT marital FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->marital = $tmp[0];
			}
			if($row->reason == '(иное)'){
				$result = db_query('SELECT reason FROM `persons_raw` WHERE `id` = ' . $row->id);
				$tmp = $result->fetch_array(MYSQL_NUM);
				$result->free();
				$row->reason = $tmp[0];
			}
			$this->records[] = $row;
		}

		$this->records_cnt = ($records_cnt !== NULL ? $records_cnt : count($this->records));
	}

	// Вывод результатов поиска в виде html-таблицы
	function show_report($brief_fields = NULL, $detailed_fields = array()){
		$max_pg = max(1, ceil($this->records_cnt / Q_LIMIT));
		if($this->page > $max_pg)	$this->page = $max_pg;

		$brief_fields_cnt = count($brief_fields);
		?>
<p class="aligncenter">Всего найдено <?php print format_num($this->records_cnt, ' запись.', ' записи.', ' записей.')?></p>
<?php
		if(false !== ($show_detailed = !empty($detailed_fields))){
?>
<script type="text/javascript">
	$(document).ready(function(){
		$(".report tr.detailed").hide();
		$(".report tr.brief").click(function(){
			$(this).next("tr").toggle();
			$(this).find(".arrow").toggleClass("up");
		});
		$('body').keydown(function(e){
			if(e.ctrlKey && e.keyCode == 37){	// Ctrl+Left
				location.href = $('.paginator:first .prev').attr('href');
			}
			if(e.ctrlKey && e.keyCode == 39){	// Ctrl+Right
				location.href = $('.paginator:first .next').attr('href');
			}
		});
	});
</script>
<?php
		}	// if($show_detailed)

		// Формируем пагинатор
		$pag = paginator($this->page, $max_pg);
		print $pag;	// Вывод пагинатора
?>
<table class="report"><thead>
	<tr>
		<th>№ <nobr>п/п</nobr></th>
<?php
		foreach(array_values($brief_fields) as $val){
			print "\t\t<th>" . htmlspecialchars($val) . "</th>\n";
		}
		if($show_detailed)
			print "\t\t<th></th>\n";
?>
	</tr>
</thead><tbody>
<?php
		$even = 0;
		$num = ($this->page - 1) * Q_LIMIT;
		foreach($this->records as $row){
			$even = 1-$even;
			print "\t<tr class='brief" . ($even ? ' even' : ' odd') . " id_" . $row->id . (!isset($row->strictMatch) || !empty($row->strictMatch) ? '' : ' nonstrict-match') . "'>\n";
// if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($row);	print "\n -->\n";	}
			print "\t\t<td class='alignright'>" . (++$num) . "</td>\n";
			foreach(array_keys($brief_fields) as $key){
				print "\t\t<td>" . htmlspecialchars($row->$key) . "</td>\n";
			}
			if($show_detailed){
				print "\t\t<td><div class='arrow'></div></td>\n";
?>
	</tr><tr class='detailed'>
		<td></td>
		<td class='detailed' colspan="<?php print $brief_fields_cnt+1 ?>">
			<table>
<?php
				foreach($detailed_fields as $key => $val){
					$text = htmlspecialchars($row->$key);
					if($key == 'source'){
						if(!empty($row->source_url)){
							$text = '<a href="' . str_replace('{pg}', (int) $row->list_pg + (int) $row->pg_correction, $row->source_url) . '" target="_blank">«' . $text . '»</a>, стр.' . $row->list_pg;
						}else{
							$text = '«' . $text . '», стр.' . $row->list_pg;
						}
					}
					print "\t\t\t\t<tr>\n";
					if($key == 'comments'){
						print "\t\t\t\t\t<td colspan='2' class='comments'>" . $row->$key . "</td>\n";
					}else{
						print "\t\t\t\t\t<th>" . htmlspecialchars($val) . ":</th>\n";
						print "\t\t\t\t\t<td>" . $text . "</td>\n";
					}
					print "\t\t\t\t</tr>\n";
				}
?>
			</table>
		</td>
	</tr>
<?php
			}	// if($show_detailed)
		}	// foreach($this->records)
		if($num == 0):
?>
	<tr>
		<td colspan="<?php print $brief_fields_cnt+2 ?>" style="text-align: center">Ничего не найдено</td>
	</tr>
<?php
		endif;
?>
</tbody></table>
<?php
		print $pag;	// Вывод пагинатора
		if($num != 0):
			static $hints = array(
				'По клику на строке интересной Вам записи открывается дополнительная информация.',
				'По страницам результатов поиска можно перемещаться, используя клавиши <span class="kbdKey">Ctrl</span>+<span class="kbdKey">→</span> и <span class="kbdKey">Ctrl</span>+<span class="kbdKey">←</span>.',
				'Многие записи снабжены ссылками на электронные копии источников, по которым создавалась эта база данных.',
				'Обычно при поиске система автоматически ищет близкие по звучанию или написанию варианты фамилий. Все найденные варианты показываются в конце списка.',
			);
			shuffle($hints);
			print "<p class='nb aligncenter' style='margin-top: 3em'><strong>Обратите внимание:</strong> " . array_shift($hints) . "</p>";
		else:
?>
<div class="notfound"><p>Что делать, если ничего не&nbsp;найдено?</p>
<ol>
	<li>Попробовать разные близкие варианты написания имён, фамилий, мест.
		<div class="nb">Изначально списки писались от-руки в&nbsp;условиях войны и&nbsp;не&nbsp;всегда очень грамотными писарями. Во&nbsp;время их написания, набора в&nbsp;типографии и&nbsp;во&nbsp;время оцифровки их волонтёрами могли закрасться различные ошибки;</div></li>
	<li>Повторить поиск, исключив один из&nbsp;критериев.
		<div class="nb">Возможно, искомые Вами данные по&nbsp;какой-то причине занесены в&nbsp;систему не&nbsp;полностью;</div></li>
	<li>Подождать неделю-другую и повторить поиск.
		<div class="nb">Система постоянно пополняется новыми материалами и, возможно, необходимая Вам информация будет добавлена в&nbsp;неё через некоторое время.</div></li>
</ol></div>
<?php
		endif;
	}
}
