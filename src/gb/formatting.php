<?php
/**
 * Main GeniBase Formatting API.
 *
 * Handles many functions for formatting output.
 *
 * @package GeniBase
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

/**
 * Checks for invalid UTF8 in a string.
 *
 * @since 2.0.0
 *       
 * @param string $string
 *            The text which is to be checked.
 * @param boolean $strip
 *            Optional. Whether to attempt to strip out invalid UTF8. Default is false.
 * @return string The checked text.
 */
function gb_check_invalid_utf8($string, $strip = false)
{
    $string = (string) $string;
    
    if (0 === strlen($string))
        return '';
        
        // Check for support for utf8 in the installed PCRE library once and store the result in a static
    static $utf8_pcre;
    if (! isset($utf8_pcre))
        $utf8_pcre = @preg_match('/^./u', 'a');
        // We can't demand utf8 in the PCRE installation, so just return the string in those cases
    if (! $utf8_pcre)
        return $string;
        
        // preg_match fails when it encounters invalid UTF8 in $string
    if (1 === @preg_match('/^./us', $string))
        return $string;
        
        // Attempt to strip the bad chars if requested (not recommended)
    if ($strip && function_exists('iconv'))
        return iconv('utf-8', 'utf-8', $string);
    
    return '';
}

/**
 * Converts a number of HTML entities into their special characters.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to decode " entities,
 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
 *
 * @since 2.0.0
 *       
 * @param string $string
 *            The text which is to be decoded.
 * @param mixed $quote_style
 *            Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old _gb_specialchars() values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @return string The decoded text without HTML entities.
 */
function gb_specialchars_decode($string, $quote_style = ENT_NOQUOTES)
{
    $string = (string) $string;
    
    if (0 === strlen($string))
        return '';
        
        // Don't bother if there are no entities - saves a lot of processing
    if (strpos($string, '&') === false)
        return $string;
        
        // Match the previous behaviour of _gb_specialchars() when the $quote_style is not an accepted value
    if (empty($quote_style))
        $quote_style = ENT_NOQUOTES;
    elseif (! in_array($quote_style, array(
        0,
        2,
        3,
        'single',
        'double'
    ), true))
        $quote_style = ENT_QUOTES;
        
        // More complete than get_html_translation_table( HTML_SPECIALCHARS )
    $single = array(
        '&#039;' => '\'',
        '&#x27;' => '\''
    );
    $single_preg = array(
        '/&#0*39;/' => '&#039;',
        '/&#x0*27;/i' => '&#x27;'
    );
    $double = array(
        '&quot;' => '"',
        '&#034;' => '"',
        '&#x22;' => '"'
    );
    $double_preg = array(
        '/&#0*34;/' => '&#034;',
        '/&#x0*22;/i' => '&#x22;'
    );
    $others = array(
        '&lt;' => '<',
        '&#060;' => '<',
        '&gt;' => '>',
        '&#062;' => '>',
        '&amp;' => '&',
        '&#038;' => '&',
        '&#x26;' => '&'
    );
    $others_preg = array(
        '/&#0*60;/' => '&#060;',
        '/&#0*62;/' => '&#062;',
        '/&#0*38;/' => '&#038;',
        '/&#x0*26;/i' => '&#x26;'
    );
    
    if ($quote_style === ENT_QUOTES) {
        $translation = array_merge($single, $double, $others);
        $translation_preg = array_merge($single_preg, $double_preg, $others_preg);
    } elseif ($quote_style === ENT_COMPAT || $quote_style === 'double') {
        $translation = array_merge($double, $others);
        $translation_preg = array_merge($double_preg, $others_preg);
    } elseif ($quote_style === 'single') {
        $translation = array_merge($single, $others);
        $translation_preg = array_merge($single_preg, $others_preg);
    } elseif ($quote_style === ENT_NOQUOTES) {
        $translation = $others;
        $translation_preg = $others_preg;
    }
    
    // Remove zero padding on numeric entities
    $string = preg_replace(array_keys($translation_preg), array_values($translation_preg), $string);
    
    // Replace characters according to translation table
    return strtr($string, $translation);
}

/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to encode " to
 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since 2.0.0
 * @access private
 *        
 * @param string $string
 *            The text which is to be encoded.
 * @param int $quote_style
 *            Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @param string $charset
 *            Optional. The character encoding of the string. Default is false.
 * @param boolean $double_encode
 *            Optional. Whether to encode existing html entities. Default is false.
 * @return string The encoded text with HTML entities.
 */
