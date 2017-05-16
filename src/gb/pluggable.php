<?php
/**
 * These functions can be replaced via plugins. If plugins do not redefine these
 * functions, then these will be used instead.
 *
 * @package GeniBase
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © GeniBase Team
 */
if (! function_exists('gb_redirect')) :

    /**
     * Redirects to another page.
     *
     * @since 2.2.3
     *       
     * @param string $location
     *            The path to redirect to.
     * @param int $status
     *            Status code to use.
     * @return bool False if $location is not provided, true otherwise.
     */
    function gb_redirect($location, $status = 302)
    {
        global $is_IIS;
        
        if (class_exists('GB_Hooks')) {
            /**
             * Filter the redirect location.
             *
             * @since 2.2.3
             *       
             * @param string $location
             *            The path to redirect to.
             * @param int $status
             *            Status code to use.
             */
            $location = apply_filters('gb_redirect', $location, $status);
            
            /**
             * Filter the redirect status code.
             *
             * @since 2.2.3
             *       
             * @param int $status
             *            Status code to use.
             * @param string $location
             *            The path to redirect to.
             */
            $status = apply_filters('gb_redirect_status', $status, $location);
        }
        
        if (! $location)
            return false;
        
        $location = gb_sanitize_redirect($location);
        
        if (! $is_IIS && PHP_SAPI != 'cgi-fcgi')
            status_header($status); // This causes problems on IIS and some FastCGI setups
        
        header("Location: $location", true, $status);
        
        return true;
    }

endif;

if (! function_exists('gb_sanitize_redirect')) :

    /**
     * Sanitizes a URL for use in a redirect.
     *
     * @since 2.2.3
     *       
     * @return string redirect-sanitized URL
     */
    function gb_sanitize_redirect($location)
    {
        $regex = '/
		(
			(?: [\xC2-\xDF][\x80-\xBF]        # double-byte sequences   110xxxxx 10xxxxxx
			|   \xE0[\xA0-\xBF][\x80-\xBF]    # triple-byte sequences   1110xxxx 10xxxxxx * 2
			|   [\xE1-\xEC][\x80-\xBF]{2}
			|   \xED[\x80-\x9F][\x80-\xBF]
			|   [\xEE-\xEF][\x80-\xBF]{2}
			|   \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
			|   [\xF1-\xF3][\x80-\xBF]{3}
			|   \xF4[\x80-\x8F][\x80-\xBF]{2}
		){1,50}                              # ...one or more times
		)/x';
        $location = preg_replace_callback($regex, '_gb_sanitize_utf8_in_redirect', $location);
        $location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()]|i', '', $location);
        $location = gb_kses_no_null($location);
        
        // remove %0d and %0a from location
        $strip = array(
            '%0d',
            '%0a',
            '%0D',
            '%0A'
        );
        $location = _deep_replace($strip, $location);
        return $location;
    }

    /**
     * URL encode UTF-8 characters in a URL.
     *
     * @ignore
     *
     * @since 2.2.3
     * @access private
     *        
     * @see gb_sanitize_redirect()
     */
    function _gb_sanitize_utf8_in_redirect($matches)
    {
        return urlencode($matches[0]);
    }

endif;

if (! function_exists('gb_safe_redirect')) :

    /**
     * Performs a safe (local) redirect, using gb_redirect().
     *
     * Checks whether the $location is using an allowed host, if it has an absolute
     * path. A plugin can therefore set or remove allowed host(s) to or from the
     * list.
     *
     * If the host is not allowed, then the redirect is to gb-admin on the siteurl
     * instead. This prevents malicious redirects which redirect to another host,
     * but only used in a few places.
     *
     * @since 2.2.3
     *       
     * @return void Does not return anything
     */
    function gb_safe_redirect($location, $status = 302)
    {
        // Need to look at the URL the way it will end up in gb_redirect()
        $location = gb_sanitize_redirect($location);
        
        $location = gb_validate_redirect($location, admin_url());
        
        gb_redirect($location, $status);
    }

endif;

if (! function_exists('gb_validate_redirect')) :

    /**
     * Validates a URL for use in a redirect.
     *
     * Checks whether the $location is using an allowed host, if it has an absolute
     * path. A plugin can therefore set or remove allowed host(s) to or from the
     * list.
     *
     * If the host is not allowed, then the redirect is to $default supplied
     *
     * @since 2.2.3
     *       
     * @param string $location
     *            The redirect to validate
     * @param string $default
     *            The value to return if $location is not allowed
     * @return string redirect-sanitized URL
     */
    function gb_validate_redirect($location, $default = '')
    {
        $location = trim($location);
        // browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
        if (substr($location, 0, 2) == '//')
            $location = 'http:' . $location;
            
            // In php 5 parse_url may fail if the URL query part contains http://, bug #38143
        $test = ($cut = strpos($location, '?')) ? substr($location, 0, $cut) : $location;
        
        $lp = parse_url($test);
        
        // Give up if malformed URL
        if (false === $lp)
            return $default;
            
            // Allow only http and https schemes. No data:, etc.
        if (isset($lp['scheme']) && ! ('http' == $lp['scheme'] || 'https' == $lp['scheme']))
            return $default;
            
            // Reject if scheme is set but host is not. This catches urls like https:host.com for which parse_url does not set the host field.
        if (isset($lp['scheme']) && ! isset($lp['host']))
            return $default;
        
        $gbp = parse_url(home_url());
        $allowed_hosts = array(
            $gbp['host']
        );
        
        if (class_exists('GB_Hooks')) {
            /**
             * Filter the whitelist of hosts to redirect to.
             *
             * @since 2.2.3
             *       
             * @param array $hosts
             *            An array of allowed hosts.
             * @param bool|string $host
             *            The parsed host; empty if not isset.
             */
            $allowed_hosts = (array) apply_filters('allowed_redirect_hosts', $allowed_hosts, isset($lp['host']) ? $lp['host'] : '');
        }
        
        if (isset($lp['host']) && (! in_array($lp['host'], $allowed_hosts) && $lp['host'] != strtolower($gbp['host'])))
            $location = $default;
        
        return $location;
    }

