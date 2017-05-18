<?php
/**
 * GeniBase Upgrade API
 *
 * Most of the functions are pluggable and can be overwritten
 *
 * @package GeniBase
 * @subpackage Administration
 */

/**
 * Include user install customize script.
 */
if (file_exists(GB_CONTENT_DIR . '/install.php'))
    require (GB_CONTENT_DIR . '/install.php');

/**
 * GeniBase Administration API
 */
    // require_once(BASE_DIR . '/gb-admin/includes/admin.php');

/**
 * GeniBase Schema API
 */
require_once (GB_ADMIN_DIR . '/includes/schema.php');

if (! function_exists('gb_install')) :

    /**
     * Installs the blog
     *
     * {@internal Missing Long Description}}
     *
     * @since 3.0.0
     *
     * @param string $site_title
     *            Blog title.
     * @param string $user_email
     *            User's email.
     * @param bool $public
     *            Whether site is public.
     * @param string $user_password
     *            Optional. User's chosen password. Will default to a random password.
     * @param string $language
     *            Optional. Language chosen.
     * @return array Array keys 'url', 'user_id', 'password', 'password_message'.
     */
    function gb_install($site_title, $user_email, $public, $user_password = '', $language = '')
    {
        gb_check_mysql_version();
        gb_cache_flush();
        make_db_current_silent();
        populate_options();
        populate_roles();

        GB_Options::update('site_title', $site_title);
        GB_Options::update('admin_email', $user_email);
        GB_Options::update('site_public', $public);

        if ($language)
            GB_Options::update('GB_LANG', $language);

        $guessurl = gb_guess_url();

        GB_Options::update('site_url', $guessurl);

        /*
         * Create default user. If the user already exists, the user tables are
         * being shared among sites. Just set the role in that case.
         */
        $user_id = gb_user_email_exists($user_email);
        $user_password = trim($user_password);
        $email_password = false;
        $user_name = _x('Administrator', 'User role');
        if (! $user_id && empty($user_password)) {
            $user_password = gb_generate_password(12, false);
            $message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
            $user_id = gb_create_user($user_email, $user_password, $user_name);
            GB_User::update_meta($user_id, 'default_password_nag', true);
            $email_password = true;
        } elseif (! $user_id) {
            // Password has been provided
            $message = '<em>' . __('Your chosen password.') . '</em>';
            $user_id = gb_create_user($user_email, $user_password, $user_name);
            GB_User::update_meta($user_id, 'default_password_nag', false);
        } else {
            $message = __('User already exists. Password inherited.');
        }

        $user = new GB_User($user_id);
        $user->set_role('administrator');

        gb_install_defaults($user_id);

        // TODO: Rewrite
        // GB_Rewrite::flush_rules();

        gb_new_blog_notification($site_title, $guessurl . '/', $user_id, ($email_password ? $user_password : __('The password you chose during the install.')));

        gb_cache_flush();

        /**
         * Fires after a site is fully installed.
         *
         * @since 3.0.0
         *
         * @param User $user
         *            The site owner.
         */
        GB_Hooks::do_action('gb_install', $user);

        return array(
            'url' => $guessurl,
            'user_id' => $user_id,
            'password' => $user_password,
            'password_message' => $message
        );
    }


endif;

if (! function_exists('gb_install_defaults')) :

    /**
     * {@internal Missing Short Description}}
     *
     * {@internal Missing Long Description}}
     *
     * @since 3.0.0
     *
     * @param int $user_id
     *            User ID.
     */
    function gb_install_defaults($user_id)
    {
        GB_User::update_meta($user_id, 'show_welcome_panel', 1);
    }


endif;

