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

    public static function isAssoc($array)
    {
        if (! is_array($array) || array() === $array) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
