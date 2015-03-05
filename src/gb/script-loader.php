<?php
/**
 * GeniBase scripts and styles default loader.
 *
 * Several constants are used to manage the loading, concatenating and compression of scripts and CSS:
 * define('GB_SCRIPT_DEBUG', true); loads the development (non-minified) versions of all scripts and CSS, and disables compression and concatenation,
 * define('CONCATENATE_SCRIPTS', false); disables compression and concatenation of scripts and CSS,
 * define('COMPRESS_SCRIPTS', false); disables compression of scripts,
 * define('COMPRESS_CSS', false); disables compression of CSS,
 * define('ENFORCE_GZIP', true); forces gzip for compression (default is deflate).
 *
 * The globals $concatenate_scripts, $compress_scripts and $compress_css can be set by plugins
 * to temporarily override the above settings. Also a compression test is run once and the result is saved
 * as option 'can_compress_scripts' (0/1). The test will run again if that option is deleted.
 *
 * @package GeniBase
 * @since	2.0.0
 * 
 * @copyright	Copyright © WordPress Team
 * @copyright	Partially copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

require_once(GB_INC_DIR . '/class.gb-dependencies.php');
require_once(GB_INC_DIR . '/class.gb-scripts.php');
require_once(GB_INC_DIR . '/functions.gb-scripts.php');
require_once(GB_INC_DIR . '/class.gb-styles.php');
require_once(GB_INC_DIR . '/functions.gb-styles.php');

/**
 * Register all GeniBase scripts.
 *
 * Localizes some of them.
 * args order: $scripts->add( 'handle', 'url', 'dependencies', 'query-string', 1 );
 * when last arg === 1 queues the script for the footer
 *
 * @since	2.0.0
 */
function gb_default_scripts() {
	if(!defined('GB_SCRIPT_DEBUG'))		define('GB_SCRIPT_DEBUG', GB_DEBUG);

// 	if ( ! $guessurl = site_url() )
// 		$guessurl = gb_guess_url();
	$guessurl = BASE_URL;

	_gb_scripts()->base_url = $guessurl;
	_gb_scripts()->content_url = defined('GB_CONTENT_URL') ? GB_CONTENT_URL : '';
	_gb_scripts()->default_version = get_siteinfo( 'version' );
	_gb_scripts()->default_dirs = array('/gb-admin/js/', '/gb/js/');

	$suffix = GB_SCRIPT_DEBUG ? '' : '.min';

	// jQuery
	_gb_scripts()->add('jquery', false, array('jquery-core'), null);
	_gb_scripts()->add('jquery-core', "http://code.jquery.com/jquery-2.1.3$suffix.js", array(), null);
}

/**
 * Assign default styles to $styles object.
 *
 * Nothing is returned, because the $styles parameter is passed by reference.
 * Meaning that whatever object is passed will be updated without having to
 * reassign the variable that was passed back to the same value. This saves
 * memory.
 *
 * Adding default styles is not the only task, it also assigns the base_url
 * property, the default version, and text direction for the object.
 *
 * @since	2.0.0
 */
function gb_default_styles() {
	if(!defined('GB_SCRIPT_DEBUG'))		define('GB_SCRIPT_DEBUG', GB_DEBUG);

// 	if ( ! $guessurl = site_url() )
// 		$guessurl = gb_guess_url();
	$guessurl = BASE_URL;

	_gb_styles()->base_url = $guessurl;
	_gb_styles()->content_url = defined('GB_CONTENT_URL')? GB_CONTENT_URL : '';
	_gb_styles()->default_version = get_siteinfo( 'version' );
	_gb_styles()->text_direction = function_exists( 'is_rtl' ) && is_rtl() ? 'rtl' : 'ltr';
	_gb_styles()->default_dirs = array('/gb-admin/css/', '/gb/css/');

	$suffix = GB_SCRIPT_DEBUG ? '' : '.min';

	// Common files
	_gb_styles()->add('responsive-tables', "/gb/css/responsive-tables.css", array('normalize'));
	_gb_styles()->add('responsive-forms', "/gb/css/responsive-forms.css", array('normalize'));
	
	// External libraries and friends
	_gb_styles()->add('normalize', "/gb/css/normalize$suffix.css");
}

/**
 * Prints the script queue in the HTML head on admin pages.
 *
 * Postpones the scripts that were queued for the footer.
 * print_footer_scripts() is called in the footer to print these scripts.
 *
 * @since	2.0.0
 *
 * @see gb_print_scripts()
 */
function print_head_scripts() {
	global $concatenate_scripts;

// 	if ( ! did_action('gb_print_scripts') ) {
// 		/** This action is documented in gb/functions.gb-scripts.php */
// 		do_action( 'gb_print_scripts' );
// 	}

	script_concat_settings();
	_gb_scripts()->do_concat = $concatenate_scripts;
	_gb_scripts()->do_head_items();

	/**
	 * Filter whether to print the head scripts.
	 *
	 * @since	2.0.0
	 *
	 * @param bool $print Whether to print the head scripts. Default true.
	*/
// 	if ( apply_filters( 'print_head_scripts', true ) ) {
		_print_scripts();
// 	}

	_gb_scripts()->reset();
	return _gb_scripts()->done;
}

/**
 * Prints the scripts that were queued for the footer or too late for the HTML head.
 *
 * @since	2.0.0
 */
function print_footer_scripts() {
	global $concatenate_scripts;

	script_concat_settings();
	_gb_scripts()->do_concat = $concatenate_scripts;
	_gb_scripts()->do_footer_items();

	/**
	 * Filter whether to print the footer scripts.
	 *
	 * @since	2.0.0
	 *
	 * @param bool $print Whether to print the footer scripts. Default true.
	*/
// 	if ( apply_filters( 'print_footer_scripts', true ) ) {
		_print_scripts();
// 	}

	_gb_scripts()->reset();
	return _gb_scripts()->done;
}

