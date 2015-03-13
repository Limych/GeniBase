<?php
/**
 * Настройки системы.
 */

// Запрещено непосредственное исполнение этого скрипта
if( empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	die('Direct execution forbidden!');



/**
 * Флаги режимов отладки
 */
// define('GB_DEBUG',	TRUE);	// Общий режим отладки
//
// define('GB_DEBUG_SQL',	TRUE);	// Режим отладки SQL-запросов
// define('GB_DEBUG_SQL_PROF',	TRUE);	// Режим профилирования SQL-запросов
// define('P_DEBUG',	TRUE);	// Режим отладки сиситемы ручной публикации



/**
 * Настройки подключения к базе данных
 */
define('DB_HOST',		'localhost');				// URL MySQL-сервера
define('DB_USER',		'yourusernamehere');		// Имя пользователя
define('DB_PASSWORD',	'yourpasswordhere');		// Пароль
define('DB_BASE',		'youremptytestdbnamehere');	// Имя базы данных
define('DB_PREFIX',		'gb_');	// Префикс таблиц в базе. Only numbers, letters, and underscores please!

/**
 * Лимиты
 */
define('Q_LIMIT',	20);	// Лимит числа строк на одной странице результатов поиска
define('P_LIMIT',	70);	// Лимит числа единовременно публикуемых записей



define('OVERLOAD_BAN_TIME',	60);	// На сколько минут блокируется нарушитель, вызвавший перегрузку системы



/**
 * Параметры обновления индексов
 */
define('IDX_EXPIRATION_DATE',	"2015-02-12");	// YYYY-MM-DD	Дата, созданные ранее которой индексы необходимо пересчитать
