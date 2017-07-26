<?php
namespace GeniBase\Util;

use Gedcomx\Util\FormalDate;
use Gedcomx\Util\SimpleDate;

/**
 *
 * @author Limych
 */
class DateUtil
{
    protected static $periods = [
        'year'  => 1,
        'month' => 1,
        'day'   => 16,
    ];

    public static function expand($isAverage, SimpleDate $date, $endOfRange = false)
    {
        $result = clone $date;
        $dt = new \DateTime($date);

        $per = self::$periods;
        if ($endOfRange) {
            $per = array_map(
                function ($v) {
                    return -$v;
                },
                $per
            );
        }

        if ($isAverage) {
            // Expand average date
            if (! empty($t = $result->getDay())) {
                $dt->sub(new \DateInterval('P' . self::$periods['day'] . 'D'));
            } elseif (! empty($t = $result->getMonth())) {
                $dt->sub(new \DateInterval('P' . self::$periods['month'] . 'M'));
            } elseif (! empty($t = $result->getYear())) {
                $dt->sub(new \DateInterval('P' . self::$periods['year'] . 'Y'));
            }

            $result->parse('+' . $dt->format(DATE_W3C)); // TODO: Remaster for BC dates
        }

        // Expand strict date
        // TODO

        $result->parse('+' . $dt->format(DATE_W3C)); // TODO: Remaster for BC dates
        return $result;
    }

    /**
     *
     * @param mixed $date
     * @return NULL[]|number[]
     */
    public static function calcPeriodInDays($date)
    {
        if (! $date instanceof FormalDate) {
            $tmp = $date;
            $date = new FormalDate();
            $date->parse($tmp);
            unset($tmp);
        }

        $start = $date->getStart();
        $end = $date->getEnd();

        if (! $date->getIsRange()) {
            $end = $start;
        }
        // TODO: Add logic for Reccuring and Durations

        $period = [
            self::calcDayOfEpoch($start),
            self::calcDayOfEpoch($end, true),
        ];

        if (($period[0] !== null) && ($period[1] !== null) && ($period[0] > $period[1])) {
            $period = [
                self::calcDayOfEpoch($end),
                self::calcDayOfEpoch($start, true),
            ];
        }

        return $period;
    }

    public static function calcDayOfEpoch($date, $calcEndOfPeriod = false)
    {
        static $mdays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if (empty($date) || (! $date->isValid() && ($date->getYear() !== 0))) {
            return null;
        }

        if ($mdays[12] < 100) {
            // First time initialization

            // Calculate number of days from start of (non leap) year
            $sum = 0;
            for ($i = 0; $i <= 12; $i++) {
                $sum += $mdays[$i];
                $mdays[$i] = $sum;
            }
        }

        $y = $date->getYear();
        $sign = 1;
        if ($y < 1) {
            $sign = -1;
            $y = 1 - $y;
        }

        $isLeap = (($y % 100 != 0) && ($y % 4 == 0)) || ($y % 400 == 0);

        $my = intval(($y-1) / 1000);        // Millenium
        $cy = intval(($y-1) / 100) % 1000;  // Century into millenium
        $sy = ($y-1) % 100;                 // Single years into century
        $k = $ky = ($y-1) *365 + $my *243 + $cy *24 + intval($cy / 4) + intval($sy / 4) + 1; // First day of year

        if (null !== $m = $date->getMonth()) {
            $k += $mdays[$m - 1] + intval($isLeap && $m > 2);  // First day of month

            if (null !== $d = $date->getDay()) {
                $k += $d - 1;
            } elseif ($calcEndOfPeriod) {
                $k = $ky + $mdays[$m] + intval($isLeap && $m >= 2) - 1;  // Last day of month
            }
        } elseif ($calcEndOfPeriod) {
            $my = intval($y / 1000);        // Millenium
            $cy = intval($y / 100) % 1000;  // Century into millenium
            $sy = $y % 100;                 // Single years into century
            $k = $y *365 + $my *243 + $cy *24 + intval($cy / 4) + intval($sy / 4); // First day of year
        }

        return $sign * $k;
    }
}
