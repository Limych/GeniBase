<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 * @link https://github.com/Limych/GeniBase
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */
namespace GeniBase\Util;

/**
 *
 */
class Profiler
{

    protected static $timersRoot = array();
    protected static $timers;

    public static function startTimer($name, $stopPrevious = false)
    {
        if (empty($name)) {
            return;
        }
        $mt = microtime(true) * 1000;
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
                    self::$timers[$currentTimer]->subtimers = array();
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
        $mt = microtime(true) * 1000;
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

    public static function omitSubtimers($omit = true)
    {
        if (! empty(self::$timers)) {
            $currentTimer = self::$timers[''];
            if (! empty(self::$timers[$currentTimer]->active)) {
                self::$timers[$currentTimer]->omitSubtimers = $omit;
            }
        }
    }

    protected static function dumpLevel($timers, $prefix = '')
    {
        $mt = microtime(true) * 1000;
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
            echo sprintf("%70s %s ", $sname, $prefix . (!$isLast ? '&#9507;' : '&#9495;')) .
                sprintf(
                    "%6.0f ms/%3d = %7.1f ms\n",
                    $timers[$name]->period,
                    $timers[$name]->counter,
                    $timers[$name]->period / $timers[$name]->counter
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
