<?php
/**
 * GeniBase Translation API
 *
 * @since	2.0.0
 *
 * @package GeniBase
 * @subpackage i18n
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

/**
 * Negotiate client's preferred locale.
 *
 * This function negotiates the clients preferred locale based on its Accept-Language HTTP header.
 *
 * @since 2.1.1
 *       
 * @param array $supported
 *            containing the supported languages as values
 * @return string|false negotiated language or the default language (i.e. first array entry)
 *         if none match. Or false if $supported is empty.
 */
function gb_negotiate_client_locale($supported)
{
    // Convert locale names to language names
    foreach ($supported as $key => $val)
        $supported[$key] = str_replace('_', '-', $val);
        
        // Initially set to default language (or false if $supported is empty).
    $lang = reset($supported);
    if (! $lang)
        return false;
    
    if (isset($_REQUEST['hl'])) {
        $locale = str_replace('-', '_', $_REQUEST['hl']);
        if (in_array($locale, $supported))
            return $_REQUEST['hl'];
    }
    
    if (isset($_COOKIE[GB_COOKIE_LANG])) {
        $locale = str_replace('-', '_', $_COOKIE[GB_COOKIE_LANG]);
        if (in_array($locale, $supported))
            return $_COOKIE[GB_COOKIE_LANG];
    }
    
    if (function_exists('http_negotiate_language')) {
        $lang = http_negotiate_language($supported);
    } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match_all('#([^;,]+)(;[^,0-9]*([0-9\.]+)[^,]*)?#i', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $matches, PREG_SET_ORDER)) {
        $prefered_languages = array();
        $priority = 1.0;
        foreach ($matches as $match) {
            if (! isset($match[3])) {
                $pr = $priority;
                $priority -= 0.001;
            } else {
                $pr = floatval($match[3]);
            }
            $prefered_languages[$match[1]] = $pr;
            $l = strtok($match[1], '-');
            if ($l != $match[1] && (! isset($prefered_languages[$l]) || $prefered_languages[$l] > $priority)) {
                $prefered_languages[$match[1]] = $priority;
                $priority -= 0.001;
            }
        }
        arsort($prefered_languages, SORT_NUMERIC);
        
        $priority = 0;
        foreach ($supported as $language) {
            $l = strtolower($language);
            if (isset($prefered_languages[$l]) && $prefered_languages[$l] > $priority) {
                $lang = $language;
                $priority = $prefered_languages[$l];
            }
            
            $language = strtok($language, '-');
            $l = strtolower($language);
            if ($l != $language && isset($prefered_languages[$l]) && $prefered_languages[$l] > $priority) {
                $lang = $language;
                $priority = $prefered_languages[$l];
            }
        }
    }
    
    // Convert selected language name to locale name
    return str_replace('-', '_', $lang);
}

/**
 * Get the current locale.
 *
 * If the locale is set, then it will filter the locale in the 'locale' filter
 * hook and return the value.
 *
 * If the locale is not set already, then the GB_LANG constant is used if it is
 * defined. Then it is filtered through the 'locale' filter hook and the value
 * for the locale global set and the locale is returned.
 *
 * The process to get the locale should only be done once, but the locale will
 * always be filtered using the 'locale' hook.
 *
 * @since 2.1.1 GB_LANG can contain multiple locales (separated by commas)
 * @since 2.0.0
 *       
 * @return string The locale of the blog or from the 'locale' hook.
 */
function get_locale()
{
    global $locale;
    
    $first_run = ! isset($locale);
    
    if ($first_run) {
        @header('Vary: Accept-Language');
        
        if (defined('GB_LOCAL_PACKAGE'))
            $locale = GB_LOCAL_PACKAGE;
            
            // GB_LANG was defined in gb-config.
        if (defined('GB_LANG'))
            $locale = gb_negotiate_client_locale(preg_split('/[^\w\-]+/si', GB_LANG, - 1, PREG_SPLIT_NO_EMPTY));
        
        if (class_exists('GB_Options')) {
            $db_locale = GB_Options::get('GB_LANG');
            if ($db_locale !== false) {
                $locale = gb_negotiate_client_locale(preg_split('/[^\w\-]+/si', $db_locale, - 1, PREG_SPLIT_NO_EMPTY));
                ;
            }
        }
        
        if (empty($locale))
            $locale = 'en_US';
    }
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter GeniBase install's locale ID.
         *
         * @since 2.1.1
         *       
         * @param string $locale
         *            The locale ID.
         */
        $locale = GB_Hooks::apply_filters('locale', $locale);
    }
    
    if ($first_run) {
        // Set a cookie with user locale
        $lang = str_replace('_', '-', $locale);
        if (! isset($_COOKIE[GB_COOKIE_LANG]) || $_COOKIE[GB_COOKIE_LANG] != $lang || 0 == rand(0, 99)) {
            $secure = ('https' === parse_url(site_url(), PHP_URL_SCHEME) && 'https' === parse_url(home_url(), PHP_URL_SCHEME));
            @setcookie(GB_COOKIE_LANG, $lang, time() + YEAR_IN_SECONDS, GB_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure);
        }
    }
    
    return $locale;
}

