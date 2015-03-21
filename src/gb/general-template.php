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
 * @deprecated	2.2.2
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
 * @deprecated	2.2.2
 * 
 * @param	integer	$pg
 * @param	integer	$max_pg
 * @return	string
 */
function paginator($pg, $max_pg){
	_deprecated_function(__FUNCTION__, '2.2', 'paginate_links');

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

/**
 * For internal use.
 * 
 * @access	private
 * @since	2.2.2
 * 
 * @param int $page_num
 * @param array $args	{@see paginate_links}
 * @param string $class
 * @param string $page_title
 * @param string $format
 * @return string
 */
function _paginate_link($page_num, $args, $class = '', $page_title = null, $format = null){
	if( $format == null )
		$format = $args['format'];
	if( $page_title == null )
		$page_title = $args['before_page_number'] . number_format_i18n($page_num) . $args['after_page_number'];
	$link = str_replace('%_%', $format, $args['base']);
	$link = str_replace('%#%', $page_num, $link);
	if( $args['add_args'] )
		$link = add_query_arg($args['add_args'], $link);
	$link .= $args['add_fragment'];
	
	$rel = '';
	if( $page_num == 1 )						$rel = 'first';
	elseif( $page_num == $args['total'] )		$rel = 'last';
	elseif( $page_num == $args['current'] - 1 )	$rel = 'prev';
	elseif( $page_num == $args['current'] + 1 )	$rel = 'next';
	
	if( $rel )	$rel = ' rel="' . $rel . '"';

	/**
	 * Filter the paginated links for the given archive pages.
	 *
	 * @since 2.2.2
	 *
	 * @param string $link The paginated link URL.
	 */
	return '<a class="pagination' . ($class ? " $class" : '') . '" href="' .
			esc_url(apply_filters('paginate_links', $link)) . '"' . $rel . '>' . $page_title . '</a>';
}

/**
 * Retrieve paginated link for multipaged data blocks.
 * 
 * @since	2.2.2
 * 
 * @param string|array $args {
 *		Optional. Array or string of arguments for generating paginated links for archives.
 *
 *		@type string $base			Base of the paginated url. Default empty.
 *		@type string $format		Format for the pagination structure. Default empty.
 *		@type int    $total			The total amount of pages. Default is the value WP_Query's
 *									`max_num_pages` or 1.
 *		@type int    $current		The current page number. Default is 'paged' query var or 1.
 *		@type int    $end_size		How many numbers on either the start and the end list edges.
 *									Default 1.
 *		@type int    $tenth_size	How many numbers to either side of the current pages at
 *									a distance of ten. Default 4.
 *		@type int    $mid_size		How many numbers to either side of the current pages. Default 4.
 *		@type bool   $prev_next		Whether to include the previous and next links in the list. Default true.
 *		@type bool   $prev_text		The previous page text. Default '←'.
 *		@type bool   $next_text		The next page text. Default '→'.
 *		@type string $type			Controls format of the returned value. Possible values are 'plain',
 *									'array' and 'list'. Default is 'plain'.
 *		@type array  $add_args		An array of query args to add. Default false.
 *		@type string $add_fragment	A string to append to each link. Default empty.
 *		@type string $before_page_number	A string to appear before the page number. Default empty.
 *		@type string $after_page_number		A string to append after the page number. Default empty.
 * }
 * @return array|string String of page links or array of page links.
 */
function paginate_links( $args = '' ) {
	// Setting up default values based on the current URL.
	$pagenum_link = html_entity_decode($_SERVER['REQUEST_URI']);
	$url_parts    = explode('?', $pagenum_link);

	// Get max pages and current page out of the current query, if available.
	$total   = 1;
	$current = isset($_REQUEST['pg']) ? intval($_REQUEST['pg']) : 1;

	// Append the format placeholder to the base URL.
	$pagenum_link = trailingslashit($url_parts[0]) . '%_%';

	// URL base depends on permalink settings.
	$format = '?pg=%#%';

	$defaults = array(
			'base'			=> $pagenum_link, // http://example.com/index.php%_% : %_% is replaced by format (below)
			'format'		=> $format, // ?pg=%#% : %#% is replaced by the page number
			'total'			=> $total,
			'current'		=> $current,
			'end_size'		=> 1,
			'tenth_size'	=> 1,
			'mid_size'		=> 4,
			'prev_next'		=> true,
			'prev_text'		=> '←',
			'next_text'		=> '→',
			'type'			=> 'plain',
			'add_args'		=> array(), // array of query args to add
			'add_fragment'	=> '',
			'before_page_number'	=> '',
			'after_page_number'		=> '',
	);
	
	$args = gb_parse_args( $args, $defaults );

	if( !is_array($args['add_args']) )
		$args['add_args'] = array();

	// Merge additional query vars found in the original URL into 'add_args' array.
	if( isset($url_parts[1]) ){
		foreach (gb_parse_args($url_parts[1]) as $key => $val)
			if( !isset($args['add_args'][$key]) )
				$args['add_args'][$key] = $val;
	}

	// Who knows what else people pass in $args
	$total = (int) $args['total'];
	if( $total < 2 )
		return;

	// Out of bounds?  Make it the default.
	if( $args['end_size'] < 1 )		$args['end_size'] = 1;
	if( $args['tenth_size'] < 0 )	$args['tenth_size'] = 1;
	if( $args['mid_size'] < 0 )		$args['mid_size'] = 4;

	$current  = (int) $args['current'];
	$end_size = (int) $args['end_size'];
	$tenth_size = (int) $args['tenth_size'];
	$mid_size = (int) $args['mid_size'];

	if( $current >= 2 ){
		if( $args['prev_next'] ){
			$page_links[] = _paginate_link($current - 1, $args, 'prev', $args['prev_text'],
					($current == 2 ? '' : $args['format']));
		}
		for ($n = 1; $n <= $end_size; $n++)
			$page_links[] = _paginate_link($n, $args);
	}

	if( $tenth_size && $current >= 11 + $end_size ){
		if( $current == 11 + $end_size + $tenth_size )
			$page_links[] = _paginate_link($current - 10 - $tenth_size, $args);
		elseif( $current > 11 + $end_size + $tenth_size )
			$page_links[] = '<span class="pagination dots">' . __( '&hellip;' ) . '</span>';

		for ($n = max(1 + $end_size, $current - 9 - $tenth_size); $n <= $current - 10; $n++)
			$page_links[] = _paginate_link($n, $args);
	}

	if( $current == 2 + $end_size + $mid_size )
		$page_links[] = _paginate_link($current - $mid_size - 1, $args);
	elseif( $current > 2 + $end_size + $mid_size )
		$page_links[] = '<span class="pagination dots">' . __( '&hellip;' ) . '</span>';

	for ($n = max(1 + $end_size, $current - $mid_size); $n < $current; $n++)
		$page_links[] = _paginate_link($n, $args);
	$page_links[] = '<span class="pagination current">' . $args['before_page_number'] .
			number_format_i18n($current) . $args['after_page_number'] . '</span>';
	for ($n = $current + 1; $n <= min($total - $end_size, $current + $mid_size); $n++)
		$page_links[] = _paginate_link($n, $args);

	if( $current == $total - 1 - $end_size - $mid_size )
		$page_links[] = _paginate_link($current + $mid_size + 1, $args);
	elseif( $current < $total - 1 - $end_size - $mid_size )
		$page_links[] = '<span class="pagination dots">' . __( '&hellip;' ) . '</span>';

	if( $tenth_size && $current <= $total - 9 - $end_size ){
		for ($n = $current + 10; $n <= min($total - $end_size, $current + 9 + $tenth_size); $n++)
			$page_links[] = _paginate_link($n, $args);

		if( $current == $total - 10 - $tenth_size - $end_size )
			$page_links[] = _paginate_link($current + 10 + $tenth_size, $args);
		elseif( $current < $total - 10 - $tenth_size - $end_size )
			$page_links[] = '<span class="pagination dots">' . __( '&hellip;' ) . '</span>';
	}

	if( $current <= $total - 1 ){
		for ($n = $total - $end_size + 1; $n <= $total; $n++)
			$page_links[] = _paginate_link($n, $args);
		if( $args['prev_next'] && $current < $total )
			$page_links[] = _paginate_link($current + 1, $args, 'next', $args['next_text']);
	}

	switch ($args['type']) {
		case 'array' :
			return $page_links;

		case 'list' :
			$r .= "<ul class='pagination'>\n\t<li>";
			$r .= join("</li>\n\t<li>", $page_links);
			$r .= "</li>\n</ul>\n";
			break;

		default :
			$r = join("\n", $page_links);
			break;
	}
	return $r;
}