function _gb_specialchars($string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false)
{
    $string = (string) $string;
    
    if (0 === strlen($string))
        return '';
        
        // Don't bother if there are no specialchars — saves some processing
    if (! preg_match('/[&<>"\']/', $string))
        return $string;
        
        // Account for the previous behaviour of the function when the $quote_style is not an accepted value
    if (empty($quote_style))
        $quote_style = ENT_NOQUOTES;
    elseif (! in_array($quote_style, array(
        0,
        2,
        3,
        'single',
        'double'
    ), TRUE))
        $quote_style = ENT_QUOTES;
    
    $charset = 'UTF-8';
    $_quote_style = $quote_style;
    
    if ($quote_style === 'double') {
        $quote_style = ENT_COMPAT;
        $_quote_style = ENT_COMPAT;
    } elseif ($quote_style === 'single')
        $quote_style = ENT_NOQUOTES;
        
        // Handle double encoding ourselves
    if ($double_encode)
        $string = @htmlspecialchars($string, $quote_style, $charset);
    else {
        // Decode &amp; into &
        $string = gb_specialchars_decode($string, $_quote_style);
        
        // Guarantee every &entity; is valid or re-encode the &
        $string = gb_kses_normalize_entities($string);
        
        // Now re-encode everything except &entity;
        $string = preg_split('/(&#?x?[0-9a-z]+;)/i', $string, - 1, PREG_SPLIT_DELIM_CAPTURE);
        
        for ($i = 0; $i < count($string); $i += 2)
            $string[$i] = @htmlspecialchars($string[$i], $quote_style, $charset);
        
        $string = implode('', $string);
    }
    
    // Backwards compatibility
    if ('single' === $_quote_style)
        $string = str_replace("'", '&#039;', $string);
    
    return $string;
}

/**
 * Escaping for HTML attributes.
 *
 * @since 2.0.0
 *       
 * @param string $text            
 * @return string
 */
function esc_attr($text)
{
    $safe_text = gb_check_invalid_utf8($text);
    $safe_text = _gb_specialchars($safe_text, ENT_QUOTES);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter a string cleaned and escaped for output in an HTML attribute.
         *
         * Text passed to esc_attr() is stripped of invalid or special characters
         * before output.
         *
         * @since 2.1.0
         *       
         * @param string $safe_text
         *            The text after it has been escaped.
         * @param string $text
         *            The text prior to being escaped.
         */
        $safe_text = GB_Hooks::apply_filters('escape_attribute', $safe_text, $text);
    }
    
    return $safe_text;
}

/**
 * Escaping for HTML blocks.
 *
 * @since 2.0.0
 *       
 * @param string $text            
 * @return string
 */
function esc_html($text)
{
    $safe_text = gb_check_invalid_utf8($text);
    $safe_text = _gb_specialchars($safe_text, ENT_QUOTES);
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter a string cleaned and escaped for output in HTML.
         *
         * Text passed to esc_html() is stripped of invalid or special characters
         * before output.
         *
         * @since 2.1.0
         *       
         * @param string $safe_text
         *            The text after it has been escaped.
         * @param string $text
         *            The text prior to being escaped.
         */
        $safe_text = GB_Hooks::apply_filters('escape_html', $safe_text, $text);
    }
    
    return $safe_text;
}

/**
 * Escape single quotes, htmlspecialchar " < > &, and fix line endings.
 *
 * Escapes text strings for echoing in JS. It is intended to be used for inline JS
 * (in a tag attribute, for example onclick="..."). Note that the strings have to
 * be in single quotes. The filter 'js_escape' is also applied here.
 *
 * @since 2.0.0
 *       
 * @param string $text
 *            The text to be escaped.
 * @return string Escaped text.
 */
function esc_js($text)
{
    $safe_text = gb_check_invalid_utf8($text);
    $safe_text = _gb_specialchars($safe_text, ENT_COMPAT);
    $safe_text = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe_text));
    $safe_text = str_replace("\r", '', $safe_text);
    $safe_text = str_replace("\n", '\\n', addslashes($safe_text));
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter a string cleaned and escaped for output in JavaScript.
         *
         * Text passed to esc_js() is stripped of invalid or special characters,
         * and properly slashed for output.
         *
         * @since 2.1.0
         *       
         * @param string $safe_text
         *            The text after it has been escaped.
         * @param string $text
         *            The text prior to being escaped.
         */
        $safe_text = GB_Hooks::apply_filters('js_escape', $safe_text, $text);
    }
    
    return $safe_text;
}

/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behaviour) ampersands are also replaced. The 'clean_url' filter
 * is applied to the returned cleaned URL.
 *
 * @since 2.0.0
 *       
 * @param string $url
 *            The URL to be cleaned.
 * @param array $protocols
 *            Optional. An array of acceptable protocols.
 *            Defaults to 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn' if not set.
 * @param string $_context
 *            Private. Use esc_url_raw() for database usage.
 * @return string The cleaned $url after the 'clean_url' filter is applied.
 */
