<?php

namespace GeniBase\Tests;

/**
 * Assorted static functions.
 *
 * @author Limych
 *
 */
class PHPUnit_Util
{

    /**
     *
     * @param object $obj
     * @param string $name
     * @return mixed
     */
    public static function callMethod($obj, $name)
    {
        $_args  = func_get_args();
        $_obj   = array_shift($_args);
        $_name  = array_shift($_args);

        $class = new \ReflectionClass($_obj);
        $method = $class->getMethod($_name);
        $method->setAccessible(true);
        return $method->invokeArgs($_obj, $_args);
    }
}

