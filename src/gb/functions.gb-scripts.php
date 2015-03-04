<?php
/**
 * BackPress Scripts Procedural API
 *
 * @since	2.0.0
 *
 * @package GeniBase
 * @subpackage BackPress
 * 
 * @copyright	Copyright © WordPress Team
 * @copyright	Partially copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

/**
 * Return instance of GB_Scripts object.
 *
 * @since	2.0.0
 * @access	private
 *
 * @return	GB_Scripts	The GB_Scripts object for printing styles.
 */
function _gb_scripts(){
	static $gb_scripts;
	if(!is_a($gb_scripts, 'GB_Scripts')){
// 		if ( ! did_action( 'init' ) )
// 			_doing_it_wrong( __FUNCTION__, sprintf( __( 'Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.' ),
// 				'<code>gb_enqueue_scripts</code>', '<code>admin_enqueue_scripts</code>', '<code>login_enqueue_scripts</code>' ), '3.3' );

		$gb_scripts = new GB_Scripts();
	}
	return $gb_scripts;
}

/**
 * Print scripts in document head that are in the $handles queue.
 *
 * Called by admin-header.php and gb_head hook. Since it is called by gb_head on every page load,
 * the function does not instantiate the GB_Scripts object unless script names are explicitly passed.
 * Makes use of already-instantiated _gb_scripts() global if present. Use provided gb_print_scripts
 * hook to register/enqueue new scripts.
 *
 * @see GB_Scripts::do_items()
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 *
 * @param string|bool|array $handles Optional. Scripts to be printed. Default 'false'.
 * @return array On success, a processed array of GB_Dependencies items; otherwise, an empty array.
 */
function gb_print_scripts( $handles = false ) {
	/**
	 * Fires before scripts in the $handles queue are printed.
	 *
	 * @since	2.0.0
	 */
// 	do_action( 'gb_print_scripts' );

// 	if ( !$handles )
// 		return array(); // No need to instantiate if nothing is there.

	return _gb_scripts()->do_items( $handles );
}

/**
 * Register a new script.
 *
 * Registers a script to be linked later using the gb_enqueue_script() function.
 *
 * @see GB_Dependencies::add(), GB_Dependencies::add_data()
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 *
 * @param string      $handle    Name of the script. Should be unique.
 * @param string      $src       Path to the script from the GeniBase root directory. Example: '/js/myscript.js'.
 * @param array       $deps      Optional. An array of registered script handles this script depends on. Set to false if there
 *                               are no dependencies. Default empty array.
 * @param string|bool $ver       Optional. String specifying script version number, if it has one, which is concatenated
 *                               to end of path as a query string. If no version is specified or set to false, a version
 *                               number is automatically added equal to current installed GeniBase version.
 *                               If set to null, no version is added. Default 'false'. Accepts 'false', 'null', or 'string'.
 * @param bool        $in_footer Optional. Whether to enqueue the script before </head> or before </body>.
 *                               Default 'false'. Accepts 'false' or 'true'.
 */
function gb_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	_gb_scripts()->add( $handle, $src, $deps, $ver );
	if ( $in_footer )
		_gb_scripts()->add_data( $handle, 'group', 1 );
}

/**
 * Localize a script.
 *
 * Works only if the script has already been added.
 *
 * Accepts an associative array $l10n and creates a JavaScript object:
 *
 *     "$object_name" = {
 *         key: value,
 *         key: value,
 *         ...
 *     }
 *
 *
 * @see GB_Dependencies::localize()
 * @link https://core.trac.wordpress.org/ticket/11520
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 *
 * @todo Documentation cleanup
 *
 * @param string $handle      Script handle the data will be attached to.
 * @param string $object_name Name for the JavaScript object. Passed directly, so it should be qualified JS variable.
 *                            Example: '/[a-zA-Z0-9_]+/'.
 * @param array $l10n         The data itself. The data can be either a single or multi-dimensional array.
 * @return bool True if the script was successfully localized, false otherwise.
 */
function gb_localize_script( $handle, $object_name, $l10n ) {
	return _gb_scripts()->localize( $handle, $object_name, $l10n );
}

/**
 * Remove a registered script.
 *
 * Note: there are intentional safeguards in place to prevent critical admin scripts,
 * such as jQuery core, from being unregistered.
 *
 * @see GB_Dependencies::remove()
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the script to be removed.
 */
function gb_deregister_script( $handle ) {
	/**
	 * Do not allow accidental or negligent de-registering of critical scripts in the admin.
	 * Show minimal remorse if the correct hook is used.
	 */
/*	$current_filter = current_filter();
	if ( ( is_admin() && 'admin_enqueue_scripts' !== $current_filter ) ||
		( 'gb-login.php' === $GLOBALS['pagenow'] && 'login_enqueue_scripts' !== $current_filter )
	) {
		$no = array(
			'jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core', 'jquery-ui-accordion',
			'jquery-ui-autocomplete', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-dialog',
			'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-menu', 'jquery-ui-mouse',
			'jquery-ui-position', 'jquery-ui-progressbar', 'jquery-ui-resizable', 'jquery-ui-selectable',
			'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-spinner', 'jquery-ui-tabs',
			'jquery-ui-tooltip', 'jquery-ui-widget', 'underscore', 'backbone',
		);

		if ( in_array( $handle, $no ) ) {
			$message = sprintf( __( 'Do not deregister the %1$s script in the administration area. To target the frontend theme, use the %2$s hook.' ),
				"<code>$handle</code>", '<code>gb_enqueue_scripts</code>' );
			_doing_it_wrong( __FUNCTION__, $message, '3.6' );
			return;
		}
	}/**/

	_gb_scripts()->remove( $handle );
}

/**
 * Enqueue a script.
 *
 * Registers the script if $src provided (does NOT overwrite), and enqueues it.
 *
 * @see GB_Dependencies::add(), GB_Dependencies::add_data(), GB_Dependencies::enqueue()
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0

 * @param string      $handle    Name of the script.
 * @param string|bool $src       Path to the script from the root directory of GeniBase. Example: '/js/myscript.js'.
 * @param array       $deps      An array of registered handles this script depends on. Default empty array.
 * @param string|bool $ver       Optional. String specifying the script version number, if it has one. This parameter
 *                               is used to ensure that the correct version is sent to the client regardless of caching,
 *                               and so should be included if a version number is available and makes sense for the script.
 * @param bool        $in_footer Optional. Whether to enqueue the script before </head> or before </body>.
 *                               Default 'false'. Accepts 'false' or 'true'.
 */
function gb_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
	if ( $src ) {
		$_handle = explode('?', $handle);
		_gb_scripts()->add( $_handle[0], $src, $deps, $ver );
		if ( $in_footer )
			_gb_scripts()->add_data( $_handle[0], 'group', 1 );
	}
	_gb_scripts()->enqueue( $handle );
}

/**
 * Remove a previously enqueued script.
 *
 * @see GB_Dependencies::dequeue()
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the script to be removed.
 */
function gb_dequeue_script( $handle ) {
	_gb_scripts()->dequeue( $handle );
}

/**
 * Check whether a script has been added to the queue.
 *
 * @global GB_Scripts _gb_scripts() The GB_Scripts object for printing scripts.
 *
 * @since	2.0.0
 * @since	2.0.0 'enqueued' added as an alias of the 'queue' list.
 *
 * @param string $handle Name of the script.
 * @param string $list   Optional. Status of the script to check. Default 'enqueued'.
 *                       Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'.
 * @return bool Whether the script script is queued.
 */
function gb_script_is( $handle, $list = 'enqueued' ) {
	return (bool) _gb_scripts()->query( $handle, $list );
}
