<?php
/**
 * GeniBase bootstrap file for setting the BASE_DIR
 * constant and loading the gb-config.php file.
 *
 * If the gb-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * gb-config.php file.
 *
 * Will also search for gb-config.php in one step upside
 * directory to allow to hide configs from hackers.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package GeniBase
 */

// Direct execution forbidden for this script
if (count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

error_reporting(E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR);

/**
 * Define BASE_DIR as initial file's directory
 */
if (! defined('BASE_DIR')) {
    /** @var string  Absolute path to the initial file's directory */
    define('BASE_DIR', dirname(__FILE__));
}

/**
 * Define GB_CORE_DIR as absolute path to the root directory of GeniBase core
 */
if (! defined('GB_CORE_DIR')){
    /** @var string  Absolute path to the root directory of GeniBase core */
    define('GB_CORE_DIR', BASE_DIR . '/gb');
}

/**
 * Loading configs and run GeniBase core
 */
$cfg_fpath = BASE_DIR . '/gb-config.php';
$cfg_exists = file_exists($cfg_fpath);
if (! $cfg_exists) {
    // Unable to locate configs in BASE_DIR. Trying to locate it in directory one level higher.
    $cfg_fpath = dirname(BASE_DIR) . '/gb-config.php';
    $cfg_exists = file_exists($cfg_fpath);
}
if ($cfg_exists || (defined('GB_SHORTINIT') && GB_SHORTINIT) || (defined('GB_INSTALLING') && GB_INSTALLING)) {
    // Config file found

    // Load configs
    if ($cfg_exists)
        require_once $cfg_fpath;

    // Run GeniBase
    require_once GB_CORE_DIR . '/core.php';

} else {
    // A config file doesn't exist

    require_once GB_CORE_DIR . '/load.php';

    // Standardize $_SERVER variables across setups
    gb_fix_server_vars();

    require_once GB_CORE_DIR . '/functions.php';

    $path = dirname(gb_guess_url()) . '/gb-admin/setup-config.php';

    /*
     * We're going to redirect to setup-config.php. While this shouldn't result
     * in an infinite loop, that's a silly thing to assume, don't you think? If
     * we're traveling in circles, our last-ditch effort is "Need more help?"
     */
    if (false === strpos($_SERVER['REQUEST_URI'], 'setup-config')) {
        header('Location: ' . $path);
        exit();
    }

    define('GB_CONTENT_DIR', BASE_DIR . '/gb-content');
    require_once GB_CORE_DIR . '/version.php';

    check_php_mysql_versions();
    gb_load_translations_early();

    // Die with an error message
    $die = __("There doesn't seem to be a <code>gb-config.php</code> file. I need this before we can get started.") . '</p>';
    $die .= '<p>' . __("Need more help? <a href='http://codex.wordpress.org/Editing_gb-config.php'>We got it</a>.") . '</p>';
    $die .= '<p>' . __("You can create a <code>gb-config.php</code> file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file.") . '</p>';
    $die .= '<p><a href="' . $path . '" class="button button-large">' . __("Create a Configuration File") . '</a>';

    gb_die($die, __('GeniBase &rsaquo; Error'));
}
