<?php
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
	const query_fields		= 'persons.id persons.surname persons.name persons.rank dic_religion.religion dic_marital.marital dic_region.region persons.place dic_reason.reason persons.date dic_source.source dic_source.source_url dic_source.pg_correction persons.list_pg persons.comments';
	
	/**
	 * Создание экземпляра класса.
	 *
	 * @param string $qmode
	 */
	function __construct($qmode = Q_SIMPLE){
		parent::__construct($qmode);

		if($qmode == Q_SIMPLE){
			// Простой режим поиска ******************************************
			foreach(explode(' ', self::simple_fields) as $key){
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
				$this->have_query |= !empty($this->query[$key]);
			}
		}else{
			// Расширенный режим поиска **************************************
			$dics = explode(' ', 'rank religion marital reason');
			foreach(explode(' ', self::extended_fields) as $key){
				$this->query[$key] = ($_REQUEST[$key] ? $_REQUEST[$key] : '');
				if(in_array($key, $dics) && !is_array($this->query[$key]))
					$this->query[$key] = array();
				$this->have_query |= !empty($this->query[$key]);
			}
			$this->surname_ext	= isset($_REQUEST['surname_ext']);
			$this->name_ext		= isset($_REQUEST['name_ext']);
		}	// if

		// Считаем, сколько всего записей в базе
		$query = 'SELECT COUNT(*) FROM persons';
		$result = db_query($query);
		$cnt = $result->fetch_array(MYSQL_NUM);
		$result->free();
		$this->records_cnt = intval($cnt[0]);
	}

	/**
	 * Генерация html-формы поиска.
	 */
	function search_form(){
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
		$dics['rank'] = array();
		$result = db_query('SELECT DISTINCT rank FROM persons WHERE rank != "" ORDER BY rank');
		while($row = $result->fetch_array(MYSQL_NUM)){
			$dics['rank'][$row[0]] = $row[0];
		}
		$result->free();

		// Получаем список всех вариантов значений вероисповеданий
		$dics['religion'] = array();
		$result = db_query('SELECT * FROM dic_religion WHERE religion_cnt != 0 ORDER BY religion');
		while($row = $result->fetch_object()){
			$dics['religion'][$row->id] = $row->religion;
		}
		$result->free();

		// Получаем список всех вариантов значений семейных положений
		$dics['marital'] = array();
		$result = db_query('SELECT * FROM dic_marital WHERE marital_cnt != 0 ORDER BY marital');
		while($row = $result->fetch_object()){
			$dics['marital'][$row->id] = $row->marital;
		}
		$result->free();

		// Получаем список всех вариантов значений причин выбытия
		$dics['reason'] = array();
		$result = db_query('SELECT * FROM dic_reason WHERE reason_cnt != 0 ORDER BY reason');
		while($row = $result->fetch_object()){
			$dics['reason'][$row->id] = $row->reason;
		}
		$result->free();

		// Выводим html-поля
		static $fields = array(
				'surname'	=> 'Фамилия',
				'name'		=> 'Имя-отчество',
				'rank'		=> 'Воинское звание',
				'religion'	=> 'Вероисповедание',
				'marital'	=> 'Семейное положение',
				'region'	=> 'Губерния, уезд, волость',
				'place'		=> 'Волость/Нас.пункт',
				'reason'	=> 'Причина выбытия',
				'date'		=> 'Дата выбытия',
				'list_nr'	=> 'Номер списка',
				'list_pg'	=> 'Страница списка',
		);
		foreach($fields as $key => $val)
			switch($key){
			case 'surname':
				// Текстовые поля
				print "\t<div class='field'><label for='q_$key'>$val:</label> <div class='block'><input type='text' id='q_$key' name='$key' value='" . htmlspecialchars($this->query[$key]) . "' /><br /><label><input type='checkbox' name='surname_ext' value='1'" . (!isset($_GET['surname_ext']) ? "" : " checked='checked'") . " />&nbsp;фонетический поиск по&nbsp;фамилиям</label></div></div>\n";
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
		$sort_by = '`surname` ASC, `name`';
		$strictMatch = '';
		$cond = array();
		
/*** ↓↓↓ Удалить после августа 2014 ↓↓↓ *************************************************/
		// Проверка на старые метасимволы и выдача предупреждения
		foreach($this->query as $val){
			if(preg_match('/[_%]/uS', $val)){
				print "<p class='aligncenter' style='color: red'>Обратите внимание, что изменились метасимволы, которые обозначают неизвестную часть слова.</p>\n";
				break;
			}
		}
/*** ↑↑↑ Удалить после августа 2014 ↑↑↑ *************************************************/

		// SELECT t.* FROM test AS t WHERE t.fio LIKE REPLACE(REPLACE('Ко*лев', '?', '_'), '*', '%') UNION SELECT t.* FROM test AS t WHERE 'Ко*лев' LIKE REPLACE(REPLACE(t.fio, '?', '_'), '*', '%')
		
		if($this->query_mode == Q_SIMPLE){
			// Простой режим поиска ******************************************

			// Формируем основной поисковый запрос в БД
			foreach(explode(' ', self::simple_fields) as $key){
				$val = fix_russian($this->query[$key]);
				if(empty($val))	continue;

				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'name' && !$is_regex)
					$q = "LOWER(`name`) RLIKE '" . implode("' AND LOWER(`name`) RLIKE '", db_escape(expand_names($val))) . "'";
				else{
					if($key == 'place'){
						// Удаляем слова типа «губерния», «уезд» и т.п.
						global $region_short;
						$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
					}
					$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
					$q = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", db_escape(db_regex($val_a))) . "'";
					if($key == 'surname' && !$is_regex){
						$q = "($q OR `surname_key` RLIKE '" . implode("' AND `surname_key` RLIKE '", db_escape(db_regex(make_search_keys($val_a)))) . "')";
						$strictMatch = ', `surname` LIKE "%' . db_escape($val) . '%" AS `strictMatch`';
						$sort_by = '`strictMatch` DESC, `name` ASC, `surname_key`';
					}
				}
				if($key == 'place'){
					$q = "LOWER(CONCAT_WS(',', `region`, `place`)) RLIKE '" . implode("' AND LOWER(CONCAT_WS(',', `region`, `place`)) RLIKE '", db_escape(db_regex($val_a))) . "'";
				}
				$cond[] = $q;
			}

		}else{
			// Расширенный режим поиска **************************************

			// Формируем основной поисковый запрос в БД
			$nums = explode(' ', 'religion marital reason list_nr list_pg');	// Список полей, в которых передаются числовые данные
			$ids = explode(' ', 'religion marital reason');	// Список полей, в которых передаются идентификаторы
			foreach(explode(' ', self::extended_fields) as $key){
				$val = fix_russian($this->query[$key]);
				if(empty($val))	continue;

				$is_regex = preg_match('/[?*]/uS', $val);
				$q = '';
				if($key == 'date_from'){
					// Дата с
					$q = '`date_to` >= STR_TO_DATE("' . db_escape($val) . '", "%Y-%m-%d")';
				}elseif($key == 'date_to'){
					// Дата по
					$q = '`date_from` <= STR_TO_DATE("' . db_escape($val) . '", "%Y-%m-%d")';
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
							$q[] = "`$key` = '" . db_escape($v) . "'";
						$q = '(' . implode(' OR ', $q) . ')';
					}else{
						// … в виде строки
						if($key == 'name' && !is_regex && $this->name_ext)
							$q = "LOWER(`name`) RLIKE '" . implode("' AND LOWER(`name`) RLIKE '", db_escape(expand_names($val))) . "'";
						else{
							if($key == 'region' || $key == 'place'){
								// Удаляем слова типа «губерния», «уезд» и т.п.
								global $region_short;
								$val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
							}
							$val_a = preg_split('/[^\w\?\*]+/uS', mb_strtolower($val), -1, PREG_SPLIT_NO_EMPTY);
							$q = "LOWER(`$key`) RLIKE '" . implode("' AND LOWER(`$key`) RLIKE '", db_escape(db_regex($val_a))) . "'";
							if($key == 'surname' && $this->surname_ext && !$is_regex){
								$q = "($q OR `surname_key` RLIKE '" . implode("' AND `surname_key` RLIKE '", db_escape(db_regex(make_search_keys($val_a)))) . "')";
								$strictMatch = ', `surname` LIKE "%' . db_escape($val) . '%" AS `strictMatch`';
								$sort_by = '`strictMatch` DESC, `name` ASC, `surname_key`';
							}
						}
					}
				}
				$cond[] = $q;
			}
		}
		$select = 'SELECT ' . (!defined('SQL_DEBUG_PROF') ? '' : 'SQL_NO_CACHE ');
		$joins =
			' LEFT JOIN dic_region ON dic_region.id = persons.region_id' .
			' LEFT JOIN dic_religion ON dic_religion.id = persons.religion_id' .
			' LEFT JOIN dic_marital ON dic_marital.id = persons.marital_id' .
			' LEFT JOIN dic_reason ON dic_reason.id = persons.reason_id' .
			' LEFT JOIN dic_source ON dic_source.id = persons.source_id';
		$cond = implode(' AND ', $cond);

		if (defined('SQL_DEBUG_PROF')) {
			db_query('SET PROFILING=1');
		}

		// Считаем, сколько результатов найдено
		$query = $select . 'COUNT(*) FROM persons' . $joins . ' WHERE ' . $cond;
