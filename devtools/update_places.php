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

$data = PlacesIterator::run($data, 'updatePlace');

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
        $alias = PlacesIterator::processURI($place['alias'], false);
        $query = "BIND( $alias AS ?item )\n  " .
            "FILTER ( LANG(?itemLabel) = \"ru\" )";
    } else {
        $tmp = [];
        foreach (PlacesIterator::expandNames($place['name']) as $name) {
            $tmp[] = "\"$name\"@ru";
        }
        $query = "VALUES ?itemLabel { " . implode(' ', $tmp) . " }";

        $filter = [];
        if (! empty($country['alias'])) {
            $alias = PlacesIterator::processURI($country['alias'], false);
            $filter[] = "?item wd:P17 $alias .";
        }
        if (! empty($parent['alias'])) {
            $alias = PlacesIterator::processURI($parent['alias'], false);
            $filter[] = "?item wd:P131 $alias .";
        }
        if (! empty($filter)) {
            $filter = ' { ' . implode(' UNION ', $filter) . ' } ';
            $candidate = digBigData($place, $parent, "$query\n  $filter");
            if ($place !== $candidate) {
                return $candidate;
            }
        }
    }

    return digBigData($place, $parent, $query);
}

function addTypes(array &$types, $key, array $newTypes)
{
    if (empty($types[$key])) {
        $types[$key] = $newTypes;
    } else {
        $types[$key] = array_unique(array_merge($types[$key], $newTypes));
    }
}