endif;

if (! function_exists('gb_set_current_user')) :

    /**
     * Changes the current user by ID or name.
     *
     * Set $id to null and specify a name if you do not know a user's ID.
     *
     * Some GeniBase functionality is based on the current user and not based on
     * the signed in user. Therefore, it opens the ability to edit and perform
     * actions on users who aren't signed in.
     *
     * @since 2.3.0
     *       
     * @param int $id
     *            User ID
     * @param string $email
     *            User's email
     * @return GB_User Current user User object
     *        
     */
    function gb_set_current_user($id, $email = '')
    {
        if (isset(GB::$user) && (GB::$user instanceof GB_User) && ($id == GB::$user->ID))
            return GB::$user;
        
        GB::$user = new GB_User($id, $email);
        
        $user_hash = GB::$user->get('user_hash');
        if (GB_User::hash() !== $user_hash)
            GB_User::hash($user_hash);
        
        /**
         * Fires after the current user is set.
         *
         * @since 2.3.0
         */
        GB_Hooks::do_action('set_current_user');
        
        return GB::$user;
    }

endif;

if (! function_exists('gb_load_current_user')) :

    /**
     * Populate global variables with information about the currently logged in user.
     *
     * Will set the current user, if the current user is not set. The current user
     * will be set to the logged in person. If no user is logged in, then it will
     * set the current user to 0, which is invalid and won't have any permissions.
     *
     * @since 2.3.0
     * @uses gp_validate_auth_cookie() Retrieves current logged in user.
     *      
     * @return bool|null False on XMLRPC Request and invalid auth cookie. Null when current user set
     *        
     */
    function gb_load_current_user()
    {
        if (! empty(GB::$user)) {
            if (GB::$user instanceof GB_User)
                return;
                
                // Upgrade stdClass to GB_User
            if (is_object(GB::$user) && isset(GB::$user->ID)) {
                $cur_id = GB::$user->ID;
                GB::$user = null;
                gb_set_current_user($cur_id);
                return;
            }
            
            // GB::$user has a junk value. Force to GB_User with ID 0.
            GB::$user = null;
            gb_set_current_user(0);
            return false;
        }
        
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            gb_set_current_user(0);
            return false;
        }
        
        if (! $user = gb_validate_auth_cookie()) {
            if ( /*is_network_admin() || */empty($_COOKIE[LOGGED_IN_COOKIE]) || ! $user = gb_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in')) {
                gb_set_current_user(0);
                return false;
            }
        }
        
        gb_set_current_user($user);
    }

endif;

if (! function_exists('gb_get_current_user')) :

    /**
     * Retrieve the current user object.
     *
     * @since 2.3.0
     *       
     * @return GB_User Current user GB_User object
     *        
     */
    function gb_get_current_user()
    {
        gb_load_current_user();
        return GB::$user;
    }

endif;

if (! function_exists('is_user_logged_in')) :

    /**
     * Checks if the current visitor is a logged in user.
     *
     * @since 3.0.0
     *       
     * @return bool True if user is logged in, false if not logged in.
     *        
     */
    function is_user_logged_in()
    {
        $user = gb_get_current_user();
        
        return $user->exists();
    }

endif;

if (! function_exists('gb_get_user')) :

    /**
     * Retrieve user info by user ID.
     *
     * @since 3.0.0
     *       
     * @param int $user_id
     *            User ID
     * @return GB_User|bool GB_User object on success, false on failure.
     */
    function gb_get_user($user_id)
    {
        return gb_get_user_by('id', $user_id);
    }

endif;

if (! function_exists('gb_get_user_by')) :

    /**
     * Retrieve user info by a given field
     *
     * @since 3.0.0
     *       
     * @param string $field
     *            The field to retrieve the user with. id | slug | email | login
     * @param int|string $value
     *            A value for $field. A user ID, slug, email address, or login name.
     * @return GB_User|bool GB_User object on success, false on failure.
     */
    function gb_get_user_by($field, $value)
    {
        $userdata = GB_User::get_data_by($field, $value);
        
        if (! $userdata)
            return false;
        
        $user = new GB_User();
        $user->init($userdata);
        
        return $user;
    }

endif;

if (! function_exists('gb_cache_users')) :

    /**
     * Retrieve info for user lists to prevent multiple queries by get_userdata()
     *
     * @since 2.3.0
     *       
     * @param array $user_ids
     *            User ID numbers list
     *            
     */
    function gb_cache_users($user_ids)
    {
        $clean = _get_non_cached_ids($user_ids, 'users');
        
        if (empty($clean))
            return;
        
        $list = implode(',', $clean);
        
        $users = gbdb()->get_table('SELECT * FROM ?_users WHERE ID IN (?list)', array(
            'list' => $list
        ));
        
        $ids = array();
        foreach ($users as $user) {
            gb_update_user_caches($user);
            $ids[] = $user->ID;
        }
        gb_update_meta_cache('user', $ids);
    }

endif;

if (! function_exists('gb_authenticate')) :

    /**
     * Checks a user's login information and logs them in if it checks out.
     *
     * @since 3.0.0
     *       
     * @param string $login
     *            User's login (email)
     * @param string $password
     *            User's password
     * @return GB_User|GB_Error GB_User object if login successful, otherwise GB_Error object.
     */
    function gb_authenticate($login, $password)
    {
        $login = sanitize_user($login);
        $password = trim($password);
        
        /**
         * Filter the user to authenticate.
         *
         * If a non-null value is passed, the filter will effectively short-circuit
         * authentication, returning an error instead.
         *
         * @since 3.0.0
         *       
         * @param null|GB_User $user
         *            User to authenticate.
         * @param string $login
         *            login
         * @param string $password
         *            password
         */
        $user = GB_Hooks::apply_filters('authenticate', null, $login, $password);
        
        if ($user == null) {
            // TODO what should the error message be? (Or would these even happen?)
            // Only needed if all authentication handlers fail to return anything.
            $user = new GB_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid login or incorrect password.'));
        }
        
        $ignore_codes = array(
            'empty_login',
            'empty_password'
        );
        
        if (is_gb_error($user) && ! in_array($user->get_error_code(), $ignore_codes)) {
            /**
             * Fires after a user login has failed.
             *
             * @since 3.0.0
             *       
             * @param string $username
             *            User login.
             */
            GB_Hooks::do_action('gb_login_failed', $login);
        }
        
        return $user;
    }