if (! function_exists('gb_new_blog_notification')) :

    /**
     * {@internal Missing Short Description}}
     *
     * {@internal Missing Long Description}}
     *
     * @since 3.0.0
     *
     * @param string $site_title
     *            Blog title.
     * @param string $site_url
     *            Blog url.
     * @param int $user_id
     *            User ID.
     * @param string $password
     *            User's Password.
     */
    function gb_new_blog_notification($site_title, $site_url, $user_id, $password)
    {
        $user = new GB_User($user_id);
        $email = $user->user_email;
        $login_url = gb_login_url();
        $message = sprintf(__("Your new GeniBase site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

User login: %2\$s
Password: %3\$s
Log in here: %4\$s

We hope you enjoy your new site. Thanks!

--The GeniBase Team
"), $site_url, $email, $password, $login_url);

        // TODO: Remove this?
        // echo "<hr/><pre>\n".print_r(compact('message'), true)."</pre><hr/>";
        // die;
        @gb_mail($email, __('New GeniBase Site'), $message);
    }


endif;

if (! function_exists('gb_upgrade')) :

    /**
     * Run GeniBase Upgrade functions.
     *
     * {@internal Missing Long Description}}
     *
     * @since 3.0.0
     *
     * @return null
     */
    function gb_upgrade()
    {
        global $gb_current_db_version, $gb_db_version;

        $gb_current_db_version = __get_option('db_version');

        // We are up-to-date. Nothing to do.
        if ($gb_db_version == $gb_current_db_version)
            return;

        if (! is_blog_installed())
            return;

        gb_check_mysql_version();
        gb_cache_flush();
        pre_schema_upgrade();
        make_db_current_silent();
        upgrade_all();
        if (is_multisite() && is_main_site())
            upgrade_network();
        gb_cache_flush();

        /**
         * Fires after a site is fully upgraded.
         *
         * @since 3.0.0
         *
         * @param int $gb_db_version
         *            The new $gb_db_version.
         * @param int $gb_current_db_version
         *            The old (current) $gb_db_version.
         */
        GB_Hooks::do_action('gb_upgrade', $gb_db_version, $gb_current_db_version);
    }


endif;

/**
 * Functions to be called in install and upgrade scripts.
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 */
function upgrade_all()
{
    global $gb_current_db_version, $gb_db_version;
    $gb_current_db_version = __get_option('db_version');

    // We are up-to-date. Nothing to do.
    if ($gb_db_version == $gb_current_db_version)
        return;

        // If the version is not set in the DB, try to guess the version.
    if (empty($gb_current_db_version)) {
        $gb_current_db_version = 0;

        // If the template option exists, we have 1.5.
        $template = __get_option('template');
        if (! empty($template))
            $gb_current_db_version = 2541;
    }

    if ($gb_current_db_version < 6039)
        upgrade_230_options_table();

    populate_options();

    if ($gb_current_db_version < 2541) {
        upgrade_100();
        upgrade_101();
        upgrade_110();
        upgrade_130();
    }

    if ($gb_current_db_version < 3308)
        upgrade_160();

    if ($gb_current_db_version < 4772)
        upgrade_210();

    if ($gb_current_db_version < 4351)
        upgrade_old_slugs();

    if ($gb_current_db_version < 5539)
        upgrade_230();

    if ($gb_current_db_version < 6124)
        upgrade_230_old_tables();

    if ($gb_current_db_version < 7499)
        upgrade_250();

    if ($gb_current_db_version < 7935)
        upgrade_252();

    if ($gb_current_db_version < 8201)
        upgrade_260();

    if ($gb_current_db_version < 8989)
        upgrade_270();

    if ($gb_current_db_version < 10360)
        upgrade_280();

    if ($gb_current_db_version < 11958)
        upgrade_290();

    if ($gb_current_db_version < 15260)
        upgrade_300();

    if ($gb_current_db_version < 19389)
        upgrade_330();

    if ($gb_current_db_version < 20080)
        upgrade_340();

    if ($gb_current_db_version < 22422)
        upgrade_350();

    if ($gb_current_db_version < 25824)
        upgrade_370();

    if ($gb_current_db_version < 26148)
        upgrade_372();

    if ($gb_current_db_version < 26691)
        upgrade_380();

    if ($gb_current_db_version < 29630)
        upgrade_400();

    maybe_disable_link_manager();

    maybe_disable_automattic_widgets();

    update_option('db_version', $gb_db_version);
    update_option('db_upgraded', true);
}

/**
 * Execute changes made in GeniBase 1.0.
 *
 * @since 3.0.0
 */
function upgrade_100()
{
    // Get the title and ID of every post, post_name to check if it already has a value
    $posts = gbdb()->get_results("SELECT ID, post_title, post_name FROM gbdb()->posts WHERE post_name = ''");
    if ($posts) {
        foreach ($posts as $post) {
            if ('' == $post->post_name) {
                $newtitle = sanitize_title($post->post_title);
                gbdb()->query(gbdb()->prepare("UPDATE gbdb()->posts SET post_name = %s WHERE ID = %d", $newtitle, $post->ID));
            }
        }
    }

    $categories = gbdb()->get_results("SELECT cat_ID, cat_name, category_nicename FROM gbdb()->categories");
    foreach ($categories as $category) {
        if ('' == $category->category_nicename) {
            $newtitle = sanitize_title($category->cat_name);
            gbdb()->update(gbdb()->categories, array(
                'category_nicename' => $newtitle
            ), array(
                'cat_ID' => $category->cat_ID
            ));
        }
    }

    $sql = "UPDATE gbdb()->options
		SET option_value = REPLACE(option_value, 'gb-links/links-images/', 'gb-images/links/')
		WHERE option_name LIKE %s
		AND option_value LIKE %s";
    gbdb()->query(gbdb()->prepare($sql, gbdb()->esc_like('links_rating_image') . '%', gbdb()->esc_like('gb-links/links-images/') . '%'));

    $done_ids = gbdb()->get_results("SELECT DISTINCT post_id FROM gbdb()->post2cat");
    if ($done_ids) :
        foreach ($done_ids as $done_id) :
            $done_posts[] = $done_id->post_id;
        endforeach
        ;
        $catwhere = ' AND ID NOT IN (' . implode(',', $done_posts) . ')';
     else :
        $catwhere = '';
    endif;

    $allposts = gbdb()->get_results("SELECT ID, post_category FROM gbdb()->posts WHERE post_category != '0' $catwhere");
    if ($allposts) :
        foreach ($allposts as $post) {
            // Check to see if it's already been imported
            $cat = gbdb()->get_row(gbdb()->prepare("SELECT * FROM gbdb()->post2cat WHERE post_id = %d AND category_id = %d", $post->ID, $post->post_category));
            if (! $cat && 0 != $post->post_category) { // If there's no result
                gbdb()->insert(gbdb()->post2cat, array(
                    'post_id' => $post->ID,
                    'category_id' => $post->post_category
                ));
            }
        }


	endif;
}

/**
 * Execute changes made in GeniBase 1.0.1.
 *
 * @since 3.0.0
 */
function upgrade_101()
{
    // Clean up indices, add a few
    add_clean_index(gbdb()->posts, 'post_name');
    add_clean_index(gbdb()->posts, 'post_status');
    add_clean_index(gbdb()->categories, 'category_nicename');
    add_clean_index(gbdb()->comments, 'comment_approved');
    add_clean_index(gbdb()->comments, 'comment_post_ID');
    add_clean_index(gbdb()->links, 'link_category');
    add_clean_index(gbdb()->links, 'link_visible');
}

/**
 * Execute changes made in GeniBase 1.2.
 *
 * @since 3.0.0
 */
function upgrade_110()
{
    // Set user_nicename.
    $users = gbdb()->get_results("SELECT ID, user_nickname, user_nicename FROM gbdb()->users");
    foreach ($users as $user) {
        if ('' == $user->user_nicename) {
            $newname = sanitize_title($user->user_nickname);
            gbdb()->update(gbdb()->users, array(
                'user_nicename' => $newname
            ), array(
                'ID' => $user->ID
            ));
        }
    }

    $users = gbdb()->get_results("SELECT ID, user_pass from gbdb()->users");
    foreach ($users as $row) {
        if (! preg_match('/^[A-Fa-f0-9]{32}$/', $row->user_pass)) {
            gbdb()->update(gbdb()->users, array(
                'user_pass' => md5($row->user_pass)
            ), array(
                'ID' => $row->ID
            ));
        }
    }

    // Get the GMT offset, we'll use that later on
    $all_options = get_alloptions_110();

    $time_difference = $all_options->time_difference;

    $server_time = time() + date('Z');
    $weblogger_time = $server_time + $time_difference * HOUR_IN_SECONDS;
    $gmt_time = time();

    $diff_gmt_server = ($gmt_time - $server_time) / HOUR_IN_SECONDS;
    $diff_weblogger_server = ($weblogger_time - $server_time) / HOUR_IN_SECONDS;
    $diff_gmt_weblogger = $diff_gmt_server - $diff_weblogger_server;
    $gmt_offset = - $diff_gmt_weblogger;

    // Add a gmt_offset option, with value $gmt_offset
    add_option('gmt_offset', $gmt_offset);

    // Check if we already set the GMT fields (if we did, then
    // MAX(post_date_gmt) can't be '0000-00-00 00:00:00'
    // <michel_v> I just slapped myself silly for not thinking about it earlier
    $got_gmt_fields = ! (gbdb()->get_var("SELECT MAX(post_date_gmt) FROM gbdb()->posts") == '0000-00-00 00:00:00');

    if (! $got_gmt_fields) {

        // Add or subtract time to all dates, to get GMT dates
        $add_hours = intval($diff_gmt_weblogger);
        $add_minutes = intval(60 * ($diff_gmt_weblogger - $add_hours));
        gbdb()->query("UPDATE gbdb()->posts SET post_date_gmt = DATE_ADD(post_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
        gbdb()->query("UPDATE gbdb()->posts SET post_modified = post_date");
        gbdb()->query("UPDATE gbdb()->posts SET post_modified_gmt = DATE_ADD(post_modified, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE) WHERE post_modified != '0000-00-00 00:00:00'");
        gbdb()->query("UPDATE gbdb()->comments SET comment_date_gmt = DATE_ADD(comment_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
        gbdb()->query("UPDATE gbdb()->users SET user_registered = DATE_ADD(user_registered, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)");
    }
}

/**
 * Execute changes made in GeniBase 1.5.
 *
 * @since 3.0.0
 */
function upgrade_130()
{
    // Remove extraneous backslashes.
    $posts = gbdb()->get_results("SELECT ID, post_title, post_content, post_excerpt, guid, post_date, post_name, post_status, post_author FROM gbdb()->posts");
    if ($posts) {
        foreach ($posts as $post) {
            $post_content = addslashes(deslash($post->post_content));
            $post_title = addslashes(deslash($post->post_title));
            $post_excerpt = addslashes(deslash($post->post_excerpt));
            if (empty($post->guid))
                $guid = get_permalink($post->ID);
            else
                $guid = $post->guid;

            gbdb()->update(gbdb()->posts, compact('post_title', 'post_content', 'post_excerpt', 'guid'), array(
                'ID' => $post->ID
            ));
        }
    }

    // Remove extraneous backslashes.
    $comments = gbdb()->get_results("SELECT comment_ID, comment_author, comment_content FROM gbdb()->comments");
    if ($comments) {
        foreach ($comments as $comment) {
            $comment_content = deslash($comment->comment_content);
            $comment_author = deslash($comment->comment_author);

            gbdb()->update(gbdb()->comments, compact('comment_content', 'comment_author'), array(
                'comment_ID' => $comment->comment_ID
            ));
        }
    }

    // Remove extraneous backslashes.
    $links = gbdb()->get_results("SELECT link_id, link_name, link_description FROM gbdb()->links");
    if ($links) {
        foreach ($links as $link) {
            $link_name = deslash($link->link_name);
            $link_description = deslash($link->link_description);

            gbdb()->update(gbdb()->links, compact('link_name', 'link_description'), array(
                'link_id' => $link->link_id
            ));
        }
    }

    $active_plugins = __get_option('active_plugins');

    /*
     * If plugins are not stored in an array, they're stored in the old
     * newline separated format. Convert to new format.
     */
    if (! is_array($active_plugins)) {
        $active_plugins = explode("\n", trim($active_plugins));
        update_option('active_plugins', $active_plugins);
    }

    // Obsolete tables
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'optionvalues');
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'optiontypes');
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'optiongroups');
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'optiongroup_options');

    // Update comments table to use comment_type
    gbdb()->query("UPDATE gbdb()->comments SET comment_type='trackback', comment_content = REPLACE(comment_content, '<trackback />', '') WHERE comment_content LIKE '<trackback />%'");
    gbdb()->query("UPDATE gbdb()->comments SET comment_type='pingback', comment_content = REPLACE(comment_content, '<pingback />', '') WHERE comment_content LIKE '<pingback />%'");

    // Some versions have multiple duplicate option_name rows with the same values
    $options = gbdb()->get_results("SELECT option_name, COUNT(option_name) AS dupes FROM `gbdb()->options` GROUP BY option_name");
    foreach ($options as $option) {
        if (1 != $option->dupes) { // Could this be done in the query?
            $limit = $option->dupes - 1;
            $dupe_ids = gbdb()->get_col(gbdb()->prepare("SELECT option_id FROM gbdb()->options WHERE option_name = %s LIMIT %d", $option->option_name, $limit));
            if ($dupe_ids) {
                $dupe_ids = join($dupe_ids, ',');
                gbdb()->query("DELETE FROM gbdb()->options WHERE option_id IN ($dupe_ids)");
            }
        }
    }

    make_site_theme();
}

