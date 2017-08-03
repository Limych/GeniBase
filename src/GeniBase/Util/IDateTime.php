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

use DateTime;

/**
 *
 * @author Limych
 *
 */
class IDateTime extends DateTime
{

    const SQL = 'Y-m-d H:i:s';

    protected $formatters = [];

    /**
     * Register new formatter for datetime.
     *
     * @param string $key Formatter key.
     * @param callable $callback Callback for formatter.
     */
    public function addFormatter($key, callable $callback)
    {
        $this->formatters[$key] = $callback;
    }

    /**
     * {@inheritDoc}
     * @see DateTime::format()
     */
    public function format($format)
    {
        $regex = '/(?<!\\\\)('.implode('|', array_map('preg_quote', array_keys($this->formatters))).')/u';
        $format = preg_split($regex, $format, null, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        for ($i = 0; $i < count($format); $i++) {
            if (! empty($format[$i])) {
                $result .= parent::format($format[$i]);
            }
            if (! empty($key = $format[++$i]) && ! empty($this->formatters[$key])) {
                $result .= call_user_func($this->formatters[$key], $this);
            }
        }

        return $result;
    }
}
