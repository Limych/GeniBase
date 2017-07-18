<?php
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
        /**
 * @var self $ex
*/
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
