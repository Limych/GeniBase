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

class PlacesProcessor
{

    const LIST_START = 'ListStart';
    const LIST_END = 'ListEnd';
    const PLACE = 'Place';

    const CONTRACT_URI = false;
    const EXPAND_URI = true;

    protected $callback;

    public function __construct($callback)
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException('Argument MUST be callable');
        }
        $this->callback = $callback;
    }

    public static function run($input, $callback)
    {
        $upd = new self($callback);
        return $upd->process($input);
    }

    protected $cnt;

    protected $max;

    protected $cnt_entity;

    protected $lastPlace;

    protected $resource;

    protected $tokens;

    /**
     *
     * @param resource|string $input
     *
     * @throws \InvalidArgumentException
     */
    public function process($input)
    {
        if (is_resource($input)) {
            $res = get_resource_type($input);
            if (!in_array($res, array( 'stream' ))) {
                throw new \InvalidArgumentException("Unsupported resource type ($res)");
            }
            $this->resource = $input;
            $this->tokens = array();
            $this->max = null;
        } else {
            $this->resource = null;
            $this->tokens = self::parseInput($input);
            $this->max = count($this->tokens);
        }

        $this->cnt = $this->cnt_entity = 0;
        $this->lastPlace = null;
        $this->processBlock();
    }

    protected static function parseInput($data)
    {
        return preg_split('/\s*([\{\}\n])\s*/u', $data, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    }

    protected function getToken()
    {
        if (empty($this->tokens) && ! empty($this->resource)) {
            switch (get_resource_type($this->resource)) {
                case 'stream':
                    $input = fgets($this->resource);
                    if (false === $input) {
                        throw new \RuntimeException('Source stream read error');
                    }
                    break;
            }
            $this->tokens = self::parseInput($input);
        }

        if (! empty($this->tokens)) {
            return array_shift($this->tokens);
        }

        return null;
    }

    protected function processBlock($input = null, $parentPlace = null)
    {
        if (false === call_user_func($this->callback, self::LIST_START, $parentPlace)) {
            return false;
        }

        $prevToken = null;
        $lastPlace = $parentPlace;
        $queue = array();
        while (true) {
            if (isset($input)) {
                $token = array_shift($input);
            } else {
                $token = $this->getToken();
            }
            if (! isset($token)) {
                break;
            }

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
                        $this->cnt --;
                    } elseif (false === $this->processToken($token[0], $parentPlace)) {
                        return false;
                    } else {
                        $lastPlace = $this->lastPlace;
                    }
                    break;
            }
        }

        if (! empty($queue) && (
            false === $this->processToken($prevToken, $parentPlace)
            || false === $this->processBlock($queue, $this->lastPlace)
        )) {
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

    static $namespaces = array(
        'wd:' => 'http://www.wikidata.org/entity/',
        'gb:' => 'http://genibase.net/',
    );

    public static function registerNamespace($namespace, $uri)
    {
        self::$namespaces[$namespace . ':'] = $uri;
    }

    public static function processURI($uri, $mode = self::EXPAND_URI)
    {
        switch ($mode) {
            case self::EXPAND_URI:
                $uri = preg_replace(
                    array_map(__CLASS__ . '::pregPrepare', array_keys(self::$namespaces)),
                    array_values(self::$namespaces),
                    $uri
                );
                break;
            case self::CONTRACT_URI:
                $uri = preg_replace(
                    array_map(__CLASS__ . '::pregPrepare', array_values(self::$namespaces)),
                    array_keys(self::$namespaces),
                    $uri
                );
                break;
            default:
                throw new \InvalidArgumentException('Unsupported mode');
        }
        return $uri;
    }

    protected function processToken($input, $parentPlace)
    {
        $data = preg_split('/\s+(\??[#@]\S+|\??\%[^%]*\%|\??\[[^\s\]]*\]?)\s*/u', $input, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $input = array(
            'rdfs:label' => array()
        );
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
                    $token = self::processURI(substr($token, 1, - 1));
                    break;
                case '?%':
                    $key = 'disputedType';
                    $token = self::processURI(substr($token, 2, - 1));
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
                        $input[$key] = array(
                            $input[$key]
                        );
                    }
                    $input[$key][] = $token;
                }
            }
        }
        $input['label'] = trim(implode(' ', $input['rdfs:label']));
        $input['rdfs:label'] = self::expandNames($input['label']);
        $input['count'] = ++$this->cnt_entity;
        $this->lastPlace = $input;
        if (false === call_user_func($this->callback, self::PLACE, $input, $parentPlace, $this->cnt, $this->max)) {
            return false;
        }
        return true;
    }

    public static function expandNames($name)
    {
        return self::expandNamesProcessor($name);
    }

    private static function expandNamesProcessor(&$input)
    {
        $input = trim($input);
        $queue = array();
        $bracket = false;
        do {
            $tokens = preg_split('!\s*([(),;/])\s*!', $input, 2, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            if ($bracket) {
                $queue = array_map(function ($v) use ($tokens) {
                    return "$v ${tokens[0]}";
                }, $queue);
                $bracket = false;
            } else {
                $queue[] = $tokens[0];
            }

            if (1 === count($tokens)) {
                return $queue;
            }
            switch ($tokens[1]) {
                case ',':
                case ';':
                case '/':
                    break;
                case '(':
                    $queue = array_merge($queue, self::expandNamesProcessor($tokens[2]));
                    $bracket = true;
                    break;
            }
            $input = @trim($tokens[2]);
        } while (! empty($input) && (')' !== $tokens[1]));

        return $queue;
    }
}
