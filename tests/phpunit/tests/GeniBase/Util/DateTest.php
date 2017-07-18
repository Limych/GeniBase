<?php
namespace GeniBase\Tests;

use Gedcomx\Util\SimpleDate;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Util::Date.
 */
class DateTest extends TestCase
{
    /**
     * @covers GeniBase\Util\Date::expand
     */
    public function testExpand()
    {
        $date = new SimpleDate();
        $date->parse('+1024');

        // TODO
//         $this->assertEquals(
//             '',
//             Util\Date::expand($date)
//         );
    }

}