/**
 * Retrieve the translation of $text.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * *Note:* Don't use {@see translate()} directly, use `{@see __()} or related functions.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text
 */
function translate($text, $domain = 'default')
{
    $translations = get_translations_for_domain($domain);
    $translations = $translations->translate($text);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter text with its translation.
         *
         * @since 2.1.1
         *       
         * @param string $translations
         *            Translated text.
         * @param string $text
         *            Text to translate.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        $translations = GB_Hooks::apply_filters('gettext', $translations, $text, $domain);
    }
    
    return $translations;
}

/**
 * Remove last item on a pipe-delimited string.
 *
 * Meant for removing the last item in a string, such as 'Role name|User role'. The original
 * string will be returned if no pipe '|' characters are found in the string.
 *
 * @since 2.0.0
 *       
 * @param string $string
 *            A pipe-delimited string.
 * @return string Either $string or everything before the last pipe.
 */
function before_last_bar($string)
{
    $last_bar = strrpos($string, '|');
    if (false == $last_bar)
        return $string;
    else
        return substr($string, 0, $last_bar);
}

/**
 * Retrieve the translation of $text in the context defined in $context.
 *
 * If there is no translation, or the text domain isn't loaded the original
 * text is returned.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text on success, original text on failure.
 */
function translate_with_context($text, $context, $domain = 'default')
{
    $translations = get_translations_for_domain($domain);
    $translations = $translations->translate($text, $context);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter text with its translation based on context information.
         *
         * @since 2.1.1
         *       
         * @param string $translations
         *            Translated text.
         * @param string $text
         *            Text to translate.
         * @param string $context
         *            Context information for the translators.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        $translations = GB_Hooks::apply_filters('gettext_with_context', $translations, $text, $context, $domain);
    }
    
    return $translations;
}

/**
 * Retrieve the translation of $text.
 * If there is no translation,
 * or the text domain isn't loaded, the original text is returned.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text.
 */
function __($text, $domain = 'default')
{
    return translate($text, $domain);
}

/**
 * Retrieve the translation of $text and escapes it for safe use in an attribute.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text on success, original text on failure.
 */
function esc_attr__($text, $domain = 'default')
{
    return esc_attr(translate($text, $domain));
}

/**
 * Retrieve the translation of $text and escapes it for safe use in HTML output.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text
 */
function esc_html__($text, $domain = 'default')
{
    return esc_html(translate($text, $domain));
}

/**
 * Display translated text.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 */
function _e($text, $domain = 'default')
{
    echo translate($text, $domain);
}

/**
 * Display translated text that has been escaped for safe use in an attribute.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 */
function esc_attr_e($text, $domain = 'default')
{
    echo esc_attr(translate($text, $domain));
}

/**
 * Display translated text that has been escaped for safe use in HTML output.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 */
function esc_html_e($text, $domain = 'default')
{
    echo esc_html(translate($text, $domain));
}

/**
 * Retrieve translated string with gettext context.
 *
 * Quite a few times, there will be collisions with similar translatable text
 * found in more than two places, but with different translated context.
 *
 * By including the context in the pot file, translators can translate the two
 * strings differently.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated context string without pipe.
 */
function _x($text, $context, $domain = 'default')
{
    return translate_with_context($text, $context, $domain);
}

/**
 * Display translated string with gettext context.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated context string without pipe.
 */
function _ex($text, $context, $domain = 'default')
{
    echo translate_with_context($text, $context, $domain);
}

/**
 * Translate string with gettext context, and escapes it for safe use in an attribute.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text
 */
function esc_attr_x($text, $context, $domain = 'default')
{
    return esc_attr(translate_with_context($text, $context, $domain));
}

/**
 * Display translated string with gettext context, and escapes it for safe use in an attribute.
 *
 * @since 2.1.1
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text
 */
