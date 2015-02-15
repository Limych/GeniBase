<?php
/**
 * Класс общего доступа к информации в базе данных.
 * 
 * Класс берёт на себя функции поиска информации в базе. По результатам поиска он возвращает
 * экземпляр класса ww1_records_set, хранящего наёденную информацию и отвечающего за её вывод
 * на экран.
 * 
 * @see ww1_records_set
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/********************************************************************************
 * Абстрактный класс работы с базой данных
 */
define('Q_SIMPLE',		'Q_SIMPLE');	// Простой режим поиска
define('Q_EXTENDED',	'Q_EXTENDED');	// Расширенный режим поиска
//
abstract class ww1_database {
	protected	$query_mode;	// Режим поиска
	public		$query;			// Набор условий поиска
	protected	$page;			// Текущая страница результатов
	public		$have_query;	// Признак наличия данных для запроса
	public		$records_cnt;	// Общее число записей в базе
	
	/**
	 * Создание экземпляра класса.
	 *
	 * @param string $qmode
	 */
	function __construct($qmode = Q_SIMPLE){
		$this->query_mode = $qmode;
		$this->query = array();
		$this->have_query = false;
		$this->records_cnt = 0;

		$this->page = intval($_REQUEST['pg']);
		if($this->page < 1)	$this->page = 1;
	}

	/**
	 * Генерация html-формы поиска.
	 */
	abstract function search_form();

	/**
	 * Осуществление поиска и генерация класса результатов поиска.
	*/
	abstract function do_search();
}



/********************************************************************************
 * Класс работы с базой данных нижних чинов
 */
class ww1_database_solders extends ww1_database {
	var	$surname_ext	= false;
	var	$name_ext		= false;
	const simple_fields		= 'surname name place';
	const extended_fields	= 'surname name rank religion marital region place reason date_from date_to list_nr list_pg';
	const query_fields		= 'id surname name rank religion.religion marital.marital region.region place reason.reason date source.source source.source_url source.pg_correction list_pg comments';
	
	/**
	 * Создание экземпляра класса.
	 *
	 * @param string $qmode
	 */
	function __construct($qmode = Q_SIMPLE){
		global $db;
		parent::__construct($qmode);

		if($qmode == Q_SIMPLE){
			// Простой режим поиска ******************************************
			foreach(explode(' ', self::simple_fields) as $key){
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
				if (is_translit($this->query[$key]))
					$this->query[$key] = translit2rus($this->query[$key]);
				$this->have_query |= !empty($this->query[$key]);
			}
		}else{
			// Расширенный режим поиска **************************************
			$dics = explode(' ', 'rank religion marital reason');
			foreach(explode(' ', self::extended_fields) as $key){
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
				if (is_translit($this->query[$key]))
					$this->query[$key] = translit2rus($this->query[$key]);
				if(in_array($key, $dics) && !is_array($this->query[$key]))
					$this->query[$key] = array();
				$this->have_query |= !empty($this->query[$key]);
			}
			$this->surname_ext	= isset($_REQUEST['surname_ext']);
			$this->name_ext		= isset($_REQUEST['name_ext']);
		}	// if

		// Считаем, сколько всего записей в базе
		$this->records_cnt = $db->get_cell('SELECT COUNT(*) FROM persons');
	}

	/**
	 * Генерация html-формы поиска.
	 */
	function search_form(){
		global $db;
		
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Выводим html-поля
			$fields = array(
					'surname'	=> 'Фамилия',
					'name'		=> 'Имя-отчество',
					'place'		=> 'Место жительства',
			);
			foreach($fields as $key => $val){
				print "\t<div class='field'><label for='q_$key'>$val:</label> <input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "'></div>\n";
			}
			return;
		}

		// Расширенный режим поиска **************************************
		$dics = array();

		// Получаем список всех вариантов значений воиских званий
		$dics['rank'] = $db->get_column('SELECT DISTINCT rank, rank FROM persons WHERE rank != "" ORDER BY rank',
				array(), TRUE);

		// Получаем список всех вариантов значений вероисповеданий
		$dics['religion'] = $db->get_column('SELECT id, religion FROM dic_religion WHERE religion_cnt != 0 ORDER BY religion',
				array(), TRUE);

		// Получаем список всех вариантов значений семейных положений
		$dics['marital'] = $db->get_column('SELECT id, marital FROM dic_marital WHERE marital_cnt != 0 ORDER BY marital',
				array(), TRUE);

		// Получаем список всех вариантов значений событий
		$dics['reason'] = $db->get_column('SELECT id, reason FROM dic_reason WHERE reason_cnt != 0 ORDER BY reason',
				array(), TRUE);