function esc_url($url, $protocols = null, $_context = 'display')
{
    $original_url = $url;
    
    if ('' == $url)
        return $url;
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
    $strip = array(
        '%0d',
        '%0a',
        '%0D',
        '%0A'
    );
    $url = _deep_replace($strip, $url);
    $url = str_replace(';//', '://', $url);
    /*
     * If the URL doesn't appear to contain a scheme, we
     * presume it needs http:// appended (unless a relative
     * link starting with /, # or ? or a php file).
     */
    if (strpos($url, ':') === false && ! in_array($url[0], array(
        '/',
        '#',
        '?'
    )) && ! preg_match('/^[a-z0-9-]+?\.php/i', $url))
        $url = 'http://' . $url;
        
        // Replace ampersands and quotes only when displaying.
    if ('display' == $_context) {
        $url = gb_kses_normalize_entities($url);
        $url = str_replace('&amp;', '&#038;', $url);
        $url = str_replace("'", '&#039;', $url);
        $url = str_replace('"', '&#034;', $url);
    }
    
    if ('/' === $url[0]) {
        $good_protocol_url = $url;
    } else {
        if (! is_array($protocols))
            $protocols = gb_allowed_protocols();
        $good_protocol_url = gb_kses_bad_protocol($url, $protocols);
        if (strtolower($good_protocol_url) != strtolower($url))
            return '';
    }
    
    if (class_exists('GB_Hooks')) {
        /**
         * Filter a string cleaned and escaped for output as a URL.
         *
         * @since 2.1.0
         *       
         * @param string $good_protocol_url
         *            The cleaned URL to be returned.
         * @param string $original_url
         *            The URL prior to cleaning.
         * @param string $_context
         *            If 'display', replace ampersands and single quotes only.
         */
        $good_protocol_url = GB_Hooks::apply_filters('clean_url', $good_protocol_url, $original_url, $_context);
    }
    
    return $good_protocol_url;
}

/**
 * Performs esc_url() for database usage.
 *
 * @since 2.2.3
 *       
 * @param string $url
 *            The URL to be cleaned.
 * @param array $protocols
 *            An array of acceptable protocols.
 * @return string The cleaned URL.
 */
function esc_url_raw($url, $protocols = null)
{
    return esc_url($url, $protocols, 'db');
}

/**
 * Perform a deep string replace operation to ensure the values in $search are no longer present
 *
 * Repeats the replacement operation until it no longer replaces anything so as to remove "nested" values
 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
 * str_replace would return
 *
 * @since 2.0.0
 * @access private
 *        
 * @param string|array $search
 *            The value being searched for, otherwise known as the needle. An array may be used to designate multiple needles.
 * @param string $subject
 *            The string being searched and replaced on, otherwise known as the haystack.
 * @return string The string with the replaced svalues.
 */
function _deep_replace($search, $subject)
{
    $subject = (string) $subject;
    
    $count = 1;
    while ($count) {
        $subject = str_replace($search, '', $subject, $count);
    }
    
    return $subject;
}

/**
 * Navigates through an array and encodes the values to be used in a URL.
 *
 * @since 2.0.0
 *       
 * @param array|string $value
 *            The array or string to be encoded.
 * @return array|string $value The encoded array (or string from the callback).
 */
function urlencode_deep($value)
{
    $value = is_array($value) ? array_map('urlencode_deep', $value) : urlencode($value);
    return $value;
}

/**
 * Appends a trailing slash.
 *
 * Will remove trailing forward and backslashes if it exists already before adding
 * a trailing forward slash. This prevents double slashing a string or path.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since 2.2.2
 *       
 * @param string $string
 *            What to add the trailing slash to.
 * @return string String with trailing slash added.
 */
function trailingslashit($string)
{
    return untrailingslashit($string) . '/';
}

/**
 * Removes trailing forward slashes and backslashes if they exist.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since 2.2.2
 *       
 * @param string $string
 *            What to remove the trailing slashes from.
 * @return string String without the trailing slashes.
 */
function untrailingslashit($string)
{
    return rtrim($string, '/\\');
}

/**
 * Navigates through an array and removes slashes from the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 *
 * @since 2.3.0
 *       
 * @param mixed $value
 *            The value to be stripped.
 * @return mixed Stripped value.
 */
function stripslashes_deep($value)
{
    if (is_array($value)) {
        $value = array_map('stripslashes_deep', $value);
    } elseif (is_object($value)) {
        $vars = get_object_vars($value);
        foreach ($vars as $key => $data) {
            $value->{$key} = stripslashes_deep($data);
        }
    } elseif (is_string($value)) {
        $value = stripslashes($value);
    }
    
    return $value;
}

/**
 * Sanitizes a username, stripping out unsafe characters.
 *
 * Removes tags, octets, entities, and if strict is enabled, will only keep
 * alphanumeric, _, space, ., -, @. After sanitizing, it passes the username,
 * raw username (the username in the parameter), and the value of $strict as
 * parameters for the 'sanitize_user' filter.
 *
 * @since 3.0.0
 *       
 * @param string $username
 *            The username to be sanitized.
 * @param bool $strict
 *            If set limits $username to specific characters. Default false.
 * @return string The sanitized username, after passing through filters.
 */
function sanitize_user($username, $strict = false)
{
    $raw_username = $username;
    $username = gb_strip_all_tags($username);
    $username = remove_accents($username);
    // Kill octets
    $username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username);
    $username = preg_replace('/&.+?;/', '', $username); // Kill entities
                                                        
    // If strict, reduce to ASCII for max portability.
    if ($strict)
        $username = preg_replace('|[^a-z0-9 _\.\-@]|i', '', $username);
    
    $username = trim($username);
    // Consolidate contiguous whitespace
    $username = preg_replace('|\s+|', ' ', $username);
    
    /**
     * Filter a sanitized username string.
     *
     * @since 3.0.0
     *       
     * @param string $username
     *            Sanitized username.
     * @param string $raw_username
     *            The username prior to sanitization.
     * @param bool $strict
     *            Whether to limit the sanitization to specific characters. Default false.
     */
    return GB_Hooks::apply_filters('sanitize_user', $username, $raw_username, $strict);
}