function esc_attr_ex($text, $context, $domain = 'default')
{
    echo esc_attr(translate_with_context($text, $context, $domain));
}

/**
 * Translate string with gettext context, and escapes it for safe use in HTML output.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text.
 */
function esc_html_x($text, $context, $domain = 'default')
{
    return esc_html(translate_with_context($text, $context, $domain));
}

/**
 * Display translated string with gettext context, and escapes it for safe use in HTML output.
 *
 * @since 2.1.1
 *       
 * @param string $text
 *            Text to translate.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Translated text.
 */
function esc_html_ex($text, $context, $domain = 'default')
{
    echo esc_html(translate_with_context($text, $context, $domain));
}

/**
 * Retrieve the plural or single form based on the supplied amount.
 *
 * If the text domain is not set in the $l10n list, then a comparison will be made
 * and either $plural or $single parameters returned.
 *
 * If the text domain does exist, then the parameters $single, $plural, and $number
 * will first be passed to the text domain's ngettext method. Then it will be passed
 * to the 'ngettext' filter hook along with the same parameters. The expected
 * type will be a string.
 *
 * @since 2.0.0
 *       
 * @param string $single
 *            The text that will be used if $number is 1.
 * @param string $plural
 *            The text that will be used if $number is not 1.
 * @param int $number
 *            The number to compare against to use either $single or $plural.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Either $single or $plural translated text.
 */
function _n($single, $plural, $number, $domain = 'default')
{
    $translations = get_translations_for_domain($domain);
    $translation = $translations->translate_plural($single, $plural, $number);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter text with its translation when plural option is available.
         *
         * @since 2.1.1
         *       
         * @param string $translation
         *            Translated text.
         * @param string $single
         *            The text that will be used if $number is 1.
         * @param string $plural
         *            The text that will be used if $number is not 1.
         * @param string $number
         *            The number to compare against to use either $single or $plural.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        $translation = GB_Hooks::apply_filters('ngettext', $translation, $single, $plural, $number, $domain);
    }
    
    return $translation;
}

/**
 * Retrieve the plural or single form based on the supplied amount with gettext context.
 *
 * This is a hybrid of _n() and _x(). It supports contexts and plurals.
 *
 * @since 2.0.0
 *       
 * @param string $single
 *            The text that will be used if $number is 1.
 * @param string $plural
 *            The text that will be used if $number is not 1.
 * @param int $number
 *            The number to compare against to use either $single or $plural.
 * @param string $context
 *            Context information for the translators.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return string Either $single or $plural translated text with context.
 */
function _nx($single, $plural, $number, $context, $domain = 'default')
{
    $translations = get_translations_for_domain($domain);
    $translation = $translations->translate_plural($single, $plural, $number, $context);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter text with its translation while plural option and context are available.
         *
         * @since 2.1.1
         *       
         * @param string $translation
         *            Translated text.
         * @param string $single
         *            The text that will be used if $number is 1.
         * @param string $plural
         *            The text that will be used if $number is not 1.
         * @param string $number
         *            The number to compare against to use either $single or $plural.
         * @param string $context
         *            Context information for the translators.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        $translation = GB_Hooks::apply_filters('ngettext_with_context', $translation, $single, $plural, $number, $context, $domain);
    }
    
    return $translation;
}

/**
 * Register plural strings in POT file, but don't translate them.
 *
 * Used when you want to keep structures with translatable plural
 * strings and use them later.
 *
 * Example:
 *
 * $messages = array(
 * 'post' => _n_noop( '%s post', '%s posts' ),
 * 'page' => _n_noop( '%s pages', '%s pages' ),
 * );
 * ...
 * $message = $messages[ $type ];
 * $usable_text = sprintf( translate_nooped_plural( $message, $count ), $count );
 *
 * @since 2.0.0
 *       
 * @param string $singular
 *            Single form to be i18ned.
 * @param string $plural
 *            Plural form to be i18ned.
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings.
 * @return array array($singular, $plural)
 */
function _n_noop($singular, $plural, $domain = null)
{
    return array(
        0 => $singular,
        1 => $plural,
        'singular' => $singular,
        'plural' => $plural,
        'context' => null,
        'domain' => $domain
    );
}

/**
 * Register plural strings with context in POT file, but don't translate them.
 *
 * @since 2.0.0
 * @param string $singular            
 * @param string $plural            
 * @param string $context            
 * @param string|null $domain            
 * @return array
 */