endif;

if (! function_exists('gb_logout')) :

    /**
     * Log the current user out.
     *
     * @since 3.0.0
     *       
     */
    function gb_logout()
    {
        gb_destroy_current_session();
        gb_clear_auth_cookie();
        
        /**
         * Fires after a user is logged-out.
         *
         * @since 3.0.0
         */
        GB_Hooks::do_action('gb_logout');
    }

endif;

if (! function_exists('gb_hash_password')) :

    /**
     * Create a hash (encrypt) of a plain text password.
     *
     * For integration with other applications, this function can be overwritten to
     * instead use the other package password checking algorithm.
     *
     * @since 3.0.0
     *       
     * @param string $password
     *            Plain text user password to hash
     * @return string The hash string of the password
     *        
     */
    function gb_hash_password($password)
    {
        return _gb_hasher()->HashPassword(trim($password));
    }

endif;

if (! function_exists('gb_set_password')) :

    /**
     * Updates the user's password with a new encrypted one.
     *
     * For integration with other applications, this function can be overwritten to
     * instead use the other package password checking algorithm.
     *
     * Please note: This function should be used sparingly and is really only meant for single-time
     * application. Leveraging this improperly in a plugin or theme could result in an endless loop
     * of password resets if precautions are not taken to ensure it does not execute on every page load.
     *
     * @since 3.0.0
     *       
     * @param string $password
     *            The plaintext new user password
     * @param int $user_id
     *            User ID
     *            
     */
    function gb_set_password($password, $user_id)
    {
        $hash = gb_hash_password($password);
        gbdb()->set_row('?_users', array(
            'user_pass' => $hash
        ), array(
            'ID' => $user_id
        ));
        
        gb_cache_delete($user_id, 'users');
    }

endif;

if (! function_exists('gb_check_password')) :

    /**
     * Checks the plaintext password against the encrypted Password.
     *
     * Maintains compatibility between old version and the new cookie authentication
     * protocol using PHPass library. The $hash parameter is the encrypted password
     * and the function compares the plain text password when encrypted similarly
     * against the already encrypted password to see if they match.
     *
     * For integration with other applications, this function can be overwritten to
     * instead use the other package password checking algorithm.
     *
     * @since 3.0.0
     *       
     * @param string $password
     *            Plaintext user's password
     * @param string $hash
     *            Hash of the user's password to check against.
     * @return bool False, if the $password does not match the hashed password
     */
    function gb_check_password($password, $hash, $user_id = '')
    {
        $check = _gb_hasher()->CheckPassword($password, $hash);
        
        /**
         * This filter is documented in gb/pluggable.php
         */
        return GB_Hooks::apply_filters('check_password', $check, $password, $hash, $user_id);
    }

endif;

if (! function_exists('gb_generate_password')) :

    /**
     * Generates a random password drawn from the defined set of characters.
     *
     * @since 3.0.0
     *       
     * @param int $length
     *            Optional. The length of password to generate. Default 12.
     * @param bool $special_chars
     *            Optional. Whether to include standard special characters.
     *            Default true.
     * @param bool $extra_special_chars
     *            Optional. Whether to include other special characters.
     *            Used when generating secret keys and salts. Default false.
     * @return string The random password.
     */
    function gb_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars)
            $chars .= '!@#$%^&*()';
        if ($extra_special_chars)
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        
        $password = '';
        for ($i = 0; $i < $length; $i ++) {
            $password .= substr($chars, gb_rand(0, strlen($chars) - 1), 1);
        }
        
        /**
         * Filter the randomly-generated password.
         *
         * @since 3.0.0
         *       
         * @param string $password
         *            The generated password.
         */
        return GB_Hooks::apply_filters('random_password', $password);
    }

endif;

if (! function_exists('gb_rand')) :

    /**
     * Generates a random number
     *
     * @since 3.0.0
     *       
     * @param int $min
     *            Lower limit for the generated number
     * @param int $max
     *            Upper limit for the generated number
     * @return int A random number between min and max
     *        
     */
    function gb_rand($min = 0, $max = 0)
    {
        static $rnd_value;
        
        // Reset $rnd_value after 14 uses
        // 32(md5) + 40(sha1) + 40(sha1) / 8 = 14 random numbers from $rnd_value
        if (strlen($rnd_value) < 8) {
            if (defined('GB_SETUP_CONFIG')) {
                static $seed = '';
            } else {
                $seed = GB_Options::get_transient('random_seed');
            }
            $rnd_value = md5(uniqid(microtime() . mt_rand(), true) . $seed);
            $rnd_value .= sha1($rnd_value);
            $rnd_value .= sha1($rnd_value . $seed);
            $seed = md5($seed . $rnd_value);
            if (! defined('GB_SETUP_CONFIG'))
                GB_Options::set_transient('random_seed', $seed);
        }
        
        // Take the first 8 digits for our value
        $value = substr($rnd_value, 0, 8);
        
        // Strip the first eight, leaving the remainder for the next call to gb_rand().
        $rnd_value = substr($rnd_value, 8);
        
        $value = abs(hexdec($value));
        
        // Some misconfigured 32bit environments (Entropy PHP, for example) truncate integers larger than PHP_INT_MAX to PHP_INT_MAX rather than overflowing them to floats.
        $max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff
                                                                                            
        // Reduce the value to be within the min - max range
        if ($max != 0)
            $value = $min + ($max - $min + 1) * $value / ($max_random_number + 1);
        
        return abs(intval($value));
    }

endif;

