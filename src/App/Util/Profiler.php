<?php
namespace App\Util;

/**
 *
 * @author Limych
 *
 */
class Profiler
{

    protected static $timersRoot = [];
    protected static $timers;

    public static function startTimer($name, $stopPrevious = false)
    {
        if (empty($name)) {
            return;
        }
        $mt = microtime(true);
        if (empty(self::$timers)) {
            self::$timers = &self::$timersRoot;
            self::$timers[''] = null;
        }
        $currentTimer = self::$timers[''];
        if (($currentTimer !== $name) && ! empty(self::$timers[$currentTimer]->active)) {
            if (! empty(self::$timers[$currentTimer]->omitSubtimers)) {
                return;
            } elseif ($stopPrevious) {
                self::$timers[$currentTimer]->active = false;
                self::$timers[$currentTimer]->period += $mt - self::$timers[$currentTimer]->startTime;
            } else {
                if (empty(self::$timers[$currentTimer]->subtimers)) {
                    self::$timers[$currentTimer]->subtimers = [];
                }
                self::$timers = &self::$timers[$currentTimer]->subtimers;
            }
        }
        if (empty(self::$timers[$name])) {
            self::$timers[$name] = new \stdClass();
            self::$timers[$name]->counter = 0;
            self::$timers[$name]->period = 0;
        }
        if (empty(self::$timers[$name]->active)) {
            self::$timers[$name]->startTime = $mt;
        }
        self::$timers[$name]->active = true;
        self::$timers[$name]->counter++;
        self::$timers[$name]->omitSubtimers = false;
        self::$timers[''] = $name;
    }

    public static function stopTimer($name)
    {
        if (empty($name)) {
            return;
        }
        $mt = microtime(true);
        $timers = $prev = &self::$timersRoot;
        while ($timers[''] !== $name) {
            $prev = &$timers;
            $timers = &$timers[$timers['']]->subtimers;
            if (empty($timers)) {
                return;
            }
        }
        self::$timers = &$prev;
        while (! empty($timers) && $timers[$timers['']]->active) {
            $timers[$timers['']]->active = false;
            $timers[$timers['']]->omitSubtimers = false;
            $timers[$timers['']]->period += $mt - $timers[$timers['']]->startTime;
            $timers = &$timers[$timers['']]->subtimers;
        }
    }

    public static function omitSubtimers()
    {
        if (! empty(self::$timers)) {
            $currentTimer = self::$timers[''];
            if (! empty(self::$timers[$currentTimer]->active)) {
                self::$timers[$currentTimer]->omitSubtimers = true;
            }
        }
    }

    protected static function dumpLevel($timers, $prefix = '')
    {
        $mt = microtime(true);
        $keys = array_values(array_filter(array_keys($timers)));
        $max = count($keys) - 1;
        for ($i = 0; $i <= $max; $i++) {
            $sname = $name = $keys[$i];
            $isLast = ($i === $max);
            if (strlen($sname) > 70) {
                $sname = '…' . substr($sname, -69);
            }
            if ($timers[$name]->active) {
                $timers[$name]->period += $mt - $timers[$name]->startTime;
                $timers[$name]->startTime = $mt;
            }
            echo sprintf(
                "%70s %s %6.4fs / %3d = %7.4fs\n",
                $sname, $prefix . (!$isLast ? '&#9507;' : '&#9495;'),
                $timers[$name]->period, $timers[$name]->counter,
                ($timers[$name]->period / $timers[$name]->counter)
            );
            if (! empty($timers[$name]->subtimers)) {
                self::dumpLevel($timers[$name]->subtimers, $prefix . (!$isLast ? '&#9475; ' : '&#8229;&#8229;'));
            }
        }
    }

    public static function dumpTimers()
    {
        echo "\n<pre>";
        self::dumpLevel(self::$timersRoot);
        echo "</pre>\n";
    }
}
