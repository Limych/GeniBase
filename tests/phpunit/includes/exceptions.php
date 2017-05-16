<?php

class GB_Tests_Exception extends \PHPUnit\Framework\Exception
{
}

/**
 * General exception for gb_die()
 */
class GBDieException extends Exception
{
}

/**
 * Exception for cases of gb_die(), for ajax tests.
 * This means there was an error (no output, and a call to gb_die)
 *
 * @package GeniBase
 * @subpackage Unit Tests
 * @since 2.0.0
 */
class GBAjaxDieStopException extends GBDieException
{
}

/**
 * Exception for cases of gb_die(), for ajax tests.
 * This means execution of the ajax function should be halted, but the unit
 * test can continue. The function finished normally and there was not an
 * error (output happened, but gb_die was called to end execution) This is
 * used with GB_Ajax_Response::send
 *
 * @package GeniBase
 * @subpackage Unit Tests
 * @since 2.0.0
 */
class GBAjaxDieContinueException extends GBDieException
{
}