if (! function_exists('gb_salt')) :

    /**
     * Get salt to add to hashes.
     *
     * Salts are created using secret keys. Secret keys are located in two places:
     * in the database and in the wp-config.php file. The secret key in the database
     * is randomly generated and will be appended to the secret keys in wp-config.php.
     *
     * The secret keys in gb-config.php should be updated to strong, random keys to maximize
     * security. Below is an example of how the secret key constants are defined.
     * Do not paste this example directly into gb-config.php. Instead, have a
     * {@link https://api.wordpress.org/secret-key/1.1/salt/ secret key created} just
     * for you.
     *
     * define('AUTH_KEY', ' Xakm<o xQy rw4EMsLKM-?!T+,PFF})H4lzcW57AF0U@N@< >M%G4Yt>f`z]MON');
     * define('SECURE_AUTH_KEY', 'LzJ}op]mr|6+![P}Ak:uNdJCJZd>(Hx.-Mh#Tz)pCIU#uGEnfFz|f ;;eU%/U^O~');
     * define('LOGGED_IN_KEY', '|i|Ux`9<p-h$aFf(qnT:sDO:D1P^wZ$$/Ra@miTJi9G;ddp_<q}6H1)o|a +&JCM');
     * define('NONCE_KEY', '%:R{[P|,s.KuMltH5}cI;/k<Gx~j!f0I)m_sIyu+&NJZ)-iO>z7X>QYR0Z_XnZ@|');
     * define('AUTH_SALT', 'eZyT)-Naw]F8CwA*VaW#q*|.)g@o}||wf~@C-YSt}(dh_r6EbI#A,y|nU2{B#JBW');
     * define('SECURE_AUTH_SALT', '!=oLUTXh,QW=H `}`L|9/^4-3 STz},T(w}W<I`.JjPi)<Bmf1v,HpGe}T1:Xt7n');
     * define('LOGGED_IN_SALT', '+XSqHc;@Q*K_b|Z?NC[3H!!EONbh.n<+=uKR:>*c(u`g~EJBf#8u#R{mUEZrozmm');
     * define('NONCE_SALT', 'h`GXHhD>SLWVfg1(1(N{;.V!MoE(SfbA_ksP@&`+AycHcAV$+?@3q+rxV{%^VyKT');
     *
     * Salting passwords helps against tools which has stored hashed values of
     * common dictionary strings. The added values makes it harder to crack.
     *
     * @since 3.0.0
     *       
     * @link https://api.wordpress.org/secret-key/1.1/salt/ Create secrets for wp-config.php
     *      
     * @param string $scheme
     *            Authentication scheme (auth, secure_auth, logged_in, nonce)
     * @return string Salt value
     */
    function gb_salt($scheme = 'auth')
    {
        static $cached_salts = array();
        static $duplicated_keys;
        
        if (isset($cached_salts[$scheme])) {
            /**
             * Filter the GeniBase salt.
             *
             * @since 3.0.0
             *       
             * @param string $cached_salt
             *            Cached salt for the given scheme.
             * @param string $scheme
             *            Authentication scheme. Values include 'auth',
             *            'secure_auth', 'logged_in', and 'nonce'.
             */
            return GB_Hooks::apply_filters('salt', $cached_salts[$scheme], $scheme);
        }
        
        if (null === $duplicated_keys) {
            $duplicated_keys = array(
                'put your unique phrase here' => true
            );
            foreach (array(
                'AUTH',
                'SECURE_AUTH',
                'LOGGED_IN',
                'NONCE',
                'SECRET'
            ) as $first) {
                foreach (array(
                    'KEY',
                    'SALT'
                ) as $second) {
                    if (! defined("{$first}_{$second}")) {
                        continue;
                    }
                    $value = constant("{$first}_{$second}");
                    $duplicated_keys[$value] = isset($duplicated_keys[$value]);
                }
            }
        }
        
        $values = array(
            'key' => '',
            'salt' => ''
        );
        if (defined('SECRET_KEY') && SECRET_KEY && empty($duplicated_keys[SECRET_KEY])) {
            $values['key'] = SECRET_KEY;
        }
        if ('auth' == $scheme && defined('SECRET_SALT') && SECRET_SALT && empty($duplicated_keys[SECRET_SALT])) {
            $values['salt'] = SECRET_SALT;
        }
        
        if (in_array($scheme, array(
            'auth',
            'secure_auth',
            'logged_in',
            'nonce'
        ))) {
            foreach (array(
                'key',
                'salt'
            ) as $type) {
                $const = strtoupper("{$scheme}_{$type}");
                if (defined($const) && constant($const) && empty($duplicated_keys[constant($const)])) {
                    $values[$type] = constant($const);
                } elseif (! $values[$type]) {
                    $values[$type] = GB_Options::get("{$scheme}_{$type}");
                    if (! $values[$type]) {
                        $values[$type] = gb_generate_password(64, true, true);
                        GB_Options::update("{$scheme}_{$type}", $values[$type]);
                    }
                }
            }
        } else {
            if (! $values['key']) {
                $values['key'] = GB_Options::get('secret_key');
                if (! $values['key']) {
                    $values['key'] = gb_generate_password(64, true, true);
                    GB_Options::update('secret_key', $values['key']);
                }
            }
            $values['salt'] = hash_hmac('md5', $scheme, $values['key']);
        }
        
        $cached_salts[$scheme] = $values['key'] . $values['salt'];
        
        /**
         * This filter is documented in gb/pluggable.php
         */
        return GB_Hooks::apply_filters('salt', $cached_salts[$scheme], $scheme);
    }

endif;

if (! function_exists('gb_hash')) :

    /**
     * Get hash of given string.
     *
     * @since 3.0.0
     *       
     * @param string $data
     *            Plain text to hash
     * @return string Hash of $data
     *        
     */
    function gb_hash($data, $scheme = 'auth')
    {
        $salt = gb_salt($scheme);
        
        return hash_hmac('md5', $data, $salt);
    }

endif;

