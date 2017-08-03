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
namespace GeniBase\Util;

/**
 * UUID class
 *
 * The following class generates VALID RFC 4122 COMPLIANT
 * Universally Unique IDentifiers (UUID) version 3, 4 and 5.
 *
 * UUIDs generated validates using OSSP UUID Tool, and output
 * for named-based UUIDs are exactly the same. This is a pure
 * PHP implementation.
 *
 * @author Andrew Moore
 * @link   http://www.php.net/manual/en/function.uniqid.php#94959
 */
class UUID
{
    const NIL = '00000000-0000-0000-0000-000000000000';

    const NAMESPACE_DNS  = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_URL  = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_OID  = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Generate v3 UUID
     *
     * Version 3 UUIDs are named based. They require a namespace (another
     * valid UUID) and a value (the name). Given the same namespace and
     * name, the output is always the same.
     *
     * @param uuid   $namespace
     * @param string $name
     */
    public static function v3($namespace, $name)
    {
        if (!self::isValid($namespace)) {
            return false;
        }

        // Get hexadecimal components of namespace
        $nhex = str_replace(array('-','{','}'), '', $namespace);

        // Binary Value
        $nstr = '';

        // Convert Namespace UUID to bits
        for ($i = 0; $i < strlen($nhex); $i+=2) {
            $nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
        }

        // Calculate hash value
        $hash = md5($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            // 32 bits for "time_low"
            substr($hash, 0, 8),
            // 16 bits for "time_mid"
            substr($hash, 8, 4),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 3
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            // 48 bits for "node"
            substr($hash, 20, 12)
        );
    }

    /**
     * Generate v4 UUID
     *
     * Version 4 UUIDs are pseudo-random.
     */
    public static function v4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate v5 UUID
     *
     * Version 5 UUIDs are named based. They require a namespace (another
     * valid UUID) and a value (the name). Given the same namespace and
     * name, the output is always the same.
     *
     * @param uuid   $namespace
     * @param string $name
     */
    public static function v5($namespace, $name)
    {
        if (!self::isValid($namespace)) {
            return false;
        }

        // Get hexadecimal components of namespace
        $nhex = str_replace(array('-','{','}'), '', $namespace);

        // Binary Value
        $nstr = '';

        // Convert Namespace UUID to bits
        for ($i = 0; $i < strlen($nhex); $i+=2) {
            $nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
        }

        // Calculate hash value
        $hash = sha1($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            // 32 bits for "time_low"
            substr($hash, 0, 8),
            // 16 bits for "time_mid"
            substr($hash, 8, 4),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 5
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            // 48 bits for "node"
            substr($hash, 20, 12)
        );
    }

    public static function isValid($uuid)
    {
        return preg_match(
            '/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
            '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i',
            $uuid
        ) === 1;
    }
}
