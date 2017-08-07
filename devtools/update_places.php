#!/usr/bin/env php
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

use App\Util\PlacesIterator;
use BorderCloud\SPARQL\Endpoint;
use App\Util;

require_once __DIR__ . '/../vendor/autoload.php';

define('VERSION', '0.1');
error_reporting(E_ALL);

if (! isset($argv)) {
    die("This script is for CLI mode only. Sorry.\n");
}

$showVersion = @in_array($argv[1], array('--version', '-V'));
$showHelp = @in_array($argv[1], array('/?', '--help', '-h'));

if ($showVersion || $showHelp) {
    echo "Places data updater v" . VERSION . ". Copyright (c) 2017 Andrey Khrolenok\n";
}
if ($showHelp) {
    $self = basename($argv[0], '.php');
    echo "\n" .
        "Usage: $self [source_php_file [target_file_or_dir]]\n" .
        "    or $self -- target_file\n";
}
if ($showVersion || $showHelp)  die;

array_shift($argv);
$src_fpath = array_shift($argv);
$dst_fpath = array_shift($argv);

if (! empty($dst_fpath)
    && (is_dir($dst_fpath) || in_array(substr($dst_fpath, -1), array('/', DIRECTORY_SEPARATOR)))
) {
    if ('--' == $src_fpath) {
        die2(3, "ERROR: Please declare target file, not directory.\n");
    }
    $dst_fpath .= DIRECTORY_SEPARATOR . basename($src_fpath);
}
if (empty($src_fpath) || ('--' == $src_fpath)) {
    $data = @file_get_contents('php://stdin');

} elseif (! file_exists($src_fpath) || ! is_readable($src_fpath) || (false === $data = @file_get_contents($src_fpath))) {
    die2(2, "ERROR: Can't read source file.\n");
}

$data = PlacesIterator::process($data, 'updatePlace');

if (empty($dst_fpath)) {
    echo $data;

} elseif (false === @file_put_contents($dst_fpath, $data)) {
    die2(3, "ERROR: Can't write to target file.\n");
}

die(0);



function die2($code, $msg)
{
    fwrite(STDERR, $msg . PHP_EOL);
    die($code);
}

function updatePlace($place, $parent, $cnt, $max)
{
    global $dst_fpath;

    if (! empty($dst_fpath)) {
        Util::printStatus($cnt, $max, "$cnt/$max tokens processed");
    }

    static $country;

    if (empty($country)) {
        $country = $place;
    }
    if (! empty($place['alias'])) {
        $query = " BIND( <${place['alias']}> AS ?item ) ";
    } else {
        $tmp = [];
        foreach (PlacesIterator::expandNames($place['name']) as $name) {
            $tmp[] = " { ?item rdfs:label \"$name\"@ru. } UNION { ?item skos:altLabel \"$name\"@ru. } ";
        }
        $query = implode('UNION', $tmp);

        $filter = [];
        if (! empty($country['alias'])) {
            $filter[] = "?item wd:P17 <${country['alias']}>.";
        }
        if (! empty($parent['alias'])) {
            $filter[] = "?item wd:P131 <${parent['alias']}>.";
        }
        if (! empty($filter)) {
            $filter = ' { ' . implode(' UNION ', $filter) . ' } ';
            $candidate = digBigData($place, $parent, $query . $filter);
            if ($place !== $candidate) {
                return $candidate;
            }
        }
    }

    return digBigData($place, $parent, $query);
}

