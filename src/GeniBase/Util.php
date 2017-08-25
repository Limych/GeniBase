<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 * @link https://github.com/Limych/GeniBase
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */
namespace GeniBase;

use FoxyTools\Fetcher;

/**
 * Assorted static functions.
 *
 * @author Limych
 */
class Util
{

    const USER_AGENT = 'Mozilla/5.0 (compatible; GeniBaseBot/0.1; +http://www.genibase.net/)';

    const CURL_TIMEOUT = 20000;

    /**
     *
     * @param array $array
     * @param mixed $_
     * @return array
     */
    public static function arraySliceKeys(array $array, $_)
    {
        $keys = func_get_args();
        $a = array_shift($keys);
        return array_intersect_key($a, array_flip($keys));
    }

    /**
     * Merge user defined arguments into defaults array.
     *
     * This function is used throughout GeniBase to allow for both string
     * or array to be merged into another array.
     *
     * @param  string|array|object $args     Value to merge with $defaults.
     * @param  string|array|object $defaults (Optional) Array that serves as the defaults. Default value: null
     * @return array Merged user defined values with defaults.
     */
    public static function parseArgs($args, $defaults = null)
    {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = & $args;
        } else {
            parse_str($args, $r);
        }

        if (is_object($defaults)) {
            $def = get_object_vars($defaults);
        } elseif (is_array($defaults)) {
            $def = & $defaults;
        } else {
            parse_str($defaults, $def);
        }

        if (! empty($def)) {
            return array_merge($def, $r);
        }

