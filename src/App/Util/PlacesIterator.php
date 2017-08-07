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

class PlacesIterator {

    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public static function process($input, callable $callback)
    {
        $upd = new self($callback);
        return $upd->processData($input);
    }

    protected $parent;

    protected $cnt;
    protected $max;

    public function processData($input)
    {
        $input = preg_split('/\s*([\{\}\n])\s*/u', $input, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $this->parent = null;
        $this->cnt = 0;
        $this->max = count($input);
        $output = $this->processBlock($input);
        return $output;
    }

    protected function processBlock(&$input, $prefix = '')
    {
        $output = '';
        $sep = '';
        $parent = null;
        $queue = [];
        while (! empty($input)) {
            $token = array_shift($input);
            $this->cnt++;
            switch ($token) {
                case "\n":
                    // Do nothing
                    break;
                case '{':
                    // Process block
                    $output .= " {\n" . $this->processBlock($input, "$prefix\t") . "$prefix}";
                    $sep = "\n";
                    break;
                case '}':
                    break 2;
                default:
                    // Process line
                    $token = preg_split('/\s*([>])\s*/u', $token, 2, PREG_SPLIT_NO_EMPTY);
                    if (($parent !== $token[0]) && ! empty($queue)) {
                        $output .= $sep . $this->processToken($parent, $prefix);
                        $output .= " {\n" . $this->processBlock($queue, "$prefix\t") . "$prefix}";
                        $queue = [];
                        $sep = "\n";
                    }
                    if (empty($token[1])) {
                        $output .= $sep . $this->processToken($token[0], $prefix);
                        $sep = "\n";
                    } elseif (! empty($token[1])) {
                        $parent = $token[0];
                        $queue[] = $token[1];
                        $this->cnt--;
                    }
                    break;
            }
        }
        if (! empty($queue)) {
            $output .= $sep . $this->processToken($parent, $prefix);
            $output .= " {\n" . $this->processBlock($queue, "$prefix\t") . "$prefix}";
        }
        $this->parent = null;
        return "$output\n";
    }

    protected static function pregPrepare($reg)
    {
        return '!^' . preg_quote($reg, '!') . '!';
    }

    public static function processURI($uri, $expand = true)
    {
        static $namespaces = [
            'wd:'    => 'http://www.wikidata.org/entity/',
//             'gb:'    => '//GeniBase/',
        ];

        if ($expand) {
            // Expand URI
            $uri = preg_replace(
                array_map(self::class . '::pregPrepare', array_keys($namespaces)),
                array_values($namespaces),
                $uri
            );
        } else {
            // Contract URI
            $uri = preg_replace(
                array_map(self::class . '::pregPrepare', array_values($namespaces)),
                array_keys($namespaces),
                $uri
            );
        }
        return $uri;
    }

    protected function processToken($input, $prefix)
    {
        $data = preg_split('/\s+(?:(\??[#%@])(\S+)|(\??\[)([^\s\]]+)\]?)\s*/u', $input, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $input = [
            'name'  => [],
        ];
        while (! empty($data)) {
            $token = array_shift($data);
            switch ($token) {
                case '%':
                    $input['type'] = self::processURI(array_shift($data));
                    break;
                case '%':
                    $input['disputedType'] = self::processURI(array_shift($data));
                    break;
                case '#':
                    $input['alias'] = self::processURI(array_shift($data));
                    break;
                case '?#':
                    $input['disputedAlias'] = self::processURI(array_shift($data));
                    break;
                case '?@':
                    $input['disputedLocation'] = array_shift($data);
                    break;
                case '@':
                    $input['location'] = array_shift($data);
                    break;
                case '?[':
                    $input['disputedTemporal'] = array_shift($data);
                    break;
                case '[':
                    $input['temporal'] = array_shift($data);
                    break;
                default:
                    $input['name'][] = $token;
                    break;
            }
        }
        $input['name'] = implode(' ', $input['name']);
        $input = call_user_func($this->callback, $input, $this->parent, $this->cnt, $this->max);
        $this->parent = $input;

        $output = $prefix . $input['name'];
        if (! empty($input['alias'])) {
            $output .= ' #' . self::processURI($input['alias'], false);
        } elseif (! empty($input['disputedAlias'])) {
            $output .= ' ?#' . self::processURI($input['disputedAlias'], false);
        }
        if (! empty($input['type'])) {
            $output .= ' %' . self::processURI($input['type'], false) . '%';
        } elseif (! empty($input['disputedType'])) {
            $output .= ' ?%' . self::processURI($input['disputedType'], false) . '%';
        }
        if (! empty($input['temporal'])) {
            $output .= ' [' . $input['temporal'] . ']';
        } elseif (! empty($input['disputedTemporal'])) {
            $output .= ' ?[' . $input['disputedTemporal'] . ']';
        }
        if (! empty($input['location'])) {
            $output .= ' @' . $input['location'];
        } elseif (! empty($input['disputedLocation'])) {
            $output .= ' ?@' . $input['disputedLocation'];
        }
        return $output;
    }

    public static function expandNames($name)
    {
        $names = [];
        if (! preg_match("/(.*?)\s*\(([^\)]+)\)(.*)/", $name, $matches)) {
            $names[] = $name;
        } else {
            $tmp = preg_split("/[;,]\s*/", $matches[2], null, PREG_SPLIT_NO_EMPTY);
            array_unshift($tmp, $matches[1]);
            foreach ($tmp as $y) {
                $names[] = $y . $matches[3];
            }
        }
        return $names;
    }
}