/**
 * Execute changes made in GeniBase 2.0.
 *
 * @since 3.0.0
 */
function upgrade_160()
{
    global $gb_current_db_version;

    populate_roles_160();

    $users = gbdb()->get_results("SELECT * FROM gbdb()->users");
    foreach ($users as $user) :
        if (! empty($user->user_firstname))
            update_user_meta($user->ID, 'first_name', gb_slash($user->user_firstname));
        if (! empty($user->user_lastname))
            update_user_meta($user->ID, 'last_name', gb_slash($user->user_lastname));
        if (! empty($user->user_nickname))
            update_user_meta($user->ID, 'nickname', gb_slash($user->user_nickname));
        if (! empty($user->user_level))
            update_user_meta($user->ID, gbdb()->prefix . 'user_level', $user->user_level);
        if (! empty($user->user_icq))
            update_user_meta($user->ID, 'icq', gb_slash($user->user_icq));
        if (! empty($user->user_aim))
            update_user_meta($user->ID, 'aim', gb_slash($user->user_aim));
        if (! empty($user->user_msn))
            update_user_meta($user->ID, 'msn', gb_slash($user->user_msn));
        if (! empty($user->user_yim))
            update_user_meta($user->ID, 'yim', gb_slash($user->user_icq));
        if (! empty($user->user_description))
            update_user_meta($user->ID, 'description', gb_slash($user->user_description));

        if (isset($user->user_idmode)) :
            $idmode = $user->user_idmode;
            if ($idmode == 'nickname')
                $id = $user->user_nickname;
            if ($idmode == 'login')
                $id = $user->user_email;
            if ($idmode == 'firstname')
                $id = $user->user_firstname;
            if ($idmode == 'lastname')
                $id = $user->user_lastname;
            if ($idmode == 'namefl')
                $id = $user->user_firstname . ' ' . $user->user_lastname;
            if ($idmode == 'namelf')
                $id = $user->user_lastname . ' ' . $user->user_firstname;
            if (! $idmode)
                $id = $user->user_nickname;
            gbdb()->update(gbdb()->users, array(
                'display_name' => $id
            ), array(
                'ID' => $user->ID
            ));


		endif;

        // FIXME: RESET_CAPS is temporary code to reset roles and caps if flag is set.
        $caps = get_user_meta($user->ID, gbdb()->prefix . 'capabilities');
        if (empty($caps) || defined('RESET_CAPS')) {
            $level = get_user_meta($user->ID, gbdb()->prefix . 'user_level', true);
            $role = translate_level_to_role($level);
            update_user_meta($user->ID, gbdb()->prefix . 'capabilities', array(
                $role => true
            ));
        }
    endforeach
    ;
    $old_user_fields = array(
        'user_firstname',
        'user_lastname',
        'user_icq',
        'user_aim',
        'user_msn',
        'user_yim',
        'user_idmode',
        'user_ip',
        'user_domain',
        'user_browser',
        'user_description',
        'user_nickname',
        'user_level'
    );
    gbdb()->hide_errors();
    foreach ($old_user_fields as $old)
        gbdb()->query("ALTER TABLE gbdb()->users DROP $old");
    gbdb()->show_errors();

    // Populate comment_count field of posts table.
    $comments = gbdb()->get_results("SELECT comment_post_ID, COUNT(*) as c FROM gbdb()->comments WHERE comment_approved = '1' GROUP BY comment_post_ID");
    if (is_array($comments))
        foreach ($comments as $comment)
            gbdb()->update(gbdb()->posts, array(
                'comment_count' => $comment->c
            ), array(
                'ID' => $comment->comment_post_ID
            ));

        /*
     * Some alpha versions used a post status of object instead of attachment
     * and put the mime type in post_type instead of post_mime_type.
     */
    if ($gb_current_db_version > 2541 && $gb_current_db_version <= 3091) {
        $objects = gbdb()->get_results("SELECT ID, post_type FROM gbdb()->posts WHERE post_status = 'object'");
        foreach ($objects as $object) {
            gbdb()->update(gbdb()->posts, array(
                'post_status' => 'attachment',
                'post_mime_type' => $object->post_type,
                'post_type' => ''
            ), array(
                'ID' => $object->ID
            ));

            $meta = get_post_meta($object->ID, 'imagedata', true);
            if (! empty($meta['file']))
                update_attached_file($object->ID, $meta['file']);
        }
    }
}

/**
 * Execute changes made in GeniBase 2.1.
 *
 * @since 3.0.0
 */
function upgrade_210()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 3506) {
        // Update status and type.
        $posts = gbdb()->get_results("SELECT ID, post_status FROM gbdb()->posts");

        if (! empty($posts))
            foreach ($posts as $post) {
                $status = $post->post_status;
                $type = 'post';

                if ('static' == $status) {
                    $status = 'publish';
                    $type = 'page';
                } else
                    if ('attachment' == $status) {
                        $status = 'inherit';
                        $type = 'attachment';
                    }

                gbdb()->query(gbdb()->prepare("UPDATE gbdb()->posts SET post_status = %s, post_type = %s WHERE ID = %d", $status, $type, $post->ID));
            }
    }

    if ($gb_current_db_version < 3845) {
        populate_roles_210();
    }

    if ($gb_current_db_version < 3531) {
        // Give future posts a post_status of future.
        $now = gmdate('Y-m-d H:i:59');
        gbdb()->query("UPDATE gbdb()->posts SET post_status = 'future' WHERE post_status = 'publish' AND post_date_gmt > '$now'");

        $posts = gbdb()->get_results("SELECT ID, post_date FROM gbdb()->posts WHERE post_status ='future'");
        if (! empty($posts))
            foreach ($posts as $post)
                gb_schedule_single_event(mysql2date('U', $post->post_date, false), 'publish_future_post', array(
                    $post->ID
                ));
    }
}

/**
 * Execute changes made in GeniBase 2.3.
 *
 * @since 3.0.0
 */