        return $r;
    }

    /**
     * Test a value for it is associative array.
     *
     * @param mixed $array
     * @return boolean
     */
    public static function isAssoc($array)
    {
        if (! is_array($array) || array() === $array) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Generate string with random characters.
     *
     * @param string $type
     * @param number $length
     * @param string $data
     * @return string
     */
    public static function hash($type = 'alnum', $length = 8, $data = null)
    {
        switch ($type) {
            case 'alnum':
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alpha':
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec':
                $pool = '0123456789abcdef';
                break;
            case 'numeric':
                $pool = '0123456789';
                break;
            case 'nozero':
                $pool = '123456789';
                break;
            case 'distinct':
                $pool = '34679ACEFHJKLMNPRTUVWXY';
                break;
            default:
                $pool = (string) $type;
                break;
        }

        if (empty($pool)) {
            return '';
        }

        $token = '';
        $max = strlen($pool);
        $log = log($max, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        for ($i = 0; $i < $length; $i ++) {
            if (! empty($data)) {
                // Predefined hash
                $rnd = hexdec(substr(md5($data.$i), 0, $bytes*2));
                $rnd = $rnd & $filter; // discard irrelevant bits
                while ($rnd >= $max) {
                    $rnd -= $max;
                }
            } else {
                // Random data
                do {
                    $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
                    $rnd = $rnd & $filter; // discard irrelevant bits
                } while ($rnd >= $max);
            }
            $token .= $pool[$rnd];
        }
        return $token;
    }

    public static function numberFormat($number, $decimals = 2, $locale = 'en')
    {
        $last_locale = setlocale(LC_ALL, 0);
        if (isset($locale)) {
            setlocale(LC_ALL, $locale);
        }

        $locale = localeconv();

        $locale_id = strtok(setlocale(LC_NUMERIC, 0), '_.;');
        switch ($locale_id) {
            case 'ru':
                $locale['thousands_sep'] = ' ';
                break;
        }

        $formatted = number_format(
            $number,
            $decimals,
            $locale['decimal_point'],
            $locale['thousands_sep']
            );

        switch ($locale_id) {
            case 'ru':
                $tmp = explode($locale['decimal_point'], $formatted);
                if (5 == strlen($tmp[0])) {
                    $tmp[0] = strtr($tmp[0], [$locale['thousands_sep'] => '']);
                    $formatted = implode($locale['decimal_point'], $tmp);
                }
                break;
        }

        setlocale(LC_ALL, $last_locale);
        return $formatted;
    }

    /**
     *
     * @return number $miliseconds
     */
    public static function executionTime()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows: The real time is measured.
            $spendMiliseconds = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        } else {
            // On Linux: Any time spent on activity that happens outside the execution
            //           of the script such as system calls using system(), stream operations
            //           database queries, etc. is not included.
            //           @see http://php.net/manual/en/function.set-time-limit.php
            $resourceUsages = getrusage();
            $spendMiliseconds = $resourceUsages['ru_utime.tv_sec'] * 1000 + $resourceUsages['ru_utime.tv_usec'] / 1000;
        }
        return (int) $spendMiliseconds;
    }

    /**
     * Check if more that `$miliseconds` ms remains
     * to error `PHP Fatal error: Maximum execution time exceeded`
     *
     * @param  number $miliseconds
     * @return bool
     *
     * @copyright https://stackoverflow.com/users/5747291/martin
     */
    public static function isRemainingExecutionTimeBiggerThan($miliseconds = 5000)
    {
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time === 0) {
            // No script time limitation
            return true;
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows: The real time is measured.
            $spendMiliseconds = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        } else {
            // On Linux: Any time spent on activity that happens outside the execution
            //           of the script such as system calls using system(), stream operations
            //           database queries, etc. is not included.
            //           @see http://php.net/manual/en/function.set-time-limit.php
            $resourceUsages = getrusage();
            $spendMiliseconds = $resourceUsages['ru_utime.tv_sec'] * 1000 + $resourceUsages['ru_utime.tv_usec'] / 1000;
        }
        $remainingMiliseconds = $max_execution_time * 1000 - $spendMiliseconds;
        return ($remainingMiliseconds >= $miliseconds);
    }

    /**
     * Get last line of text file.
     *
     * @param string $fpath Path to file
     * @return NULL|string Last line of file or NULL on error.
     */
    public static function fileGetLastLine($fpath)
    {
        $line = '';
        $cursor = -1;

        $handle = fopen($fpath, 'r');
        if (false === $handle) {
            return null;
        }

        /**
         * Trim trailing newline chars of the file
         */
        while (true) {
            fseek($handle, $cursor--, SEEK_END);
            $char = fgetc($handle);
            if ($char !== "\n" && $char !== "\r") {
                break;
            }
            $line = $char . $line;
        }

        /**
         * Read until the start of file or first newline char
         */
        while ($char !== false && $char !== "\n" && $char !== "\r") {
            $line = $char . $line;
            fseek($handle, $cursor--, SEEK_END);
            $char = fgetc($handle);
        }

        fclose($handle);
        return $line;
    }

    public static function printStatus($done, $max, $msg = '', $newLine = false)
    {
        static $lastMsgLen = 0;

        static $progressLen = 25;

        if ($max) {
            $prc = $done * 100 / $max;
            $progress = str_repeat('#', round($progressLen * $prc / 100));
            $sprc = rtrim(substr("$prc", 0, 5), '.');
        } else {
            $progress = str_repeat('?', $progressLen);
            $sprc = '??';
        }

        $msg = sprintf("\r[%'.-{$progressLen}s] %s%% %s", $progress, $sprc, $msg);

        if ($newLine) {
            $spc = "\n";
            $lastMsgLen = 0;
        } else {
            $spc = str_repeat(' ', max(0, ($lastMsgLen - strlen($msg))));
            $lastMsgLen = strlen($msg);
        }

        echo $msg . $spc;
    }

    public static function markdownify($content)
    {
        $url = 'http://fuckyeahmarkdown.com/go/';
        return Fetcher::fetchUrl($url, array(
            'html' => $content,
        ), array(
            'requireProxy' => false,
            'userAgent' => self::USER_AGENT,
            'timeout' => self::CURL_TIMEOUT,
        ));
    }
}
