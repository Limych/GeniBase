<?php
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
    private static function init(){
        self::$collection = new Collection(array(
//             'Gedcomx\Gedcomx' => 'gedcomx',
        ));
    }

    /**
     * Get the collection or initialize it if empty.
     *
     * @return Collection
     */
    private static function collection(){
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