/**
 * Strips out all characters that are not allowable in an email.
 *
 * @since 3.0.0
 *       
 * @param string $email
 *            Email address to filter.
 * @return string Filtered email address.
 */
function sanitize_email($email)
{
    $raw_email = $email;
    
    // Test for the minimum length the email can be
    if (strlen($email) < 3) {
        /**
         * Filter a sanitized email address.
         *
         * This filter is evaluated under several contexts, including 'email_too_short',
         * 'email_no_at', 'local_invalid_chars', 'domain_period_sequence', 'domain_period_limits',
         * 'domain_no_periods', 'domain_no_valid_subs', or no context.
         *
         * @since 3.0.0
         *       
         * @param string $email
         *            sanitized email address.
         * @param string $raw_email
         *            email address, as provided to sanitize_email().
         * @param string $message
         *            message to pass to the user.
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'email_too_short');
    }
    
    // Test for an @ character after the first position
    if (strpos($email, '@', 1) === false) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'email_no_at');
    }
    
    // Split out the local and domain parts
    list ($local, $domain) = explode('@', $email, 2);
    
    // LOCAL PART
    // Test for invalid characters
    $local = preg_replace('/[^a-z0-9\'\._+\-]/i', '', $local);
    if ('' === $local) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'local_invalid_chars');
    }
    
    // DOMAIN PART
    // Test for sequences of periods
    $domain = preg_replace('/\.{2,}/', '', $domain);
    if ('' === $domain) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'domain_period_sequence');
    }
    
    // Test for leading and trailing periods and whitespace
    $domain = trim($domain, " \t\n\r\0\x0B.");
    if ('' === $domain) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'domain_period_limits');
    }
    
    // Split the domain into subs
    $subs = explode('.', $domain);
    
    // Assume the domain will have at least two subs
    if (2 > count($subs)) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'domain_no_periods');
    }
    
    // Create an array that will contain valid subs
    $new_subs = array();
    
    // Loop through each sub
    foreach ($subs as $sub) {
        // Test for leading and trailing hyphens
        $sub = trim($sub, " \t\n\r\0\x0B-");
        
        // Test for invalid characters
        $sub = preg_replace('/[^a-z0-9-]+/i', '', $sub);
        
        // If there's anything left, add it to the valid subs
        if ('' !== $sub) {
            $new_subs[] = strtolower($sub);
        }
    }
    
    // If there aren't 2 or more valid subs
    if (2 > count($new_subs)) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'domain_no_valid_subs');
    }
    
    // If there are last sub have less than 2 chars
    if (2 > strlen(end($new_subs))) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('sanitize_email', '', $raw_email, 'domain_invalid');
    }
    
    // Join valid subs into the new domain
    $domain = join('.', $new_subs);
    
    // Put the email back together
    $email = $local . '@' . $domain;
    
    // Congratulations your email made it!
    /**
     * This filter is documented in gb/formatting.php
     */
    return GB_Hooks::apply_filters('sanitize_email', $email, $raw_email, null);
}

/**
 * Sanitizes a string key.
 *
 * Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes and underscores are allowed.
 *
 * @since 2.3.0
 *       
 * @param string $key
 *            String key
 * @return string Sanitized key
 */
function sanitize_key($key)
{
    $raw_key = $key;
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
    
    /**
     * Filter a sanitized key string.
     *
     * @since 2.3.0
     *       
     * @param string $key
     *            Sanitized key.
     * @param string $raw_key
     *            The key prior to sanitization.
     */
    return GB_Hooks::apply_filters('sanitize_key', $key, $raw_key);
}

/**
 * Add slashes to a string or array of strings.
 *
 * This should be used when preparing data for core API that expects slashed data.
 * This should not be used to escape data going directly into an SQL query.
 *
 * @since 2.3.0
 *       
 * @param string|array $value
 *            String or array of strings to slash.
 * @return string|array Slashed $value
 */
function gb_slash($value)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = gb_slash($v);
            } else {
                $value[$k] = addslashes($v);
            }
        }
    } else {
        $value = addslashes($value);
    }
    
    return $value;
}

/**
 * Remove slashes from a string or array of strings.
 *
 * This should be used to remove slashes from data passed to core API that
 * expects data to be unslashed.
 *
 * @since 2.3.0
 *       
 * @param string|array $value
 *            String or array of strings to unslash.
 * @return string|array Unslashed $value
 */
function gb_unslash($value)
{
    return stripslashes_deep($value);
}

/**
 * Properly strip all HTML tags including script and style
 *
 * This differs from strip_tags() because it removes the contents of
 * the `<script>` and `<style>` tags. E.g. `strip_tags( '<script>something</script>' )`
 * will return 'something'. gb_strip_all_tags will return ''
 *
 * @since 3.0.0
 *       
 * @param string $string
 *            String containing HTML tags
 * @param bool $remove_breaks
 *            optional Whether to remove left over line breaks and white space chars
 * @return string The processed string.
 */
function gb_strip_all_tags($string, $remove_breaks = false)
{
    $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
    $string = strip_tags($string);
    
    if ($remove_breaks)
        $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
    
    return trim($string);
}