function digBigData($place, $parent, $query)
{
    static $types, $metaTypes;

    if (empty($types)) {
        // Define main metaTypes (in order of importance)
        $metaTypes = [
            'wd:Q6256',     // country (страна)
            'wd:Q515',      // city (город)
            'wd:Q2989457',  // town (посад, п.г.т.)
            'wd:Q532',      // village (село)
            'wd:Q5084',     // hamlet (деревня)
            'wd:Q748331',   // stanitsa (станица)
            'wd:Q2023000',  // khutor (хутор)
        ];

        // Add basic metatypes to types
        $types = [];
        foreach ($metaTypes as $key) {
            if (empty($types[$key])) {
                $types[$key] = [];
            }
            $types[$key][] = $key;
        }

        // Define dependences from types to metatypes (in order of importance)
        addTypes($types, 'wd:Q10864048', [  // АТЕ-1
            'wd:Q10864048', // АТЕ-1
            'wd:Q86622',    // губерния
            'wd:Q171308',   // область
            'wd:Q7075127',  // область Российской империи
            'wd:Q217691',   // Губернии Финляндии
        ]);
        addTypes($types, 'wd:Q13220204', [  // АТЕ-2
            'wd:Q13220204', // АТЕ-2
            'wd:Q18867465', // уезд
        ]);
        addTypes($types, 'wd:Q13221722', [  // АТЕ-3
            'wd:Q13221722', // АТЕ-3
            'wd:Q3504085',  // гмина
            'wd:Q687121',   // волость
            'wd:Q20732405', // волость Российской Империи
        ]);
        addTypes($types, 'wd:Q2989457', [   // посад (посёлок городского типа)
            'wd:Q2989457',  // посад (посёлок городского типа)
            'wd:Q3957',     // малый город
        ]);
        addTypes($types, 'wd:Q2514025', [   // посёлок
            'wd:Q2514025',  // посёлок
            'wd:Q486972',   // населённый пункт
            'wd:Q1989945',  // агрогородок
        ]);

        // Add commom metaTypes for regions and settlements
        $metaTypes[] = 'wd:Q15642541';  // human-geographic territorial entity (геополитическая область)
        $metaTypes[] = 'wd:Q486972';    // settlement (населённый пункт)

        // Join metaTypes to one string
        $metaTypes = implode(' ', array_unique($metaTypes));
    }

	if (empty($place['alias'])) {
		$place['disputedAlias'] = 'wd:';
	} else {
		unset($place['disputedAlias']);
	}
	if (empty($place['type'])) {
		$place['disputedType'] = 'wd:';
	} else {
		unset($place['disputedType']);
	}
	if (empty($place['location'])) {
		$place['disputedLocation'] = ',';
	} else {
		unset($place['disputedLocation']);
	}
	if (empty($place['temporal'])) {
		$place['disputedTemporal'] = '/';
	} else {
		unset($place['disputedTemporal']);
	}
	
    $sp = new Endpoint('https://query.wikidata.org/sparql');

    $query = "SELECT DISTINCT ?item ?itemType ?itemMetaType ?itemLabel ?location ?founding ?dissolution WHERE {
  ?item (rdfs:label|skos:altLabel) ?itemLabel;
        wdt:P31 ?itemType;
        wdt:P31/wdt:P279* ?itemMetaType.
  $query
  VALUES ?itemMetaType { $metaTypes }
  OPTIONAL{ ?item wdt:P625 ?location. }
  OPTIONAL{ ?item wdt:P571 ?founding. }
  OPTIONAL{ ?item wdt:P576 ?dissolution. }
}";

    $rows = $sp->query($query);
    $err = $sp->getErrors();
    if ($err) {
//         print_r($err);
//         throw new \Exception(print_r($err, true));
        return $place;
    }

    // Prepare candidates
    $candidates = [];
    foreach ($rows['result']['rows'] as $row) {
        $item = PlacesIterator::processURI($row['item'], false);
        if (empty($candidates[$item])) {
            $candidates[$item] = [];
        }
        $candidates[$item]['item'] = $item;

        // Type
        if (empty($candidates[$item]['type'])) {
            $candidates[$item]['type'] = [];
        }
        $candidates[$item]['type'][PlacesIterator::processURI($row['itemType'], false)] = true;
        $candidates[$item]['type'][PlacesIterator::processURI($row['itemMetaType'], false)] = true;

        // Labels
        if (empty($candidates[$item]['label'])) {
            $candidates[$item]['label'] = [];
        }
        $candidates[$item]['label'][] = $row['itemLabel'];

        // Location
        if (! empty($row['location']) && preg_match('/Point\(([\d\.]+)\s+([\d\.]+)\)/', $row['location'], $matches)) {
            $candidates[$item]['location'] = $matches[2] . ',' . $matches[1];
        }

        // Existence dates
        if (empty($candidates[$item]['temporal'])) {
            $candidates[$item]['temporal'] = [];
        }
        $temporal = '';
        if (! empty($row['founding'])) {
            $dt = strtok($row['founding'], 'T');
            $dt = preg_replace('/(-01)?-01$/', '', $dt);
            $temporal = '+' . $dt . '/';
        }
        if (! empty($row['dissolution'])) {
            if (empty($temporal)) {
                $temporal = '/';
            }
            $dt = strtok($row['dissolution'], 'T');
            $dt = preg_replace('/(-01)?-01$/', '', $dt);
            $temporal .= '+' . $dt;
        }
        if (! empty($temporal)) {
            $candidates[$item]['temporal'][] = $temporal;
        }
    }
    if (empty($candidates)) {
        return $place;
    }

    // Filter candidates
    $filtered = [];
    foreach ($candidates as $item => $wikidata) {
        $passed = false;

        // Types
        foreach ($types as $key => $tests) {
            foreach ($tests as $test) {
                if (! empty($wikidata['type'][$test])) {
                    $wikidata['type'] = $key;
                    $passed = true;
                    break 2;
                }
            }
        }

        // Labels
        $wikidata['label'] = array_unique($wikidata['label']);

        // Existence dates
        $wikidata['temporal'] = implode(',', array_unique($wikidata['temporal']));

        if ($passed) {
            $filtered[$item] = $wikidata;
        }
    }

    // Apply candidates
    if (1 === count($filtered)) {
        $wikidata = array_shift($filtered);
        $hasAlias = ! empty($place['alias']);
        $tmp = PlacesIterator::processURI($wikidata['item']);
        if (! $hasAlias || ($place['alias'] !== $tmp)) {
            $place['disputedAlias'] = $tmp;
        }
        $tmp = PlacesIterator::processURI($wikidata['type']);
        if (! empty($tmp)
            && (empty($place['type']) || ($place['type'] !== $tmp))
        ) {
            $place['disputedType'] = $tmp;
        }
        if (! empty($wikidata['location'])
            && (empty($place['location']) || ($place['location'] !== $wikidata['location']))
        ) {
            if ($hasAlias && empty($place['location'])) {
                $place['location'] = $wikidata['location'];
				unset($place['disputedLocation']);
            } else {
                $place['disputedLocation'] = $wikidata['location'];
            }
        }
        if (! empty($wikidata['temporal'])
            && (empty($place['temporal']) || ($place['temporal'] !== $wikidata['temporal']))
        ) {
            if ($hasAlias && empty($place['temporal'])) {
                $place['temporal'] = $wikidata['temporal'];
				unset($place['disputedTemporal']);
            } else {
                $place['disputedTemporal'] = $wikidata['temporal'];
            }
        }
    } elseif (! empty($filtered)) {
        if (! empty($place['alias'])) {
            unset($filtered[$place['alias']]);
        }
        if (! empty($filtered)) {
            $place['disputedAlias'] = implode(',', array_keys($filtered));
        }
    } elseif (! empty($candidates)) {
        if (! empty($place['alias'])) {
            unset($candidates[$place['alias']]);
        }
        if (! empty($candidates)) {
            $place['disputedAlias'] = implode(',', array_keys($candidates));
        }
    }
	
    return $place;
}