if (! function_exists('gb_validate_auth_cookie')) :

    /**
     * Validates authentication cookie.
     *
     * The checks include making sure that the authentication cookie is set and
     * pulling in the contents (if $cookie is not used).
     *
     * Makes sure the cookie is not expired. Verifies the hash in cookie is what is
     * should be and compares the two.
     *
     * @since 3.0.0
     *       
     * @param string $cookie
     *            Optional. If used, will validate contents instead of cookie's
     * @param string $scheme
     *            Optional. The cookie scheme to use: auth, secure_auth, or logged_in
     * @return bool|int False if invalid cookie, User ID if valid.
     *        
     */
    function gb_validate_auth_cookie($cookie = '', $scheme = '')
    {
        if (! $cookie_elements = gb_parse_auth_cookie($cookie, $scheme)) {
            /**
             * Fires if an authentication cookie is malformed.
             *
             * @since 3.0.0
             *       
             * @param string $cookie
             *            Malformed auth cookie.
             * @param string $scheme
             *            Authentication scheme. Values include 'auth', 'secure_auth',
             *            or 'logged_in'.
             */
            GB_Hooks::do_action('auth_cookie_malformed', $cookie, $scheme);
            return false;
        }
        
        $scheme = $cookie_elements['scheme'];
        $username = $cookie_elements['username'];
        $hmac = $cookie_elements['hmac'];
        $token = $cookie_elements['token'];
        $expired = $expiration = $cookie_elements['expiration'];
        
        // Allow a grace period for POST and AJAX requests
        if (defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD']) {
            $expired += HOUR_IN_SECONDS;
        }
        
        // Quick check to see if an honest cookie has expired
        if ($expired < time()) {
            /**
             * Fires once an authentication cookie has expired.
             *
             * @since 3.0.0
             *       
             * @param array $cookie_elements
             *            An array of data for the authentication cookie.
             */
            GB_Hooks::do_action('auth_cookie_expired', $cookie_elements);
            return false;
        }
        
        $user = GB_User::get_data_by('email', $username);
        if (! $user) {
            /**
             * Fires if a bad username is entered in the user authentication process.
             *
             * @since 3.0.0
             *       
             * @param array $cookie_elements
             *            An array of data for the authentication cookie.
             */
            GB_Hooks::do_action('auth_cookie_bad_username', $cookie_elements);
            return false;
        }
        
        $pass_frag = substr($user->user_pass, 8, 4);
        
        $key = gb_hash($username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);
        
        // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
        $algo = function_exists('hash') ? 'sha256' : 'sha1';
        $hash = hash_hmac($algo, $username . '|' . $expiration . '|' . $token, $key);
        
        if (! hash_equals($hash, $hmac)) {
            /**
             * Fires if a bad authentication cookie hash is encountered.
             *
             * @since 3.0.0
             *       
             * @param array $cookie_elements
             *            An array of data for the authentication cookie.
             */
            GB_Hooks::do_action('auth_cookie_bad_hash', $cookie_elements);
            return false;
        }
        
        $manager = GB_Session_Tokens::get_instance($user->ID);
        if (! $manager->verify($token)) {
            GB_Hooks::do_action('auth_cookie_bad_session_token', $cookie_elements);
            return false;
        }
        
        // AJAX/POST grace period set above
        // if ( $expiration < time() ) {
        // $GLOBALS['login_grace_period'] = 1;
        // }
        
        /**
         * Fires once an authentication cookie has been validated.
         *
         * @since 3.0.0
         *       
         * @param array $cookie_elements
         *            An array of data for the authentication cookie.
         * @param GB_User $user
         *            User object.
         */
        GB_Hooks::do_action('auth_cookie_valid', $cookie_elements, $user);
        
        return $user->ID;
    }

endif;

if (! function_exists('gb_generate_auth_cookie')) :

    /**
     * Generate authentication cookie contents.
     *
     * @since 3.0.0
     *       
     * @param int|object $user
     *            User ID or object.
     * @param int $expiration
     *            Cookie expiration in seconds
     * @param string $scheme
     *            Optional. The cookie scheme to use: auth, secure_auth, or logged_in
     * @param string $token
     *            User's session token to use for this cookie
     * @return string Authentication cookie contents. Empty string if user does not exist.
     */
    function gb_generate_auth_cookie($user, $expiration, $scheme = 'auth', $token = '')
    {
        $user = new GB_User($user);
        if (! $user) {
            return '';
        }
        
        if (! $token) {
            $manager = GB_Session_Tokens::get_instance($user->ID);
            $token = $manager->create($expiration);
        }
        
        $pass_frag = substr($user->user_pass, 8, 4);
        
        $key = gb_hash($user->user_email . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);
        
        // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
        $algo = function_exists('hash') ? 'sha256' : 'sha1';
        $hash = hash_hmac($algo, $user->user_email . '|' . $expiration . '|' . $token, $key);
        
        $cookie = $user->user_email . '|' . $expiration . '|' . $token . '|' . $hash;
        
        /**
         * Filter the authentication cookie.
         *
         * @since 3.0.0
         *       
         * @param string $cookie
         *            Authentication cookie.
         * @param int $user_id
         *            User ID.
         * @param int $expiration
         *            Authentication cookie expiration in seconds.
         * @param string $scheme
         *            Cookie scheme used. Accepts 'auth', 'secure_auth', or 'logged_in'.
         * @param string $token
         *            User's session token used.
         */
        return GB_Hooks::apply_filters('auth_cookie', $cookie, $user->ID, $expiration, $scheme, $token);
    }

endif;

if (! function_exists('gb_parse_auth_cookie')) :

    /**
     * Parse a cookie into its components
     *
     * @since 3.0.0
     *       
     * @param string $cookie            
     * @param string $scheme
     *            Optional. The cookie scheme to use: auth, secure_auth, or logged_in
     * @return array Authentication cookie components
     *        
     */
    function gb_parse_auth_cookie($cookie = '', $scheme = '')
    {
        if (empty($cookie)) {
            switch ($scheme) {
                case 'auth':
                    $cookie_name = AUTH_COOKIE;
                    break;
                case 'secure_auth':
                    $cookie_name = SECURE_AUTH_COOKIE;
                    break;
                case 'logged_in':
                    $cookie_name = LOGGED_IN_COOKIE;
                    break;
                default:
                    if (is_ssl()) {
                        $cookie_name = SECURE_AUTH_COOKIE;
                        $scheme = 'secure_auth';
                    } else {
                        $cookie_name = AUTH_COOKIE;
                        $scheme = 'auth';
                    }
            }
            
            if (empty($_COOKIE[$cookie_name]))
                return false;
            
            $cookie = $_COOKIE[$cookie_name];
        }
        
        $cookie_elements = explode('|', $cookie);
        if (count($cookie_elements) !== 4) {
            return false;
        }
        
        list ($username, $expiration, $token, $hmac) = $cookie_elements;
        
        return compact('username', 'expiration', 'token', 'hmac', 'scheme');
    }

