<?php
/**
 * Основной подключаемый файл системы.
 * 
 * Файл хранит некоторые основные функции, отвечает за её инициализацию и подключение всех
 * необходимых дополнительных модулей. 
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if( !defined('GB_CORE_DIR') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');

// Include files required for initialization.
require_once(GB_CORE_DIR . '/version.php');
require_once(GB_CORE_DIR . '/default-constants.php');
require_once(GB_CORE_DIR . '/load.php');

// Set initial default constants including GB_MEMORY_LIMIT, GB_MAX_MEMORY_LIMIT, GB_DEBUG
gb_initial_constants();

// Check for the required PHP version and for the MySQL extension or a database drop-in.
gb_check_php_mysql_versions();

// Базовые настройки системы
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, array('ru_RU.utf8', 'ru_RU.UTF-8'));

// GeniBase calculates offsets from UTC.
date_default_timezone_set( 'UTC' );

// Turn register_globals off.
gb_unregister_GLOBALS();

// Standardize $_SERVER variables across setups.
gb_fix_server_vars();

// Check if we have received a request due to missing favicon.ico
gb_favicon_request();

// Check if we're in maintenance mode.
gb_maintenance();

// Start loading timer.
timer_start();

// Check if we're in GB_DEBUG mode.
gb_debug_mode();

// Load early GeniBase files.
require_once(GB_CORE_DIR . '/compat.php');
require_once(GB_CORE_DIR . '/functions.php');
require_once(GB_CORE_DIR . '/class.gb-error.php');
require_once(GB_CORE_DIR . '/class.gb-hooks.php');
require_once(GB_CORE_DIR . '/pomo/mo.php');
require_once(GB_CORE_DIR . '/class.gb-dbase.php');

// Attach the default filters.
require_once(GB_CORE_DIR . '/default-filters.php');

// Load the L10n library.
require_once(GB_CORE_DIR . '/l10n.php');

// Load most of GeniBase.
require_once(GB_CORE_DIR . '/user.php');
require_once(GB_CORE_DIR . '/general-template.php');
require_once(GB_CORE_DIR . '/link-template.php');
require_once(GB_CORE_DIR . '/kses.php');
require_once(GB_CORE_DIR . '/formatting.php');
require_once(GB_CORE_DIR . '/script-loader.php');
require_once(GB_CORE_DIR . '/text.php');

// Define constants that rely on the API to obtain the default value.
gb_plugin_constants();

// Define cookie constants.
gb_cookie_constants();

// Load the default text localization domain.
load_default_textdomain();

$locale = get_locale();
$locale_file = GB_LANG_DIR . "/$locale.php";
if( (0 === validate_file($locale)) && is_readable($locale_file))
	require($locale_file);
unset($locale_file);

// Pull in locale data after loading text domain.
require_once(GB_CORE_DIR . '/locale.php');

// Set up current user (renew cookie every 100 runs).
gb_userid(0 == rand(0, 99));

/**
 * GeniBase Locale object for loading locale domain date and various strings.
 * @global object $gb_locale
 * @since	2.0.0
*/
$GLOBALS['gb_locale'] = new GB_Locale();