/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * @since 3.0.0
 *       
 * @param string $string
 *            Text that might have accent characters
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents($string)
{
    if (! preg_match('/[\x80-\xff]/', $string))
        return $string;
    
    if (seems_utf8($string)) {
        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(194) . chr(170) => 'a',
            chr(194) . chr(186) => 'o',
            chr(195) . chr(128) => 'A',
            chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A',
            chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A',
            chr(195) . chr(133) => 'A',
            chr(195) . chr(134) => 'AE',
            chr(195) . chr(135) => 'C',
            chr(195) . chr(136) => 'E',
            chr(195) . chr(137) => 'E',
            chr(195) . chr(138) => 'E',
            chr(195) . chr(139) => 'E',
            chr(195) . chr(140) => 'I',
            chr(195) . chr(141) => 'I',
            chr(195) . chr(142) => 'I',
            chr(195) . chr(143) => 'I',
            chr(195) . chr(144) => 'D',
            chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O',
            chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O',
            chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O',
            chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U',
            chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U',
            chr(195) . chr(157) => 'Y',
            chr(195) . chr(158) => 'TH',
            chr(195) . chr(159) => 's',
            chr(195) . chr(160) => 'a',
            chr(195) . chr(161) => 'a',
            chr(195) . chr(162) => 'a',
            chr(195) . chr(163) => 'a',
            chr(195) . chr(164) => 'a',
            chr(195) . chr(165) => 'a',
            chr(195) . chr(166) => 'ae',
            chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e',
            chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e',
            chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i',
            chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i',
            chr(195) . chr(175) => 'i',
            chr(195) . chr(176) => 'd',
            chr(195) . chr(177) => 'n',
            chr(195) . chr(178) => 'o',
            chr(195) . chr(179) => 'o',
            chr(195) . chr(180) => 'o',
            chr(195) . chr(181) => 'o',
            chr(195) . chr(182) => 'o',
            chr(195) . chr(184) => 'o',
            chr(195) . chr(185) => 'u',
            chr(195) . chr(186) => 'u',
            chr(195) . chr(187) => 'u',
            chr(195) . chr(188) => 'u',
            chr(195) . chr(189) => 'y',
            chr(195) . chr(190) => 'th',
            chr(195) . chr(191) => 'y',
            chr(195) . chr(152) => 'O',
            // Decompositions for Latin Extended-A
            chr(196) . chr(128) => 'A',
            chr(196) . chr(129) => 'a',
            chr(196) . chr(130) => 'A',
            chr(196) . chr(131) => 'a',
            chr(196) . chr(132) => 'A',
            chr(196) . chr(133) => 'a',
            chr(196) . chr(134) => 'C',
            chr(196) . chr(135) => 'c',
            chr(196) . chr(136) => 'C',
            chr(196) . chr(137) => 'c',
            chr(196) . chr(138) => 'C',
            chr(196) . chr(139) => 'c',
            chr(196) . chr(140) => 'C',
            chr(196) . chr(141) => 'c',
            chr(196) . chr(142) => 'D',
            chr(196) . chr(143) => 'd',
            chr(196) . chr(144) => 'D',
            chr(196) . chr(145) => 'd',
            chr(196) . chr(146) => 'E',
            chr(196) . chr(147) => 'e',
            chr(196) . chr(148) => 'E',
            chr(196) . chr(149) => 'e',
            chr(196) . chr(150) => 'E',
            chr(196) . chr(151) => 'e',
            chr(196) . chr(152) => 'E',
            chr(196) . chr(153) => 'e',
            chr(196) . chr(154) => 'E',
            chr(196) . chr(155) => 'e',
            chr(196) . chr(156) => 'G',
            chr(196) . chr(157) => 'g',
            chr(196) . chr(158) => 'G',
            chr(196) . chr(159) => 'g',
            chr(196) . chr(160) => 'G',
            chr(196) . chr(161) => 'g',
            chr(196) . chr(162) => 'G',
            chr(196) . chr(163) => 'g',
            chr(196) . chr(164) => 'H',
            chr(196) . chr(165) => 'h',
            chr(196) . chr(166) => 'H',
            chr(196) . chr(167) => 'h',
            chr(196) . chr(168) => 'I',
            chr(196) . chr(169) => 'i',
            chr(196) . chr(170) => 'I',
            chr(196) . chr(171) => 'i',
            chr(196) . chr(172) => 'I',
            chr(196) . chr(173) => 'i',
            chr(196) . chr(174) => 'I',
            chr(196) . chr(175) => 'i',
            chr(196) . chr(176) => 'I',
            chr(196) . chr(177) => 'i',
            chr(196) . chr(178) => 'IJ',
            chr(196) . chr(179) => 'ij',
            chr(196) . chr(180) => 'J',
            chr(196) . chr(181) => 'j',
            chr(196) . chr(182) => 'K',
            chr(196) . chr(183) => 'k',
            chr(196) . chr(184) => 'k',
            chr(196) . chr(185) => 'L',
            chr(196) . chr(186) => 'l',
            chr(196) . chr(187) => 'L',
            chr(196) . chr(188) => 'l',
            chr(196) . chr(189) => 'L',
            chr(196) . chr(190) => 'l',
            chr(196) . chr(191) => 'L',
            chr(197) . chr(128) => 'l',
            chr(197) . chr(129) => 'L',
            chr(197) . chr(130) => 'l',
            chr(197) . chr(131) => 'N',
            chr(197) . chr(132) => 'n',
            chr(197) . chr(133) => 'N',
            chr(197) . chr(134) => 'n',
            chr(197) . chr(135) => 'N',
            chr(197) . chr(136) => 'n',
            chr(197) . chr(137) => 'N',
            chr(197) . chr(138) => 'n',
            chr(197) . chr(139) => 'N',
            chr(197) . chr(140) => 'O',
            chr(197) . chr(141) => 'o',
            chr(197) . chr(142) => 'O',
            chr(197) . chr(143) => 'o',
            chr(197) . chr(144) => 'O',
            chr(197) . chr(145) => 'o',
            chr(197) . chr(146) => 'OE',
            chr(197) . chr(147) => 'oe',
            chr(197) . chr(148) => 'R',
            chr(197) . chr(149) => 'r',
            chr(197) . chr(150) => 'R',
            chr(197) . chr(151) => 'r',
            chr(197) . chr(152) => 'R',
            chr(197) . chr(153) => 'r',
            chr(197) . chr(154) => 'S',
            chr(197) . chr(155) => 's',
            chr(197) . chr(156) => 'S',
            chr(197) . chr(157) => 's',
            chr(197) . chr(158) => 'S',
            chr(197) . chr(159) => 's',
            chr(197) . chr(160) => 'S',
            chr(197) . chr(161) => 's',
            chr(197) . chr(162) => 'T',
            chr(197) . chr(163) => 't',
            chr(197) . chr(164) => 'T',
            chr(197) . chr(165) => 't',
            chr(197) . chr(166) => 'T',
            chr(197) . chr(167) => 't',
            chr(197) . chr(168) => 'U',
            chr(197) . chr(169) => 'u',
            chr(197) . chr(170) => 'U',
            chr(197) . chr(171) => 'u',
            chr(197) . chr(172) => 'U',
            chr(197) . chr(173) => 'u',
            chr(197) . chr(174) => 'U',
            chr(197) . chr(175) => 'u',
            chr(197) . chr(176) => 'U',
            chr(197) . chr(177) => 'u',
            chr(197) . chr(178) => 'U',
            chr(197) . chr(179) => 'u',
            chr(197) . chr(180) => 'W',
            chr(197) . chr(181) => 'w',
            chr(197) . chr(182) => 'Y',
            chr(197) . chr(183) => 'y',
            chr(197) . chr(184) => 'Y',
            chr(197) . chr(185) => 'Z',
            chr(197) . chr(186) => 'z',
            chr(197) . chr(187) => 'Z',
            chr(197) . chr(188) => 'z',
            chr(197) . chr(189) => 'Z',
            chr(197) . chr(190) => 'z',
            chr(197) . chr(191) => 's',
            // Decompositions for Latin Extended-B
            chr(200) . chr(152) => 'S',
            chr(200) . chr(153) => 's',
            chr(200) . chr(154) => 'T',
            chr(200) . chr(155) => 't',
            // Euro Sign
            chr(226) . chr(130) . chr(172) => 'E',
            // GBP (Pound) Sign
            chr(194) . chr(163) => '',
            // Vowels with diacritic (Vietnamese)
            // unmarked
            chr(198) . chr(160) => 'O',
            chr(198) . chr(161) => 'o',
            chr(198) . chr(175) => 'U',
            chr(198) . chr(176) => 'u',
            // grave accent
            chr(225) . chr(186) . chr(166) => 'A',
            chr(225) . chr(186) . chr(167) => 'a',
            chr(225) . chr(186) . chr(176) => 'A',
            chr(225) . chr(186) . chr(177) => 'a',
            chr(225) . chr(187) . chr(128) => 'E',
            chr(225) . chr(187) . chr(129) => 'e',
            chr(225) . chr(187) . chr(146) => 'O',
            chr(225) . chr(187) . chr(147) => 'o',
            chr(225) . chr(187) . chr(156) => 'O',
            chr(225) . chr(187) . chr(157) => 'o',
            chr(225) . chr(187) . chr(170) => 'U',
            chr(225) . chr(187) . chr(171) => 'u',
            chr(225) . chr(187) . chr(178) => 'Y',
            chr(225) . chr(187) . chr(179) => 'y',
            // hook
            chr(225) . chr(186) . chr(162) => 'A',
            chr(225) . chr(186) . chr(163) => 'a',
            chr(225) . chr(186) . chr(168) => 'A',
            chr(225) . chr(186) . chr(169) => 'a',
            chr(225) . chr(186) . chr(178) => 'A',
            chr(225) . chr(186) . chr(179) => 'a',
            chr(225) . chr(186) . chr(186) => 'E',
            chr(225) . chr(186) . chr(187) => 'e',
            chr(225) . chr(187) . chr(130) => 'E',
            chr(225) . chr(187) . chr(131) => 'e',
            chr(225) . chr(187) . chr(136) => 'I',
            chr(225) . chr(187) . chr(137) => 'i',
            chr(225) . chr(187) . chr(142) => 'O',
            chr(225) . chr(187) . chr(143) => 'o',
            chr(225) . chr(187) . chr(148) => 'O',
            chr(225) . chr(187) . chr(149) => 'o',
            chr(225) . chr(187) . chr(158) => 'O',
            chr(225) . chr(187) . chr(159) => 'o',
            chr(225) . chr(187) . chr(166) => 'U',
            chr(225) . chr(187) . chr(167) => 'u',
            chr(225) . chr(187) . chr(172) => 'U',
            chr(225) . chr(187) . chr(173) => 'u',
            chr(225) . chr(187) . chr(182) => 'Y',
            chr(225) . chr(187) . chr(183) => 'y',
            // tilde
            chr(225) . chr(186) . chr(170) => 'A',
            chr(225) . chr(186) . chr(171) => 'a',
            chr(225) . chr(186) . chr(180) => 'A',
            chr(225) . chr(186) . chr(181) => 'a',
            chr(225) . chr(186) . chr(188) => 'E',
            chr(225) . chr(186) . chr(189) => 'e',
            chr(225) . chr(187) . chr(132) => 'E',
            chr(225) . chr(187) . chr(133) => 'e',
            chr(225) . chr(187) . chr(150) => 'O',
            chr(225) . chr(187) . chr(151) => 'o',
            chr(225) . chr(187) . chr(160) => 'O',
            chr(225) . chr(187) . chr(161) => 'o',
            chr(225) . chr(187) . chr(174) => 'U',
            chr(225) . chr(187) . chr(175) => 'u',
            chr(225) . chr(187) . chr(184) => 'Y',
            chr(225) . chr(187) . chr(185) => 'y',
            // acute accent
            chr(225) . chr(186) . chr(164) => 'A',
            chr(225) . chr(186) . chr(165) => 'a',
            chr(225) . chr(186) . chr(174) => 'A',
            chr(225) . chr(186) . chr(175) => 'a',
            chr(225) . chr(186) . chr(190) => 'E',
            chr(225) . chr(186) . chr(191) => 'e',
            chr(225) . chr(187) . chr(144) => 'O',
            chr(225) . chr(187) . chr(145) => 'o',
            chr(225) . chr(187) . chr(154) => 'O',
            chr(225) . chr(187) . chr(155) => 'o',
            chr(225) . chr(187) . chr(168) => 'U',
            chr(225) . chr(187) . chr(169) => 'u',
            // dot below
            chr(225) . chr(186) . chr(160) => 'A',
            chr(225) . chr(186) . chr(161) => 'a',
            chr(225) . chr(186) . chr(172) => 'A',
            chr(225) . chr(186) . chr(173) => 'a',
            chr(225) . chr(186) . chr(182) => 'A',
            chr(225) . chr(186) . chr(183) => 'a',
            chr(225) . chr(186) . chr(184) => 'E',
            chr(225) . chr(186) . chr(185) => 'e',
            chr(225) . chr(187) . chr(134) => 'E',
            chr(225) . chr(187) . chr(135) => 'e',
            chr(225) . chr(187) . chr(138) => 'I',
            chr(225) . chr(187) . chr(139) => 'i',
            chr(225) . chr(187) . chr(140) => 'O',
            chr(225) . chr(187) . chr(141) => 'o',
            chr(225) . chr(187) . chr(152) => 'O',
            chr(225) . chr(187) . chr(153) => 'o',
            chr(225) . chr(187) . chr(162) => 'O',
            chr(225) . chr(187) . chr(163) => 'o',
            chr(225) . chr(187) . chr(164) => 'U',
            chr(225) . chr(187) . chr(165) => 'u',
            chr(225) . chr(187) . chr(176) => 'U',
            chr(225) . chr(187) . chr(177) => 'u',
            chr(225) . chr(187) . chr(180) => 'Y',
            chr(225) . chr(187) . chr(181) => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin)
            chr(201) . chr(145) => 'a',
            // macron
            chr(199) . chr(149) => 'U',
            chr(199) . chr(150) => 'u',
            // acute accent
            chr(199) . chr(151) => 'U',
            chr(199) . chr(152) => 'u',
            // caron
            chr(199) . chr(141) => 'A',
            chr(199) . chr(142) => 'a',
            chr(199) . chr(143) => 'I',
            chr(199) . chr(144) => 'i',
            chr(199) . chr(145) => 'O',
            chr(199) . chr(146) => 'o',
            chr(199) . chr(147) => 'U',
            chr(199) . chr(148) => 'u',
            chr(199) . chr(153) => 'U',
            chr(199) . chr(154) => 'u',
            // grave accent
            chr(199) . chr(155) => 'U',
            chr(199) . chr(156) => 'u'
        );
        
        // Used for locale-specific rules
        $locale = get_locale();
        
        if ('de_DE' == $locale) {
            $chars[chr(195) . chr(132)] = 'Ae';
            $chars[chr(195) . chr(164)] = 'ae';
            $chars[chr(195) . chr(150)] = 'Oe';
            $chars[chr(195) . chr(182)] = 'oe';
            $chars[chr(195) . chr(156)] = 'Ue';
            $chars[chr(195) . chr(188)] = 'ue';
            $chars[chr(195) . chr(159)] = 'ss';
        } elseif ('da_DK' === $locale) {
            $chars[chr(195) . chr(134)] = 'Ae';
            $chars[chr(195) . chr(166)] = 'ae';
            $chars[chr(195) . chr(152)] = 'Oe';
            $chars[chr(195) . chr(184)] = 'oe';
            $chars[chr(195) . chr(133)] = 'Aa';
            $chars[chr(195) . chr(165)] = 'aa';
        }
        
        $string = strtr($string, $chars);
    } else {
        // Assume ISO-8859-1 if not UTF-8
        $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158) . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194) . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202) . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210) . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218) . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227) . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235) . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243) . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251) . chr(252) . chr(253) . chr(255);
        
        $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
        
        $string = strtr($string, $chars['in'], $chars['out']);
        $double_chars['in'] = array(
            chr(140),
            chr(156),
            chr(198),
            chr(208),
            chr(222),
            chr(223),
            chr(230),
            chr(240),
            chr(254)
        );
        $double_chars['out'] = array(
            'OE',
            'oe',
            'AE',
            'DH',
            'TH',
            'ss',
            'ae',
            'dh',
            'th'
        );
        $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }
    
    return $string;
}