endif;

if (! function_exists('gb_set_auth_cookie')) :

    /**
     * Sets the authentication cookies based on user ID.
     *
     * The $remember parameter increases the time that the cookie will be kept. The
     * default the cookie is kept without remembering is two days. When $remember is
     * set, the cookies will be kept for 14 days or two weeks.
     *
     * @since 3.0.0
     *       
     * @param int $user_id
     *            User ID
     * @param bool $remember
     *            Whether to remember the user
     * @param mixed $secure
     *            Whether the admin cookies should only be sent over HTTPS.
     *            Default is_ssl().
     *            
     */
    function gb_set_auth_cookie($user_id, $remember = false, $secure = '')
    {
        if ($remember) {
            /**
             * Filter the duration of the authentication cookie expiration period.
             *
             * @since 3.0.0
             *       
             * @param int $length
             *            Duration of the expiration period in seconds.
             * @param int $user_id
             *            User ID.
             * @param bool $remember
             *            Whether to remember the user login. Default false.
             */
            $expiration = time() + GB_Hooks::apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember);
            
            /*
             * Ensure the browser will continue to send the cookie after the expiration time is reached.
             * Needed for the login grace period in gb_validate_auth_cookie().
             */
            $expire = $expiration + (12 * HOUR_IN_SECONDS);
        } else {
            /**
             * This filter is documented in gb/pluggable.php
             */
            $expiration = time() + GB_Hooks::apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember);
            $expire = 0;
        }
        
        if ('' === $secure) {
            $secure = is_ssl();
        }
        
        // Frontend cookie is secure when the auth cookie is secure and the site's home URL is forced HTTPS.
        $secure_logged_in_cookie = $secure && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);
        
        /**
         * Filter whether the connection is secure.
         *
         * @since 3.0.0
         *       
         * @param bool $secure
         *            Whether the connection is secure.
         * @param int $user_id
         *            User ID.
         */
        $secure = GB_Hooks::apply_filters('secure_auth_cookie', $secure, $user_id);
        
        /**
         * Filter whether to use a secure cookie when logged-in.
         *
         * @since 3.0.0
         *       
         * @param bool $secure_logged_in_cookie
         *            Whether to use a secure cookie when logged-in.
         * @param int $user_id
         *            User ID.
         * @param bool $secure
         *            Whether the connection is secure.
         */
        $secure_logged_in_cookie = GB_Hooks::apply_filters('secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure);
        
        if ($secure) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme = 'auth';
        }
        
        $manager = GB_Session_Tokens::get_instance($user_id);
        $token = $manager->create($expiration);
        
        $auth_cookie = gb_generate_auth_cookie($user_id, $expiration, $scheme, $token);
        $logged_in_cookie = gb_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);
        
        /**
         * Fires immediately before the authentication cookie is set.
         *
         * @since 3.0.0
         *       
         * @param string $auth_cookie
         *            Authentication cookie.
         * @param int $expire
         *            Login grace period in seconds. Default 43,200 seconds, or 12 hours.
         * @param int $expiration
         *            Duration in seconds the authentication cookie should be valid.
         *            Default 1,209,600 seconds, or 14 days.
         * @param int $user_id
         *            User ID.
         * @param string $scheme
         *            Authentication scheme. Values include 'auth', 'secure_auth', or 'logged_in'.
         */
        GB_Hooks::do_action('set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme);
        
        /**
         * Fires immediately before the secure authentication cookie is set.
         *
         * @since 3.0.0
         *       
         * @param string $logged_in_cookie
         *            The logged-in cookie.
         * @param int $expire
         *            Login grace period in seconds. Default 43,200 seconds, or 12 hours.
         * @param int $expiration
         *            Duration in seconds the authentication cookie should be valid.
         *            Default 1,209,600 seconds, or 14 days.
         * @param int $user_id
         *            User ID.
         * @param string $scheme
         *            Authentication scheme. Default 'logged_in'.
         */
        GB_Hooks::do_action('set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in');
        
        setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, GB_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure_logged_in_cookie, true);
        // TODO: plugins
        // setcookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure, true);
        // TODO: admin
        // setcookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure, true);
    }

endif;

if (! function_exists('gb_clear_auth_cookie')) :

    /**
     * Removes all of the cookies associated with authentication.
     *
     * @since 3.0.0
     *       
     */
    function gb_clear_auth_cookie()
    {
        /**
         * Fires just before the authentication cookies are cleared.
         *
         * @since 3.0.0
         */
        GB_Hooks::do_action('clear_auth_cookie');
        
        setcookie(LOGGED_IN_COOKIE, ' ', time() - YEAR_IN_SECONDS, GB_COOKIE_PATH, GB_COOKIE_DOMAIN);
        // TODO: plugins
        // setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, GB_COOKIE_DOMAIN );
        // setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, GB_COOKIE_DOMAIN );
        // TODO: admin
        // setcookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, GB_COOKIE_DOMAIN );
        // setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, GB_COOKIE_DOMAIN );
    }

endif;

