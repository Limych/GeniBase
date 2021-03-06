<?php
/**
 * Класс общего доступа к СУБД MySQL.
 *
 * Этот файл базируется на коде, опубликованным Михаилом Серовым по адресу
 * @see http://webew.ru/articles/3237.webew
 *
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © 2010, Michail Serov
 * @copyright	Partially copyright © WordPress Team
 *
 *
 * Подстановка параметров: «?key» — подстановка данных, «?#key» — подстановка имени поля,
 * 		«?@key» — подстановка имени таблицы, «?_tablename» — добавление префикса перед именем таблицы
 *
 */

// Direct execution forbidden for this script
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

/**
 * *************************************************************************
 * Класс работы с базой данных
 */
class GB_DBase
{

    /**
     *
     * @var MySQLi
     */
    protected $db;

    protected $host, $user, $password, $base, $prefix;

    /**
     * The last error.
     *
     * @since 3.0.0
     * @var null|string|GB_Error
     */
    public $error;

    /**
     * Amount of queries made
     *
     * @since 2.0.0
     * @access private
     * @var int
     */
    var $num_queries = 0;

    /**
     * Whether to show SQL/DB errors.
     *
     * Default behavior is to show errors if both GB_DEBUG and GB_DEBUG_DISPLAY
     * evaluated to true.
     *
     * @since 2.0.0
     * @access private
     * @var bool
     */
    var $show_errors = false;

    /**
     * Whether to suppress errors during the DB bootstrapping.
     *
     * @since 2.0.0
     * @access private
     * @var bool
     */
    var $suppress_errors = false;

    /**
     * Saved queries that were executed.
     *
     * @since 2.0.0
     * @access private
     * @var array
     */
    var $queries;

    /**
     * Last query made.
     *
     * @since 2.0.0
     * @access private
     * @var array
     */
    var $last_query;

    /**
     * The last error during query.
     *
     * @since 2.0.0
     * @var string
     */
    public $last_error = '';

    /**
     * Count of affected rows by previous query
     *
     * @since 2.0.0
     * @access private
     * @var int
     */
    var $rows_affected = 0;

    /**
     * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
     *
     * @since 2.0.0
     * @access public
     * @var int
     */
    var $insert_id = 0;

    /**
     * Создание экземпляра класса.
     */
    function __construct($host, $user, $password, $base, $prefix = '')
    {
        $this->db = NULL;

        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->base = $base;
        $this->prefix = $prefix;

        if (GB_DEBUG && GB_DEBUG_DISPLAY)
            $this->show_errors();
    }

    /**
     * Уничтожаем экземпляр класса.
     *
     * @return bool true
     */
    function __destruct()
    {
        // Закрываем соединение с СУБД, если оно было
        if ($this->db)
            @$this->db->close();

        return TRUE;
    }

    /**
     * Формирование из строки с метасимволами регулярного выражения для поиска
     * через оператор RLIKE.
     *
     * Используемые на входе метасимволы:
     * '?' — один любой символ;
     * '*' — один или несколько любых символов.
     *
     * @since 2.0.0
     *
     * @param string $str
     *            с метасимволами
     * @param string $full_word
     *            если надо искать по этой маске части слов
     * @return string выражение для поиска через RLIKE
     */
    static function make_regex($str, $full_word = TRUE)
    {
        // Если вместо строки передан массив, обработать каждое значение в отдельности
        // и вернуть результат в виде массива
        if (is_array($str)) {
            foreach ($str as $key => $val)
                $str[$key] = self::make_regex($val, $full_word);
            return $str;
        }

        $str = strtr(preg_quote($str), array(
            '\\?' => '?',
            '\\*' => '*',
            '\\' => '\\\\',
            'ё' => '(е|ё)',
            'Ё' => '(Е|Ё)'
        ));
        $str = preg_replace_callback('/(\?+|\*+)/us', function ($matches) {
            $ch = substr($matches[1], 0, 1);
            $len = strlen($matches[1]);
            // return '[[:alpha:]]' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}'));
            return '(..)' . ($ch == '*' ? '+' : ($len == 1 ? '' : '{' . $len . '}')); // Костыли для учёта двухбайтной кодировки
        }, $str);
        if ($full_word)
            $str = "[[:<:]]${str}[[:>:]]";

        return $str;
    }

    /**
     * Формирование из строки с метасимволами условного выражения для поиска
     * через оператор LIKE.
     *
     * Используемые на входе метасимволы:
     * '?' — один любой символ;
     * '*' — один или несколько любых символов.
     *
     * @since 2.0.0
     *
     * @param string $str
     *            с метасимволами
     * @param string $full_text
     *            если надо искать по этой маске части данных
     * @return string выражение для поиска через LIKE
     */
    static function make_condition($str, $full_text = TRUE)
    {
        // Если вместо строки передан массив, обработать каждое значение в отдельности
        // и вернуть результат в виде массива
        if (is_array($str)) {
            foreach ($str as $key => $val)
                $str[$key] = self::make_condition($val, $full_text);
            return $str;
        }

        $str = strtr($str, array(
            '_' => '\\_',
            '%' => '\\%'
        ));
        $str = strtr($str, array(
            '?' => '_',
            '*' => '_%'
        ));
        if (! $full_text)
            $str = '%' . $str . '%';

        return $str;
    }

