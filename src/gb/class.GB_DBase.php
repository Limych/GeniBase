<?php
/**
 * Класс общего доступа к СУБД MySQL.
 * 
 * Этот файл базируется на коде, опубликованным Михаилом Серовым по адресу
 * @see http://webew.ru/articles/3237.webew
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © 2010, Michail Serov
 */

// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');

// Проверка версии PHP
if(version_compare(phpversion(), "5.3.0", "<"))	die('<b>ERROR:</b> PHP version 5.3+ needed!');



/***************************************************************************
 * Класс работы с базой данных
 */
class GB_DBase	{
	/**	@var MySQLi */
	protected	$db;
	protected	$host, $user, $password, $base, $prefix;
	
	
	
	/**
	 * Создание экземпляра класса.
	 */
	function __construct($host, $user, $password, $base, $prefix = ''){
		$this->db = NULL;

		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->base = $base;
		$this->prefix = $prefix;
	}



	/**
	 * Уничтожаем экземпляр класса.
	 */
	function __destruct(){
		// Закрываем соединение с СУБД, если оно было
		if($this->db)	$this->db->close();
	}
	
	
	
	/**
	 * Соединяемся с СУБД, выбираем базу данных.
	 */
	protected function connect(){
		if($this->db)	return;
	
		$this->db = new MySQLi($this->host, $this->user, $this->password, $this->base);
		if ($this->db->connect_error) {
			@header('HTTP/1.1 503 Service Temporarily Unavailable');
			@header('Status: 503 Service Temporarily Unavailable');
			@header('Retry-After: 600');	// 600 seconds
			die('Ошибка подключения (' . $this->db->connect_errno . ') ' . $this->db->connect_error);
		}
	
		// Проверка версии MySQL
		if(version_compare($this->db->server_info, "5.0.0", "<"))	die('<b>ERROR:</b> MySQL version 5.0+ needed!');
	
		$this->db->set_charset('utf8');
	}
	
	
	
	/**
	 * Добавление префикса к имени таблицы.
	 * 
	 * @param string $table	Исходное имя таблицы
	 * @return string	Имя таблицы с префиксом
	 */
	function table_escape($table){
		if(substr($table, 0, 1) == '`')	// First unescape table name if it already escaped
			$table = strtr(trim($table, '`'), '``', '`');

		return self::field_escape($this->prefix . $table);
	}



	/**
	 * Экранирование значения переменной, учитывая его тип.
	 *
	 * @param mixed $value	Значение переменной
	 * @return mixed	Экранированное значение переменной
	 */
	function field_escape($value) {
		if (is_array($value))
			return implode(', ', array_map(__FUNCTION__, $value));
	
		else
			return '`' . str_replace('`', '``', $value) . '`';
	}



	/**
	 * Экранирование значения переменной, учитывая его тип.
	 *
	 * @param mixed $value	Значение переменной
	 * @param boolean $preserve_array	TRUE, чтобы возвращать массивы в виде массивов
	 * @return mixed	Экранированное значение переменной
	 */
	function data_escape($value, $preserve_array = FALSE) {
		if (is_array($value)){
			$result = array_map(__FUNCTION__, $value);
			return ($preserve_array)
				? $result
				: implode(',', $result);
	
		}elseif (is_string($value)){
			$this->connect();
			return '"' . $this->db->real_escape_string($value) . '"';
	
		}elseif (is_numeric($value))
			return $value;
	
		elseif (is_null($value))
			return 'NULL';
	
		else
			return intval($value);
	}
	