function _nx_noop($singular, $plural, $context, $domain = null)
{
    return array(
        0 => $singular,
        1 => $plural,
        2 => $context,
        'singular' => $singular,
        'plural' => $plural,
        'context' => $context,
        'domain' => $domain
    );
}

/**
 * Translate the result of _n_noop() or _nx_noop().
 *
 * @since 2.0.0
 *       
 * @param array $nooped_plural
 *            Array with singular, plural and context keys, usually the result of _n_noop() or _nx_noop()
 * @param int $count
 *            Number of objects
 * @param string $domain
 *            Optional. Text domain. Unique identifier for retrieving translated strings. If $nooped_plural contains
 *            a text domain passed to _n_noop() or _nx_noop(), it will override this value.
 * @return string Either $single or $plural translated text.
 */
function translate_nooped_plural($nooped_plural, $count, $domain = 'default')
{
    if ($nooped_plural['domain'])
        $domain = $nooped_plural['domain'];
    
    if ($nooped_plural['context'])
        return _nx($nooped_plural['singular'], $nooped_plural['plural'], $count, $nooped_plural['context'], $domain);
    else
        return _n($nooped_plural['singular'], $nooped_plural['plural'], $count, $domain);
}

/**
 * Load a .
 * mo file into the text domain $domain.
 *
 * If the text domain already exists, the translations will be merged. If both
 * sets have the same string, the translation from the original value will be taken.
 *
 * On success, the .mo file will be placed in the $l10n global by $domain
 * and will be a MO object.
 *
 * @since 2.0.0
 *       
 * @param string $domain
 *            Text domain. Unique identifier for retrieving translated strings.
 * @param string $mofile
 *            Path to the .mo file.
 * @return bool True on success, false on failure.
 */
function load_textdomain($domain, $mofile)
{
    global $l10n;
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter text domain and/or MO file path for loading translations.
         *
         * @since 2.1.1
         *       
         * @param bool $override
         *            Whether to override the text domain. Default false.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         * @param string $mofile
         *            Path to the MO file.
         */
        if (GB_Hooks::apply_filters('override_load_textdomain', false, $domain, $mofile))
            return true;
        
        /**
         * Fires before the MO translation file is loaded.
         *
         * @since 2.1.1
         *       
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         * @param string $mofile
         *            Path to the .mo file.
         */
        GB_Hooks::do_action('load_textdomain', $domain, $mofile);
        
        /**
         * Filter MO file path for loading translations for a specific text domain.
         *
         * @since 2.1.1
         *       
         * @param string $mofile
         *            Path to the MO file.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        $mofile = GB_Hooks::apply_filters('load_textdomain_mofile', $mofile, $domain);
    }
    
    if (! is_readable($mofile))
        return false;
    
    $mo = new MO();
    if (! $mo->import_from_file($mofile))
        return false;
    
    if (isset($l10n[$domain]))
        $mo->merge_with($l10n[$domain]);
    
    $l10n[$domain] = &$mo;
    
    return true;
}

/**
 * Unload translations for a text domain.
 *
 * @since 2.0.0
 *       
 * @param string $domain
 *            Text domain. Unique identifier for retrieving translated strings.
 * @return bool Whether textdomain was unloaded.
 */
function unload_textdomain($domain)
{
    global $l10n;
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter the text domain for loading translation.
         *
         * @since 2.1.1
         *       
         * @param bool $override
         *            Whether to override unloading the text domain. Default false.
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        if (GB_Hooks::apply_filters('override_unload_textdomain', false, $domain))
            return true;
        
        /**
         * Fires before the text domain is unloaded.
         *
         * @since 2.1.1
         *       
         * @param string $domain
         *            Text domain. Unique identifier for retrieving translated strings.
         */
        GB_Hooks::do_action('unload_textdomain', $domain);
    }
    
    if (isset($l10n[$domain])) {
        unset($l10n[$domain]);
        return true;
    }
    
    return false;
}

/**
 * Load default translated strings based on locale.
 *
 * Loads the .mo file in GB_LANG_DIR constant path from GeniBase root.
 * The translated (.mo) file is named based on the locale.
 *
 * @see load_textdomain()
 *
 * @since 2.0.0
 *       
 * @param string $locale
 *            Optional. Locale to load. Default is the value of {@see get_locale()}.
 * @return bool Whether the textdomain was loaded.
 */
