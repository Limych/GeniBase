<?php
/**
 * General template tags that can go anywhere in a template.
 *
 * @package GeniBase
 * @subpackage Template
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Display information about the site.
 *
 * @see siteinfo() For possible values for the parameter.
 * @since 2.0.0
 *
 * @param string $show What to display.
 */
function siteinfo($show = ''){
	print get_siteinfo($show, 'display');
}

/**
 * Retrieve information about the site.
 *
 * The possible values for the 'show' parameter are listed below.
 *
 * 1. url - Site URI to homepage.
 * 2. gburl - Site URI path to GeniBase.
 * 3. description - Secondary title
 *
 * @since 2.0.0
 *
 * @param string $show Blog info to retrieve.
 * @param string $filter How to filter what is retrieved.
 * @return string Mostly string values, might be empty.
 */
function get_siteinfo($show = '', $filter = 'raw'){
	$output = '';
	switch($show) {
		case 'url' :
			$output = home_url();
			break;
		case 'gburl' :
			$output = site_url();
			break;
		case 'description':
// 			$output = get_option('blogdescription');	// TODO: options
			break;
		case 'pingback_url':
			$output = site_url( 'xmlrpc.php' );
			break;
		case 'stylesheet_url':
			$output = get_stylesheet_uri();
			break;
		case 'stylesheet_directory':
			$output = get_stylesheet_directory_uri();
			break;
		case 'template_directory':
		case 'template_url':
// 			$output = get_template_directory_uri();
			break;
		case 'charset':
// 			$output = get_option('blog_charset');	// TODO: options
			if( empty($output))	$output = 'UTF-8';
			break;
		case 'html_type' :
// 			$output = get_option('html_type');	// TODO: options
			break;
		case 'version':
			$output = GB_VERSION;
			break;
		case 'language':
			$output = str_replace('_', '-', get_locale());
			break;
		case 'text_direction':
			$output = (function_exists('is_rtl') && is_rtl()) ? 'rtl' : 'ltr';
			break;
		default:
			break;
	}

	$url = (strpos($show, 'url') !== false || strpos($show, 'directory') !== false);
	if( 'display' == $filter){
		if( $url){
			/**
			 * Filter the URL returned by get_siteinfo().
			 *
			 * @since 2.1.1
			 *
			 * @param mixed $output The URL returned by siteinfo().
			 * @param mixed $show   Type of information requested.
			 */
			$output = apply_filters( 'siteinfo_url', $output, $show );
		}else{
			/**
			 * Filter the site information returned by get_siteinfo().
			 *
			 * @since 2.1.1
			 *
			 * @param mixed $output The requested non-URL site information.
			 * @param mixed $show   Type of information requested.
			 */
			$output = apply_filters( 'siteinfo', $output, $show );
		}
	}

	return $output;
}

/**
 * Display the language attributes for the html tag.
 *
 * Builds up a set of html attributes containing the text direction and language
 * information for the page.
 *
 * @since 2.0.0
 *
 * @param string $doctype The type of html document (xhtml|html).
 */
function language_attributes($doctype = 'html') {
	$attributes = array();

	if( function_exists('is_rtl') && is_rtl())
		$attributes[] = 'dir="rtl"';

	if( $lang = get_siteinfo('language')){
		if( /* get_option('html_type') == 'text/html' || */ $doctype == 'html' )	// TODO: options
			$attributes[] = "lang=\"$lang\"";

		if( /* get_option('html_type') != 'text/html' || */ $doctype == 'xhtml')	// TODO: options
			$attributes[] = "xml:lang=\"$lang\"";
	}

	$output = implode(' ', $attributes);

	/**
	 * Filter the language attributes for display in the html tag.
	 *
	 * @since 2.1.0
	 *
	 * @param string $output A space-separated list of language attributes.
	*/
	echo apply_filters('language_attributes', $output);
}

/**
 * Fire the gb_head action.
 * 
 * @since	2.0.0
 */
function gb_head(){
	/**
	 * Print scripts or data in the head tag on the front end.
	 *
	 * @since	2.1.0
	 */
	do_action('gb_head');
}

/**
 * Fire the wp_footer action
 *
 * @since	2.1.0
 */
function gb_footer() {
	/**
	 * Print scripts or data before the closing body tag on the front end.
	 *
	 * @since	2.1.0
	 */
	do_action('gb_footer');
}

/**
 * For internal use.
 * 
 * @access	private
 * @since	2.0.1
 * 
 * @param string $pg
 * @return string
 */
function _paginator_url($pg){
	return add_query_arg('pg', ($pg > 1) ? $pg : false) . '#report';
}

/**
 * Функция формирования блока ссылок для перемещения между страницами.
 * 
 * @param	integer	$pg
 * @param	integer	$max_pg
 * @return	string
 */
function paginator($pg, $max_pg){
	$pag = array();
	
	if( $pg > 1)	$pag[] = '<a href="' . _paginator_url($pg - 1) . '" class="prev">←</a>';
	if( $pg > 1)	$pag[] = '<a href="' . _paginator_url(1) . '">1</a>';

	if( $pg > 12)	$pag[] = '<span>…</span>';
	if( $pg > 11)	$pag[] = '<a href="' . _paginator_url($pg - 10) . '">' . ($pg - 10) . '</a>';
	
	if( $pg == 8)	$pag[] = '<a href="' . _paginator_url(2) . '">2</a>';
	elseif($pg > 8)	$pag[] = '<span>…</span>';
	for($i = max($pg - 5, 2); $i < $pg; $i++)
		$pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';

	$pag[] = '<span class="current">' . $pg . '</span>';

	for($i = $pg + 1; $i < min($pg + 5, $max_pg); $i++)
		$pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';
	if( $pg == $max_pg - 6)		$pag[] = '<a href="' . _paginator_url($max_pg - 1) . '">' . ($max_pg - 1) . '</a>';
	elseif($pg < $max_pg - 6)	$pag[] = '<span>…</span>';

	if( $pg < $max_pg - 10)	$pag[] = '<a href="' . _paginator_url($pg + 10) . '">' . ($pg + 10) . '</a>';
	if( $pg < $max_pg - 11)	$pag[] = '<span>…</span>';
	
	if( $pg < $max_pg)	$pag[] = '<a href="' . _paginator_url($max_pg) . '">' . $max_pg . '</a>';
	if( $pg < $max_pg)	$pag[] = '<a href="' . _paginator_url($pg + 1) . '" class="next">→</a>';

	return '<div class="paginator">' . implode(' ', $pag) . '</div>';
}
