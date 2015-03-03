<?php
/**
 * BackPress Styles Procedural API
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
 * Return instance of GB_Style object.
 *
 * @since	2.0.0
 * @access	private
 * 
 * @return	GB_Styles	The GB_Styles object for printing styles.
 */
function _gb_styles(){
	static $gb_styles;
	if(!is_a($gb_styles, 'GB_Styles')){
// 		if ( ! did_action( 'init' ) )
// 			_doing_it_wrong( __FUNCTION__, sprintf( __( 'Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.' ),
// 				'<code>gb_enqueue_scripts</code>', '<code>admin_enqueue_scripts</code>', '<code>login_enqueue_scripts</code>' ), '3.3' );
	
		$gb_styles = new GB_Styles();
	}
	return $gb_styles;
}

/**
 * Display styles that are in the $handles queue.
 *
 * Passing an empty array to $handles prints the queue,
 * passing an array with one string prints that style,
 * and passing an array of strings prints those styles.
 *
 * @since	2.0.0
 *
 * @param string|bool|array $handles Styles to be printed. Default 'false'.
 * @return array On success, a processed array of GB_Dependencies items; otherwise, an empty array.
 */
function gb_print_styles( $handles = false ) {
	/**
	 * Fires before styles in the $handles queue are printed.
	 *
	 * @since	2.0.0
	 */
// 	if ( ! $handles )
// 		do_action( 'gb_print_styles' );

	return _gb_styles()->do_items( $handles );
}

/**
 * Add extra CSS styles to a registered stylesheet.
 *
 * Styles will only be added if the stylesheet in already in the queue.
 * Accepts a string $data containing the CSS. If two or more CSS code blocks
 * are added to the same stylesheet $handle, they will be printed in the order
 * they were added, i.e. the latter added styles can redeclare the previous.
 *
 * @see GB_Styles::add_inline_style()
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the stylesheet to add the extra styles to. Must be lowercase.
 * @param string $data   String containing the CSS styles to be added.
 * @return bool True on success, false on failure.
 */
function gb_add_inline_style( $handle, $data ) {
	if(false !== stripos($data, '</style>')){
		_doing_it_wrong(__FUNCTION__, __('Do not pass style tags to gb_add_inline_style().'));
		$data = trim(preg_replace('#<style[^>]*>(.*)</style>#is', '$1', $data));
	}

	return _gb_styles()->add_inline_style($handle, $data);
}

/**
 * Register a CSS stylesheet.
 *
 * @see GB_Dependencies::add()
 * @link http://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since	2.0.0
 *
 * @param string      $handle Name of the stylesheet.
 * @param string|bool $src    Path to the stylesheet from the GeniBase root directory. Example: '/css/mystyle.css'.
 * @param array       $deps   An array of registered style handles this stylesheet depends on. Default empty array.
 * @param string|bool $ver    String specifying the stylesheet version number. Used to ensure that the correct version
 *                            is sent to the client regardless of caching. Default 'false'. Accepts 'false', 'null', or 'string'.
 * @param string      $media  Optional. The media for which this stylesheet has been defined.
 *                            Default 'all'. Accepts 'all', 'aural', 'braille', 'handheld', 'projection', 'print',
 *                            'screen', 'tty', or 'tv'.
 */
function gb_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
	_gb_styles()->add( $handle, $src, $deps, $ver, $media );
}

/**
 * Remove a registered stylesheet.
 *
 * @see GB_Dependencies::remove()
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function gb_deregister_style( $handle ) {
	_gb_styles()->remove( $handle );
}

/**
 * Enqueue a CSS stylesheet.
 *
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @see GB_Dependencies::add(), GB_Dependencies::enqueue()
 * @link http://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since	2.0.0
 *
 * @param string      $handle Name of the stylesheet.
 * @param string|bool $src    Path to the stylesheet from the root directory of GeniBase. Example: '/css/mystyle.css'.
 * @param array       $deps   An array of registered style handles this stylesheet depends on. Default empty array.
 * @param string|bool $ver    String specifying the stylesheet version number, if it has one. This parameter is used
 *                            to ensure that the correct version is sent to the client regardless of caching, and so
 *                            should be included if a version number is available and makes sense for the stylesheet.
 * @param string      $media  Optional. The media for which this stylesheet has been defined.
 *                            Default 'all'. Accepts 'all', 'aural', 'braille', 'handheld', 'projection', 'print',
 *                            'screen', 'tty', or 'tv'.
 */
function gb_enqueue_style( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' ) {
	if ( $src ) {
		$_handle = explode('?', $handle);
		_gb_styles()->add( $_handle[0], $src, $deps, $ver, $media );
	}
	_gb_styles()->enqueue( $handle );
}

/**
 * Remove a previously enqueued CSS stylesheet.
 *
 * @see GB_Dependencies::dequeue()
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function gb_dequeue_style( $handle ) {
	_gb_styles()->dequeue( $handle );
}

/**
 * Check whether a CSS stylesheet has been added to the queue.
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $list   Optional. Status of the stylesheet to check. Default 'enqueued'.
 *                       Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'.
 * @return bool Whether style is queued.
 */
function gb_style_is( $handle, $list = 'enqueued' ) {
	return (bool) _gb_styles()->query( $handle, $list );
}

/**
 * Add metadata to a CSS stylesheet.
 *
 * Works only if the stylesheet has already been added.
 *
 * Possible values for $key and $value:
 * 'conditional' string      Comments for IE 6, lte IE 7 etc.
 * 'rtl'         bool|string To declare an RTL stylesheet.
 * 'suffix'      string      Optional suffix, used in combination with RTL.
 * 'alt'         bool        For rel="alternate stylesheet".
 * 'title'       string      For preferred/alternate stylesheets.
 *
 * @see GB_Dependency::add_data()
 *
 * @since	2.0.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $key    Name of data point for which we're storing a value.
 *                       Accepts 'conditional', 'rtl' and 'suffix', 'alt' and 'title'.
 * @param mixed  $value  String containing the CSS data to be added.
 * @return bool True on success, false on failure.
 */
function gb_style_add_data( $handle, $key, $value ) {
	return _gb_styles()->add_data( $handle, $key, $value );
}
