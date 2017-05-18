<?php

/**
 * GeniBase Administration Scheme API
 *
 * Here we keep the DB structure and option values.
 *
 * @package GeniBase
 * @subpackage Administration
 */

/**
 * Retrieve database table names.
 *
 * @since 3.0.0
 *
 * @param string $scope
 *            tables for which to retrieve names. Can be all, global, site. Defaults to all.
 * @return array of table names.
 */
function gb_get_tables($scope = 'all')
{
    $site_tables = [
        '?_options',
        '?_persons',
        '?_places',
        '?_sources',
        '?_dic_names',
        '?_idx_search_keys',
        '?_name_parts',
        '?_rel_persons2search_keys'
    ];
    $global_tables = [
        '?_users',
        '?_usermeta'
    ];

    switch ($scope) {
        case 'site':
            $tables = $site_tables;
            break;
        case 'global':
            $tables = $global_tables;
            break;
        case 'all':
        default:
            $tables = array_merge($global_tables, $site_tables);
            break;
    }

    return $tables;
}

/**
 * Retrieve the SQL for creating database tables.
 *
 * @since 3.0.0
 *
 * @param string $scope
 *            Optional. The tables for which to retrieve SQL. Can be all, global, site. Defaults to all.
 * @return string The SQL needed to create the requested tables.
 */
