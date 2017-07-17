<?php
namespace GeniBase\Util;

use Gedcomx\Util\SimpleDate;

/**
 *
 * @author Limych
 *        
 */
class Date
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
            $per = array_map(function ($v) { return -$v; }, $per);
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
    
}
