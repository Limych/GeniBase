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
namespace App\Util;

class MsCsv
{

    const UTF8_BOM = "\xEF\xBB\xBF";

    public static function fPutBom($handle)
    {
        fwrite($handle, self::UTF8_BOM); // Write BOM
    }

    /**
     * Write array as CSV record to file using MS Excel notation.
     *
     * @param resource $handle File handler
     * @param array $csv_arr Array to write to file
     * @param string $delimiter Set the field delimiter (one character only).
     * @param string $enclosure Set the field enclosure character (one character only).
     * @return boolean|number Number of written chars, false on error.
     */
    public static function fPutCsv($handle, array $csv_arr, $delimiter = ';', $enclosure = '"')
    {
        if (! is_array($csv_arr)) {
            return false;
        }

        for ($i = 0, $n = count($csv_arr); $i < $n; $i ++) {
            if (! is_numeric($csv_arr[$i])) {
                // Quote string and double all quotes inside string
                $csv_arr[$i] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $csv_arr[$i]) . $enclosure;
            }
            // If we have a dot inside a number, quote that number too
            if (($delimiter == '.') && (is_numeric($csv_arr[$i]))) {
                $csv_arr[$i] = $enclosure . $csv_arr[$i] . $enclosure;
            }
        }

        $str = implode($delimiter, $csv_arr) . "\n";
        fwrite($handle, $str);

        return strlen($str);
    }

    /**
     * Read one record from CSV file using MS Excel notation.
     *
     * @param resource $handle File handler
     * @param string $delimiter Set the field delimiter (one character only).
     * @param string $enclosure Set the field enclosure character (one character only).
     * @param string $escape Set the escape character (one character only). Defaults as a backslash.
     * @return array|null|false An indexed array containing the fields read.<br/><br/>
     * A blank line in a CSV file will be returned as an array comprising a single null field, and will not be treated as an error.
     */
    public static function fGetCsv($handle, $delimiter = ';', $enclosure = '"', $escape = '\\')
    {
        $input = fgets($handle);
        if (0 === strncmp($input, self::UTF8_BOM, 3)) {
            // Strip BOM
            $input = substr($input, 3);
        }
        $csv_arr = str_getcsv($input, $delimiter, $enclosure, $escape);
        return ($csv_arr);
    }

    /**
     * Parse a CSV string to array using MS Excel notation.
     *
     * @param string $input The string to parse.
     * @param string $delimiter Set the field delimiter (one character only).
     * @param string $enclosure Set the field enclosure character (one character only).
     * @param string $escape Set the escape character (one character only). Defaults as a backslash.
     * @return array An indexed array containing the fields read.<br/><br/>
     * A blank line in a CSV file will be returned as an array comprising a single null field, and will not be treated as an error.
     */
    public static function strGetCsv($input, $delimiter = ';', $enclosure = '"', $escape = '\\')
    {
        $csv_arr = str_getcsv($input, $delimiter, $enclosure, $escape);
        return ($csv_arr);
    }

    /**
     *
     * @param resource $fp File handler
     * @param callable $callback
     * @param mixed $startId
     * @param string $delimiter Set the field delimiter (one character only).
     * @param string $enclosure Set the field enclosure character (one character only).
     * @param string $escape Set the escape character (one character only). Defaults as a backslash.
     * @return number|null|false Returns null if an invalid handle is supplied or false on other errors.
     */
    public static function fGetCsvIterator($fp, callable $callback, $startId = null, $delimiter = ';', $enclosure = '"', $escape = null)
    {
        $cnt = 0;
        while (is_array($record = self::fGetCsv($fp, 0, $delimiter, $enclosure, $escape))) {
            if ((null !== $startId) && ($startId > $record[0])) {
                continue;
            }
            $cnt++;
            if (false === call_user_func($callback, $record)) {
                return $cnt;
            }
        }
        if (feof($fp)) {
            return $cnt;
        }
        return $record;
    }
}