function digBigData($place, $parent, $query)
{
    $sp = new Endpoint('https://query.wikidata.org/sparql');

    $query = "SELECT DISTINCT ?item ?itemType ?itemLabel ?location ?founding ?dissolution WHERE {
  $query
  {
    # страна
    ?item wdt:P31/wdt:P279* wd:Q6256.
    BIND( wd:Q6256 AS ?itemType )
  } UNION {
    # (обобщаем) геополитическая область
    ?item wdt:P31/wdt:P279* wd:Q15642541;
          wdt:P31 ?itemType.
  } UNION {
    # город
    ?item wdt:P31/wdt:P279* wd:Q515.
    BIND( wd:Q515 AS ?itemType )
  } UNION {
    # посад (посёлок городского типа)
    ?item wdt:P31/wdt:P279* wd:Q2989457.
    BIND( wd:Q2989457 AS ?itemType )
  } UNION {
    # село
    ?item wdt:P31/wdt:P279* wd:Q532.
    BIND( wd:Q532 AS ?itemType )
  } UNION {
    # деревня
    ?item wdt:P31/wdt:P279* wd:Q5084.
    BIND( wd:Q5084 AS ?itemType )
  } UNION {
    # (обобщаем) населённый пункт
    ?item wdt:P31/wdt:P279* wd:Q486972;
          wdt:P31 ?itemType.
  }
  OPTIONAL{ ?item wdt:P625 ?location. }
  OPTIONAL{ ?item wdt:P571 ?founding. }
  OPTIONAL{ ?item wdt:P576 ?dissolution. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language \"ru\". }
}";

    $rows = $sp->query($query);
    $err = $sp->getErrors();
    if ($err) {
//         print_r($err);
//         throw new \Exception(print_r($err, true));
        return $place;
    }
    $data = [];
    foreach ($rows['result']['rows'] as $row) {
        $item = $row['item'];
        $data[$item]['item'] = $item;
        if (empty($data[$item])) {
            $data[$item] = [];
        }
        if (empty($data[$item]['type'])) {
            $data[$item]['type'] = [];
        }
        $data[$item]['type'][] = $row['itemType'];
        $data[$item]['label'] = $row['itemLabel'];
        if (! empty($row['location']) && preg_match('/Point\(([\d\.]+)\s+([\d\.]+)\)/', $row['location'], $matches)) {
            $data[$item]['location'] = $matches[2] . ',' . $matches[1];
        }
        if (! empty($row['founding'])) {
            $dt = strtok($row['founding'], 'T');
            $dt = preg_replace('/(-01)?-01$/', '', $dt);
            $data[$item]['temporal'] = '+' . $dt . '/';
        }
        if (! empty($row['dissolution'])) {
            if (empty($data[$item]['temporal'])) {
                $data[$item]['temporal'] = '/';
            }
            $dt = strtok($row['dissolution'], 'T');
            $dt = preg_replace('/(-01)?-01$/', '', $dt);
            $data[$item]['temporal'] .= '+' . $dt;
        }
    }

    static $types;

    if (empty($types)) {
        $types = [];
        $types[PlacesIterator::processURI('wd:Q6256')] = [  // страна
            PlacesIterator::processURI('wd:Q6256'),         // страна
        ];
        $types[PlacesIterator::processURI('wd:Q10864048')] = [  // АТЕ-1
            PlacesIterator::processURI('wd:Q10864048'), // АТЕ-1
            PlacesIterator::processURI('wd:Q86622'),    // губерния
            PlacesIterator::processURI('wd:Q171308'),   // область
            PlacesIterator::processURI('wd:Q7075127'),  // область Российской империи
            PlacesIterator::processURI('wd:Q217691'),   // Губернии Финляндии
        ];
        $types[PlacesIterator::processURI('wd:Q13220204')] = [  // АТЕ-2
            PlacesIterator::processURI('wd:Q13220204'), // АТЕ-2
            PlacesIterator::processURI('wd:Q18867465'), // уезд
        ];
        $types[PlacesIterator::processURI('wd:Q13221722')] = [  // АТЕ-3
            PlacesIterator::processURI('wd:Q13221722'), // АТЕ-3
            PlacesIterator::processURI('wd:Q3504085'),  // гмина
            PlacesIterator::processURI('wd:Q687121'),   // волость
            PlacesIterator::processURI('wd:Q20732405'), // волость Российской Империи
        ];
        $types[PlacesIterator::processURI('wd:Q515')] = [   // город
            PlacesIterator::processURI('wd:Q515'),      // город
        ];
        $types[PlacesIterator::processURI('wd:Q2989457')] = [   // посад (посёлок городского типа)
            PlacesIterator::processURI('wd:Q2989457'),  // посад (посёлок городского типа)
            PlacesIterator::processURI('wd:Q3957'),     // малый город
        ];
        $types[PlacesIterator::processURI('wd:Q2514025')] = [   // посёлок
            PlacesIterator::processURI('wd:Q2514025'),  // посёлок
            PlacesIterator::processURI('wd:Q486972'),   // населённый пункт
            PlacesIterator::processURI('wd:Q1989945'),  // агрогородок
        ];
        $types[PlacesIterator::processURI('wd:Q532')] = [   // село
            PlacesIterator::processURI('wd:Q532'),      // село
        ];
        $types[PlacesIterator::processURI('wd:Q5084')] = [   // деревня
            PlacesIterator::processURI('wd:Q5084'),     // деревня
        ];
        $types[PlacesIterator::processURI('wd:Q748331')] = [   // станица
            PlacesIterator::processURI('wd:Q748331'),     // станица
        ];
        $types[PlacesIterator::processURI('wd:Q2023000')] = [   // хутор
            PlacesIterator::processURI('wd:Q2023000'),     // хутор
        ];
    }

    $tested = [];
    foreach ($data as $item => $wikidata) {
        foreach ($types as $key => $tests) {
            foreach ($tests as $test) {
                if (in_array($test, $wikidata['type'])) {
                    $wikidata['type'] = $key;
                    $tested[$item] = $wikidata;
                    break 3;
                }
            }
        }
    }
    if (1 === count($tested)) {
        $wikidata = array_shift($tested);
        $hasAlias = ! empty($place['alias']);
        if (! $hasAlias || ($place['alias'] != $wikidata['item'])) {
            $place['disputedAlias'] = $wikidata['item'];
        }
        if (! empty($wikidata['type'])
            && (empty($place['type']) || ($place['type'] != $wikidata['type']))
        ) {
            $place['disputedType'] = $wikidata['type'];
        }
        if (! empty($wikidata['location'])
            && (empty($place['location']) || ($place['location'] != $wikidata['location']))
        ) {
            if ($hasAlias && empty($place['location'])) {
                $place['location'] = $wikidata['location'];
                unset($place['disputedLocation']);
            } else {
                $place['disputedLocation'] = $wikidata['location'];
            }
        }
        if (! empty($wikidata['temporal'])
            && (empty($place['temporal']) || ($place['temporal'] != $wikidata['temporal']))
        ) {
            if ($hasAlias && empty($place['temporal'])) {
                $place['temporal'] = $wikidata['temporal'];
                unset($place['temporal']);
            } else {
                $place['disputedTemporal'] = $wikidata['temporal'];
            }
        }
    } elseif (! empty($tested)) {
        if (! empty($place['alias'])) {
            unset($tested[$place['alias']]);
        }
        if (! empty($tested)) {
            $place['disputedAlias'] = implode(',', array_keys($tested));
        }
    } elseif (! empty($data)) {
        if (! empty($place['alias'])) {
            unset($data[$place['alias']]);
        }
        if (! empty($data)) {
            $place['disputedAlias'] = implode(',', array_keys($data));
        }
    }
    return $place;
}
