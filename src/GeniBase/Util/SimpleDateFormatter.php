<?php
namespace GeniBase\Util;

use Gedcomx\Util\SimpleDate;

/**
 *
 * @author Limych
 *
 */
class SimpleDateFormatter
{

    public static $months1 = [
        1 => 'января',      2 => 'февраля',     3 => 'марта',   4 => 'апреля',
        5 => 'мая',         6 => 'июня',        7 => 'июля',    8 => 'августа',
        9 => 'сентября',   10 => 'октября',    11 => 'ноября', 12 => 'декабря'
    ];
    public static $months2 = [
        1 => 'январь',      2 => 'февраль',     3 => 'март',    4 => 'апрель',
        5 => 'май',         6 => 'июнь',        7 => 'июль',    8 => 'август',
        9 => 'сентябрь',   10 => 'октябрь',    11 => 'ноябрь', 12 => 'декабрь'
    ];

    public static $formats = [
        'YMDhms'    => 'j F Y, H:i:s',
        'YMDhm'     => 'j F Y, H:i',
        'YMDh'      => 'j F Y, H:??',
        'YMD'       => 'j F Y',
        'YM'        => 'f Y',
        'Y'         => 'Y',
    ];

    public static function format(SimpleDate $date)
    {
        $dt = new IDateTime();
        $dt->addFormatter('F', function ($d) {
            return self::$months1[$d->format('n')];
        });
        $dt->addFormatter('f', function ($d) {
            return self::$months2[$d->format('n')];
        });

        $year = $date->getYear();
        $format = 'Y';
        if (null !== $month = $date->getMonth()) {
            $format .= 'M';
            if (null !== $day = $date->getDay()) {
                $format .= 'D';
                $dt->setDate($year, $month, $day);
                if (null !== $hour = $date->getHour()) {
                    $format .= 'h';
                    if (null !== $minute = $date->getMinute()) {
                        $format .= 'm';
                        if (null !== $second = $date->getSecond()) {
                            $format .= 's';
                            $dt->setTime($hour, $minute, $second);
                        } else {
                            $dt->setTime($hour, $minute, 0);
                        }
                    } else {
                        $dt->setTime($hour, 0, 0);
                    }
                }
            } else {
                $dt->setDate($year, $month, 1);
            }
        } else {
            $dt->setDate($year, 1, 1);
        }

        return $dt->format(self::$formats[$format]);
    }
}
