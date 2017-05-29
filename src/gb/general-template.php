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
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

/**
 * Display information about the site.
 *
 * @see siteinfo() For possible values for the parameter.
 * @since 2.0.0
 *
 * @param string $show
 *            What to display.
 */
function siteinfo($show = '')
{
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
 * @param string $show
 *            Blog info to retrieve.
 * @param string $filter
 *            How to filter what is retrieved.
 * @return string Mostly string values, might be empty.
 */
function get_siteinfo($show = '', $filter = 'raw')
{
    $output = '';
    switch ($show) {
        case 'url':
            $output = home_url();
            break;
        case 'gburl':
            $output = site_url();
            break;
        case 'description':
            // $output = get_option('blogdescription'); // TODO: options
            break;
        case 'pingback_url':
            $output = site_url('xmlrpc.php');
            break;
        case 'stylesheet_url':
            $output = get_stylesheet_uri();
            break;
        case 'stylesheet_directory':
            $output = get_stylesheet_directory_uri();
            break;
        case 'template_directory':
        case 'template_url':
            // $output = get_template_directory_uri();
            break;
        case 'charset':
            // $output = get_option('blog_charset'); // TODO: options
            if (empty($output))
                $output = 'UTF-8';
            break;
        case 'html_type':
            // $output = get_option('html_type'); // TODO: options
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
    if ('display' == $filter && class_exists('GB_Hooks')) {
        if ($url) {
            /**
             * Filter the URL returned by get_siteinfo().
             *
             * @since 2.1.1
             *
             * @param mixed $output
             *            The URL returned by siteinfo().
             * @param mixed $show
             *            Type of information requested.
             */
            $output = GB_Hooks::apply_filters('siteinfo_url', $output, $show);
        } else {
            /**
             * Filter the site information returned by get_siteinfo().
             *
             * @since 2.1.1
             *
             * @param mixed $output
             *            The requested non-URL site information.
             * @param mixed $show
             *            Type of information requested.
             */
            $output = GB_Hooks::apply_filters('siteinfo', $output, $show);
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
 * @param string $doctype
 *            The type of html document (xhtml|html).
 */
function language_attributes($doctype = 'html')
{
    $attributes = array();

    if (function_exists('is_rtl') && is_rtl())
        $attributes[] = 'dir="rtl"';

    if ($lang = get_siteinfo('language')) {
        $opt_doctype = 'text/html';
        if (class_exists('GB_Options')) {
            $opt_doctype = GB_Options::get('html_type');
        }

        if( $opt_doctype == 'text/html' || $doctype == 'html')
            $attributes[] = "lang=\"$lang\"";

        if( $opt_doctype != 'text/html' || $doctype == 'xhtml')
            $attributes[] = "xml:lang=\"$lang\"";
    }

    $output = implode(' ', $attributes);

    if (class_exists('GB_Hooks')) {
        /**
         * Filter the language attributes for display in the html tag.
         *
         * @since 2.1.0
         *
         * @param string $output
         *            A space-separated list of language attributes.
         */
        $output = GB_Hooks::apply_filters('language_attributes', $output);
    }

    echo $output;
}

/**
 * Fire the gb_head action.
 *
 * @since 2.0.0
 */
function gb_head()
{
    if (class_exists('GB_Hooks')) {
        /**
         * Print scripts or data in the head tag on the front end.
         *
         * @since 2.1.0
         */
        GB_Hooks::do_action('gb_head');
    }
}

/**
 * Fire the wp_footer action
 *
 * @since 2.1.0
 */
function gb_footer()
{
    if (class_exists('GB_Hooks')) {
        /**
         * Print scripts or data before the closing body tag on the front end.
         *
         * @since 2.1.0
         */
        GB_Hooks::do_action('gb_footer');
    }
}

/**
 * For internal use.
 *
 * @deprecated 2.2.2
 *
 * @access private
 * @since 2.0.1
 *
 * @param string $pg
 * @return string
 */
function _paginator_url($pg)
{
    return add_query_arg('pg', ($pg > 1) ? $pg : false) . '#report';
}

/**
 * Функция формирования блока ссылок для перемещения между страницами.
 *
 * @deprecated 2.2.2
 *
 * @param integer $pg
 * @param integer $max_pg
 * @return string
 */
function paginator($pg, $max_pg)
{
    _deprecated_function('2.2', 'paginate_links');

    $pag = array();

    if ($pg > 1)
        $pag[] = '<a href="' . _paginator_url($pg - 1) . '" class="prev">←</a>';
    if ($pg > 1)
        $pag[] = '<a href="' . _paginator_url(1) . '">1</a>';

    if ($pg > 12)
        $pag[] = '<span>…</span>';
    if ($pg > 11)
        $pag[] = '<a href="' . _paginator_url($pg - 10) . '">' . ($pg - 10) . '</a>';

    if ($pg == 8)
        $pag[] = '<a href="' . _paginator_url(2) . '">2</a>';
    elseif ($pg > 8)
        $pag[] = '<span>…</span>';
    for ($i = max($pg - 5, 2); $i < $pg; $i ++)
        $pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';

    $pag[] = '<span class="current">' . $pg . '</span>';

    for ($i = $pg + 1; $i < min($pg + 5, $max_pg); $i ++)
        $pag[] = '<a href="' . _paginator_url($i) . '">' . $i . '</a>';
    if ($pg == $max_pg - 6)
        $pag[] = '<a href="' . _paginator_url($max_pg - 1) . '">' . ($max_pg - 1) . '</a>';
    elseif ($pg < $max_pg - 6)
        $pag[] = '<span>…</span>';

    if ($pg < $max_pg - 10)
        $pag[] = '<a href="' . _paginator_url($pg + 10) . '">' . ($pg + 10) . '</a>';
    if ($pg < $max_pg - 11)
        $pag[] = '<span>…</span>';

    if ($pg < $max_pg)
        $pag[] = '<a href="' . _paginator_url($max_pg) . '">' . $max_pg . '</a>';
    if ($pg < $max_pg)
        $pag[] = '<a href="' . _paginator_url($pg + 1) . '" class="next">→</a>';

    return '<div class="paginator">' . implode(' ', $pag) . '</div>';
}

/**
 * For internal use.
 *
 * @access private
 * @since 2.2.2
 *
 * @param int $page_num
 * @param array $args
 *            paginate_links}
 * @param string $class
 * @param string $page_title
 * @param string $format
 * @return string
 */
function _paginate_link($page_num, $args, $class = '', $page_title = null)
{
    if ($page_title == null)
        $page_title = $args['before_page_number'] . number_format_i18n($page_num) . $args['after_page_number'];

    $format = ($page_num == 1) ? '' : $args['format'];
    $link = str_replace('%_%', $format, $args['base']);
    $link = str_replace('%#%', $page_num, $link);
    if ($args['add_args'])
        $link = add_query_arg($args['add_args'], $link);
    $link .= $args['add_fragment'];

    $rel = '';
    if ($page_num == 1)
        $rel = 'first';
    elseif ($page_num == $args['total'])
        $rel = 'last';
    elseif ($page_num == $args['current'] - 1)
        $rel = 'prev';
    elseif ($page_num == $args['current'] + 1)
        $rel = 'next';

    if ($rel)
        $rel = ' rel="' . $rel . '"';

    if (class_exists('GB_Hooks')) {
        /**
         * Filter the paginated links for the given archive pages.
         *
         * @since 2.2.2
         *
         * @param string $link
         *            The paginated link URL.
         */
        $link = GB_Hooks::apply_filters('paginate_links', $link);
    }
    return '<a class="pagination' . ($class ? " $class" : '') . '" href="' . esc_url($link) . '"' . $rel . '>' . $page_title . '</a>';
}

/**
 * Retrieve paginated link for multipaged data blocks.
 *
 * @since 2.2.2
 *
 * @param string|array $args
 *            {
 *            Optional. Array or string of arguments for generating paginated links for archives.
 *
 *            @type string $base Base of the paginated url. Default empty.
 *            @type string $format Format for the pagination structure. Default empty.
 *            @type int $total The total amount of pages. Default is the value WP_Query's
 *            `max_num_pages` or 1.
 *            @type int $current The current page number. Default is 'paged' query var or 1.
 *            @type int $end_size How many numbers on either the start and the end list edges.
 *            Default 1.
 *            @type int $tenth_size How many numbers to either side of the current pages at
 *            a distance of ten. Default 4.
 *            @type int $mid_size How many numbers to either side of the current pages. Default 4.
 *            @type bool $prev_next Whether to include the previous and next links in the list. Default true.
 *            @type bool $prev_text The previous page text. Default '←'.
 *            @type bool $next_text The next page text. Default '→'.
 *            @type string $type Controls format of the returned value. Possible values are 'plain',
 *            'array' and 'list'. Default is 'plain'.
 *            @type array $add_args An array of query args to add. Default false.
 *            @type string $add_fragment A string to append to each link. Default empty.
 *            @type string $before_page_number A string to appear before the page number. Default empty.
 *            @type string $after_page_number A string to append after the page number. Default empty.
 *            }
 * @return array|string String of page links or array of page links.
 */
function paginate_links($args = '')
{
    // Setting up default values based on the current URL.
    $pagenum_link = html_entity_decode(get_pagenum_link());
    $url_parts = explode('?', $pagenum_link);

    // Get max pages and current page out of the current query, if available.
    $total = 1;
    $current = isset($_REQUEST['pg']) ? intval($_REQUEST['pg']) : 1;

    // Append the format placeholder to the base URL.
    $pagenum_link = $url_parts[0] . '%_%'; // TODO: rewrite
                                           // $pagenum_link = trailingslashit($url_parts[0]) . '%_%';

    // URL base depends on permalink settings.
    $format = '?pg=%#%'; // TODO: rewrite
                         // $format = $wp_rewrite->using_index_permalinks() && !strpos($pagenum_link, 'index.php') ? 'index.php/' : '';
                         // $format .= $wp_rewrite->using_permalinks() ? user_trailingslashit( $wp_rewrite->pagination_base . '/%#%', 'paged' ) : '?paged=%#%';

    $defaults = array(
        'base' => $pagenum_link, // http://example.com/index.php%_% : %_% is replaced by format (below)
        'format' => $format, // ?pg=%#% : %#% is replaced by the page number
        'total' => $total,
        'current' => $current,
        'end_size' => 1,
        'tenth_size' => 1,
        'mid_size' => 4,
        'prev_next' => true,
        'prev_text' => '&larr;',
        'next_text' => '&rarr;',
        'type' => 'plain',
        'add_args' => array(), // array of query args to add
        'add_fragment' => '',
        'before_page_number' => '',
        'after_page_number' => ''
    );

    $args = gb_parse_args($args, $defaults);

    if (! is_array($args['add_args']))
        $args['add_args'] = array();

        // Merge additional query vars found in the original URL into 'add_args' array.
    if (isset($url_parts[1])) {
        // Find the format argument.
        $format_query = parse_url(str_replace('%_%', $args['format'], $args['base']), PHP_URL_QUERY);
        gb_parse_str($format_query, $format_arg);

        // Remove the format argument from the array of query arguments, to avoid overwriting custom format.
        gb_parse_str(remove_query_arg(array_keys($format_arg), $url_parts[1]), $query_args);
        $args['add_args'] = array_merge($args['add_args'], urlencode_deep($query_args));
    }

    // Who knows what else people pass in $args
    $total = (int) $args['total'];
    if ($total < 2)
        return;

        // Out of bounds? Make it the default.
    if ($args['end_size'] < 1)
        $args['end_size'] = 1;
    if ($args['tenth_size'] < 0)
        $args['tenth_size'] = 1;
    if ($args['mid_size'] < 0)
        $args['mid_size'] = 4;

    $current = (int) $args['current'];
    $end_size = (int) $args['end_size'];
    $tenth_size = (int) $args['tenth_size'];
    $mid_size = (int) $args['mid_size'];

    if ($current >= 2) {
        if ($args['prev_next']) {
            $page_links[] = _paginate_link($current - 1, $args, 'prev', $args['prev_text']);
        }
        for ($n = 1; $n <= $end_size; $n ++)
            $page_links[] = _paginate_link($n, $args);
    }

    if ($tenth_size && $current >= 11 + $end_size) {
        if ($current == 11 + $end_size + $tenth_size)
            $page_links[] = _paginate_link($current - 10 - $tenth_size, $args);
        elseif ($current > 11 + $end_size + $tenth_size)
            $page_links[] = '<span class="pagination dots">' . __('&hellip;') . '</span>';

        for ($n = max(1 + $end_size, $current - 9 - $tenth_size); $n <= $current - 10; $n ++)
            $page_links[] = _paginate_link($n, $args);
    }

    if ($current == 2 + $end_size + $mid_size)
        $page_links[] = _paginate_link($current - $mid_size - 1, $args);
    elseif ($current > 2 + $end_size + $mid_size)
        $page_links[] = '<span class="pagination dots">' . __('&hellip;') . '</span>';

    for ($n = max(1 + $end_size, $current - $mid_size); $n < $current; $n ++)
        $page_links[] = _paginate_link($n, $args);
    $page_links[] = '<span class="pagination current">' . $args['before_page_number'] . number_format_i18n($current) . $args['after_page_number'] . '</span>';
    for ($n = $current + 1; $n <= min($total - $end_size, $current + $mid_size); $n ++)
        $page_links[] = _paginate_link($n, $args);

    if ($current == $total - 1 - $end_size - $mid_size)
        $page_links[] = _paginate_link($current + $mid_size + 1, $args);
    elseif ($current < $total - 1 - $end_size - $mid_size)
        $page_links[] = '<span class="pagination dots">' . __('&hellip;') . '</span>';

    if ($tenth_size && $current <= $total - 9 - $end_size) {
        for ($n = $current + 10; $n <= min($total - $end_size, $current + 9 + $tenth_size); $n ++)
            $page_links[] = _paginate_link($n, $args);

        if ($current == $total - 10 - $tenth_size - $end_size)
            $page_links[] = _paginate_link($current + 10 + $tenth_size, $args);
        elseif ($current < $total - 10 - $tenth_size - $end_size)
            $page_links[] = '<span class="pagination dots">' . __('&hellip;') . '</span>';
    }

    if ($current <= $total - 1) {
        for ($n = $total - $end_size + 1; $n <= $total; $n ++)
            $page_links[] = _paginate_link($n, $args);
        if ($args['prev_next'] && $current < $total)
            $page_links[] = _paginate_link($current + 1, $args, 'next', $args['next_text']);
    }

    switch ($args['type']) {
        case 'array':
            return $page_links;

        case 'list':
            $r .= "<ul class='pagination'>\n\t<li>";
            $r .= join("</li>\n\t<li>", $page_links);
            $r .= "</li>\n</ul>\n";
            break;

        default:
            $r = join("\n", $page_links);
            break;
    }
    return $r;
}

/**
 * Enqueues or directly prints a stylesheet link to the specified CSS file.
 *
 * "Intelligently" decides to enqueue or to print the CSS file. If the
 * 'gb_print_styles' action has *not* yet been called, the CSS file will be
 * enqueued. If the gb_print_styles action *has* been called, the CSS link will
 * be printed. Printing may be forced by passing true as the $force_echo
 * (second) parameter.
 *
 * @since 3.0.0
 *
 * @param string $file
 *            Optional. Style handle name or file name (without ".css" extension) relative
 *            to gb-admin/. Defaults to 'gb-admin'.
 * @param bool $force_echo
 *            Optional. Force the stylesheet link to be printed rather than enqueued.
 */
function gb_admin_css($file = 'gb-admin', $force_echo = false)
{
    if (gb_styles()->query($file)) {
        if ($force_echo || GB_Hooks::did_action('gb_print_styles')) // we already printed the style queue. Print this one immediately
            gb_print_styles($file);
        else // Add to style queue
            gb_enqueue_style($file);
    }
}

/**
 * Private helper function for checked, selected, and disabled.
 *
 * Compares the first two arguments and if identical marks as $type
 *
 * @since 3.0.0
 * @access private
 *
 * @param mixed $helper
 *            One of the values to compare
 * @param mixed $current
 *            (true) The other value to compare if not just true
 * @param bool $echo
 *            Whether to echo or just return the string
 * @param string $type
 *            The type of checked|selected|disabled we are doing
 * @return string html attribute or empty string
 */
function __checked_selected_helper($helper, $current, $echo, $type)
{
    if ((string) $helper === (string) $current)
        $result = " $type='$type'";
    else
        $result = '';

    if ($echo)
        echo $result;

    return $result;
}

/**
 * Outputs the html checked attribute.
 *
 * Compares the first two arguments and if identical marks as checked
 *
 * @since 3.0.0
 *
 * @param mixed $checked
 *            One of the values to compare
 * @param mixed $current
 *            (true) The other value to compare if not just true
 * @param bool $echo
 *            Whether to echo or just return the string
 * @return string html attribute or empty string
 */
function checked($checked, $current = true, $echo = true)
{
    return __checked_selected_helper($checked, $current, $echo, 'checked');
}

/**
 * Outputs the html selected attribute.
 *
 * Compares the first two arguments and if identical marks as selected
 *
 * @since 3.0.0
 *
 * @param mixed $selected
 *            One of the values to compare
 * @param mixed $current
 *            (true) The other value to compare if not just true
 * @param bool $echo
 *            Whether to echo or just return the string
 * @return string html attribute or empty string
 */
function selected($selected, $current = true, $echo = true)
{
    return __checked_selected_helper($selected, $current, $echo, 'selected');
}

/**
 * Outputs the html disabled attribute.
 *
 * Compares the first two arguments and if identical marks as disabled
 *
 * @since 3.0.0
 *
 * @param mixed $disabled
 *            One of the values to compare
 * @param mixed $current
 *            (true) The other value to compare if not just true
 * @param bool $echo
 *            Whether to echo or just return the string
 * @return string html attribute or empty string
 */
function disabled($disabled, $current = true, $echo = true)
{
    return __checked_selected_helper($disabled, $current, $echo, 'disabled');
}

/**
 * Returns the Log In URL.
 *
 * Returns the URL that allows the user to log in to the site.
 *
 * @since 3.0.0
 *
 * @param string $redirect
 *            Path to redirect to on login.
 * @param bool $force_reauth
 *            Whether to force reauthorization, even if a cookie is present. Default is false.
 * @return string A log in URL.
 */
function gb_login_url($redirect = '', $force_reauth = false)
{
    $login_url = site_url('gb-login.php', 'login');

    if (! empty($redirect))
        $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);

    if ($force_reauth)
        $login_url = add_query_arg('reauth', '1', $login_url);

    /**
     * Filter the login URL.
     *
     * @since 3.0.0
     *
     * @param string $login_url
     *            The login URL.
     * @param string $redirect
     *            The path to redirect to on login, if supplied.
     */
    return GB_Hooks::apply_filters('login_url', $login_url, $redirect);
}

/**
 * Returns the Log Out URL.
 *
 * Returns the URL that allows the user to log out of the site.
 *
 * @since 3.0.0
 *
 * @param string $redirect
 *            Path to redirect to on logout.
 * @return string A log out URL.
 */
function gb_logout_url($redirect = '')
{
    $args = array(
        'action' => 'logout'
    );
    if (! empty($redirect)) {
        $args['redirect_to'] = urlencode($redirect);
    }

    $logout_url = add_query_arg($args, site_url('gb-login.php', 'login'));
    $logout_url = gb_nonce_url($logout_url, 'log-out');

    /**
     * Filter the logout URL.
     *
     * @since 3.0.0
     *
     * @param string $logout_url
     *            The Log Out URL.
     * @param string $redirect
     *            Path to redirect to on logout.
     */
    return GB_Hooks::apply_filters('logout_url', $logout_url, $redirect);
}

/**
 * Returns the user registration URL.
 *
 * Returns the URL that allows the user to register on the site.
 *
 * @since 3.0.0
 *
 * @return string User registration URL.
 */
function gb_registration_url()
{
    /**
     * Filter the user registration URL.
     *
     * @since 3.0.0
     *
     * @param string $register
     *            The user registration URL.
     */
    return GB_Hooks::apply_filters('register_url', site_url('gb-login.php?action=register', 'login'));
}
