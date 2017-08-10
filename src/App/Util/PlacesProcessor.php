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

class PlacesProcessor {

    const LIST_START    = 'ListStart';
    const LIST_END      = 'ListEnd';
    const PLACE         = 'Place';

    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public static function run($input, callable $callback)
    {
        $upd = new self($callback);
        return $upd->process($input);
    }

    protected $cnt;
    protected $max;
    protected $cnt_entity;
    protected $lastPlace;

    public function process($input)
    {
        $input = preg_split('/\s*([\{\}\n])\s*/u', $input, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $this->cnt = $this->cnt_entity = 0;
        $this->max = count($input);
        $this->lastPlace = null;
        return $this->processBlock($input);
    }

    protected function processBlock(&$input, $parentPlace = null)
    {
        if (false === call_user_func($this->callback, self::LIST_START, $parentPlace)) {
            return false;
        }
        $prevToken = null;
        $lastPlace = $parentPlace;
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
                    if (false === $this->processBlock($input, $lastPlace)) {
                        return false;
                    }
                    break;
                case '}':
                    break 2;
                default:
                    // Process line
                    $token = preg_split('/\s*([>])\s*/u', $token, 2, PREG_SPLIT_NO_EMPTY);
                    if (($prevToken !== $token[0]) && ! empty($queue)) {
                        if (false === $this->processToken($prevToken, $parentPlace)) {
                            return false;
                        }
                        $lastPlace = $this->lastPlace;
                        if (false === $this->processBlock($queue, $lastPlace)) {
                            return false;
                        }
                    }
                    if (! empty($token[1])) {
                        $prevToken = $token[0];
                        $queue[] = $token[1];
                        $this->cnt--;
                    } elseif (false === $this->processToken($token[0], $parentPlace)) {
                        return false;
                    } else {
                        $lastPlace = $this->lastPlace;
                    }
                    break;
            }
        }
        if (! empty($queue)
            && (
                false === $this->processToken($prevToken, $parentPlace)
                || false === $this->processBlock($queue, $this->lastPlace)
            )
        ) {
            return false;
        }
        if (false === call_user_func($this->callback, self::LIST_END, $parentPlace)) {
            return false;
        }
        return true;
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
                array_map(self::class.'::pregPrepare', array_keys($namespaces)),
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

    protected function processToken($input, $parentPlace)
    {
        $data = preg_split('/\s+(\??[#%@]\S+|\??\[[^\s\]]+\]?)\s*/u', $input, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $input = [
            'rdfs:label'  => [],
        ];
        while (! empty($data)) {
            $token = array_shift($data);
            $type = substr($token, 0, 1);
            if ($type === '?') {
                $type .= substr($token, 1, 1);
            }
            unset($key);
            switch ($type) {
                case '%':
                    $key = 'rdf:type';
                    $token = self::processURI(substr($token, 1));
                    break;
                case '?%':
                    $key = 'disputedType';
                    $token = self::processURI(substr($token, 2));
                    break;
                case '#':
                    $key = 'owl:sameAs';
                    $token = self::processURI(substr($token, 1));
                    break;
                case '?#':
                    $key = 'disputedSameAs';
                    $token = self::processURI(substr($token, 2));
                    break;
                case '@':
                    $key = 'location';
                    $token = substr($token, 1);
                    break;
                case '?@':
                    $key = 'disputedLocation';
                    $token = substr($token, 2);
                    break;
                case '[':
                    $key = 'gx:temporalDescription';
                    $token = rtrim(substr($token, 1), ']');
                    break;
                case '?[':
                    $key = 'disputedTemporal';
                    $token = rtrim(substr($token, 2), ']');
                    break;
                default:
                    $input['rdfs:label'][] = $token;
                    break;
            }
            if (! empty($key)) {
                if (empty($input[$key])) {
                    $input[$key] = $token;
                } else {
                    if (! is_array($input[$key])) {
                        $input[$key] = [$input[$key]];
                    }
                    $input[$key][] = $token;
                }
            }
        }
        $input['label'] = implode(' ', $input['rdfs:label']);
        $input['rdfs:label'] = self::expandNames($input['label']);
        $input['count'] = ++$this->cnt_entity;
        $this->lastPlace = $input;
        if (false === call_user_func($this->callback, self::PLACE, $input, $parentPlace, $this->cnt, $this->max)) {
            return false;
        }
        return true;
    }

    public static function expandNames($input)
    {
        $tokens = preg_split('!\s*([(),;/])\s*!', $input, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $names = [];
        while (! empty($tokens)) {
            $tok = array_shift($tokens);
            switch ($tok) {
                case '(':
                case ',':
                case ';':
                case '/':
                    break;
                case ')':
                    $tok = array_shift($tokens);
                    $names = array_map(function ($v) use ($tok) {
                        return trim("$v $tok");
                    }, $names);
                        break;
                default:
                    $names[] = $tok;
                    break;
            }
        }
        return $names;
    }
}