		// Выводим html-поля
		static $fields = array(
				'surname'	=> 'Фамилия',
				'name'		=> 'Имя-отчество',
				'rank'		=> 'Воинское звание',
				'religion'	=> 'Вероисповедание',
				'marital'	=> 'Семейное положение',
				'region'	=> 'Губерния, уезд, волость',
				'place'		=> 'Волость/Нас.пункт',
				'reason'	=> 'Событие',
				'date'		=> 'Дата события',
				'list_nr'	=> 'Номер списка',
				'list_pg'	=> 'Страница списка',
		);
		foreach($fields as $key => $val)
			switch($key){
			case 'surname':
				// Текстовые поля
				
				//Отключение фонетического поиска
				//print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /><label><input type='checkbox' name='surname_ext' value='1'" . (!isset($_GET['surname_ext']) ? "" : " checked='checked'") . " />&nbsp;фонетический поиск по&nbsp;фамилиям</label></div></div>\n";
				print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /></div></div>\n";
				break;
			case 'name':
				// Текстовые поля
				print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /><label><input type='checkbox' name='name_ext' value='1'" . (!isset($_GET['name_ext']) ? "" : " checked='checked'") . " />&nbsp;автоматическое расширение поиска</label></div></div>\n";
				break;
			case 'rank':
			case 'religion':
			case 'marital':
			case 'reason':
				// Списковые поля
				print "\t<div class='field'><label for='q_$key'>$val:</label> <select id='q_$key' name='${key}[]' multiple='multiple' size='5'>\n";
				foreach($dics[$key] as $k => $v){
					print "\t\t<option value='" . htmlspecialchars($k) . "'" . (is_array($this->query[$key]) && in_array($k, $this->query[$key]) ? " selected='selected'" : '') . ">" . htmlspecialchars($v) . "</option>\n";
				}
				print "</select></div>\n";
				break;
			case 'date':
				// Поля дат
				print "\t<div class='field'><label for='q_$key'>$val:</label> c&nbsp;<input type='date' id='q_$key' name='date_from' value='" . htmlspecialchars($this->query['date_from']) . "' min='1914-07-28' max='1918-11-11' /> по&nbsp;<input type='date' name='date_to' value='" . htmlspecialchars($this->query['date_to']) . "' min='1914-07-28' max='1918-11-11' /></div>\n";
				break;
			default:
				// Текстовые поля
				print "\t<div class='field'><label for='q_$key'>$val:</label> <input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /></div>\n";
				break;
			}	// switch
	}	// function

		
		
	/**
	 * Осуществление поиска и генерация класса результатов поиска.
	 */
	function do_search(){
		global $db;

		$sort_by = '`surname` ASC, `name`';
		$strictMatch = '';
		$cond = array();
			$select = 'SELECT ' . (!defined('SQL_DEBUG_PROF') ? '' : 'SQL_NO_CACHE ');
		
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Формируем основной поисковый запрос в БД
			foreach(explode(' ', self::simple_fields) as $key){
				$val = fix_russian($this->query[$key]);
				if(empty($val))	continue;

				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'name' && !$is_regex)
						$q = 'LOWER(`name`) RLIKE ' . implode(' AND LOWER(`name`) RLIKE ', GB_DBase::data_escape(expand_names($val), TRUE));
				else{
					if($key == 'place'){
						// Удаляем слова типа «губерния», «уезд» и т.п.
						global $region_short;
						$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
					}
					$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
						$q = "LOWER(`$key`) RLIKE " . implode(" AND LOWER(`$key`) RLIKE ", GB_DBase::data_escape(GB_DBase::make_regex($val_a), TRUE));
					if($key == 'surname' && !$is_regex){
//Отключение фонетического поиска
// 						$tmp = array();
// 						foreach (make_search_keys($val_a) as $term)
// 							$tmp[] = '`surname_key` IN (' . GB_DBase::data_escape($term) . ')';
// 						$q = '(' . $q . ' OR id IN ( ' . $select . 'person_id FROM idx_search_keys WHERE ' . implode(' AND ', $tmp) . ' ))';
						$strictMatch = ', `surname` LIKE ' . GB_DBase::data_escape("%$val%") . ' AS `strictMatch`';
						$sort_by = '`strictMatch` DESC, `name` ASC, `surname`';

					} elseif ($key == 'place') {
						$q = 'LOWER(CONCAT_WS(",", ( SELECT `region` FROM `dic_region` AS sq WHERE sq.`id` = p.`region_id` ), `place`)) RLIKE ' .
								implode(' AND ' . $tmp, GB_DBase::data_escape(GB_DBase::make_regex($val_a), TRUE));
					}
				}
				$cond[] = $q;
			}

		}else{
			// Расширенный режим поиска **************************************

			// Формируем основной поисковый запрос в БД
			$nums = explode(' ', 'religion marital reason list_nr list_pg');	// Список полей, в которых передаются числовые данные
			$ids = explode(' ', 'religion marital reason');	// Список полей, в которых передаются идентификаторы
			foreach(explode(' ', self::extended_fields) as $key){
					$val = $this->query[$key];
					if(!is_array($val))
						$val = fix_russian($val);
				if(empty($val))	continue;

				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'date_from'){
					// Дата с
					$q = '`date_to` >= STR_TO_DATE(' . GB_DBase::data_escape($val) . ', "%Y-%m-%d")';
				}elseif($key == 'date_to'){
					// Дата по
					$q = '`date_from` <= STR_TO_DATE(' . GB_DBase::data_escape($val) . ', "%Y-%m-%d")';
				}elseif(in_array($key, $nums)){
					// Числовые данные
					if(in_array($key, $ids))
						$key .= '_id';	// Модифицируем название поля
					if(!is_array($val))
						$val = preg_split('/\D+/uS', trim($val));
					$val = implode(', ', array_map('intval', $val));
					if(false === strchr($val, ','))
						$q = "`$key` = $val";	// Одиночное значение
					else
						$q = "`$key` IN ($val)";	// Множественное значение
				}else{
					// Текстовые данные…
					if(is_array($val)){
						// … в виде массива строк
						$q = array();
						foreach($val as $v)
							$q[] = "`$key` = " . GB_DBase::data_escape($v);
						$q = '(' . implode(' OR ', $q) . ')';
					}else{
						// … в виде строки
						if($key == 'name' && !is_regex && $this->name_ext)
							$q = "LOWER(`name`) RLIKE " . implode(" AND LOWER(`name`) RLIKE ", GB_DBase::data_escape(expand_names($val), TRUE));
						else{
							if($key == 'region' || $key == 'place'){
								// Удаляем слова типа «губерния», «уезд» и т.п.
								global $region_short;
								$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
							}
							$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
								if ($key == 'region') {
									$q = '( SELECT LOWER(`region`) RLIKE ' . implode(' AND LOWER(`region`) RLIKE ', GB_DBase::data_escape(GB_DBase::make_regex($val_a), TRUE)) . ' FROM dic_region AS sq WHERE sq.id = p.region_id )';
								} else {
							$q = "LOWER(`$key`) RLIKE " . implode(" AND LOWER(`$key`) RLIKE ", GB_DBase::data_escape(GB_DBase::make_regex($val_a), TRUE));
								}
							if($key == 'surname' && $this->surname_ext && !$is_regex){
//Отключение фонетического поиска
// 								$tmp = array();
// 								foreach (make_search_keys($val_a) as $term)
// 									$tmp[] = '`surname_key` IN (' . GB_DBase::data_escape($term) . ')';
// 								$q = '(' . $q . ' OR id IN ( ' . $select . 'person_id FROM idx_search_keys WHERE ' . implode(' AND ', $tmp) . ' ))';
								$strictMatch = ', `surname` LIKE ' . GB_DBase::data_escape("%$val%") . ' AS `strictMatch`';
								$sort_by = '`strictMatch` DESC, `name` ASC, `surname`';
							}
						}
					}
				}
				$cond[] = $q;
			}
		}
		$cond = implode(' AND ', $cond);

		if (defined('SQL_DEBUG_PROF'))
			$db->query('SET PROFILING=1');

		// Считаем, сколько результатов найдено
		$query = $select . 'COUNT(*) FROM persons AS p WHERE ' . $cond;
