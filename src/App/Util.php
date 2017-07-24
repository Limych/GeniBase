<?php

namespace App;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assorted static functions.
 *
 * @author Limych
 */
class Util
{

    public static function numberFormat(Application $app, $number, $decimals = 2)
    {
        $last_locale = setlocale(LC_ALL, 0);
        if (isset($app['locale'])) {
            setlocale(LC_ALL, $app['locale']);
        }

        $locale = localeconv();

        $locale_id = strtok(setlocale(LC_NUMERIC, 0), '_.;');
        switch ($locale_id) {
            case 'ru':
                $locale['thousands_sep'] = ' ';
                break;
        }

        $formatted = number_format(
            $number,
            $decimals,
            $locale['decimal_point'],
            $locale['thousands_sep']
        );

        switch ($locale_id) {
            case 'ru':
                $tmp = explode($locale['decimal_point'], $formatted);
                if (5 == strlen($tmp[0])) {
                    $tmp[0] = strtr($tmp[0], [$locale['thousands_sep'] => '']);
                    $formatted = implode($locale['decimal_point'], $tmp);
                }
                break;
        }

        setlocale(LC_ALL, $last_locale);
        return $formatted;
    }

    /**
     *
     * @return number $miliseconds
     */
    public static function executionTime()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows: The real time is measured.
            $spendMiliseconds = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;

        } else {
            // On Linux: Any time spent on activity that happens outside the execution
            //           of the script such as system calls using system(), stream operations
            //           database queries, etc. is not included.
            //           @see http://php.net/manual/en/function.set-time-limit.php
            $resourceUsages = getrusage();
            $spendMiliseconds = $resourceUsages['ru_utime.tv_sec'] * 1000 + $resourceUsages['ru_utime.tv_usec'] / 1000;
        }
        return (int) $spendMiliseconds;
    }

    /**
     * Check if more that `$miliseconds` ms remains
     * to error `PHP Fatal error: Maximum execution time exceeded`
     *
     * @param  number $miliseconds
     * @return bool
     *
     * @copyright https://stackoverflow.com/users/5747291/martin
     */
    public static function isRemainingExecutionTimeBiggerThan($miliseconds = 5000)
    {
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time === 0) {
            // No script time limitation
            return true;
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows: The real time is measured.
            $spendMiliseconds = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        } else {
            // On Linux: Any time spent on activity that happens outside the execution
            //           of the script such as system calls using system(), stream operations
            //           database queries, etc. is not included.
            //           @see http://php.net/manual/en/function.set-time-limit.php
            $resourceUsages = getrusage();
            $spendMiliseconds = $resourceUsages['ru_utime.tv_sec'] * 1000 + $resourceUsages['ru_utime.tv_usec'] / 1000;
        }
        $remainingMiliseconds = $max_execution_time * 1000 - $spendMiliseconds;
        return ($remainingMiliseconds >= $miliseconds);
    }
}
