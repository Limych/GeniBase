<?php
/**
 * Класс хранения результатов поиска в базе данных и отображения их на экране.
 * 
 * Класс берёт на себя функции хранения информации и правильного отображения их пользователю.
 * Для выборки информации из базы используется парный класс ww1_database.
 *  
 * @see ww1_database
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



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
	var	$records;
	public	$records_cnt;

	// Создание экземпляра класса и сохранение результатов поиска
	function __construct($page, $data = array(), $records_cnt = NULL){
		parent::__construct($page);

		$this->records = array();
		foreach ($data as $row){
			if( $row['religion'] == '(иное)')
				$row['religion'] = gbdb()->get_cell('SELECT religion FROM ?_persons_raw WHERE `id` = ?id',
						array('id' => $row['id']));
			
			if( $row['marital'] == '(иное)')
				$row['marital'] = gbdb()->get_cell('SELECT marital FROM ?_persons_raw WHERE `id` = ?id',
						array('id' => $row['id']));
			
			if( $row['reason'] == '(иное)')
				$row['reason'] = gbdb()->get_cell('SELECT reason FROM ?_persons_raw WHERE `id` = ?id',
						array('id' => $row['id']));
			
			$this->records[] = $row;
		}

		$this->records_cnt = ($records_cnt !== NULL ? $records_cnt : count($this->records));
	}

	/**
	 * For internal use.
	 * 
	 * @param unknown $brief_fields
	 * @param unknown $detailed_fields
	 */
	private function _show_report($brief_fields, $detailed_fields){
		$max_pg = max(1, ceil($this->records_cnt / Q_LIMIT));
		if( $this->page > $max_pg )
			$this->page = $max_pg;

		$brief_fields_cnt = count($brief_fields);
		if( false !== ($show_detailed = !empty($detailed_fields)) ){
			gb_enqueue_script('ww1-records', site_url('/js/ww1-records.js'), array('jquery'));
		}
	
		// Формируем пагинатор
		$pag = paginator($this->page, $max_pg);
		print $pag;	// Вывод пагинатора
		// TODO: gettext
		print '<table id="report" class="report responsive-table"><thead><tr><th scope="col">' . _x('No.', 'Table header “Item number”', WW1_TXTDOM) . '</th>';
		foreach(array_values($brief_fields) as $val)
			print "<th scope='col'>" . esc_html($val) . "</th>";
		if( $show_detailed )
			print "<th class='no-print'></th>";
		print "</tr></thead><tbody>";
		$even = 0;
		$num = ($this->page - 1) * Q_LIMIT;
		foreach($this->records as $row){
			$even = 1-$even;
			print "<tr class='brief" . ($even ? ' even' : ' odd') . " id_" . $row['id'] . (!isset($row['fused_match']) || empty($row['fused_match']) ? '' : ' nonstrict-match') . "'>\n";
			// TODO: gettext
			$details = '. ' . ($row['surname'] ? $row['surname'] : '<span class="na">(фамилия не указана)</span>') . ', ' . ($row['name'] ? $row['name'] : '<span class="na">(имя не указано)</span>'); 
			print "<td scope='row' class='align-right'>" . (++$num) . "<span class='rt-show'>$details</span></td>\n";
			foreach($brief_fields as $key => $title){
				// TODO: gettext
				$val = $row[$key] ? esc_html($row[$key]) : '(не&nbsp;указано)';
				if( substr($val, 0, 1) === '(')	$val = "<span class='na'>$val</span>";
				print "<td " . ($key == 'surname' || $key == 'name' ? "class='rt-hide'" : "data-rt-title='" . esc_attr($title) . ": '") . ">" . $val . "</td>\n";
			}
			if( $show_detailed ){
				print "<td class='rt-hide no-print'><div class='arrow'></div></td>\n";

				print "</tr><tr class='detailed h" . ($even ? ' even' : ' odd') . "'>\n";
?>
		<td></td>
		<td class='detailed' colspan="<?php print $brief_fields_cnt+1; ?>">
			<table>
<?php
				foreach($detailed_fields as $key => $val){
					if( !isset($row[$key]) )
						continue;
					$text = esc_html($row[$key]);
					if( $key == 'source' ){
						if( !empty($row['source_url']) ){
							if( $row['source_pg'] > 0 ){
								// TODO: gettext
								$text = '<a href="' . str_replace('{pg}', (int) ($row['source_pg'] + $row['source_pg_corr']), $row['source_url']) . '" target="_blank">«' . $text . '»</a>, стр.' . $row['source_pg'];
							}else{
								// TODO: gettext
								$text = '<a href="' . $row['source_url'] . '" target="_blank">«' . $text . '»</a>';
							}
						} else{
							// TODO: gettext
							$text = '«' . $text . '», стр.' . $row['source_pg'];
						}
					}

					if( !empty($text) ){
						print "<tr>\n";
						if( $key == 'comments' )					
							print "<td colspan='2' class='comments'>" . $row[$key] . "</td>\n";
						else {
							print "<th>" . $val . ":</th>\n";
							// TODO: gettext
							$text = $text ? $text : '(не&nbsp;указано)';
							if( substr($text, 0, 1) === '(' )
								$text = "<span class='na'>$text</span>";
							print "<td>" . $text . "</td>\n";
						}
						print "</tr>\n";
					}
				}
				print "</table></td></tr>";
			}	// if( $show_detailed)
		}	// foreach($this->records)
		print "</tbody></table>\n";
		print $pag;	// Вывод пагинатора

		static $hints;
		if( !is_array($hints) ){
			$hints = array(
				__('By clicking on the line that you are interested in, you will see an additional information.', WW1_TXTDOM),
				__('You can navigate through the pages of search results by using the keys <span class="kbdKey">Ctrl</span>+<span class="kbdKey">→</span> and <span class="kbdKey">Ctrl</span>+<span class="kbdKey">←</span>', WW1_TXTDOM),
				__('Many records have links to electronic copies of sources on which the database is created.', WW1_TXTDOM),
				__('Usually when searching system automatically searches for similar-sounding names or spelling variations. All of these findings are displayed at the end of the list and highlighted.', WW1_TXTDOM),
			);
		}
		shuffle($hints);
		print "<p class='nb align-center no-print' style='margin-top: 3em'><strong>" .
				__('Please note:', WW1_TXTDOM) . "</strong> " . array_shift($hints) . "</p>";
	} // function
	
	/**
	 * Вывод результатов поиска в виде html-таблицы
	 * 
	 * @see ww1_records_set::show_report()
	 */
	function show_report($brief_fields = NULL, $detailed_fields = array()){
		if( $this->records_cnt){
			print '<p class="align-center">' . sprintf(_n('Found %s record.', 'Found %s records.',
					$this->records_cnt, WW1_TXTDOM), number_format_i18n($this->records_cnt)) . '</p>';
			$this->_show_report($brief_fields, $detailed_fields);
		}else{
			print '<p class="align-center">' . _('Unfortunately, nothing found.', WW1_TXTDOM) . '</p>';
			// TODO: gettext
?>
<div class="notfound"><p><?php _e('What to do now?', WW1_TXTDOM); ?></p>
<ol>
	<li><?php _e('Try to search for different spellings for names and places.', WW1_TXTDOM); ?>
		<div class="nb">Изначально списки писались от-руки в&nbsp;условиях войны и&nbsp;не&nbsp;всегда очень грамотными писарями. Во&nbsp;время их написания, набора в&nbsp;типографии и&nbsp;во&nbsp;время оцифровки их волонтёрами могли закрасться различные ошибки;</div></li>
	<li>Повторить поиск, исключив один из&nbsp;критериев.
		<div class="nb">Возможно, искомые Вами данные по&nbsp;какой-то причине занесены в&nbsp;систему не&nbsp;полностью;</div></li>
	<li>Подождать неделю-другую и повторить поиск.
		<div class="nb">Система постоянно пополняется новыми материалами и, возможно, необходимая Вам информация будет добавлена в&nbsp;неё через некоторое время.</div></li>
</ol></div>
<?php
		} // if( $this->records_cnt)
	}
}