if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($query);	print "\n -->\n";	}
		$result = db_query($query);
		$cnt = $result->fetch_array(MYSQL_NUM);
		$result->free();
		
		// Запрашиваем текущую порцию результатов для вывода в таблицу
		$query = $select . implode(', ', explode(' ', self::query_fields)) . $strictMatch . ' FROM persons' . $joins . ' WHERE ' . $cond . ' ORDER BY ' . $sort_by . ' ASC, region ASC, place ASC LIMIT ' . (($this->page - 1) * Q_LIMIT) . ', ' . Q_LIMIT;
if(defined('HIDDEN_DEBUG')){	print "\n<!-- \n";	var_export($query);	print "\n -->\n";	}
		$result = db_query($query);
		$report = new ww1_solders_set($this->page, $result, $cnt[0]);
		$result->free();

		if (defined('SQL_DEBUG_PROF')) {
			$result = db_query('SHOW PROFILE');
			$profile = 0;
			print("\n<!-- SQL-Profile:\n");
			while ($row = $result->fetch_array(MYSQL_NUM)) {
				printf("%20s: %7.3f sec\n", $row[0], $row[1]);
				if ($row[0] == 'Table lock')
					continue;
				$profile += $row[1];
			}
			$result->free();
			printf("-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-\n%20s: %7.3f sec\n -->\n", 'Total', $profile);
			db_query('SET PROFILING=0');
		}

		return $report;
	}
}