function upgrade_230()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 5200) {
        populate_roles_230();
    }

    // Convert categories to terms.
    $tt_ids = array();
    $have_tags = false;
    $categories = gbdb()->get_results("SELECT * FROM gbdb()->categories ORDER BY cat_ID");
    foreach ($categories as $category) {
        $term_id = (int) $category->cat_ID;
        $name = $category->cat_name;
        $description = $category->category_description;
        $slug = $category->category_nicename;
        $parent = $category->category_parent;
        $term_group = 0;

        // Associate terms with the same slug in a term group and make slugs unique.
        if ($exists = gbdb()->get_results(gbdb()->prepare("SELECT term_id, term_group FROM gbdb()->terms WHERE slug = %s", $slug))) {
            $term_group = $exists[0]->term_group;
            $id = $exists[0]->term_id;
            $num = 2;
            do {
                $alt_slug = $slug . "-$num";
                $num ++;
                $slug_check = gbdb()->get_var(gbdb()->prepare("SELECT slug FROM gbdb()->terms WHERE slug = %s", $alt_slug));
            } while ($slug_check);

            $slug = $alt_slug;

            if (empty($term_group)) {
                $term_group = gbdb()->get_var("SELECT MAX(term_group) FROM gbdb()->terms GROUP BY term_group") + 1;
                gbdb()->query(gbdb()->prepare("UPDATE gbdb()->terms SET term_group = %d WHERE term_id = %d", $term_group, $id));
            }
        }

        gbdb()->query(gbdb()->prepare("INSERT INTO gbdb()->terms (term_id, name, slug, term_group) VALUES
		(%d, %s, %s, %d)", $term_id, $name, $slug, $term_group));

        $count = 0;
        if (! empty($category->category_count)) {
            $count = (int) $category->category_count;
            $taxonomy = 'category';
            gbdb()->query(gbdb()->prepare("INSERT INTO gbdb()->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count));
            $tt_ids[$term_id][$taxonomy] = (int) gbdb()->insert_id;
        }

        if (! empty($category->link_count)) {
            $count = (int) $category->link_count;
            $taxonomy = 'link_category';
            gbdb()->query(gbdb()->prepare("INSERT INTO gbdb()->term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ( %d, %s, %s, %d, %d)", $term_id, $taxonomy, $description, $parent, $count));
            $tt_ids[$term_id][$taxonomy] = (int) gbdb()->insert_id;
        }

        if (! empty($category->tag_count)) {
            $have_tags = true;
            $count = (int) $category->tag_count;
            $taxonomy = 'post_tag';
            gbdb()->insert(gbdb()->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count'));
            $tt_ids[$term_id][$taxonomy] = (int) gbdb()->insert_id;
        }

        if (empty($count)) {
            $count = 0;
            $taxonomy = 'category';
            gbdb()->insert(gbdb()->term_taxonomy, compact('term_id', 'taxonomy', 'description', 'parent', 'count'));
            $tt_ids[$term_id][$taxonomy] = (int) gbdb()->insert_id;
        }
    }

    $select = 'post_id, category_id';
    if ($have_tags)
        $select .= ', rel_type';

    $posts = gbdb()->get_results("SELECT $select FROM gbdb()->post2cat GROUP BY post_id, category_id");
    foreach ($posts as $post) {
        $post_id = (int) $post->post_id;
        $term_id = (int) $post->category_id;
        $taxonomy = 'category';
        if (! empty($post->rel_type) && 'tag' == $post->rel_type)
            $taxonomy = 'tag';
        $tt_id = $tt_ids[$term_id][$taxonomy];
        if (empty($tt_id))
            continue;

        gbdb()->insert(gbdb()->term_relationships, array(
            'object_id' => $post_id,
            'term_taxonomy_id' => $tt_id
        ));
    }

    // < 3570 we used linkcategories. >= 3570 we used categories and link2cat.
    if ($gb_current_db_version < 3570) {
        /*
         * Create link_category terms for link categories. Create a map of link
         * cat IDs to link_category terms.
         */
        $link_cat_id_map = array();
        $default_link_cat = 0;
        $tt_ids = array();
        $link_cats = gbdb()->get_results("SELECT cat_id, cat_name FROM " . gbdb()->prefix . 'linkcategories');
        foreach ($link_cats as $category) {
            $cat_id = (int) $category->cat_id;
            $term_id = 0;
            $name = gb_slash($category->cat_name);
            $slug = sanitize_title($name);
            $term_group = 0;

            // Associate terms with the same slug in a term group and make slugs unique.
            if ($exists = gbdb()->get_results(gbdb()->prepare("SELECT term_id, term_group FROM gbdb()->terms WHERE slug = %s", $slug))) {
                $term_group = $exists[0]->term_group;
                $term_id = $exists[0]->term_id;
            }

            if (empty($term_id)) {
                gbdb()->insert(gbdb()->terms, compact('name', 'slug', 'term_group'));
                $term_id = (int) gbdb()->insert_id;
            }

            $link_cat_id_map[$cat_id] = $term_id;
            $default_link_cat = $term_id;

            gbdb()->insert(gbdb()->term_taxonomy, array(
                'term_id' => $term_id,
                'taxonomy' => 'link_category',
                'description' => '',
                'parent' => 0,
                'count' => 0
            ));
            $tt_ids[$term_id] = (int) gbdb()->insert_id;
        }

        // Associate links to cats.
        $links = gbdb()->get_results("SELECT link_id, link_category FROM gbdb()->links");
        if (! empty($links))
            foreach ($links as $link) {
                if (0 == $link->link_category)
                    continue;
                if (! isset($link_cat_id_map[$link->link_category]))
                    continue;
                $term_id = $link_cat_id_map[$link->link_category];
                $tt_id = $tt_ids[$term_id];
                if (empty($tt_id))
                    continue;

                gbdb()->insert(gbdb()->term_relationships, array(
                    'object_id' => $link->link_id,
                    'term_taxonomy_id' => $tt_id
                ));
            }

            // Set default to the last category we grabbed during the upgrade loop.
        update_option('default_link_category', $default_link_cat);
    } else {
        $links = gbdb()->get_results("SELECT link_id, category_id FROM gbdb()->link2cat GROUP BY link_id, category_id");
        foreach ($links as $link) {
            $link_id = (int) $link->link_id;
            $term_id = (int) $link->category_id;
            $taxonomy = 'link_category';
            $tt_id = $tt_ids[$term_id][$taxonomy];
            if (empty($tt_id))
                continue;
            gbdb()->insert(gbdb()->term_relationships, array(
                'object_id' => $link_id,
                'term_taxonomy_id' => $tt_id
            ));
        }
    }

    if ($gb_current_db_version < 4772) {
        // Obsolete linkcategories table
        gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'linkcategories');
    }

    // Recalculate all counts
    $terms = gbdb()->get_results("SELECT term_taxonomy_id, taxonomy FROM gbdb()->term_taxonomy");
    foreach ((array) $terms as $term) {
        if (('post_tag' == $term->taxonomy) || ('category' == $term->taxonomy))
            $count = gbdb()->get_var(gbdb()->prepare("SELECT COUNT(*) FROM gbdb()->term_relationships, gbdb()->posts WHERE gbdb()->posts.ID = gbdb()->term_relationships.object_id AND post_status = 'publish' AND post_type = 'post' AND term_taxonomy_id = %d", $term->term_taxonomy_id));
        else
            $count = gbdb()->get_var(gbdb()->prepare("SELECT COUNT(*) FROM gbdb()->term_relationships WHERE term_taxonomy_id = %d", $term->term_taxonomy_id));
        gbdb()->update(gbdb()->term_taxonomy, array(
            'count' => $count
        ), array(
            'term_taxonomy_id' => $term->term_taxonomy_id
        ));
    }
}

/**
 * Remove old options from the database.
 *
 * @since 3.0.0
 */
function upgrade_230_options_table()
{
    $old_options_fields = array(
        'option_can_override',
        'option_type',
        'option_width',
        'option_height',
        'option_description',
        'option_admin_level'
    );
    gbdb()->hide_errors();
    foreach ($old_options_fields as $old)
        gbdb()->query("ALTER TABLE gbdb()->options DROP $old");
    gbdb()->show_errors();
}

/**
 * Remove old categories, link2cat, and post2cat database tables.
 *
 * @since 3.0.0
 */
function upgrade_230_old_tables()
{
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'categories');
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'link2cat');
    gbdb()->query('DROP TABLE IF EXISTS ' . gbdb()->prefix . 'post2cat');
}

/**
 * Upgrade old slugs made in version 2.2.
 *
 * @since 3.0.0
 */
function upgrade_old_slugs()
{
    // Upgrade people who were using the Redirect Old Slugs plugin.
    gbdb()->query("UPDATE gbdb()->postmeta SET meta_key = '_gb_old_slug' WHERE meta_key = 'old_slug'");
}

/**
 * Execute changes made in GeniBase 2.8.
 *
 * @since 3.0.0
 */
function upgrade_280()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 10360)
        populate_roles_280();
    if (is_multisite()) {
        $start = 0;
        while ($rows = gbdb()->get_results("SELECT option_name, option_value FROM gbdb()->options ORDER BY option_id LIMIT $start, 20")) {
            foreach ($rows as $row) {
                $value = $row->option_value;
                if (! @unserialize($value))
                    $value = stripslashes($value);
                if ($value !== $row->option_value) {
                    update_option($row->option_name, $value);
                }
            }
            $start += 20;
        }
        refresh_blog_details(gbdb()->blogid);
    }
}

/**
 * Execute changes made in GeniBase 2.9.
 *
 * @since 3.0.0
 */
function upgrade_290()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 11958) {
        // Previously, setting depth to 1 would redundantly disable threading, but now 2 is the minimum depth to avoid confusion
        if (get_option('thread_comments_depth') == '1') {
            update_option('thread_comments_depth', 2);
            update_option('thread_comments', 0);
        }
    }
}

/**
 * Execute changes made in GeniBase 3.0.
 *
 * @since 3.0.0
 */
function upgrade_300()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 15093)
        populate_roles_300();

    if ($gb_current_db_version < 14139 && is_multisite() && is_main_site() && ! defined('MULTISITE') && get_site_option('siteurl') === false)
        add_site_option('siteurl', '');

        // 3.0 screen options key name changes.
    if (is_main_site() && ! defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
        $sql = "DELETE FROM gbdb()->usermeta
			WHERE meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key LIKE %s
			OR meta_key = 'manageedittagscolumnshidden'
			OR meta_key = 'managecategoriescolumnshidden'
			OR meta_key = 'manageedit-tagscolumnshidden'
			OR meta_key = 'manageeditcolumnshidden'
			OR meta_key = 'categories_per_page'
			OR meta_key = 'edit_tags_per_page'";
        $prefix = gbdb()->esc_like(gbdb()->base_prefix);
        gbdb()->query(gbdb()->prepare($sql, $prefix . '%' . gbdb()->esc_like('meta-box-hidden') . '%', $prefix . '%' . gbdb()->esc_like('closedpostboxes') . '%', $prefix . '%' . gbdb()->esc_like('manage-') . '%' . gbdb()->esc_like('-columns-hidden') . '%', $prefix . '%' . gbdb()->esc_like('meta-box-order') . '%', $prefix . '%' . gbdb()->esc_like('metaboxorder') . '%', $prefix . '%' . gbdb()->esc_like('screen_layout') . '%'));
    }
}