function load_default_textdomain($locale = null)
{
    if (null === $locale)
        $locale = get_locale();
        
        // Unload previously loaded strings so we can switch translations.
    unload_textdomain('default');
    
    $return = load_textdomain('default', GB_LANG_DIR . "/$locale.mo");
    
    // TODO: admin
    // if( /* is_admin() || */ defined( 'GB_INSTALLING' ) || ( defined( 'GB_REPAIRING' ) && GB_REPAIRING ) )
    // load_textdomain( 'default', GB_LANG_DIR . "/admin-$locale.mo" );
    
    return $return;
}

/**
 * Return the Translations instance for a text domain.
 *
 * If there isn't one, returns empty Translations instance.
 *
 * @since 2.0.0
 *       
 * @param string $domain
 *            Text domain. Unique identifier for retrieving translated strings.
 * @return NOOP_Translations A Translations instance.
 */
function get_translations_for_domain($domain)
{
    global $l10n;
    if (! isset($l10n[$domain])) {
        $l10n[$domain] = new NOOP_Translations();
    }
    return $l10n[$domain];
}

/**
 * Whether there are translations for the text domain.
 *
 * @since 2.0.0
 *       
 * @param string $domain
 *            Text domain. Unique identifier for retrieving translated strings.
 * @return bool Whether there are translations.
 */
function is_textdomain_loaded($domain)
{
    global $l10n;
    return isset($l10n[$domain]);
}

/**
 * Get all available languages based on the presence of *.mo files in a given directory.
 *
 * The default directory is GB_LANG_DIR.
 *
 * @since 2.0.0
 *       
 * @param string $dir
 *            A directory to search for language files.
 *            Default GB_LANG_DIR.
 * @return array An array of language codes or an empty array if no languages are present. Language codes are formed by stripping the .mo extension from the language file names.
 */
function get_available_languages($dir = null)
{
    $languages = array();
    
    foreach ((array) glob((is_null($dir) ? GB_LANG_DIR : $dir) . '/*.mo') as $lang_file) {
        $lang_file = basename($lang_file, '.mo');
        // if( 0 !== strpos( $lang_file, 'continents-cities' ) && 0 !== strpos( $lang_file, 'ms-' ) &&
        // 0 !== strpos( $lang_file, 'admin-' ))
        $languages[] = $lang_file;
    }
    
    return $languages;
}

/**
 * Get installed translations.
 *
 * Looks in the gb-content/languages directory for translations of
 * plugins or themes.
 *
 * @since 2.0.0
 *       
 * @param string $type
 *            What to search for. Accepts 'plugins', 'themes', 'core'.
 * @return array Array of language data.
 */
function gb_get_installed_translations($type)
{
    if ($type !== 'themes' && $type !== 'plugins' && $type !== 'core')
        return array();
    
    $dir = 'core' === $type ? '' : "/$type";
    
    if (! is_dir(GB_LANG_DIR))
        return array();
    
    if ($dir && ! is_dir(GB_LANG_DIR . $dir))
        return array();
    
    $files = scandir(GB_LANG_DIR . $dir);
    if (! $files)
        return array();
    
    $language_data = array();
    
    foreach ($files as $file) {
        if ('.' === $file[0] || is_dir($file)) {
            continue;
        }
        if (substr($file, - 3) !== '.po') {
            continue;
        }
        if (! preg_match('/(?:(.+)-)?([A-Za-z_]{2,6}).po/', $file, $match)) {
            continue;
        }
        if (! in_array(substr($file, 0, - 3) . '.mo', $files)) {
            continue;
        }
        
        list (, $textdomain, $language) = $match;
        if ('' === $textdomain) {
            $textdomain = 'default';
        }
        $language_data[$textdomain][$language] = gb_get_pomo_file_data(GB_LANG_DIR . "$dir/$file");
    }
    return $language_data;
}

/**
 * Extract headers from a PO file.
 *
 * @since 2.0.0
 *       
 * @param string $po_file
 *            Path to PO file.
 * @return array PO file headers.
 */
function gb_get_pomo_file_data($po_file)
{
    $headers = get_file_data($po_file, array(
        'POT-Creation-Date' => '"POT-Creation-Date',
        'PO-Revision-Date' => '"PO-Revision-Date',
        'Project-Id-Version' => '"Project-Id-Version',
        'X-Generator' => '"X-Generator'
    ));
    foreach ($headers as $header => $value) {
        // Remove possible contextual '\n' and closing double quote.
        $headers[$header] = preg_replace('~(\\\n)?"$~', '', $value);
    }
    return $headers;
}
