<?php
/**
 * Настройки системы.
 */

// Запрещено непосредственное исполнение этого скрипта
if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	die('Direct execution forbidden!');



/**
 * Флаги режимов отладки
 */
// define('DEBUG',	TRUE);	// Общий режим отладки
//
// define('HIDDEN_DEBUG',	TRUE);	// Режим «тихой» отладки в некоторых местах
// define('SQL_DEBUG',	TRUE);	// Режим отладки SQL-запросов
// define('P_DEBUG',	TRUE);	// Режим отладки сиситемы ручной публикации
// define('SQL_DEBUG_PROF',	TRUE);	// Режим профилирования SQL-запросов



/**
 * Настройки подключения к базе данных
 */
define('DB_HOST',		'');		// URL MySQL-сервера
define('DB_USER',		'');		// Имя пользователя
define('DB_PASSWORD',	'');		// Пароль
define('DB_BASE',		'');		// Имя базы данных

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
