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
namespace GeniBase\DBase;

use Gedcomx\Common\ExtensibleData;

/**
 *
 * @author Limych
 */
class GeniBaseInternalProperties
{

    protected $properties;

    /**
     *
     * @param mixed[] $properties
     */
    public function __construct($properties = null)
    {
        if (null !== $properties) {
            $this->setProperties($properties);
        }
    }

    /**
     *
     * @return mixed[]
     */
    public function getProperties()
    {
        return empty($this->properties) ? array() : $this->properties;
    }

    /**
     *
     * @param mixed[] $properties
     */
    public function setProperties($properties)
    {
        if (is_array($properties)) {
            $this->properties = $properties;
        }
    }

    /**
     *
     * @param mixed $key
     * @return NULL|mixed
     */
    public function getProperty($key)
    {
        return empty($this->properties) || ! isset($this->properties[$key])
            ? null : $this->properties[$key];
    }

    /**
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function setProperty($key, $value)
    {
        if (empty($this->properties)) {
            $this->properties = array();
        }
        $this->properties[$key] = $value;
    }

    /**
     *
     * @param ExtensibleData $object
     * @return mixed[]
     */
    public static function getPropertiesOf(ExtensibleData $object)
    {
        /**
 * @var self $ex
*/
        $ex = $object->findExtensionOfType(self::class);

        return (null === $ex) ? array() : $ex->getProperties();
    }

    /**
     *
     * @param ExtensibleData $object
     * @param mixed[]        $properties
     */
    public static function setPropertiesOf(ExtensibleData $object, $properties)
    {
        /**
 * @var self $ex
*/
        $ex = $object->findExtensionOfType(self::class);
        if (null === $ex) {
            $object->addExtensionElement(new self($properties));
        } else {
            $ex->setProperties($properties);
        }
    }

    /**
     *
     * @param ExtensibleData $object
     * @param mixed          $key
     * @return NULL|mixed
     */
    public static function getPropertyOf(ExtensibleData $object, $key)
    {
        /**
 * @var self $ex
*/
        $ex = $object->findExtensionOfType(self::class);

        return (null === $ex) ? null : $ex->getProperty($key);
    }

    /**
     *
     * @param ExtensibleData $object
     * @param mixed          $key
     * @param mixed          $value
     */
    public static function setPropertyOf(ExtensibleData $object, $key, $value)
    {
        /** @var self $ex */
        $ex = $object->findExtensionOfType(self::class);
        if (null === $ex) {
            $object->addExtensionElement(
                new self(array(
                $key => $value
                ))
            );
        } else {
            $ex->setProperty($key, $value);
        }
    }
}