    /**
     * Wraps errors in a nice header and footer and dies.
     *
     * Will not die if GB_DBase::$show_errors is false.
     *
     * @since 3.0.0
     *
     * @param string $message
     *            The Error message
     * @param string $error_code
     *            Optional. A Computer readable string to identify the error.
     * @return false|void
     */
    public function bail($message, $error_code = '500')
    {
        if (! $this->show_errors) {
            if (class_exists('GB_Error'))
                $this->error = new GB_Error($error_code, $message);
            else
                $this->error = $message;
            return false;
        }
        gb_die($message);
    }

    /**
     * Connect to and select database.
     *
     * If $allow_bail is false, the lack of database connection will need
     * to be handled manually.
     *
     * @since 2.0.0
     * @since 3.0.0 $allow_bail parameter added.
     *
     * @param bool $allow_bail
     *            the function to bail. Default true.
     * @return bool with a successful connection, false on failure.
     */
    public function connect($allow_bail = true)
    {
        if ($this->db)
            return true;

        if (GB_DEBUG) {
            $this->db = new MySQLi($this->host, $this->user, $this->password, $this->base);
        } else {
            $this->db = @new MySQLi($this->host, $this->user, $this->password, $this->base);
        }

        $is_connected = $this->db && ! $this->db->connect_error;
        if (! $is_connected && $allow_bail) {
            gb_load_translations_early();

            // Load custom DB error template, if present.
            if (file_exists(GB_CONTENT_DIR . '/db-error.php')) {
                require_once (GB_CONTENT_DIR . '/db-error.php');
                die();
            }

            @header('Retry-After: 600'); // 600 seconds
            $this->bail(sprintf(__("
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>gb-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host.</p>
"), htmlspecialchars($this->host, ENT_QUOTES)), 'db_connect_fail');

            return false;
        } elseif ($is_connected) {
            $this->check_database_version();
            $this->db->set_charset('utf8');

            return true;
        }

        return false;
    }

    /**
     * The database version number.
     *
     * @since 3.0.0
     *
     * @return null|string Null on failure, version number on success.
     */
    public function db_version()
    {
        return preg_replace('/[^0-9.].*/', '', $this->db->server_info);
    }

    /**
     * Whether MySQL database is at least the required minimum version.
     *
     * @since 3.0.0
     *
     * @return GB_Error
     */
    public function check_database_version()
    {
        // Make sure the server has the required MySQL version
        if (version_compare($this->db_version(), GB_MYSQL_REQUIRED, '<'))
            return new GB_Error('database_version', sprintf(__('<strong>ERROR</strong>: GeniBase %1$s requires MySQL %2$s or higher.'), GB_VERSION, GB_MYSQL_REQUIRED));
    }

    /**
     * Determine if a database supports a particular feature.
     *
     * @since 3.0.0
     *
     * @param string $db_cap
     *            The feature to check for. Accepts 'collation',
     *            'group_concat', 'subqueries', 'set_charset',
     *            or 'utf8mb4'.
     * @return bool Whether the database feature is supported, false otherwise.
     */
    public function has_cap($db_cap)
    {
        $version = $this->db_version();

        switch (strtolower($db_cap)) {
            case 'collation':
            case 'group_concat':
            case 'subqueries':
                return version_compare($version, '4.1', '>=');
            case 'set_charset':
                return version_compare($version, '5.0.7', '>=');
            case 'utf8mb4':
                return version_compare($version, '5.5.3', '>=');
        }

        return false;
    }

    /**
     * Добавление префикса к имени таблицы.
     *
     * @since 2.0.0
     *
     * @param string|array $table
     *            имя таблицы
     * @param boolean $preserve_array
     *            чтобы возвращать массивы в виде массивов
     * @return string|array таблицы с префиксом
     */
    function table_escape($table, $preserve_array = false)
    {
        if (is_array($table)) {
            $result = array_map(array(
                $this,
                __FUNCTION__
            ), $table);
            return ($preserve_array) ? $result : implode(', ', $result);
        } else {
            $table = (string) $table;

            // First unescape table name if it already escaped
            if (substr($table, 0, 1) == '`')
                $table = $this->table_unescape($table);

            return $this->field_escape($this->prefix . $table);
        }
    }

    /**
     * Unescape table name.
     *
     * @since 2.0.0
     *
     * @param string $table
     *            table name.
     * @return string table name.
     */
    function table_unescape($table)
    {
        return $this->field_unescape($table);
    }

    /**
     * Экранирование имя поля.
     *
     * @since 2.0.0
     *
     * @param string|array $field
     *            поля.
     * @param boolean $preserve_array
     *            чтобы возвращать массивы в виде массивов
     * @return string|array имя поля.
     */
    function field_escape($field, $preserve_array = false)
    {
        if (is_array($field)) {
            $result = array_map(array(
                $this,
                __FUNCTION__
            ), $field);
            return ($preserve_array) ? $result : implode(', ', $result);
        } else
            return '`' . str_replace('`', '``', $field) . '`';
    }

    /**
     * Unescape fields names.
     *
     * @since 2.0.0
     *
     * @param string|array $field
     *            field name. Array of escaped fields names.
     * @return string|array field name. Array of unescaped fields names.
     */
    function field_unescape($field)
    {
        if (is_array($field))
            return array_map(array(
                $this,
                __FUNCTION__
            ), $field);

        $field = (string) $field;

        // First remove quotes if it have
        if (substr($field, 0, 1) == '`')
            $field = substr($field, 1, - 1);

        return str_replace('``', '`', $field);
    }

    /**
     * Экранирование значения переменной, учитывая его тип.
     *
     * @since 2.0.0
     *
     * @param mixed $value
     *            переменной
     * @param boolean $preserve_array
     *            чтобы возвращать массивы в виде массивов
     * @return mixed значение переменной
     */
    function data_escape($value, $preserve_array = false)
    {
        if (is_array($value)) {
            $result = array_map(array(
                $this,
                __FUNCTION__
            ), $value);
            return ($preserve_array) ? $result : implode(', ', $result);
        } elseif (is_string($value)) {
            $this->connect();
            return '"' . $this->db->real_escape_string($value) . '"';
        } elseif (is_numeric($value))
            return ($value == intval($value)) ? intval($value) : rtrim(sprintf('%F', $value), '0');

        elseif (is_null($value))
            return 'NULL';

        else
            return intval($value);
    }

    /**
     * Экранирование значения переменной для оператора LIKE.
     *
     * @since 3.0.0
     *
     * @param mixed $value
     *            переменной
     * @return mixed значение переменной
     */
    function like_escape($text)
    {
        return addcslashes($text, '_%\\');
    }

    /**
     * Подготовка SQL-запроса к исполнению.
     * Подстановка параметров.
     *
     * Подстановка параметров: «?key» — подстановка данных, «?#key» — подстановка имени поля,
     * «?@key» — подстановка имени таблицы, «?_tablename» — добавление префикса перед именем таблицы
     *
     * @since 2.0.0
     *
     * @param string $query
     * @param array $substitutions
     *            массив параметров для подстановки в запрос
     * @return string с подставленными параметрами
     */
    function prepare_query($query, $substitutions = array())
    {
        if (! is_array($substitutions)) {
            // TODO: Print error
            $substitutions = array();
        }

        // Чтобы следующая метка не могла затронуть содержание предыдущей,
        // например, в случае $subst = array('id' => 5, 'title' => 'а тут ?id'),
        // проводить их замену приходится не по очереди через простой foreach,
        // а за один вызов заменяющий функции,
        // для чего нужно составить регулярное выражение, охватывающее
        // все метки. Впрочем, это несложно.
        // О производительности здесь беспокоиться не будем,
        // т.к. запрос - это довольно короткая строка, поэтому он
        // будет обработан быстро в любом случае.

        $regexp = '/\?(_([0-9a-zA-Z$_]+)';
        foreach ($substitutions as $key => $value) {
            $regexp .= '|' . preg_quote($key) . (substr($key, - 1) != '`' ? // нужно учесть,
'\b' : // что теоретически метки могут быть
''); // не только вида ?word, но и вида ?`…`

        }
        $regexp .= ')/';

        $self = $this;
        $query = preg_replace_callback($regexp, function ($matches) use(&$substitutions, &$self) {
            switch (substr($matches{1}, 0, 1)) { // Определяем тип информации для подстановки

                case '_': // Подставляем префикс к имени таблицы
                    return $self->table_escape($matches{2});

                case '@': // Подставляем имя таблицы
                    return $self->table_escape($substitutions[$matches{1}]);

                case '#': // Подставляем имя поля
                    return $self->field_escape($substitutions[$matches{1}]);

                default: // Подставляем данные
                    return $self->data_escape($substitutions[$matches{1}]);
            }
        }, $query);
        return $query;
    }

    /**
     * Enables showing of database errors.
     *
     * This function should be used only to enable showing of errors.
     * GB_DBase::hide_errors() should be used instead for hiding of errors. However,
     * this function can be used to enable and disable showing of database
     * errors.
     *
     * @since 2.0.0
     * @see GB_DBase::hide_errors()
     *
     * @param bool $show
     *            Whether to show or hide errors
     * @return bool Old value for showing errors.
     */
    public function show_errors($show = true)
    {
        $errors = $this->show_errors;
        $this->show_errors = $show;
        return $errors;
    }

    /**
     * Disables showing of database errors.
     *
     * By default database errors are not shown.
     *
     * @since 2.0.0
     * @see GB_DBase::show_errors()
     *
     * @return bool Whether showing of errors was active
     */
    public function hide_errors()
    {
        $show = $this->show_errors;
        $this->show_errors = false;
        return $show;
    }

    /**
     * Whether to suppress database errors.
     *
     * By default database errors are suppressed, with a simple
     * call to this function they can be enabled.
     *
     * @since 2.0.0
     * @see GB_DBase::hide_errors()
     *
     * @param bool $suppress
     *            Optional. New value. Defaults to true.
     * @return bool Old value
     */
    public function suppress_errors($suppress = true)
    {
        $errors = $this->suppress_errors;
        $this->suppress_errors = (bool) $suppress;
        return $errors;
    }

    /**
     * Retrieve the name of the function that called this class.
     *
     * Searches up the list of functions until it reaches
     * the one that would most logically had called this method.
     *
     * @since 2.0.0
     *
     * @return string The name of the calling function
     */
    public function get_caller()
    {
        return gb_debug_backtrace_summary(__CLASS__);
    }

    /**
     * Print SQL/DB error.
     *
     * @since 2.0.0
     * @global array $GB_SQL_ERROR Stores error information of query and error string
     *
     * @param string $error
     *            The error text to display
     */
    public function print_error($error = '')
    {
        global $GB_SQL_ERROR;

        if (! $error)
            $error = $this->db->error;
        $this->last_error = $error;
        $GB_SQL_ERROR[] = array(
            'query' => $this->last_query,
            'error_str' => $error
        );

        if ($this->suppress_errors)
            return;

        gb_load_translations_early();

        if ($caller = $this->get_caller())
            $error_str = sprintf('GeniBase database error "%1$s" for query %2$s made by %3$s', $error, $this->last_query, $caller);
        else
            $error_str = sprintf('GeniBase database error "%1$s" for query %2$s', $error, $this->last_query);

        error_log($error_str);

        // Are we showing errors?
        if (! $this->show_errors)
            return;

            // If there is an error then take note of it
        $str = htmlspecialchars($error, ENT_QUOTES);
        $query = htmlspecialchars($this->last_query, ENT_QUOTES);

        print "<div class='error'>
		<p class='db_error'><strong>GeniBase database error:</strong> [$str]<br />
		<code>$query</code></p>
		</div>";
    }

    /**
     * Kill cached query results.
     *
     * @since 2.0.0
     * @return void
     */
    public function flush()
    {
        $this->last_query = null;
        $this->rows_affected = 0;
        $this->last_error = '';
    }

    /**
     * Отправка запроса в MySQL и слежение за ошибками.
     *
     * @since 2.0.0
     * @see GB_DBase::prepare_query()
     *
     * @param string $query
     * @param array $substitutions
     *            массив параметров для подстановки в запрос
     * @return mixed выполнения запроса. false при ошибке.
     */
    function query($query, $substitutions = array())
    {
        $this->connect();
        $this->flush();

        $query_sub = $this->prepare_query($query, $substitutions);

        if (defined('GB_DEBUG_SQL') && GB_DEBUG_SQL)
            gb_debug_info($query_sub, __CLASS__);

            // Remove any comments from query and trim space symbols.
        $query_sub = trim($this->remove_comments($query_sub));

        // Keep track of the last query for debug.
        $this->last_query = $query_sub;

        if (defined('GB_DBASE_SAVE_QUERIES') && GB_DBASE_SAVE_QUERIES)
            $time_start = microtime(true);

        $result = @$this->db->query($query_sub);
        $this->num_queries ++;

        if (defined('GB_DBASE_SAVE_QUERIES') && GB_DBASE_SAVE_QUERIES)
            $this->queries[] = array(
                $query_sub,
                microtime(true) - $time_start,
                $this->get_caller()
            );

        $this->last_error = $this->db->error;

        // If there is an error then take note of it.
        if ($this->last_error) {
            // Clear insert_id on a subsequent failed insert.
            if ($this->insert_id && preg_match('/^(INSERT|REPLACE)\s/usi', $query_sub))
                $this->insert_id = 0;

            $this->print_error();
            return false;
        }

        if (preg_match('/^(INSERT|DELETE|UPDATE|REPLACE)\s/usi', $query)) {
            $this->rows_affected = $this->db->affected_rows;

            // Take note of the insert_id
            if (preg_match('/^(INSERT|REPLACE)\s/usi', $query))
                $this->insert_id = $this->db->insert_id;

                // Return number of rows affected
            $result = $this->rows_affected;
        }

        return $result;
    }

    /**
     * Получение результата запроса, который состоит из нескольких строк и одного
     * столбца.
     *
     * @since 2.0.0
     * @see GB_DBase::query()
     *
     * @param string $query
     * @param array $substitutions
     *            массив параметров для подстановки в запрос
     * @param boolean $get_assoc
     *            для превращения первого столбца результата
     *            в ключи массива, а второго — в значения.
     * @return array|bool on failure. Результат выполнения запроса
     */
    function get_column($query, $substitutions = array(), $get_assoc = false)
    {
        $result = $this->query($query, $substitutions);
        if (false === $result)
            return false;

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
     * @since 2.0.0
     * @see GB_DBase::query()
     *
     * @param string $query
     * @param array $substitutions
     *            параметров для подстановки в запрос
     * @return bool|mixed on failure. Результат выполнения запроса
     */
    function get_cell($query, $substitutions = array())
    {
        $result = $this->get_column($query, $substitutions, false);
        if (false === $result)
            return false;

        return ($result) ? reset($result) : NULL;
    }

    /**
     * Получение результата табличного запроса (состоящего из нескольких строк
     * и нескольких столбцов).
     *
     * @since 2.0.0
     * @see GB_DBase::query()
     *
     * @param string $query
     * @param array $substitutions
     *            массив параметров для подстановки в запрос
     * @param string $key_col
     *            или имя столбца, значения которого превратить
     *            в ключи массива
     * @return array|bool on failure. Результат выполнения запроса
     */
    function get_table($query, $substitutions = array(), $key_col = false)
    {
        $result = $this->query($query, $substitutions);
        if (false === $result)
            return false;

        $data = array();
        if ($key_col) {
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
     * @since 2.0.0
     * @see GB_DBase::query()
     *
     * @param string $query
     * @param array $substitutions
     *            массив параметров для подстановки в запрос
     * @return array|bool on failure. Результат выполнения запроса
     */
    function get_row($query, $substitutions = array())
    {
        $result = $this->get_table($query, $substitutions, false);
        if (false === $result)
            return false;

        return ($result) ? reset($result) : array();
    }

    /**
     * Константы режимов работы метода set_row.
     *
     * @since 2.0.0
     * @see GB_DBase::set_row()
     */
    const MODE_INSERT = 'INSERT';

    const MODE_IGNORE = 'IGNORE';

    const MODE_REPLACE = 'REPLACE';

    const MODE_UPDATE = 'UPDATE';

    const MODE_DUPLICATE = 'DUPLICATE';

    /**
     * Вставка в таблицу новых данных или обновление существующих.
     *
     * @since 2.0.0
     *
     * @param string $tablename
     *            обновляемой таблицы
     * @param array $data
     *            с данными для добавления
     * @param mixed $unique_key
     *            по которому определяется обновляемая строка таблицы.
     *            Либо массив вида 'ключ' => 'значение',
     *            либо массив имён ключей из $data,
     *            либо скалярное значение, тогда ключём будет "id".
     *            false — для добавления новой строки в таблицу.
     * @param string $mode
     *            обновления. По-умолчанию: MODE_INSERT, если нет $unique_key,
     *            иначе — MODE_UPDATE.
     *            Варианты: добавление данных: MODE_INSERT, MODE_IGNORE («INSERT IGNORE …»), MODE_REPLACE;
     *            обновление данных: MODE_UPDATE, MODE_DUPLICATE («INSERT … ON DUPLICATE KEY UPDATE»).
     * @return number|bool изменённых/добавленных строк. False при ошибке.
     */
    function set_row($tablename, $data, $unique_key = false, $mode = false)
    {
        $query = (! $unique_key) ?
        // INSERT or REPLACE
        $this->_set_row_insert($tablename, $data, $mode) :
        // UPDATE or INSERT … ON DUPLICATE KEY UPDATE
        $this->_set_row_update($tablename, $data, $unique_key, $mode);
        if (false === $query)
            return false;

        return $this->query($query);
    }

    /**
     * Making query for inserting new data.
     *
     * @since 2.0.0
     * @access private
     * @see GB_DBase::set_row()
     *
     * @param string $tablename
     *            обновляемой таблицы
     * @param array $data
     *            с данными для добавления
     * @param string $mode
     *            добавление данных: MODE_INSERT, MODE_IGNORE («INSERT IGNORE …»), MODE_REPLACE.
     *            Default MODE_INSERT.
     * @return string or false on error.
     */
    function _set_row_insert($tablename, $data, $mode)
    {
        if (empty($tablename)) {
            $this->print_error('Table name not defined.');
            return false;
        }
        if (! $mode || $mode == self::MODE_INSERT)
            $query = 'INSERT';
        elseif ($mode == self::MODE_IGNORE)
            $query = 'INSERT IGNORE';
        elseif ($mode == self::MODE_REPLACE)
            $query = 'REPLACE';
        else {
            $this->print_error("Unknown mode '$mode'");
            return false;
        }

        $first_el = reset($data);
        if (! is_array($first_el)) {
            // Insert single row — convert data array to array of arrays with single element.
            $first_el = $data;
            $data = array(
                $data
            );
        }
        $self = $this;
        $data = array_map(function ($vals) use($self) {
            return '(' . implode(', ', array_map(array(
                $self,
                'data_escape'
            ), $vals)) . ')';
        }, $data);
        $query .= " INTO $tablename (" . implode(', ', array_map(array(
            $this,
            'field_escape'
        ), array_keys($first_el))) . ") VALUES " . implode(', ', $data);

        return $query;
    }
    // function

    /**
     * Making query for updating data
     *
     * @since 2.0.0
     * @access private
     * @see GB_DBase::set_row()
     *
     * @param string $tablename
     *            обновляемой таблицы
     * @param array $data
     *            с данными для добавления
     * @param mixed $unique_key
     *            по которому определяется обновляемая строка таблицы.
     *            Либо массив вида 'ключ' => 'значение',
     *            либо массив имён ключей из $data,
     *            либо скалярное значение, тогда ключём будет "id".
     * @param string $mode
     *            обновление данных: MODE_UPDATE, MODE_DUPLICATE («INSERT … ON DUPLICATE KEY UPDATE»).
     * @return string or false on error.
     */
    function _set_row_update($tablename, $data, $unique_key, $mode)
    {
        if (! is_array($unique_key)) // если указана скалярная величина —
            $unique_key = array(
                'id' => $unique_key
            ); // воспринимаем её как 'id'

        $append = is_string(key($unique_key));
        // $append: если массив $unique_key ассоциативный,
        // значит, в них данные для уникальных полей —
        // включаем их в INSERT и в подставновку в query()
        // Если же массив числовой, значит
        // все необходимые данные переданы во втором аргументе,
        // а $unique_key содержит только имена полей,
        // которые следует исключить из ON DUPLICATE KEY

        if ($append) {
            // Все данные для ON DUPLICATE KEY UPDATE есть в $data
            $all_data = array_merge($data, $unique_key);
            $data_to_update = $data;
        } else {
            $all_data = $data;
            $unique_key = array_fill_keys($unique_key, true);
            $data_to_update = array_diff_key( // В $unique_key переданы имена полей,
$data, // которые необходимо исключить
$unique_key); // из части ON DUPLICATE KEY UPDATE

        }

        if (! $mode || $mode == self::MODE_UPDATE) { // обычный UPDATE
                                                     // В данном случае поля из второго аргумента подставляются в часть SET,
                                                     // а поля из третьего — в часть WHERE

            $query = "UPDATE $tablename SET ";

            // Чтобы одно и то же поле можно было использовать
            // и в части SET, и в части WHERE с разными значениями, например
            // UPDATE table
            // SET col1 = 'A', col2 = 'B'
            // WHERE col1 = 'C'
            // подстановку значений в запрос проводим "вручную" —
            // без использования меток.

            foreach ($data as $key => $value)
                $query .= $this->field_escape($key) . ' = ' . $this->data_escape($value) . ', ';
            $query = substr($query, 0, - 2); // убираем последние запятую и пробел

            if ($unique_key) {
                $query .= ' WHERE ';
                foreach ($unique_key as $key => $value) {
                    $query .= $this->field_escape($key) . ' = ' . $this->data_escape($all_data[$key]) . ' AND ';
                }
                $query = substr($query, 0, - 5); // убираем последние AND и пробелы
            }

            return $query;
        } elseif ($mode == self::MODE_DUPLICATE) { // INSERT … ON DUPLICATE KEY UPDATE
            $query = "INSERT INTO $tablename SET ";
            foreach ($all_data as $key => $value)
                $query .= $this->field_escape($key) . ' = ' . $this->data_escape($value) . ', ';
            $query = substr($query, 0, - 2); // убираем последние запятую и пробел

            if ($data_to_update) {
                $query .= ' ON DUPLICATE KEY UPDATE ';
                foreach ($data_to_update as $key => $value)
                    $query .= $this->field_escape($key) . ' = ' . $this->data_escape($value) . ', ';
                $query = substr($query, 0, - 2); // убираем последние запятую и пробел
            }

            return $query;
        } else {
            $this->print_error("Unknown mode '$mode'");
            return false;
        }
    }
    // function

    /**
     * Delete records from table.
     *
     * @since 2.3.0
     *
     * @param string $tablename
     *            from which data has been deleted.
     * @param mixed $unique_key
     *            array of unique key(s) for selecting
     *            record(s) to delete.
     * @return integer|boolean of deleted records. False on error.
     */
    function delete($tablename, $unique_key)
    {
        if (empty($tablename)) {
            $this->print_error('Table name not defined.');
            return false;
        }
        if (empty($unique_key) || ! is_array($unique_key) || ! is_string(key($unique_key))) {
            $this->print_error('Record key not defined.');
            return false;
        }

        $tablename = $this->table_escape($tablename);
        $query = "DELETE FROM $tablename WHERE ";
        foreach ($unique_key as $key => $value)
            $query .= $this->field_escape($key) . ' = ' . $this->data_escape($value) . ' AND ';
        $query = substr($query, 0, - 5); // убираем последние «AND» и пробелы

        return $this->query($query);
    }

    /**
     * Separate individual queries into an array.
     *
     * {@internal Missing Long Description}}
     *
     * @since 2.0.0
     *
     * @param string $queries
     * @return array
     */
    function split_queries($queries)
    {
        return preg_split("~" . "/\*.*?\*/(*SKIP)(*FAIL)|" . // Skip comments /*…*/
"-- [^\n]*\n(*SKIP)(*FAIL)|" . // Skip comments -- …
"#[^\n]*\n(*SKIP)(*FAIL)|" . // Skip comments #…
"\\(((?>[^()]+)|(?R))*\\)(*SKIP)(*FAIL)|" . // Skip code in brackets
"\"(?:[^\"\\\\]+|\\\\.)*\"(*SKIP)(*FAIL)|" . // Skip double quoted strings
"'(?:[^'\\\\]+|\\\\.)*'(*SKIP)(*FAIL)|" . // Skip single quoted strings
"`(?:``|[^`]+)*`(*SKIP)(*FAIL)|" . // Skip quoted names
";~uSis", // Split by semicolon
$queries, - 1, PREG_SPLIT_NO_EMPTY);
    }

    function remove_comments($query)
    {
        return preg_replace("~" . "\"(?:[^\"\\\\]+|\\\\.)*\"(*SKIP)(*FAIL)|" . // Skip double quoted strings
"'(?:[^'\\\\]+|\\\\.)*'(*SKIP)(*FAIL)|" . // Skip single quoted strings
"`(?:``|[^`]+)*`(*SKIP)(*FAIL)|" . // Skip quoted names
"/\*.*?\*/|" . // Remove comments /*…*/
"-- [^\n]*\n|" . // Remove comments -- …
"#[^\n]*\n" . // Remove comments #…
"~uSis", ' ', $query);
    }

    /**
     * {@internal Missing Short Description}}
     *
     * {@internal Missing Long Description}}
     *
     * @since 2.0.0
     *
     * @param string $query
     * @param bool $allow_deletions
     * @return array
     */
    function create_table_patch($query, $allow_deletions = false)
    {
        // Run function only for CREATE TABLE queries
        if (! preg_match('/CREATE\s+TABLE\s+(\S+)/uSi', $query, $matches)) {
            // TODO: Добавить сообщение об ошибке
            // $this->print_error("Unknown mode '$mode'");
            return false;
        }

        $table = $matches[1];

        // Fetch the table column structure from the database
        $suppress = gbdb()->suppress_errors();
        $tablefields = $this->get_table("DESCRIBE {$table}");
        $this->suppress_errors($suppress);

        if (! $tablefields)
            return array(
                $query
            );

            // Clear the field and index arrays.
        $cqueries = $cfields = $indices = array();

        // Get all of the field names in the query from between the parentheses.
        preg_match('/\((.*)\)/uSms', $query, $match2);
        $qryline = trim($match2[1]);

        // Separate field lines into an array.
        $flds = array_filter(preg_split('~"(?:[^"\\\\]+|\\\\.)*"(*SKIP)(*FAIL)|\'(?:[^\'\\\\]+|\\\\.)*\'(*SKIP)(*FAIL)|`(?:``|[^`]+)*`(*SKIP)(*FAIL)|,~uSis', $qryline));

        // For every field line specified in the query.
        foreach ($flds as $fld) {
            $fld = trim($fld);

            // Extract the field name.
            preg_match('/^(\S+)\s+(.*)$/uS', $fld, $fvals);

            // Verify the found field name.
            $validfield = TRUE;
            switch (strtoupper($fvals[1])) {
                case 'PRIMARY':
                case 'INDEX':
                case 'FULLTEXT':
                case 'UNIQUE':
                case 'KEY':
                    $validfield = false;
                    $indices[] = trim($fld, ", \n");
                    break;
            }

            // If it's a valid field, add it to the field array.
            if ($validfield)
                $cfields[strtolower($this->field_unescape($fvals[1]))] = trim($fvals[2], ", \n");
        }

        // For every field in the table.
        foreach ($tablefields as $tablefield) {
            $fld = strtolower($tablefield['Field']);

            // If the table field exists in the field array…
            if (array_key_exists($fld, $cfields)) {

                // Get the field type from the query.
                preg_match('/(\S+( unsigned)?)/uSi', $cfields[$fld], $matches);
                $fieldtype = $matches[1];

                // Is actual field type different from the field type in query?
                if (0 != strcasecmp($tablefield['Type'], $fieldtype)) {
                    // Add a query to change the column type
                    $cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN `{$tablefield[Field]}` `{$tablefield[Field]}` $cfields[$fld]";
                    // $for_update[$table.'.'.$tablefield['Field']] = "Changed type of {$table}.{$tablefield['Field']} from {$tablefield['Type']} to {$fieldtype}";
                }

                // Get the default value from the array
                // TODO: Remove this?
                // echo "{$cfields[$fld]}<br>";
                if (preg_match('/\bDEFAULT\s+(?:"([^"\\\\]+|\\\\.)*"|\'([^\'\\\\]+|\\\\.)*\'|(\S+))/uSi', $cfields[$fld], $matches)) {
                    $default_value = ! empty($matches[1]) ? $matches[1] : ! empty($matches[2]) ? $matches[2] : $matches[3];
                    if ($tablefield['Default'] != $default_value) {
                        // Add a query to change the column's default value
                        $cqueries[] = "ALTER TABLE {$table} ALTER COLUMN `{$tablefield['Field']}` SET DEFAULT '{$default_value}'";
                        // $for_update[$table.'.'.$tablefield['Field']] = "Changed default value of {$table}.{$tablefield['Field']} from {$tablefield->Default} to {$default_value}";
                    }
                }

                // Remove the field from the array (so it's not added).
                unset($cfields[$fld]);

                // This field exists in the table, but not in the creation queries?
            } elseif ($allow_deletions) {
                // Add a query to delete unused column
                $cqueries[] = "ALTER TABLE {$table} DROP COLUMN `{$tablefield[Field]}`";
            }
        }

        // For every remaining field specified for the table.
        foreach ($cfields as $fieldname => $fielddef) {
            // Push a query line into $cqueries that adds the field to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD COLUMN `$fieldname` $fielddef";
            // $for_update[$table.'.'.$fieldname] = 'Added column '.$table.'.'.$fieldname;
        }

        // Index stuff goes here. Fetch the table index structure from the database.
        $tableindices = $this->get_table("SHOW INDEX FROM {$table}");

        if ($tableindices) {
            // Clear the index array.
            unset($index_ary);

            // For every index in the table.
            foreach ($tableindices as $tableindex) {

                // Add the index to the index data array.
                $keyname = $tableindex['Key_name'];
                $index_ary[$keyname]['columns'][] = array(
                    'fieldname' => $tableindex['Column_name'],
                    'subpart' => $tableindex['Sub_part']
                );
                $index_ary[$keyname]['unique'] = ($tableindex['Non_unique'] == 0) ? TRUE : false;
            }

            // For each actual index in the index array.
            foreach ($index_ary as $index_name => $index_data) {

                // Build a create string to compare to the query.
                $index_string = '';
                if ($index_name == 'PRIMARY')
                    $index_string .= 'PRIMARY ';
                elseif ($index_data['unique'])
                    $index_string .= 'UNIQUE ';
                $index_string .= 'KEY ';
                if ($index_name != 'PRIMARY')
                    $index_string .= $index_name;
                $index_columns = '';

                // For each column in the index.
                foreach ($index_data['columns'] as $column_data) {
                    if ($index_columns != '')
                        $index_columns .= ',';

                        // Add the field to the column list string.
                    $index_columns .= $column_data['fieldname'];
                    if ($column_data['subpart'] != '')
                        $index_columns .= '(' . $column_data['subpart'] . ')';
                }
                // Add the column list to the index create string.
                $index_string .= ' (' . $index_columns . ')';

                if (! (($aindex = array_search($index_string, $indices)) === false)) {
                    unset($indices[$aindex]);
                    // TODO: Remove this?
                    // echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br />Found index:".$index_string."</pre>\n";
                }
                // TODO: Remove this?
                // else echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br /><b>Did not find index:</b>".$index_string."<br />".print_r($indices, true)."</pre>\n";
            }
        }

        // For every remaining index specified for the table.
        foreach ((array) $indices as $index) {
            // Push a query line into $cqueries that adds the index to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD $index";
            // $for_update[] = 'Added index ' . $table . ' ' . $index;
        }

        return $cqueries;
    }
}
// class

/**
 * Глобальная функция доступа к экземпляру класса GB_DBase.
 *
 * @since 2.0.0
 *
 * @return GB_DBase
 */
function gbdb()
{
    /**
     *
     * @var GB_DBase
     */
    static $db = null;

    if ($db === null)
        $db = new GB_DBase(DB_HOST, DB_USER, DB_PASSWORD, DB_BASE, DB_PREFIX);
    return $db;
}