/**
 * Execute changes made in GeniBase 3.3.
 *
 * @since 3.0.0
 */
function upgrade_330()
{
    global $gb_current_db_version, $gb_registered_widgets, $sidebars_widgets;

    if ($gb_current_db_version < 19061 && is_main_site() && ! defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
        gbdb()->query("DELETE FROM gbdb()->usermeta WHERE meta_key IN ('show_admin_bar_admin', 'plugins_last_view')");
    }

    if ($gb_current_db_version >= 11548)
        return;

    $sidebars_widgets = get_option('sidebars_widgets', array());
    $_sidebars_widgets = array();

    if (isset($sidebars_widgets['gb_inactive_widgets']) || empty($sidebars_widgets))
        $sidebars_widgets['array_version'] = 3;
    elseif (! isset($sidebars_widgets['array_version']))
        $sidebars_widgets['array_version'] = 1;

    switch ($sidebars_widgets['array_version']) {
        case 1:
            foreach ((array) $sidebars_widgets as $index => $sidebar)
                if (is_array($sidebar))
                    foreach ((array) $sidebar as $i => $name) {
                        $id = strtolower($name);
                        if (isset($gb_registered_widgets[$id])) {
                            $_sidebars_widgets[$index][$i] = $id;
                            continue;
                        }
                        $id = sanitize_title($name);
                        if (isset($gb_registered_widgets[$id])) {
                            $_sidebars_widgets[$index][$i] = $id;
                            continue;
                        }

                        $found = false;

                        foreach ($gb_registered_widgets as $widget_id => $widget) {
                            if (strtolower($widget['name']) == strtolower($name)) {
                                $_sidebars_widgets[$index][$i] = $widget['id'];
                                $found = true;
                                break;
                            } elseif (sanitize_title($widget['name']) == sanitize_title($name)) {
                                $_sidebars_widgets[$index][$i] = $widget['id'];
                                $found = true;
                                break;
                            }
                        }

                        if ($found)
                            continue;

                        unset($_sidebars_widgets[$index][$i]);
                    }
            $_sidebars_widgets['array_version'] = 2;
            $sidebars_widgets = $_sidebars_widgets;
            unset($_sidebars_widgets);

        case 2:
            $sidebars_widgets = retrieve_widgets();
            $sidebars_widgets['array_version'] = 3;
            update_option('sidebars_widgets', $sidebars_widgets);
    }
}

/**
 * Execute changes made in GeniBase 3.4.
 *
 * @since 3.0.0
 */
function upgrade_340()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 19798) {
        gbdb()->hide_errors();
        gbdb()->query("ALTER TABLE gbdb()->options DROP COLUMN blog_id");
        gbdb()->show_errors();
    }

    if ($gb_current_db_version < 19799) {
        gbdb()->hide_errors();
        gbdb()->query("ALTER TABLE gbdb()->comments DROP INDEX comment_approved");
        gbdb()->show_errors();
    }

    if ($gb_current_db_version < 20022 && is_main_site() && ! defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
        gbdb()->query("DELETE FROM gbdb()->usermeta WHERE meta_key = 'themes_last_view'");
    }

    if ($gb_current_db_version < 20080) {
        if ('yes' == gbdb()->get_var("SELECT autoload FROM gbdb()->options WHERE option_name = 'uninstall_plugins'")) {
            $uninstall_plugins = get_option('uninstall_plugins');
            delete_option('uninstall_plugins');
            add_option('uninstall_plugins', $uninstall_plugins, null, 'no');
        }
    }
}

/**
 * Execute changes made in GeniBase 3.5.
 *
 * @since 3.0.0
 */
function upgrade_350()
{
    global $gb_current_db_version;

    if ($gb_current_db_version < 22006 && gbdb()->get_var("SELECT link_id FROM gbdb()->links LIMIT 1"))
        update_option('link_manager_enabled', 1); // Previously set to 0 by populate_options()

    if ($gb_current_db_version < 21811 && is_main_site() && ! defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
        $meta_keys = array();
        foreach (array_merge(get_post_types(), get_taxonomies()) as $name) {
            if (false !== strpos($name, '-'))
                $meta_keys[] = 'edit_' . str_replace('-', '_', $name) . '_per_page';
        }
        if ($meta_keys) {
            $meta_keys = implode("', '", $meta_keys);
            gbdb()->query("DELETE FROM gbdb()->usermeta WHERE meta_key IN ('$meta_keys')");
        }
    }

    if ($gb_current_db_version < 22422 && $term = get_term_by('slug', 'post-format-standard', 'post_format'))
        gb_delete_term($term->term_id, 'post_format');
}

/**
 * Execute changes made in GeniBase 3.7.
 *
 * @since 3.0.0
 */
function upgrade_370()
{
    global $gb_current_db_version;
    if ($gb_current_db_version < 25824)
        gb_clear_scheduled_hook('gb_auto_updates_maybe_update');
}

/**
 * Execute changes made in GeniBase 3.7.2.
 *
 * @since 3.0.0
 * @since 3.0.0
 */
function upgrade_372()
{
    global $gb_current_db_version;
    if ($gb_current_db_version < 26148)
        gb_clear_scheduled_hook('gb_maybe_auto_update');
}

/**
 * Execute changes made in GeniBase 3.8.0.
 *
 * @since 3.0.0
 */
function upgrade_380()
{
    global $gb_current_db_version;
    if ($gb_current_db_version < 26691) {
        deactivate_plugins(array(
            'mp6/mp6.php'
        ), true);
    }
}

/**
 * Execute changes made in GeniBase 4.0.0.
 *
 * @since 3.0.0
 */
function upgrade_400()
{
    global $gb_current_db_version;
    if ($gb_current_db_version < 29630) {
        if (! is_multisite() && false === get_option('WPLANG')) {
            if (defined('WPLANG') && ('' !== WPLANG) && in_array(WPLANG, get_available_languages())) {
                update_option('WPLANG', WPLANG);
            } else {
                update_option('WPLANG', '');
            }
        }
    }
}

/**
 * Execute network level changes
 *
 * @since 3.0.0
 */
function upgrade_network()
{
    global $gb_current_db_version;

    // Always.
    if (is_main_network()) {
        /*
         * Deletes all expired transients. The multi-table delete syntax is used
         * to delete the transient record from table a, and the corresponding
         * transient_timeout record from table b.
         */
        $time = time();
        $sql = "DELETE a, b FROM gbdb()->sitemeta a, gbdb()->sitemeta b
			WHERE a.meta_key LIKE %s
			AND a.meta_key NOT LIKE %s
			AND b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )
			AND b.meta_value < %d";
        gbdb()->query(gbdb()->prepare($sql, gbdb()->esc_like('_site_transient_') . '%', gbdb()->esc_like('_site_transient_timeout_') . '%', $time));
    }

    // 2.8.
    if ($gb_current_db_version < 11549) {
        $wpmu_sitewide_plugins = get_site_option('wpmu_sitewide_plugins');
        $active_sitewide_plugins = get_site_option('active_sitewide_plugins');
        if ($wpmu_sitewide_plugins) {
            if (! $active_sitewide_plugins)
                $sitewide_plugins = (array) $wpmu_sitewide_plugins;
            else
                $sitewide_plugins = array_merge((array) $active_sitewide_plugins, (array) $wpmu_sitewide_plugins);

            update_site_option('active_sitewide_plugins', $sitewide_plugins);
        }
        delete_site_option('wpmu_sitewide_plugins');
        delete_site_option('deactivated_sitewide_plugins');

        $start = 0;
        while ($rows = gbdb()->get_results("SELECT meta_key, meta_value FROM {gbdb()->sitemeta} ORDER BY meta_id LIMIT $start, 20")) {
            foreach ($rows as $row) {
                $value = $row->meta_value;
                if (! @unserialize($value))
                    $value = stripslashes($value);
                if ($value !== $row->meta_value) {
                    update_site_option($row->meta_key, $value);
                }
            }
            $start += 20;
        }
    }

    // 3.0
    if ($gb_current_db_version < 13576)
        update_site_option('global_terms_enabled', '1');

        // 3.3
    if ($gb_current_db_version < 19390)
        update_site_option('initial_db_version', $gb_current_db_version);

    if ($gb_current_db_version < 19470) {
        if (false === get_site_option('active_sitewide_plugins'))
            update_site_option('active_sitewide_plugins', array());
    }

    // 3.4
    if ($gb_current_db_version < 20148) {
        // 'allowedthemes' keys things by stylesheet. 'allowed_themes' keyed things by name.
        $allowedthemes = get_site_option('allowedthemes');
        $allowed_themes = get_site_option('allowed_themes');
        if (false === $allowedthemes && is_array($allowed_themes) && $allowed_themes) {
            $converted = array();
            $themes = gb_get_themes();
            foreach ($themes as $stylesheet => $theme_data) {
                if (isset($allowed_themes[$theme_data->get('Name')]))
                    $converted[$stylesheet] = true;
            }
            update_site_option('allowedthemes', $converted);
            delete_site_option('allowed_themes');
        }
    }

    // 3.5
    if ($gb_current_db_version < 21823)
        update_site_option('ms_files_rewriting', '1');

        // 3.5.2
    if ($gb_current_db_version < 24448) {
        $illegal_names = get_site_option('illegal_names');
        if (is_array($illegal_names) && count($illegal_names) === 1) {
            $illegal_name = reset($illegal_names);
            $illegal_names = explode(' ', $illegal_name);
            update_site_option('illegal_names', $illegal_names);
        }
    }
}

