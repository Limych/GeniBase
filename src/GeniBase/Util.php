<?php
namespace GeniBase;

/**
 * Assorted static functions.
 *
 * @author Limych
 */
class Util
{

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
}