if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($query);	print "\n -->\n";	}
		$cnt = $db->get_cell($query);
		
		// Запрашиваем текущую порцию результатов для вывода в таблицу
		$fields = array_map(function ($field) use ($select) {
			$tmp = explode('.', $field);
			if (count($tmp) == 1)
				return $field;
			return vsprintf('( ' . $select . '%2$s FROM dic_%1$s AS sq WHERE sq.id = p.%1$s_id ) AS %2$s', $tmp);
		}, explode(' ', self::query_fields));
		$query = $select . implode(', ', $fields) . $strictMatch . ' ,p.id FROM persons AS p WHERE ' . $cond . ' ORDER BY ' . $sort_by . ' ASC, region ASC, place ASC LIMIT ' . (($this->page - 1) * Q_LIMIT) . ', ' . Q_LIMIT;
if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($query);	print "\n -->\n";	}
		$data = $db->get_table($query, array(), 'id');
		//
		// Дополняем данные новыми (неформализуемыми) полями
		$add_fields = $db->get_row('SELECT military_unit, place_of_event, estate_or_title, additional_info, birthdate FROM persons_raw WHERE id IN (:ids)',
				array('ids' => array_keys($data)));
		$add_fields = array_filter(array_map('array_filter', $add_fields));
		array_merge($data, $add_fields);
		//
		$report = new ww1_solders_set($this->page, $data, $cnt);

		if (defined('SQL_DEBUG_PROF')) {
			print("\n<!-- SQL-Profile:\n");
			$total = 0;
			$result = $db->get_column('SHOW PROFILE', TRUE);
			foreach ($result as $key => $val){
				printf("%20s: %7.3f sec\n", $key, $val);
				if ($row[0] == 'Table lock')
					continue;
				$total += $val;
			}
			printf("-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-\n%20s: %7.3f sec\n -->\n", 'Total', $total);
			$db->query('SET PROFILING=0');
		}

		return $report;
	}
}