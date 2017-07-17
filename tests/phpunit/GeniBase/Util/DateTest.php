<?php
namespace GeniBase\Tests;

use PHPUnit\Framework\TestCase;
use Gedcomx\Util\SimpleDate;
use GeniBase\Util;

/**
 * Test class for Util::Date.
 */
class DateTest extends TestCase
{
    /**
     * @covers GeniBase\Server\Util\Date::expand
     */
    public function testExpand()
    {
        $date = new SimpleDate();
        $date->parse('+1024');
        
        $this->assertEquals(
            '',
            Util\Date::expand($date)
        );
    }

}