/**
 * @internal use
 */
function _print_scripts() {
	global $compress_scripts;

	$zip = $compress_scripts ? 1 : 0;
	if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
		$zip = 'gzip';

	_gb_scripts()->do_concat = FALSE;	// TODO: Remove for enable concatenate mode
	if ( $concat = trim( _gb_scripts()->concat, ', ' ) ) {
		if ( !empty(_gb_scripts()->print_code) ) {
			echo "\n<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n"; // not needed in HTML 5
			echo _gb_scripts()->print_code;
			echo "/* ]]> */\n";
			echo "</script>\n";
		}

		$concat = str_split( $concat, 128 );
		$concat = 'load%5B%5D=' . implode( '&load%5B%5D=', $concat );

		$src = _gb_scripts()->base_url . "/gb/load-scripts.php?c={$zip}&" . $concat . '&ver=' . _gb_scripts()->default_version;
		echo "<script type='text/javascript' src='" . esc_attr($src) . "'></script>\n";
	}

	if ( !empty(_gb_scripts()->print_html) )
		echo _gb_scripts()->print_html;
}

/**
 * Prints the script queue in the HTML head on the front end.
 *
 * Postpones the scripts that were queued for the footer.
 * gb_print_footer_scripts() is called in the footer to print these scripts.
 *
 * @since	2.0.0
 */
function gb_print_head_scripts() {
// 	if ( ! did_action('gb_print_scripts') ) {
// 		/** This action is documented in wp-includes/functions.wp-scripts.php */
// 		do_action( 'gb_print_scripts' );
// 	}

	return print_head_scripts();
}

/**
 * Private, for use in *_footer_scripts hooks
 *
 * @since	2.0.0
 */
function _gb_footer_scripts() {
	print_late_styles();
	print_footer_scripts();
}

/**
 * Hooks to print the scripts and styles in the footer.
 *
 * @since	2.0.0
 */
function gb_print_footer_scripts() {
	/**
	 * Fires when footer scripts are printed.
	 *
	 * @since	2.0.0
	 */
// 	do_action( 'gb_print_footer_scripts' );
	_gb_footer_scripts();	// TODO: Remove after actions enabled
}

/**
 * Wrapper for do_action('gb_enqueue_scripts')
 *
 * Allows plugins to queue scripts for the front end using gb_enqueue_script().
 * Runs first in gb_head() where all is_home(), is_page(), etc. functions are available.
 *
 * @since	2.0.0
 */
function gb_enqueue_scripts() {
	/**
	 * Fires when scripts and styles are enqueued.
	 *
	 * @since	2.0.0
	 */
	do_action( 'gb_enqueue_scripts' );
}

/**
 * Prints the styles that were queued too late for the HTML head.
 *
 * @since	2.0.0
 */
function print_late_styles() {
	global $concatenate_scripts;

	_gb_styles()->do_concat = $concatenate_scripts;
	_gb_styles()->do_footer_items();

	/**
	 * Filter whether to print the styles queued too late for the HTML head.
	 *
	 * @since	2.0.0
	 *
	 * @param bool $print Whether to print the 'late' styles. Default true.
	 */
// 	if ( apply_filters( 'print_late_styles', true ) ) {
		_print_styles();
// 	}

	_gb_styles()->reset();
	return _gb_styles()->done;
}

/**
 * @internal use
 */
function _print_styles() {
	global $compress_css;

	$zip = $compress_css ? 1 : 0;
	if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
		$zip = 'gzip';

	_gb_styles()->do_concat = FALSE;	// TODO: Remove for enable concatenate mode
	if ( !empty(_gb_styles()->concat) ) {
		$dir = _gb_styles()->text_direction;
		$ver = _gb_styles()->default_version;
		$href = _gb_styles()->base_url . "/gb/load-styles.php?c={$zip}&dir={$dir}&load=" . trim(_gb_styles()->concat, ', ') . '&ver=' . $ver;
		echo "<link rel='stylesheet' href='" . esc_attr($href) . "' type='text/css' media='all' />\n";

		if ( !empty(_gb_styles()->print_code) ) {
			echo "<style type='text/css'>\n";
			echo _gb_styles()->print_code;
			echo "\n</style>\n";
		}
	}

	if ( !empty(_gb_styles()->print_html) )
		echo _gb_styles()->print_html;
}

/**
 * Determine the concatenation and compression settings for scripts and styles.
 *
 * @since	2.0.0
 */
function script_concat_settings() {
	global $concatenate_scripts, $compress_scripts, $compress_css;

	$compressed_output = ( ini_get('zlib.output_compression') || 'ob_gzhandler' == ini_get('output_handler') );

	if ( ! isset($concatenate_scripts) ) {
		$concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;
		if ( ! is_admin() || ( defined('GB_SCRIPT_DEBUG') && GB_SCRIPT_DEBUG ) )
			$concatenate_scripts = false;
	}

	if ( ! isset($compress_scripts) ) {
		$compress_scripts = defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : true;
		if ( $compress_scripts && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_scripts = false;
	}

	if ( ! isset($compress_css) ) {
		$compress_css = defined('COMPRESS_CSS') ? COMPRESS_CSS : true;
		if ( $compress_css && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_css = false;
	}
}

// add_action( 'gb_default_scripts', 'gb_default_scripts' );
// add_filter( 'gb_print_scripts', 'gb_just_in_time_script_localization' );
// add_filter( 'print_scripts_array', 'gb_prototype_before_jquery' );

// add_action( 'gb_default_styles', 'gb_default_styles' );
// add_filter( 'style_loader_src', 'gb_style_loader_src', 10, 2 );
