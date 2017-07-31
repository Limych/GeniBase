<?php

namespace GeniBase\Tests\Util;

/**
 * DateUtil test case.
 */
use Gedcomx\Util\SimpleDate;
use GeniBase\Util\DateUtil;
use PHPUnit\Framework\TestCase;

class DateUtilTest extends TestCase
{

    /**
     * Tests DateUtil::expand()
     */
    public function testExpand()
    {
        // TODO Auto-generated DateUtilTest::testExpand()
//         $this->markTestIncomplete("expand test not implemented");

//         DateUtil::expand(/* parameters */);
    }

    /**
     * Tests DateUtil::calcPeriodInDays()
     */
    public function testCalcPeriodInDays()
    {
        static $tests = [
            '+0001-01-01/-0001-01-01'   => [-366,    1],
            '-0001-01-01/+0001-01-01'   => [-366,    1],
            '+0001-01-01'               => [   1,    1],
            '+0001-01-01/'              => [   1, null],
            '/+0001-01-01'              => [null,    1],
            'A+0001-01-01'              => [   1,    1],
//             '+0001-01-01/P17Y6M2D'      => [   1,    1],
//             '+0001-01-01/P186D'         => [   1,    1],
//             '+0001-01-01/P1000Y18M72DT56H10M1S' => [   1,    1],
            // TODO: Add tests for Reccuring and Durations
        ];

        foreach ($tests as $testDate => $expected) {
            $actual = DateUtil::calcPeriodInDays($testDate);
            $this->assertEquals($expected, $actual, $testDate);
        }
    }

    /**
     * Tests DateUtil::calcDayOfEpoch()
     */
    public function testCalcDayOfEpoch()
    {
        static $tests = [
            '+0001-01-01'   => [    1,      1],     '-0000-01-01'   => [   -1,     -1],
            '+0001-01'      => [    1,     31],     '-0000-01'      => [   -1,    -31],
            '+0001'         => [    1,    365],     '-0000'         => [   -1,   -365],
            '+0002-02-02'   => [  398,    398],     '-0001-02-02'   => [ -398,   -398],
            '+0002-02'      => [  397,    424],     '-0001-02'      => [ -397,   -424],
            '+0002'         => [  366,    730],     '-0001'         => [ -366,   -730],
            '+0004-02-04'   => [ 1130,   1130],     '-0003-02-04'   => [-1130,  -1130],
            '+0004-02'      => [ 1127,   1155],     '-0003-02'      => [-1127,  -1155],
            '+0004'         => [ 1096,   1461],     '-0003'         => [-1096,  -1461],
//             '+1000-02-01'   => [ 1130,   1130],     '-0999-02-01'   => [-1130,  -1130],
//             '+1000-02'      => [ 1127,   1155],     '-0999-02'      => [-1127,  -1155],
//             '+1000'         => [ 1096,   1461],     '-0999'         => [-1096,  -1461],
//             '+1100-02-01'   => [ 1130,   1130],     '-1099-02-01'   => [-1130,  -1130],
//             '+1100-02'      => [ 1127,   1155],     '-1099-02'      => [-1127,  -1155],
//             '+1100'         => [ 1096,   1461],     '-1099'         => [-1096,  -1461],
//             '+1400-02-01'   => [ 1130,   1130],     '-1399-02-01'   => [-1130,  -1130],
//             '+1400-02'      => [ 1127,   1155],     '-1399-02'      => [-1127,  -1155],
//             '+1400'         => [ 1096,   1461],     '-1399'         => [-1096,  -1461],
//             '+1800-02-01'   => [ 1130,   1130],     '-1799-02-01'   => [-1130,  -1130],
//             '+1800-02'      => [ 1127,   1155],     '-1799-02'      => [-1127,  -1155],
//             '+1800'         => [ 1096,   1461],     '-1799'         => [-1096,  -1461],
        ];

        $date = new SimpleDate();

        foreach ($tests as $testDate => $expected) {
            $date->parse($testDate);

            $actual = DateUtil::calcDayOfEpoch($date);
            $this->assertEquals($expected[0], $actual, $testDate);

            $actual = DateUtil::calcDayOfEpoch($date, true);
            $this->assertEquals($expected[1], $actual, $testDate);
        }
    }
}

