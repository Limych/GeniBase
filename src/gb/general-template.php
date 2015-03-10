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
if(!defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



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
			if(empty($output))	$output = 'UTF-8';
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
	if('display' == $filter){
		if($url){
			/**
			 * Filter the URL returned by get_siteinfo().
			 *
			 * @since 2.0.0
			 *
			 * @param mixed $output The URL returned by siteinfo().
			 * @param mixed $show   Type of information requested.
			 */
// 			$output = apply_filters( 'siteinfo_url', $output, $show );	// TODO: actions
		}else{
			/**
			 * Filter the site information returned by get_siteinfo().
			 *
			 * @since 2.0.0
			 *
			 * @param mixed $output The requested non-URL site information.
			 * @param mixed $show   Type of information requested.
			 */
// 			$output = apply_filters( 'siteinfo', $output, $show );	// TODO: actions
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

	if(function_exists('is_rtl') && is_rtl())
		$attributes[] = 'dir="rtl"';

	if($lang = get_siteinfo('language')){
		if(/* get_option('html_type') == 'text/html' || */ $doctype == 'html' )	// TODO: options
			$attributes[] = "lang=\"$lang\"";

		if(/* get_option('html_type') != 'text/html' || */ $doctype == 'xhtml')	// TODO: options
			$attributes[] = "xml:lang=\"$lang\"";
	}

	$output = implode(' ', $attributes);

	/**
	 * Filter the language attributes for display in the html tag.
	 *
	 * @since 2.0.0
	 *
	 * @param string $output A space-separated list of language attributes.
	*/
	echo $output;
// 	echo apply_filters('language_attributes', $output);	// TODO: actions
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
	 * @since	2.0.0
	 */
// 	do_action( 'gb_head' );	// TODO: actions

	// TODO: Remove block after actions will be enabled
	@header("X-Generator: GeniBase/" . GB_VERSION . "\n");
	gb_print_styles();
	gb_print_scripts();
}

/**
 * Вывод начальной части страницы
 * 
 * @since	2.1.0	Added $do_index parameter.
 *
 * @param	string	$title	Title of the page.
 * @param	boolean	$do_index	FALSE for restrict indexation of page.
 */
function html_header($title, $do_index = TRUE){
	gb_enqueue_style('styles', '/styles.css', array('normalize', 'responsive-tables', 'responsive-forms'));
	gb_enqueue_style('print', '/print.css', array(), FALSE, 'print');
	
	gb_enqueue_script('jquery');
	
	$robots = $do_index ? 'All' : 'NoIndex,Follow';

	@header('Content-Type: text/html; charset=utf-8');
	?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>><head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, user-scalable=no" />
	<meta name='robots' content='<?php print $robots; ?>' />

	<title><?php echo $title; ?> - Первая мировая война, 1914–1918 гг. Алфавитные списки потерь нижних чинов</title>

	<link rel="icon" type="image/vnd.microsoft.icon" href="<?php print BASE_URL; ?>/favicon.ico" />
	<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="<?php print BASE_URL; ?>/favicon.ico" />
<?php gb_head(); ?>
</head><body>
	<header>
		<div class='logo'>
			<a href="<?php print BASE_URL; ?>" tabindex="-2"><img src="<?php print BASE_URL; ?>/img/logo.jpg" alt='' /></a>
		</div>
		<div class='title'>
			<h1>Первая мировая война, <nobr>1914&ndash;1918 гг.</nobr></h1>
			<div><small><a href="http://www.svrt.ru/" tabindex="-1">Проект Союза Возрождений Родословных Традиций (СВРТ)</a></small></div>
		</div>
	</header>
<?php
}


/**
 * Вывод хвостовой части страницы
 */
function html_footer(){
?>
<footer>
	<p style="text-align: center; margin-top: 3em" class="no-print">
		<a href="<?php print site_url('/stat.php'); ?>">Статистика</a>
		| <a href="<?php print site_url('/guestbook/'); ?>">Гостевая книга</a> 
		| <a href="http://forum.svrt.ru/index.php?showforum=127" target="_blank">Обсуждение проекта</a>
		| <a href="<?php print site_url('/crue.php'); ?>">Команда проекта</a>
	</p>
	<p class="copyright"><strong>Обратите внимание:</strong> Обработанные списки размещаются в свободном доступе только для некоммерческих исследований. Использование обработанных списков в коммерческих целях запрещено без получения Вами явного согласия правообладателя источника информации, СВРТ и участников проекта, осуществлявших обработку и систематизацию списков.</p>
<?php if(GB_DEBUG): ?>
	<p><small>Statistic: <?php
		print(timer_stop(0, 3) . 's');
		if(function_exists('memory_get_usage'))
			print(' / ' . round(memory_get_usage()/1024/1024, 2) . 'mb ');
	?></small></p>
<?php endif; ?>
</footer>
<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

	ga('create', 'UA-289367-8', 'auto');
	ga('require', 'displayfeatures');
	ga('require', 'linkid', 'linkid.js');
	ga('send', 'pageview');
</script>
</body></html>
<?php
}

/**
 * For internal use.
 * 
 * @access	private
 * @since	2.1.0
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
	
	if($pg > 1)	$pag[] = '<a href="' . _paginator_url($pg - 1) . '" class="prev">←</a>';
	if($pg > 1)	$pag[] = '<a href="' . _paginator_url(1) . '">1</a>';

	if($pg > 12)	$pag[] = '<span>…</span>';
	if($pg > 11)	$pag[] = '<a href="' . _paginator_url($pg - 10) . '">' . ($pg - 10) . '</a>';
	
	if($pg == 8)	$pag[] = '<a href="' . _paginator_url(2) . '">2</a>';
	elseif($pg > 8)	$pag[] = '<span>…</span>';
	for($i = max($pg - 5, 2); $i < $pg; $i++)
		$pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';

	$pag[] = '<span class="current">' . $pg . '</span>';

	for($i = $pg + 1; $i < min($pg + 5, $max_pg); $i++)
		$pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';
	if($pg == $max_pg - 6)		$pag[] = '<a href="' . _paginator_url($max_pg - 1) . '">' . ($max_pg - 1) . '</a>';
	elseif($pg < $max_pg - 6)	$pag[] = '<span>…</span>';

	if($pg < $max_pg - 10)	$pag[] = '<a href="' . _paginator_url($pg + 10) . '">' . ($pg + 10) . '</a>';
	if($pg < $max_pg - 11)	$pag[] = '<span>…</span>';
	
	if($pg < $max_pg)	$pag[] = '<a href="' . _paginator_url($max_pg) . '">' . $max_pg . '</a>';
	if($pg < $max_pg)	$pag[] = '<a href="' . _paginator_url($pg + 1) . '" class="next">→</a>';

	return '<div class="paginator">' . implode(' ', $pag) . '</div>';
}