// The functions we use to actually do stuff

// General

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $table_name
 *            Database table name to create.
 * @param string $create_ddl
 *            SQL statement to create table.
 * @return bool If table already exists or was created by function.
 */
function maybe_create_table($table_name, $create_ddl)
{
    $query = gbdb()->prepare("SHOW TABLES LIKE %s", gbdb()->esc_like($table_name));

    if (gbdb()->get_var($query) == $table_name) {
        return true;
    }

    // Didn't find it try to create it..
    gbdb()->query($create_ddl);

    // We cannot directly tell that whether this succeeded!
    if (gbdb()->get_var($query) == $table_name) {
        return true;
    }
    return false;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $table
 *            Database table name.
 * @param string $index
 *            Index name to drop.
 * @return bool True, when finished.
 */
function drop_index($table, $index)
{
    gbdb()->hide_errors();
    gbdb()->query("ALTER TABLE `$table` DROP INDEX `$index`");
    // Now we need to take out all the extra ones we may have created
    for ($i = 0; $i < 25; $i ++) {
        gbdb()->query("ALTER TABLE `$table` DROP INDEX `{$index}_$i`");
    }
    gbdb()->show_errors();
    return true;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $table
 *            Database table name.
 * @param string $index
 *            Database table index column.
 * @return bool True, when done with execution.
 */
function add_clean_index($table, $index)
{
    drop_index($table, $index);
    gbdb()->query("ALTER TABLE `$table` ADD INDEX ( `$index` )");
    return true;
}

/**
 * * maybe_add_column()
 * * Add column to db table if it doesn't exist.
 * * Returns: true if already exists or on successful completion
 * * false on error
 */
function maybe_add_column($table_name, $column_name, $create_ddl)
{
    foreach (gbdb()->get_col("DESC $table_name", 0) as $column) {
        if ($column == $column_name) {
            return true;
        }
    }

    // Didn't find it try to create it.
    gbdb()->query($create_ddl);

    // We cannot directly tell that whether this succeeded!
    foreach (gbdb()->get_col("DESC $table_name", 0) as $column) {
        if ($column == $column_name) {
            return true;
        }
    }
    return false;
}

/**
 * Retrieve all options as it was for 1.2.
 *
 * @since 3.0.0
 *
 * @return stdClass List of options.
 */
function get_alloptions_110()
{
    $all_options = new \stdClass();
    if ($options = gbdb()->get_results("SELECT option_name, option_value FROM gbdb()->options")) {
        foreach ($options as $option) {
            if ('siteurl' == $option->option_name || 'home' == $option->option_name || 'category_base' == $option->option_name)
                $option->option_value = untrailingslashit($option->option_value);
            $all_options->{$option->option_name} = stripslashes($option->option_value);
        }
    }
    return $all_options;
}

/**
 * Version of get_option that is private to install/upgrade.
 *
 * @since 3.0.0
 * @access private
 *
 * @param string $setting
 *            Option name.
 * @return mixed
 */
function __get_option($setting)
{
    if ($setting == 'home' && defined('GB_HOME'))
        return untrailingslashit(GB_HOME);

    if ($setting == 'siteurl' && defined('GB_SITEURL'))
        return untrailingslashit(GB_SITEURL);

    $option = gbdb()->get_cell('SELECT option_value FROM ?_options WHERE section = "" AND option_name = ?option', [
        'option' => $setting
    ]);

    if ('home' == $setting && '' == $option)
        return __get_option('siteurl');

    if ('siteurl' == $setting || 'home' == $setting)
        $option = untrailingslashit($option);

    return maybe_unserialize($option);
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $content
 * @return string
 */
function deslash($content)
{
    // Note: \\\ inside a regex denotes a single backslash.

    /*
     * Replace one or more backslashes followed by a single quote with
     * a single quote.
     */
    $content = preg_replace("/\\\+'/", "'", $content);

    /*
     * Replace one or more backslashes followed by a double quote with
     * a double quote.
     */
    $content = preg_replace('/\\\+"/', '"', $content);

    // Replace one or more backslashes with one backslash.
    $content = preg_replace("/\\\+/", "\\", $content);

    return $content;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $queries
 * @param bool $execute
 * @return array
 */
function dbDelta($queries = '', $execute = true)
{
    if (in_array($queries, array(
        '',
        'all',
        'site',
        'global'
    ), true))
        $queries = gb_get_db_schema($queries);

        // Separate individual queries into an array
    if (! is_array($queries)) {
        $queries = explode(';', $queries);
        $queries = array_filter($queries);
    }

    /**
     * Filter the dbDelta SQL queries.
     *
     * @since 3.0.0
     *
     * @param array $queries
     *            An array of dbDelta SQL queries.
     */
    $queries = GB_Hooks::apply_filters('dbdelta_queries', $queries);

    $cqueries = array(); // Creation Queries
    $iqueries = array(); // Insertion Queries
    $for_update = array();

    // Create a tablename index for an array ($cqueries) of queries
    foreach ($queries as $qry) {
        if (preg_match("|CREATE\s+TABLE\s+([^ ]*)|usiS", $qry, $matches)) {
            $cqueries[trim($matches[1], '`')] = $qry;
            $for_update[$matches[1]] = 'Created table ' . trim(gbdb()->prepare_query($matches[1]), '`');
        } else
            if (preg_match("|CREATE\s+DATABASE\s+([^ ]*)|usiS", $qry, $matches)) {
                array_unshift($cqueries, $qry);
            } else
                if (preg_match("|INSERT\s+INTO\s+([^ ]*)|usiS", $qry, $matches)) {
                    $iqueries[] = $qry;
                } else
                    if (preg_match("|UPDATE\s+([^ ]*)|usiS", $qry, $matches)) {
                        $iqueries[] = $qry;
                    } else {
                        // Unrecognized query type
                    }
    }

    /**
     * Filter the dbDelta SQL queries for creating tables and/or databases.
     *
     * Queries filterable via this hook contain "CREATE TABLE" or "CREATE DATABASE".
     *
     * @since 3.0.0
     *
     * @param array $cqueries
     *            An array of dbDelta create SQL queries.
     */
    $cqueries = GB_Hooks::apply_filters('dbdelta_create_queries', $cqueries);

    /**
     * Filter the dbDelta SQL queries for inserting or updating.
     *
     * Queries filterable via this hook contain "INSERT INTO" or "UPDATE".
     *
     * @since 3.0.0
     *
     * @param array $iqueries
     *            An array of dbDelta insert or update SQL queries.
     */
    $iqueries = GB_Hooks::apply_filters('dbdelta_insert_queries', $iqueries);

    $global_tables = gb_get_tables('global');
    foreach ($cqueries as $table => $qry) {
        // Don't upgrade global tables if DO_NOT_UPGRADE_GLOBAL_TABLES is defined.
        if (in_array($table, $global_tables) && defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
            unset($cqueries[$table], $for_update[$table]);
            continue;
        }

        // Fetch the table column structure from the database
        $suppress = gbdb()->suppress_errors();
        $tablefields = gbdb()->get_table("DESCRIBE {$table}");
        gbdb()->suppress_errors($suppress);

        if (! $tablefields)
            continue;

            // Clear the field and index arrays.
        $cfields = $indices = array();

        // Get all of the field names in the query from between the parentheses.
        preg_match("|\((.*)\)|ms", $qry, $match2);
        $qryline = trim($match2[1]);

        // Separate field lines into an array.
        $flds = explode("\n", $qryline);

        // TODO: Remove this?
        // echo "<hr/><pre>\n".print_r(strtolower($table), true).":\n".print_r($cqueries, true)."</pre><hr/>";

        // For every field line specified in the query.
        foreach ($flds as $fld) {

            // Extract the field name.
            preg_match("|^([^ ]*)|", trim($fld), $fvals);
            $is_field = preg_match('/`/', $fvals[1]);
            $fieldname = trim($fvals[1], '`');

            // Verify the found field name.
            $validfield = true;
            if (! $is_field) {
                switch (strtolower($fieldname)) {
                    case '':
                    case 'primary':
                    case 'index':
                    case 'fulltext':
                    case 'unique':
                    case 'key':
                        $validfield = false;
                        $indices[] = trim(trim($fld), ", \n");
                        break;
                    case 'constraint':
                        $validfield = false;
                        // Ignore constraints
                        break;
                }
            }
            $fld = trim($fld);

            // If it's a valid field, add it to the field array.
            if ($validfield) {
                $cfields[strtolower($fieldname)] = trim($fld, ", \n");
            }
        }
        // TODO: Remove this?
        // echo "<hr/><pre>\n".print_r($cfields, true)."</pre><hr/>";
        // echo "<hr/><pre>\n".print_r($tablefields, true)."</pre><hr/>";
        // die;

        // For every field in the table.
        foreach ($tablefields as $tablefield) {

            // If the table field exists in the field array ...
            if (array_key_exists(strtolower($tablefield['Field']), $cfields)) {

                // Get the field type from the query.
                preg_match("|`?" . $tablefield['Field'] . "`? ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield['Field'])], $matches);
                $fieldtype = $matches[1];

                // Is actual field type different from the field type in query?
                if ($tablefield['Type'] != $fieldtype) {
                    // Add a query to change the column type
                    $cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN `{$tablefield['Field']}` " . $cfields[strtolower($tablefield['Field'])];
                    $table_full = trim(gbdb()->prepare_query($table), '`');
                    $for_update[$table . '.' . $tablefield['Field']] = "Changed type of {$table_full}.{$tablefield['Field']} from {$tablefield['Type']} to {$fieldtype}";
                }

                // Get the default value from the array
                // todo: Remove this?
                // echo "{$cfields[strtolower($tablefield->Field)]}<br>";
                if (preg_match("| DEFAULT '(.*?)'|i", $cfields[strtolower($tablefield['Field'])], $matches)) {
                    $default_value = $matches[1];
                    if ($tablefield['Default'] != $default_value) {
                        // Add a query to change the column's default value
                        $cqueries[] = "ALTER TABLE {$table} ALTER COLUMN `{$tablefield['Field']}` SET DEFAULT '{$default_value}'";
                        $table_full = trim(gbdb()->prepare_query($table), '`');
                        $for_update[$table . '.' . $tablefield['Field']] = "Changed default value of {$table_full}.{$tablefield['Field']} from {$tablefield['Default']} to {$default_value}";
                    }
                }

                // Remove the field from the array (so it's not added).
                unset($cfields[strtolower($tablefield['Field'])]);
            } else {
                // This field exists in the table, but not in the creation queries?
            }
        }

        // For every remaining field specified for the table.
        foreach ($cfields as $fieldname => $fielddef) {
            // Push a query line into $cqueries that adds the field to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
            $table_full = trim(gbdb()->prepare_query($table), '`');
            $for_update[$table . '.' . $fieldname] = 'Added column ' . $table_full . '.' . $fieldname;
        }

        // Index stuff goes here. Fetch the table index structure from the database.
        $tableindices = gbdb()->get_table("SHOW INDEX FROM {$table}");

        if ($tableindices) {
            // Clear the index array.
            unset($index_ary);

            // For every index in the table.
            foreach ($tableindices as $tableindex) {

                // Add the index to the index data array.
                $keyname = $tableindex['Key_name'];
                $index_ary[$keyname]['columns'][] = array(
                    'fieldname' => $tableindex['Column_name'],
                    'subpart' => $tableindex['Sub_part']
                );
                $index_ary[$keyname]['unique'] = ($tableindex['Non_unique'] == 0) ? true : false;
                $index_ary[$keyname]['fulltext'] = ($tableindex['Index_type'] == 'FULLTEXT') ? true : false;
            }

            // TODO: Remove this?
            // echo "<hr/><pre>\n".print_r($index_ary, true)."</pre><hr/>";
            // For each actual index in the index array.
            foreach ($index_ary as $index_name => $index_data) {

                // Build a create string to compare to the query.
                $index_string = '';
                if ($index_name == 'PRIMARY') {
                    $index_string .= 'PRIMARY ';
                } elseif ($index_data['unique']) {
                    $index_string .= 'UNIQUE ';
                } elseif ($index_data['fulltext']) {
                    $index_string .= 'FULLTEXT ';
                }
                $index_string .= 'KEY';
                if ($index_name != 'PRIMARY') {
                    $index_string .= ' `' . $index_name . '`';
                }
                $index_columns = '';

                // For each column in the index.
                foreach ($index_data['columns'] as $column_data) {
                    if ($index_columns != '')
                        $index_columns .= ',';

                        // Add the field to the column list string.
                    $index_columns .= '`' . $column_data['fieldname'] . '`';
                    if ($column_data['subpart'] != '') {
                        $index_columns .= '(' . $column_data['subpart'] . ')';
                    }
                }
                // Add the column list to the index create string.
                $index_string .= ' (' . $index_columns . ')';
                if (! (($aindex = array_search($index_string, $indices)) === false)) {
                    unset($indices[$aindex]);
                    // TODO: Remove this?
                    // echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br />Found index:".$index_string."</pre>\n";
                }
                // TODO: Remove this?
                // else echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br /><b>Did not find index:</b>".$index_string."<br />".print_r($indices, true)."</pre>\n";
            }
        }

        // For every remaining index specified for the table.
        foreach ((array) $indices as $index) {
            // Push a query line into $cqueries that adds the index to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD $index";
            $table_full = trim(gbdb()->prepare_query($table), '`');
            $for_update[] = 'Added index ' . $table_full . ' ' . $index;
        }

        // Remove the original table creation query from processing.
        unset($cqueries[$table], $for_update[$table]);
    }

    $allqueries = array_merge($cqueries, $iqueries);
    if ($execute) {
        foreach ($allqueries as $query) {
            // TODO: Remove this?
            // echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">".print_r(gbdb()->prepare_query($query), true)."</pre>\n";
            gbdb()->query($query);
        }
    }

    return $for_update;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 */
function make_db_current($tables = 'all')
{
    $alterations = dbDelta($tables);
    echo "<ol>\n";
    foreach ($alterations as $alteration)
        echo "<li>$alteration</li>\n";
    echo "</ol>\n";
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 */
function make_db_current_silent($tables = 'all')
{
    dbDelta($tables);
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $theme_name
 * @param string $template
 * @return bool
 */
function make_site_theme_from_oldschool($theme_name, $template)
{
    $home_path = get_home_path();
    $site_dir = GB_CONTENT_DIR . "/themes/$template";

    if (! file_exists("$home_path/index.php"))
        return false;

        /*
     * Copy files from the old locations to the site theme.
     * TODO: This does not copy arbitrary include dependencies. Only the standard WP files are copied.
     */
    $files = array(
        'index.php' => 'index.php',
        'gb-layout.css' => 'style.css',
        'gb-comments.php' => 'comments.php',
        'gb-comments-popup.php' => 'comments-popup.php'
    );

    foreach ($files as $oldfile => $newfile) {
        if ($oldfile == 'index.php')
            $oldpath = $home_path;
        else
            $oldpath = BASE_DIR;

            // Check to make sure it's not a new index.
        if ($oldfile == 'index.php') {
            $index = implode('', file("$oldpath/$oldfile"));
            if (strpos($index, 'GB_USE_THEMES') !== false) {
                if (! @copy(GB_CONTENT_DIR . '/themes/' . GB_DEFAULT_THEME . '/index.php', "$site_dir/$newfile"))
                    return false;

                    // Don't copy anything.
                continue;
            }
        }

        if (! @copy("$oldpath/$oldfile", "$site_dir/$newfile"))
            return false;

        chmod("$site_dir/$newfile", 0777);

        // Update the blog header include in each file.
        $lines = explode("\n", implode('', file("$site_dir/$newfile")));
        if ($lines) {
            $f = fopen("$site_dir/$newfile", 'w');

            foreach ($lines as $line) {
                if (preg_match('/require.*gb-blog-header/', $line))
                    $line = '//' . $line;

                    // Update stylesheet references.
                $line = str_replace("<?php echo __get_option('siteurl'); ?>/gb-layout.css", "<?php bloginfo('stylesheet_url'); ?>", $line);

                // Update comments template inclusion.
                $line = str_replace("<?php include(BASE_DIR . 'gb-comments.php'); ?>", "<?php comments_template(); ?>", $line);

                fwrite($f, "{$line}\n");
            }
            fclose($f);
        }
    }

    // Add a theme header.
    $header = "/*\nTheme Name: $theme_name\nTheme URI: " . __get_option('siteurl') . "\nDescription: A theme automatically created by the update.\nVersion: 1.0\nAuthor: Moi\n*/\n";

    $stylelines = file_get_contents("$site_dir/style.css");
    if ($stylelines) {
        $f = fopen("$site_dir/style.css", 'w');

        fwrite($f, $header);
        fwrite($f, $stylelines);
        fclose($f);
    }

    return true;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @param string $theme_name
 * @param string $template
 * @return null|false
 */
function make_site_theme_from_default($theme_name, $template)
{
    $site_dir = GB_CONTENT_DIR . "/themes/$template";
    $default_dir = GB_CONTENT_DIR . '/themes/' . GB_DEFAULT_THEME;

    // Copy files from the default theme to the site theme.
    // $files = array('index.php', 'comments.php', 'comments-popup.php', 'footer.php', 'header.php', 'sidebar.php', 'style.css');

    $theme_dir = @ opendir($default_dir);
    if ($theme_dir) {
        while (($theme_file = readdir($theme_dir)) !== false) {
            if (is_dir("$default_dir/$theme_file"))
                continue;
            if (! @copy("$default_dir/$theme_file", "$site_dir/$theme_file"))
                return;
            chmod("$site_dir/$theme_file", 0777);
        }
    }
    @closedir($theme_dir);

    // Rewrite the theme header.
    $stylelines = explode("\n", implode('', file("$site_dir/style.css")));
    if ($stylelines) {
        $f = fopen("$site_dir/style.css", 'w');

        foreach ($stylelines as $line) {
            if (strpos($line, 'Theme Name:') !== false)
                $line = 'Theme Name: ' . $theme_name;
            elseif (strpos($line, 'Theme URI:') !== false)
                $line = 'Theme URI: ' . __get_option('url');
            elseif (strpos($line, 'Description:') !== false)
                $line = 'Description: Your theme.';
            elseif (strpos($line, 'Version:') !== false)
                $line = 'Version: 1';
            elseif (strpos($line, 'Author:') !== false)
                $line = 'Author: You';
            fwrite($f, $line . "\n");
        }
        fclose($f);
    }

    // Copy the images.
    umask(0);
    if (! mkdir("$site_dir/images", 0777)) {
        return false;
    }

    $images_dir = @ opendir("$default_dir/images");
    if ($images_dir) {
        while (($image = readdir($images_dir)) !== false) {
            if (is_dir("$default_dir/images/$image"))
                continue;
            if (! @copy("$default_dir/images/$image", "$site_dir/images/$image"))
                return;
            chmod("$site_dir/images/$image", 0777);
        }
    }
    @closedir($images_dir);
}

// Create a site theme from the default theme.
/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 *
 * @return false|string
 */
function make_site_theme()
{
    // Name the theme after the blog.
    $theme_name = __get_option('blogname');
    $template = sanitize_title($theme_name);
    $site_dir = GB_CONTENT_DIR . "/themes/$template";

    // If the theme already exists, nothing to do.
    if (is_dir($site_dir)) {
        return false;
    }

    // We must be able to write to the themes dir.
    if (! is_writable(GB_CONTENT_DIR . "/themes")) {
        return false;
    }

    umask(0);
    if (! mkdir($site_dir, 0777)) {
        return false;
    }

    if (file_exists(BASE_DIR . 'gb-layout.css')) {
        if (! make_site_theme_from_oldschool($theme_name, $template)) {
            // TODO: rm -rf the site theme directory.
            return false;
        }
    } else {
        if (! make_site_theme_from_default($theme_name, $template))
            // TODO: rm -rf the site theme directory.
            return false;
    }

    // Make the new site theme active.
    $current_template = __get_option('template');
    if ($current_template == GB_DEFAULT_THEME) {
        update_option('template', $template);
        update_option('stylesheet', $template);
    }
    return $template;
}

/**
 * Translate user level to user role name.
 *
 * @since 3.0.0
 *
 * @param int $level
 *            User level.
 * @return string User role name.
 */
function translate_level_to_role($level)
{
    switch ($level) {
        case 10:
        case 9:
        case 8:
            return 'administrator';
        case 7:
        case 6:
        case 5:
            return 'editor';
        case 4:
        case 3:
        case 2:
            return 'author';
        case 1:
            return 'contributor';
        case 0:
            return 'subscriber';
    }
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 3.0.0
 */
function gb_check_mysql_version()
{
    $result = gbdb()->check_database_version();
    if (is_gb_error($result))
        die($result->get_error_message());
}

/**
 * Disables the Automattic widgets plugin, which was merged into core.
 *
 * @since 3.0.0
 */
function maybe_disable_automattic_widgets()
{
    $plugins = __get_option('active_plugins');

    foreach ((array) $plugins as $plugin) {
        if (basename($plugin) == 'widgets.php') {
            array_splice($plugins, array_search($plugin, $plugins), 1);
            update_option('active_plugins', $plugins);
            break;
        }
    }
}

/**
 * Disables the Link Manager on upgrade, if at the time of upgrade, no links exist in the DB.
 *
 * @since 3.0.0
 */
function maybe_disable_link_manager()
{
    global $gb_current_db_version;

    if ($gb_current_db_version >= 22006 && get_option('link_manager_enabled') && ! gbdb()->get_var("SELECT link_id FROM gbdb()->links LIMIT 1"))
        update_option('link_manager_enabled', 0);
}

/**
 * Runs before the schema is upgraded.
 *
 * @since 3.0.0
 */
function pre_schema_upgrade()
{
    global $gb_current_db_version;

    // Upgrade versions prior to 2.9
    if ($gb_current_db_version < 11557) {
        // Delete duplicate options. Keep the option with the highest option_id.
        gbdb()->query("DELETE FROM gbdb()->options AS o1 JOIN gbdb()->options AS o2 USING (`option_name`) WHERE o2.option_id > o1.option_id");

        // Drop the old primary key and add the new.
        gbdb()->query("ALTER TABLE gbdb()->options DROP PRIMARY KEY, ADD PRIMARY KEY(option_id)");

        // Drop the old option_name index. dbDelta() doesn't do the drop.
        gbdb()->query("ALTER TABLE gbdb()->options DROP INDEX option_name");
    }

    // Multisite schema upgrades.
    if ($gb_current_db_version < 25448 && is_multisite() && ! defined('DO_NOT_UPGRADE_GLOBAL_TABLES') && is_main_network()) {

        // Upgrade verions prior to 3.7
        if ($gb_current_db_version < 25179) {
            // New primary key for signups.
            gbdb()->query("ALTER TABLE gbdb()->signups ADD signup_id BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
            gbdb()->query("ALTER TABLE gbdb()->signups DROP INDEX domain");
        }

        if ($gb_current_db_version < 25448) {
            // Convert archived from enum to tinyint.
            gbdb()->query("ALTER TABLE gbdb()->blogs CHANGE COLUMN archived archived varchar(1) NOT NULL default '0'");
            gbdb()->query("ALTER TABLE gbdb()->blogs CHANGE COLUMN archived archived tinyint(2) NOT NULL default 0");
        }
    }

    if ($gb_current_db_version < 30133) {
        // dbDelta() can recreate but can't drop the index.
        gbdb()->query("ALTER TABLE gbdb()->terms DROP INDEX slug");
    }
}

/**
 * Install global terms.
 *
 * @since 3.0.0
 *
 */
if (! function_exists('install_global_terms')) :

    function install_global_terms()
    {
        global $charset_collate;
        $ms_queries = "
CREATE TABLE gbdb()->sitecategories (
  cat_ID bigint(20) NOT NULL auto_increment,
  cat_name varchar(55) NOT NULL default '',
  category_nicename varchar(200) NOT NULL default '',
  last_updated timestamp NOT NULL,
  PRIMARY KEY  (cat_ID),
  KEY category_nicename (category_nicename),
  KEY last_updated (last_updated)
) $charset_collate;
";
        // now create tables
        dbDelta($ms_queries);
    }


endif;