/**
 * Verifies that an email is valid.
 *
 * Does not grok i18n domains. Not RFC compliant.
 *
 * @since 3.0.0
 *       
 * @param string $email
 *            Email address to verify.
 * @return string|bool Either false or the valid email address.
 */
function is_email($email)
{
    // Test for the minimum length the email can be
    if (strlen($email) < 3) {
        /**
         * Filter whether an email address is valid.
         *
         * This filter is evaluated under several different contexts, such as 'email_too_short',
         * 'email_no_at', 'local_invalid_chars', 'domain_period_sequence', 'domain_period_limits',
         * 'domain_no_periods', 'sub_hyphen_limits', 'sub_invalid_chars', or no specific context.
         *
         * @since 3.0.0
         *       
         * @param bool $is_email
         *            Whether the email address has passed the is_email() checks. Default false.
         * @param string $email
         *            The email address being checked.
         * @param string $message
         *            An explanatory message to the user.
         * @param string $context
         *            Context under which the email was tested.
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'email_too_short');
    }
    
    // Test for an @ character after the first position
    if (strpos($email, '@', 1) === false) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'email_no_at');
    }
    
    // Split out the local and domain parts
    list ($local, $domain) = explode('@', $email, 2);
    
    // LOCAL PART
    // Test for invalid characters
    if (! preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local)) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'local_invalid_chars');
    }
    
    // DOMAIN PART
    // Test for sequences of periods
    if (preg_match('/\.{2,}/', $domain)) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'domain_period_sequence');
    }
    
    // Test for leading and trailing periods and whitespace
    if (trim($domain, " \t\n\r\0\x0B.") !== $domain) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'domain_period_limits');
    }
    
    // Split the domain into subs
    $subs = explode('.', $domain);
    
    // Assume the domain will have at least two subs
    if (2 > count($subs)) {
        /**
         * This filter is documented in gb/formatting.php
         */
        return GB_Hooks::apply_filters('is_email', false, $email, 'domain_no_periods');
    }
    
    // Loop through each sub
    foreach ($subs as $sub) {
        // Test for leading and trailing hyphens and whitespace
        if (trim($sub, " \t\n\r\0\x0B-") !== $sub) {
            /**
             * This filter is documented in gb/formatting.php
             */
            return GB_Hooks::apply_filters('is_email', false, $email, 'sub_hyphen_limits');
        }
        
        // Test for invalid characters
        if (! preg_match('/^[a-z0-9-]+$/i', $sub)) {
            /**
             * This filter is documented in gb/formatting.php
             */
            return GB_Hooks::apply_filters('is_email', false, $email, 'sub_invalid_chars');
        }
    }
    
    // Congratulations your email made it!
    /**
     * This filter is documented in gb/formatting.php
     */
    return GB_Hooks::apply_filters('is_email', $email, $email, null);
}

/**
 * Sanitizes a title, or returns a fallback title.
 *
 * Specifically, HTML and PHP tags are stripped. Further actions can be added
 * via the plugin API. If $title is empty and $fallback_title is set, the latter
 * will be used.
 *
 * @since 3.0.0
 *       
 * @param string $title
 *            The string to be sanitized.
 * @param string $fallback_title
 *            Optional. A title to use if $title is empty.
 * @param string $context
 *            Optional. The operation for which the string is sanitized
 * @return string The sanitized string.
 */
function sanitize_title($title, $fallback_title = '', $context = 'save')
{
    $raw_title = $title;
    
    if ('save' == $context)
        $title = remove_accents($title);
    
    /**
     * Filter a sanitized title string.
     *
     * @since 3.0.0
     *       
     * @param string $title
     *            Sanitized title.
     * @param string $raw_title
     *            The title prior to sanitization.
     * @param string $context
     *            The context for which the title is being sanitized.
     */
    $title = GB_Hooks::apply_filters('sanitize_title', $title, $raw_title, $context);
    
    if ('' === $title || false === $title)
        $title = $fallback_title;
    
    return $title;
}
