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

use Gedcomx\Util\Collection;

/**
 * Class JsonMapper
 *
 * @package GeniBase\Util
 *
 *          Stores the mapping between class names and JSON attribute names
 */
class JsonMapper
{
    private static $collection;

    /**
     * Initialize the collection object with the map
     */
    private static function init()
    {
        self::$collection = new Collection(
            array(
            //             'Gedcomx\Gedcomx' => 'gedcomx',
            )
        );
    }

    /**
     * Get the collection or initialize it if empty.
     *
     * @return Collection
     */
    private static function collection()
    {
        if (self::$collection == null) {
            self::init();
        }

        return self::$collection;
    }

    /**
     * Return whether or not we recognize the tag name
     *
     * @param string $key
     *
     * @return bool
     */
    public static function isKnownType($key)
    {
        return self::collection()->contains($key);
    }

    /**
     * Return the JSON attribute name for a given class name
     *
     * @param $class
     *
     * @return string
     */
    public static function getJsonKey($class)
    {
        return self::collection()->get($class);
    }

    /**
     * Return the class name associated with a JSON attribute name
     *
     * @param $json
     *
     * @return mixed
     */
    public static function getClassName($json)
    {
        return self::collection()->getKey($json);
    }
}