	/**
	 * Формирование из строки с метасимволами регулярного выражения для поиска
	 * в системе.
	 * 
	 * Используемые метасимволы: '?' — один любой символ; '*' — один или несколько
	 * любых символов.
	 * 
	 * @param string $str	Строка с метасимволами
	 * @param string $full_word	FALSE, если не надо искать по этой маске только полные слова
	 * @return string	Регулярное выражение для поиска
	 */
	static function make_regex($str, $full_word = TRUE){
		// Если вместо строки передан массив, обработать каждое значение в отдельности
		// и вернуть результат в виде массива
		if(is_array($str)){
			foreach($str as $key => $val)
				$str[$key] = GB_DBase::regex($val, $full_word);
			return $str;
		}
	
		$str = strtr($str, array(
				'ё'	=> '(е|ё)',
				'Ё'	=> '(Е|Ё)',
		));
		$str = preg_replace_callback('/(\?+|\*+)/uS', function ($matches){
			$ch = substr($matches[1], 0, 1);
			$len = strlen($matches[1]);
			// return '[[:alpha:]]' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}'));
			return '(..)' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}'));	// Костыли для учёта двухбайтной кодировки
		}, $str);
		if($full_word)
			$str = "[[:<:]]${str}[[:>:]]";
		return $str;
	}
	


	/**
	 * Отправка запроса в MySQL и слежение за ошибками.
	 * 
	 * Подстановка параметров: ":&lt;key>" — подстановка данных, ":#&lt;key>" — подстановка имени поля,
	 * 		":@&lt;key>" — подстановка имени таблицы
	 *
	 * @param string $query	SQL-запрос
	 * @param array $substitutions	Ассоциативный массив параметров для подстановки в запрос 
	 * @return mixed	Результат выполнения запроса
	 */
	function query($query, $substitutions = array()) {
		if ($substitutions) {
			// Чтобы следующая метка не могла затронуть содержание предыдущей,
			// например, в случае $subst = array('id' => 5, 'title' => 'а тут :id'),
			// проводить их замену приходится не по очереди через простой foreach,
			// а за один вызов заменяющий функции,
			// для чего нужно составить регулярное выражение, охватывающее
			// все метки. Впрочем, это несложно.
			// О производительности здесь беспокоиться не будем,
			// т.к. запрос - это довольно короткая строка, поэтому он
			// будет обработан быстро в любом случае.
	
			$regexp = '/:(';
			foreach ($substitutions as $key => $value)
				$regexp .= $key
					. (
							substr($key, -1) != '`' // нужно учесть,
							? '\b' // что теоретически метки могут быть
							: ''   // не только вида :word, но и вида :`...`
					)
					. '|';
	
			$regexp = substr($regexp, 0, -1); // убираем лишний '|'
			$regexp .= ')/';
	
			$query = preg_replace_callback(
					$regexp,
					function($matches) use ($substitutions) {
						$type = substr($matches{1}, 0, 1);	// Определяем тип информации для подстановки
						return ($type == '@')
							? gbdb()->table_escape($substitutions[$matches{1}])	// Кодируем имя таблицы
							: ($type == '#')
								? gbdb()->field_escape($substitutions[$matches{1}])	// Кодируем имя поля
								: gbdb()->data_escape($substitutions[$matches{1}]);	// Кодируем данные
					},
					$query
			);
		}
	
		$this->connect();
		$result = $this->db->query($query);
		if ($result)
			return $result;
	
		// Error detected. Print backtrace and stop script
		$trace = debug_backtrace();
		$mysql_functions = array(
				'GB_DBase::get_cell',
				'GB_DBase::getrow',
				'GB_DBase::get_column',
				'GB_DBase::get_table',
				'GB_DBase::write_row'
		);
		if (isset($trace[1]) AND in_array($trace[1]['function'], $mysql_functions))
			$level = 1;
		else
			$level = 0;

		$db_error = $this->db->error;

		$message = '<p><strong>MySQL error</strong> in file <strong>'.$trace[$level]['file'].'</strong>' .
			" at line <strong>" .$trace[$level]['line']."</strong> " .
			"(function <strong>" . $trace[$level]['function'] ."</strong>):<br/>" .
			"\n<span style='color:blue'>$db_error</span>\n\n<pre>$query</pre></p>";
		trigger_error($message, E_USER_ERROR);
		die();
	}

	
	
	/**
	 * Получение результата запроса, который состоит из нескольких строк и одного
	 * столбца.
	 * 
	 * @see GB_DBase::query()
	 * 
	 * @param string $query			SQL-запрос
	 * @param array $substitutions	Ассоциативный массив параметров для подстановки в запрос 
	 * @param boolean $get_assoc	TRUE для превращения первого столбца результата
	 * 								в ключи массива, а второго — в значения.
	 * @return array	Результат выполнения запроса
	 */
	function get_column($query, $substitutions = array(), $get_assoc = FALSE) {
		$result = $this->query($query, $substitutions);
	
		$data = array();
		if ($get_assoc) {
			while ($row = $result->fetch_row())
				$data[$row{0}] = $row[1];
		} else {
			while ($row = $result->fetch_row())
				$data[] = $row[0];
		}

		$result->free();
		return $data;
	}
	
	
	
	/**
	 * Получения результата скалярного запроса (состоящего из одной строки и одной
	 * ячейки в ней).
	 * 
	 * @see GB_DBase::query()
	 * 
	 * @param string $query	SQL-запрос
	 * @param array $substitutions	Ассоциативный массив параметров для подстановки в запрос 
	 * @return mixed	Результат выполнения запроса
	 */
	function get_cell($query, $substitutions = array()) {
	    $tmp = $this->get_column($query, $substitutions, FALSE);
	    
	    $cell = ($tmp)
	          ? reset($tmp)
	          : FALSE ;
	    
	    return $cell;
	}
	
	

	/**
	 * Получение результата табличного запроса (состоящего из нескольких строк
	 * и нескольких столбцов).
	 * 
	 * @see GB_DBase::query()
	 * 
	 * @param string $query		SQL-запрос
	 * @param array $substitutions	Ассоциативный массив параметров для подстановки в запрос 
	 * @param string $key_col	FALSE или имя столбца, значения которого превратить
	 * 							в ключи массива
	 * @return array	Результат выполнения запроса
	 */
	function get_table($query, $substitutions = array(), $key_col = FALSE) {
		$result = query($query, $substitutions);
	
		$data = array();
		if ($key_col){
			while ($row = $result->fetch_assoc())
				$data[$row{$key_col}] = $row;
		} else {
			while ($row = $result->fetch_assoc())
				$data[] = $row;
		}
	
		$result->free();
		return $data;
	}
	
	
	
	/**
	 * Получение результата запроса, который состоит из одной строки.
	 * 
	 * @see GB_DBase::query()
	 * 
	 * @param string $query		SQL-запрос
	 * @param array $substitutions	Ассоциативный массив параметров для подстановки в запрос 
	 * @return array	Результат выполнения запроса
	 */
	function get_row($query, $substitutions = array()) {
		$tmp = $this->get_table($query, $substitutions, FALSE);
	
		$row = ($tmp)
			? reset($tmp)
			: array();
	
		return $row;
	}

	
	
	/**
	 * Константы режимов работы метода set_row.
	 * @see	GB_DBase::set_row()
	 */
	const MODE_INSERT		= 'INSERT';
	const MODE_IGNORE		= 'IGNORE';
	const MODE_REPLACE		= 'REPLACE';
	const MODE_UPDATE		= 'UPDATE';
	const MODE_DUPLICATE	= 'DUPLICATE';
	
	/**
	 * Вставка в таблицу новых данных или обновление существующих.
	 * 
	 * @param string $tablename	Имя обновляемой таблицы
	 * @param array $data		Массив с данными для добавления
	 * @param mixed $unique_key	Ключ, по которому определяется обновляемая строка таблицы.
	 * 							Либо массив вида 'ключ' => 'значение',
	 * 							либо массив имён ключей из $data,
	 * 							либо скалярное значение, тогда ключём будет "id".
	 * 							FALSE — для добавления новой строки в таблицу.
	 * @param string $mode		Режим обновления. По-умолчанию: MODE_INSERT, если нет $unique_key,
	 * 							иначе — MODE_UPDATE.
	 * 							Варианты: добавление данных: MODE_INSERT, MODE_IGNORE («INSERT IGNORE …»), MODE_REPLACE;
	 * 							обновление данных: MODE_UPDATE, MODE_DUPLICATE («INSERT … ON DUPLICATE KEY UPDATE»).
	 * @return number	Число изменённых строк (для MODE_UPDATE), или ID добавленной строки (во всех прочих случаях).
	 */
	function set_row($tablename, $data, $unique_key = FALSE, $mode = FALSE) {
		$tablename = $this->table($tablename);
		if (!$unique_key) { // Уникальный идентификатор не указан - INSERT
			if (!$mode || $mode == MODE_INSERT)	$query = 'INSERT';
			elseif($mode == MODE_IGNORE)		$query = 'INSERT IGNORE';
			elseif($mode == MODE_REPLACE)		$query = 'REPLACE';
			else {
				$trace = reset(debug_backtrace());
				$message = "Unknown mode \"$mode\" given to $trace[function]() " .
					"in file $trace[file] at line $trace[line].<br />" .
					"Terminating function run.";
				trigger_error($message, E_USER_WARNING);
				return FALSE;
			}
			
			$query .= " INTO $tablename SET ";
			foreach ($data as $key => $value)
				$query .= $this->field_escape($key) . " = :$key, ";
			$query = substr($query, 0, -2); // убираем последние запятую и пробел
			
			$result = $this->query($query, $data);
			
			$out = $this->db->insert_id;
			
		} else { // UPDATE или INSERT … ON DUPLICATE KEY UPDATE
			if (!is_array($unique_key))		// если указана скалярная величина —
				$unique_key = array('id' => $unique_key);	// воспринимаем её как 'id'

			if (!$mode || $mode == MODE_UPDATE) {	// обычный UPDATE
				// В данном случае поля из второго аргумента подставляются в часть SET,
				// а поля из третьего — в часть WHERE
					
				$query = "UPDATE $tablename SET ";
					
				// Чтобы одно и то же поле можно было использовать
				// и в части SET, и в части WHERE с разными значениями, например
				//		UPDATE table
				// 		SET col1 = 'A', col2 = 'B'
				// 		WHERE col1 = 'C'
				// подстановку значений в запрос проводим "вручную" —
				// без использования меток.
					
				foreach ($data as $key => $value)
					$query .= $this->field_escape($key) . ' = ' . gbdb()->data_escape($value) . ', ';
				$query = substr($query, 0, -2);	// убираем последние запятую и пробел
						
				if ($unique_key) {
					$query .= ' WHERE ';
					foreach ($unique_key as $key => $value)
						$query .= $this->field_escape($key) . ' = ' . mysql_escape($value) . ' AND ';
					$query = substr($query, 0, -4);	// убираем последние AND и пробел
				}
					
				$result = $this->query($query);
					
				$out = $this->db->affected_rows;
				
			} elseif ($mode == MODE_DUPLICATE) {	// INSERT … ON DUPLICATE KEY UPDATE
				$append = is_string(key($unique_key));
				// $append: если массив $unique_key ассоциативный,
				// значит, в них данные для уникальных полей —
				// включаем их в INSERT и в подставновку в query()
				// Если же массив числовой, значит
				// все необходимые данные переданы во втором аргументе,
				// а $unique_key содержит только имена полей,
				// которые следует исключить из ON DUPLICATE KEY

				if ($append) {
					$all_data = $data + $unique_key;	// Все данные для ON DUPLICATE KEY UPDATE
					$data_to_update = $data;			// есть в $data
				} else {
					$all_data = $data;
					$data_to_update = array_diff_key(		// В $unique_key переданы имена полей,
						$data,								// которые необходимо исключить
						array_fill_keys($unique_key, TRUE)	// из части ON DUPLICATE KEY UPDATE
					);
				}
			
				$query = "INSERT INTO $tablename SET ";
				foreach ($all_data as $key => $value)
					$query .= $this->field_escape($key) . " = :$key, ";
				$query = substr($query, 0, -2);	// убираем последние запятую и пробел

				if ($data_to_update) {
					$query .= ' ON DUPLICATE KEY UPDATE ';
					foreach ($data_to_update as $key => $value)
						$query .= $this->field_escape($key) . " = :$key, ";
					$query = substr($query, 0, -2); // убираем последние запятую и пробел
				}

				$result = query($query, $all_data);
			
				// Т.к. запрос INSERT - возвращает LAST_INSERT_ID()
				$out = $this->db->insert_id;

			}	// if
		}	// if

		return $out;
	}	// function

}	// class



/**
 * Глобальная функция доступа к экземпляру класса GB_DBase.
 * 
 * @return GB_DBase
 */
function gbdb(){
	/** @var GB_DBase */
	static $db = NULL;
	
	if ($db == NULL)	$db = new GB_DBase(DB_HOST, DB_USER, DB_PASSWORD, DB_BASE, DB_PREFIX);
	return $db;
}