if (! function_exists('gb_mail')) :

    /**
     * Send mail, similar to PHP's mail
     *
     * A true return value does not automatically mean that the user received the
     * email successfully. It just only means that the method used was able to
     * process the request without any errors.
     *
     * Using the two 'gb_mail_from' and 'gb_mail_from_name' hooks allow from
     * creating a from address like 'Name <email@address.com>' when both are set. If
     * just 'gb_mail_from' is set, then just the email address will be used with no
     * name.
     *
     * The default content type is 'text/plain' which does not allow using HTML.
     * However, you can set the content type of the email by using the
     * 'gb_mail_content_type' filter.
     *
     * The default charset is based on the charset used on the blog. The charset can
     * be set using the 'gb_mail_charset' filter.
     *
     * @since 3.0.0
     *       
     * @uses PHPMailer
     *      
     * @param string|array $to
     *            Array or comma-separated list of email addresses to send message.
     * @param string $subject
     *            Email subject
     * @param string $message
     *            Message contents
     * @param string|array $headers
     *            Optional. Additional headers.
     * @param string|array $attachments
     *            Optional. Files to attach.
     * @return bool Whether the email contents were sent successfully.
     *        
     */
    function gb_mail($to, $subject, $message, $headers = '', $attachments = array())
    {
        // Compact the input, apply the filters, and extract them back out
        
        /**
         * Filter the gb_mail() arguments.
         *
         * @since 3.0.0
         *       
         * @param array $args
         *            A compacted array of gb_mail() arguments, including the "to" email,
         *            subject, message, headers, and attachments values.
         */
        $atts = GB_Hooks::apply_filters('gb_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));
        
        if (isset($atts['to']))
            $to = $atts['to'];
        if (isset($atts['subject']))
            $subject = $atts['subject'];
        if (isset($atts['message']))
            $message = $atts['message'];
        if (isset($atts['headers']))
            $headers = $atts['headers'];
        if (isset($atts['attachments']))
            $attachments = $atts['attachments'];
        
        if (! is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }
        
        global $phpmailer;
        
        // (Re)create it, if it's gone missing
        if (! is_object($phpmailer) || ! is_a($phpmailer, 'PHPMailer')) {
            require_once GB_CORE_DIR . '/class.phpmailer.php';
            require_once GB_CORE_DIR . '/class.smtp.php';
            $phpmailer = new PHPMailer(true);
        }
        
        // Headers
        if (empty($headers)) {
            $headers = array();
        } else {
            if (! is_array($headers)) {
                // Explode the headers out, so this function can take both
                // string headers and an array of headers.
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }
            $headers = array();
            $cc = array();
            $bcc = array();
            
            // If it's actually got contents
            if (! empty($tempheaders)) {
                // Iterate through the raw headers
                foreach ((array) $tempheaders as $header) {
                    if (strpos($header, ':') === false) {
                        if (false !== stripos($header, 'boundary=')) {
                            $parts = preg_split('/boundary=/i', trim($header));
                            $boundary = trim(str_replace(array(
                                "'",
                                '"'
                            ), '', $parts[1]));
                        }
                        continue;
                    }
                    // Explode them out
                    list ($name, $content) = explode(':', trim($header), 2);
                    
                    // Cleanup crew
                    $name = trim($name);
                    $content = trim($content);
                    
                    switch (strtolower($name)) {
                        // Mainly for legacy -- process a From: header if it's there
                        case 'from':
                            if (strpos($content, '<') !== false) {
                                // So... making my life hard again?
                                $from_name = substr($content, 0, strpos($content, '<') - 1);
                                $from_name = str_replace('"', '', $from_name);
                                $from_name = trim($from_name);
                                
                                $from_email = substr($content, strpos($content, '<') + 1);
                                $from_email = str_replace('>', '', $from_email);
                                $from_email = trim($from_email);
                            } else {
                                $from_email = trim($content);
                            }
                            break;
                        case 'content-type':
                            if (strpos($content, ';') !== false) {
                                list ($type, $charset) = explode(';', $content);
                                $content_type = trim($type);
                                if (false !== stripos($charset, 'charset=')) {
                                    $charset = trim(str_replace(array(
                                        'charset=',
                                        '"'
                                    ), '', $charset));
                                } elseif (false !== stripos($charset, 'boundary=')) {
                                    $boundary = trim(str_replace(array(
                                        'BOUNDARY=',
                                        'boundary=',
                                        '"'
                                    ), '', $charset));
                                    $charset = '';
                                }
                            } else {
                                $content_type = trim($content);
                            }
                            break;
                        case 'cc':
                            $cc = array_merge((array) $cc, explode(',', $content));
                            break;
                        case 'bcc':
                            $bcc = array_merge((array) $bcc, explode(',', $content));
                            break;
                        default:
                            // Add it to our grand headers array
                            $headers[trim($name)] = trim($content);
                            break;
                    }
                }
            }
        }
        
        // Empty out the values that may be set
        $phpmailer->ClearAllRecipients();
        $phpmailer->ClearAttachments();
        $phpmailer->ClearCustomHeaders();
        $phpmailer->ClearReplyTos();
        
        // From email and name
        // If we don't have a name from the input headers
        if (! isset($from_name))
            $from_name = 'GeniBase';
            
            /*
         * If we don't have an email from the input headers default to genibase@$sitename
         * Some hosts will block outgoing mail from this address if it doesn't exist but
         * there's no easy alternative. Defaulting to admin_email might appear to be another
         * option but some hosts may refuse to relay mail from an unknown domain. See
         * https://core.trac.wordpress.org/ticket/5007.
         */
        
        if (! isset($from_email)) {
            // Get the site domain and get rid of www.
            $sitename = strtolower($_SERVER['SERVER_NAME']);
            if (substr($sitename, 0, 4) == 'www.') {
                $sitename = substr($sitename, 4);
            }
            
            $from_email = 'genibase@' . $sitename;
        }
        
        /**
         * Filter the email address to send from.
         *
         * @since 3.0.0
         *       
         * @param string $from_email
         *            Email address to send from.
         */
        $phpmailer->From = GB_Hooks::apply_filters('gb_mail_from', $from_email);
        
        /**
         * Filter the name to associate with the "from" email address.
         *
         * @since 3.0.0
         *       
         * @param string $from_name
         *            Name associated with the "from" email address.
         */
        $phpmailer->FromName = GB_Hooks::apply_filters('gb_mail_from_name', $from_name);
        
        // Set destination addresses
        if (! is_array($to))
            $to = explode(',', $to);
        
        foreach ((array) $to as $recipient) {
            try {
                // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
                $recipient_name = '';
                if (preg_match('/(.*)<(.+)>/', $recipient, $matches)) {
                    if (count($matches) == 3) {
                        $recipient_name = $matches[1];
                        $recipient = $matches[2];
                    }
                }
                $phpmailer->AddAddress($recipient, $recipient_name);
            } catch (phpmailerException $e) {
                continue;
            }
        }
        
        // Set mail's subject and body
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $message;
        
        // Add any CC and BCC recipients
        if (! empty($cc)) {
            foreach ((array) $cc as $recipient) {
                try {
                    // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
                    $recipient_name = '';
                    if (preg_match('/(.*)<(.+)>/', $recipient, $matches)) {
                        if (count($matches) == 3) {
                            $recipient_name = $matches[1];
                            $recipient = $matches[2];
                        }
                    }
                    $phpmailer->AddCc($recipient, $recipient_name);
                } catch (phpmailerException $e) {
                    continue;
                }
            }
        }
        
        if (! empty($bcc)) {
            foreach ((array) $bcc as $recipient) {
                try {
                    // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
                    $recipient_name = '';
                    if (preg_match('/(.*)<(.+)>/', $recipient, $matches)) {
                        if (count($matches) == 3) {
                            $recipient_name = $matches[1];
                            $recipient = $matches[2];
                        }
                    }
                    $phpmailer->AddBcc($recipient, $recipient_name);
                } catch (phpmailerException $e) {
                    continue;
                }
            }
        }
        
        // Set to use PHP's mail()
        $phpmailer->IsMail();
        
        // Set Content-Type and charset
        // If we don't have a content-type from the input headers
        if (! isset($content_type))
            $content_type = 'text/plain';
        
        /**
         * Filter the gb_mail() content type.
         *
         * @since 3.0.0
         *       
         * @param string $content_type
         *            Default gb_mail() content type.
         */
        $content_type = GB_Hooks::apply_filters('gb_mail_content_type', $content_type);
        
        $phpmailer->ContentType = $content_type;
        
        // Set whether it's plaintext, depending on $content_type
        if ('text/html' == $content_type)
            $phpmailer->IsHTML(true);
            
            // If we don't have a charset from the input headers
        if (! isset($charset))
            $charset = get_siteinfo('charset');
            
            // Set the content-type and charset
        
        /**
         * Filter the default gb_mail() charset.
         *
         * @since 3.0.0
         *       
         * @param string $charset
         *            Default email charset.
         */
        $phpmailer->CharSet = GB_Hooks::apply_filters('gb_mail_charset', $charset);
        
        // Set custom headers
        if (! empty($headers)) {
            foreach ((array) $headers as $name => $content) {
                $phpmailer->AddCustomHeader(sprintf('%1$s: %2$s', $name, $content));
            }
            
            if (false !== stripos($content_type, 'multipart') && ! empty($boundary))
                $phpmailer->AddCustomHeader(sprintf("Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary));
        }
        
        if (! empty($attachments)) {
            foreach ($attachments as $attachment) {
                try {
                    $phpmailer->AddAttachment($attachment);
                } catch (phpmailerException $e) {
                    continue;
                }
            }
        }
        
        /**
         * Fires after PHPMailer is initialized.
         *
         * @since 3.0.0
         *       
         * @param
         *            PHPMailer &$phpmailer The PHPMailer instance, passed by reference.
         */
        GB_Hooks::do_action_ref_array('phpmailer_init', array(
            &$phpmailer
        ));
        
        // Send!
        try {
            return $phpmailer->Send();
        } catch (phpmailerException $e) {
            return false;
        }
    }

endif;

if (! function_exists('gb_nonce_tick')) :

    /**
     * Get the time-dependent variable for nonce creation.
     *
     * A nonce has a lifespan of two ticks. Nonces in their second tick may be
     * updated, e.g. by autosave.
     *
     * @since 3.0.0
     *       
     * @return float Float value rounded up to the next highest integer.
     *        
     */
    function gb_nonce_tick()
    {
        /**
         * Filter the lifespan of nonces in seconds.
         *
         * @since 3.0.0
         *       
         * @param int $lifespan
         *            Lifespan of nonces in seconds. Default 86,400 seconds, or one day.
         */
        $nonce_life = GB_Hooks::apply_filters('nonce_life', DAY_IN_SECONDS);
        
        return ceil(time() / ($nonce_life / 2));
    }

endif;

if (! function_exists('gb_create_nonce')) :

    /**
     * Creates a cryptographic token tied to a specific action, user, and window of time.
     *
     * @since 3.0.0
     *       
     * @param string|int $action
     *            Scalar value to add context to the nonce.
     * @return string The token.
     *        
     */
    function gb_create_nonce($action = -1)
    {
        $user_hash = GB_User::hash();
        $token = gb_get_session_token();
        $i = gb_nonce_tick();
        
        return substr(gb_hash($i . '|' . $action . '|' . $user_hash . '|' . $token, 'nonce'), - 12, 10);
    }

endif;

if (! function_exists('gb_verify_nonce')) :

    /**
     * Verify that correct nonce was used with time limit.
     *
     * The user is given an amount of time to use the token, so therefore, since the
     * UID and $action remain the same, the independent variable is the time.
     *
     * @since 3.0.0
     *       
     * @param string $nonce
     *            Nonce that was used in the form to verify
     * @param string|int $action
     *            Should give context to what is taking place and be the same when nonce was created.
     * @return bool Whether the nonce check passed or failed.
     *        
     */
    function gb_verify_nonce($nonce, $action = -1)
    {
        $nonce = (string) $nonce;
        if (empty($nonce))
            return false;
        
        $user_hash = GB_User::hash();
        $token = gb_get_session_token();
        $i = gb_nonce_tick();
        
        // Nonce generated within current tick (default: 0-12 hours ago)
        $expected = substr(gb_hash($i . '|' . $action . '|' . $user_hash . '|' . $token, 'nonce'), - 12, 10);
        if (hash_equals($expected, $nonce))
            return 1;
            
            // Nonce generated within previous tick (default: 0-24 hours ago)
        $expected = substr(gb_hash(($i - 1) . '|' . $action . '|' . $user_hash . '|' . $token, 'nonce'), - 12, 10);
        if (hash_equals($expected, $nonce))
            return 2;
            
            // Invalid nonce
        return false;
    }

endif;