function gb_get_db_schema($scope = 'all')
{
    $charset_collate = 'DEFAULT CHARSET=utf8';

    // Site specific tables.
    $site_tables = <<<EOF
CREATE TABLE ?_options (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`section` varchar(150) NOT NULL,
	`option_name` varchar(64) NOT NULL DEFAULT '',
	`option_value` longtext NOT NULL,
	`autoload` varchar(20) NOT NULL DEFAULT 'yes',
	PRIMARY KEY (`ID`),
	UNIQUE KEY `option` (`section`,`option_name`)
) $charset_collate;
CREATE TABLE ?_persons (
	`ID` bigint(20) unsigned NOT NULL,
	`source_id` bigint(20) unsigned NOT NULL,
	`gender` set('Male','Female') DEFAULT NULL,
	`name` text NOT NULL,
	`fact_type` varchar(150) NOT NULL,
	`fact_date` text NOT NULL,
	`fact_date_formal` text NOT NULL,
	`fact_date_from` date NOT NULL,
	`fact_date_to` date NOT NULL,
	`fact_place` text NOT NULL,
	`fact_place_id` bigint(20) unsigned NOT NULL,
	`author_id` bigint(20) unsigned NOT NULL,
	`update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`ID`),
	KEY `update_datetime` (`update_datetime`),
	KEY `gender` (`gender`),
	KEY `source_id` (`source_id`),
	KEY `fact_type` (`fact_type`),
	KEY `fact_date_from` (`fact_date_from`),
	KEY `fact_date_to` (`fact_date_to`),
	KEY `fact_place_id` (`fact_place_id`),
	KEY `author_id` (`author_id`)
) $charset_collate;
CREATE TABLE ?_places (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`locale` char(2) NOT NULL DEFAULT 'ru',
	`jurisdiction_id` bigint(20) unsigned NOT NULL,
	`region` text NOT NULL,
	`name` text NOT NULL,
	`region_ids` text NOT NULL,
	`region_comment` varchar(250) NOT NULL,
	`region_cnt` int(10) unsigned NOT NULL,
	`region_idx` varchar(250) NOT NULL,
	`update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`ID`,`locale`),
	KEY `parent_id` (`jurisdiction_id`,`locale`)
) $charset_collate;
CREATE TABLE ?_sources (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`type` varchar(150) NOT NULL,
	`citation` longtext NOT NULL,
	`componentOf` bigint(20) unsigned DEFAULT NULL,
	`title` text,
	`repository` text,
	`update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`ID`),
	KEY `type` (`type`),
	KEY `componentOf` (`componentOf`)
) $charset_collate;
CREATE TABLE ?_dic_names (
	`name_key` varchar(100) NOT NULL,
	`is_patronimic` int(1) unsigned NOT NULL DEFAULT '0',
	`expand` varchar(250) NOT NULL,
	KEY `is_patronimic` (`is_patronimic`)
) $charset_collate;
CREATE TABLE ?_idx_search_keys (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`type` varchar(150) NOT NULL,
	`search_key` varchar(60) NOT NULL,
	`backsearch_mask` varchar(60) NOT NULL,
	`update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`ID`),
	UNIQUE KEY `search_key_1` (`type`,`search_key`),
	KEY `type` (`type`),
	KEY `search_key_2` (`search_key`),
	KEY `backsearch_mask` (`backsearch_mask`)
) $charset_collate;
CREATE TABLE ?_name_parts (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`person_id` bigint(20) unsigned NOT NULL,
	`type` varchar(150) NOT NULL,
	`value` text NOT NULL,
	`update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`ID`),
	KEY `type` (`type`),
	KEY `person_id` (`person_id`)
) $charset_collate;
CREATE TABLE ?_rel_persons2search_keys (
	`person_id` bigint(20) unsigned NOT NULL,
	`search_key_id` bigint(20) unsigned NOT NULL,
	PRIMARY KEY (`person_id`,`search_key_id`),
	KEY `search_key_id` (`search_key_id`)
) $charset_collate;
EOF;

    $global_tables = <<<EOF
CREATE TABLE ?_users (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`user_hash` varchar(28) NOT NULL DEFAULT '',
	`user_nicename` varchar(50) NOT NULL DEFAULT '',
	`user_pass` varchar(64) NOT NULL DEFAULT '',
	`user_email` varchar(100) NOT NULL DEFAULT '',
	`user_name` varchar(250) NOT NULL DEFAULT '',
	`user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  	`user_activation_key` varchar(60) NOT NULL DEFAULT '',
	PRIMARY KEY (`ID`),
	UNIQUE KEY `user_hash` (`user_hash`),
	UNIQUE KEY `user_nicename` (`user_nicename`),
	UNIQUE KEY `user_email` (`user_email`)
) $charset_collate;
CREATE TABLE ?_usermeta (
	`mID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
	`meta_key` varchar(255) NOT NULL DEFAULT '',
	`meta_value` longtext NOT NULL,
	PRIMARY KEY (`mID`),
	UNIQUE KEY `usermeta` (`user_id`,`meta_key`),
	KEY `user_id` (`user_id`),
	KEY `meta_key` (`meta_key`)
) $charset_collate;
EOF;

    switch ($scope) {
        case 'site':
            $queries = $site_tables;
            break;
        case 'global':
            $queries = $global_tables;
            break;
        case 'all':
        default:
            $queries = $global_tables . $site_tables;
            break;
    }

    return $queries;
}

/**
 * Create GeniBase options and set the default values.
 *
 * @since 3.0.0
 */
function populate_options()
{
    global $gb_current_db_version;

    $guessurl = gb_guess_url();

    /**
     * Fires before creating GeniBase options and populating their default values.
     *
     * @since 3.0.0
     */
    GB_Hooks::do_action('populate_options');

    if (ini_get('safe_mode')) {
        // Safe mode can break mkdir() so use a flat structure by default.
        $uploads_use_yearmonth_folders = 0;
    } else {
        $uploads_use_yearmonth_folders = 1;
    }

    $timezone_string = '';
    $gmt_offset = 0;
    /*
     * translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
     * or a valid timezone string (America/New_York). See http://us3.php.net/manual/en/timezones.php
     * for all timezone strings supported by PHP.
     */
    $offset_or_tz = _x('0', 'default GMT offset or timezone string');
    if (is_numeric($offset_or_tz))
        $gmt_offset = $offset_or_tz;
    elseif ($offset_or_tz && in_array($offset_or_tz, timezone_identifiers_list()))
        $timezone_string = $offset_or_tz;

    $options = array(
        'site_url' => $guessurl,
        'home' => $guessurl,
        'site_title' => __('My Site'),
        'users_can_register' => 0,
        'admin_email' => 'you@example.com',
		/* translators: default start of the week. 0 = Sunday, 1 = Monday */
		'start_of_week' => _x('1', 'start of week'),
        'require_name_email' => 1,
		/* translators: default date format, see http://php.net/date */
		'date_format' => __('j F Y'),
		/* translators: default time format, see http://php.net/date */
		'time_format' => __('G:i'),
        'gzip_compression' => 0,
        'site_charset' => 'UTF-8',
        'active_plugins' => array(),
        'gmt_offset' => $gmt_offset,
        'html_type' => 'text/html',
        'default_role' => 'reader',
        'db_version' => GB_DB_VERSION,
        'uploads_use_yearmonth_folders' => $uploads_use_yearmonth_folders,
        'upload_path' => '',
        'site_public' => '1',
        'upload_url_path' => '',
        'thumbnail_size_w' => 300,
        'thumbnail_size_h' => 300,
        'thumbnail_crop' => 1,
        'medium_size_w' => 750,
        'medium_size_h' => 650,
        'large_size_w' => 1024,
        'large_size_h' => 1024,
        'image_default_link_type' => 'file',
        'image_default_size' => '',
        'image_default_align' => '',
        'uninstall_plugins' => array(),
        'timezone_string' => $timezone_string,
        'initial_db_version' => ! empty($gb_current_db_version) && $gb_current_db_version < GB_DB_VERSION ? $gb_current_db_version : GB_DB_VERSION
    );

    // Set autoload to no for these options
    $fat_options = array(
        'uninstall_plugins'
    );

    $existing_options = gbdb()->get_column('SELECT option_name FROM ?_options WHERE option_name IN ( ?keys )', [
        'keys' => array_keys($options)
    ]);

    $insert = '';
    foreach ($options as $option => $value) {
        if (in_array($option, $existing_options))
            continue;

        if (is_array($value))
            $value = serialize($value);
        $autoload = (in_array($option, $fat_options) ? 'no' : 'yes');

        if (! empty($insert))
            $insert .= ', ';
        $insert .= gbdb()->prepare_query("(?option, ?value, ?autoload)", compact('option', 'value', 'autoload'));
    }

    if (! empty($insert))
        gbdb()->query('INSERT INTO ?_options (`option_name`, `option_value`, `autoload`) VALUES ' . $insert);

        // In case it is set, but blank, update "home".
    if (! __get_option('home'))
        update_option('home', $guessurl);

        // Delete unused options.
    $unusedoptions = array();
    foreach ($unusedoptions as $option)
        GB_Options::delete($option);

        /*
     * Deletes all expired transients. The multi-table delete syntax is used
     * to delete the transient record from table a, and the corresponding
     * transient_timeout record from table b.
     */
    $time = time();
    $sql = 'DELETE a, b FROM ?_options AS a, ?_options AS b
		WHERE a.option_name LIKE ?key_a
		AND a.option_name NOT LIKE ?key_b
		AND b.option_name = CONCAT( "_transient_timeout_", SUBSTRING( a.option_name, 12 ) )
		AND b.option_value < ?time';
    gbdb()->query($sql, [
        'key_a' => gbdb()->like_escape('_transient_') . '%',
        'key_b' => gbdb()->like_escape('_transient_timeout_') . '%',
        'time' => $time
    ]);
    //
    $sql = 'DELETE a, b FROM ?_options AS a, ?_options AS b
		WHERE a.option_name LIKE ?key_a
		AND a.option_name NOT LIKE ?key_b
		AND b.option_name = CONCAT( "_site_transient_timeout_", SUBSTRING( a.option_name, 17 ) )
		AND b.option_value < ?time';
    gbdb()->query($sql, [
        'key_a' => gbdb()->like_escape('_site_transient_') . '%',
        'key_b' => gbdb()->like_escape('_site_transient_timeout_') . '%',
        'time' => $time
    ]);
}

/**
 * Execute GeniBase role creation for the various GeniBase versions.
 *
 * @since 3.0.0
 */
function populate_roles()
{
    populate_roles_300();
}

/**
 * Create the roles for GeniBase 3.0
 *
 * @since 3.0.0
 */
function populate_roles_300()
{
    // Add roles

    // Dummy gettext calls to get strings in the catalog.
    /* translators: user role */
    _x('Administrator', 'User role');
    /* translators: user role */
    _x('Moderator', 'User role');
    /* translators: user role */
    _x('Editor', 'User role');
    /* translators: user role */
    _x('Contributor', 'User role');
    /* translators: user role */
    _x('Reader', 'User role');

    gb_add_role('administrator', 'Administrator');
    gb_add_role('moderator', 'Moderator');
    gb_add_role('editor', 'Editor');
    gb_add_role('contributor', 'Contributor');
    gb_add_role('reader', 'Reader');

    // Add caps for Administrator role
    $role = gb_get_role('administrator');
    $role->add_cap('activate_plugins');
    $role->add_cap('edit_plugins');
    $role->add_cap('edit_users');
    $role->add_cap('list_users');
    $role->add_cap('remove_users');
    $role->add_cap('delete_users');
    $role->add_cap('create_users');
    $role->add_cap('promote_users');
    $role->add_cap('edit_files');
    $role->add_cap('manage_options');
    $role->add_cap('upload_files');
    $role->add_cap('unfiltered_upload');
    $role->add_cap('import');
    $role->add_cap('read');
    $role->add_cap('edit_dashboard');
    $role->add_cap('update_plugins');
    $role->add_cap('delete_plugins');
    $role->add_cap('install_plugins');
    $role->add_cap('update_core');
    $role->add_cap('export');

    // Add caps for Moderator role
    $role = gb_get_role('moderator');
    // $role->add_cap('moderate_comments');
    $role->add_cap('read');

    // Add caps for Editor role
    $role = gb_get_role('editor');
    $role->add_cap('upload_files');
    $role->add_cap('read');

    // Add caps for Contributor role
    $role = gb_get_role('contributor');
    $role->add_cap('read');

    // Add caps for Reader role
    $role = gb_get_role('reader');
    $role->add_cap('read');
}
