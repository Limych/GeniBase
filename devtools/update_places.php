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

function digBigData($place, $parent, $query)
{
    static $metaTypes;

    if (empty($metaTypes)) {
        // ATE-0
        PlaceTypes::registerType('страна', 'wd:Q6256');

        // ATE-1
//         PlaceTypes::registerType('ATE-1', 'wd:Q10864048');
        PlaceTypes::registerType('губерния', 'wd:Q86622', 'wd:Q217691');
        PlaceTypes::registerType('область', 'wd:Q171308', 'wd:Q7075127');

        // ATE-2
//         PlaceTypes::registerType('ATE-2', 'wd:Q13220204');
        PlaceTypes::registerType('уезд', 'wd:Q18867465');

        // ATE-3
//         PlaceTypes::registerType('ATE-3', 'wd:Q13221722');
        PlaceTypes::registerType('волость', 'wd:Q687121', 'wd:Q20732405');
        PlaceTypes::registerType('гмина', 'wd:Q3504085');

//         PlaceTypes::registerType('город', 'wd:Q515');
//         PlaceTypes::registerType('посад', 'wd:Q2989457', 'wd:Q3957');
//         PlaceTypes::registerType('село', 'wd:Q532');
//         PlaceTypes::registerType('посёлок', 'wd:Q2514025', 'wd:Q486972 wd:Q1989945');
//         PlaceTypes::registerType('деревня', 'wd:Q5084');
//         PlaceTypes::registerType('станица', 'wd:Q748331');
//         PlaceTypes::registerType('хутор', 'wd:Q2023000');

        PlaceTypes::registerType('', 'wd:Q486972', 'wd:Q2989457 wd:Q3957 wd:Q532 wd:Q2514025 wd:Q486972 " .
            "wd:Q1989945 wd:Q5084 wd:Q748331 wd:Q2023000');

        $metaTypes = PlaceTypes::getMainTypes();
        $metaTypes[] = 'wd:Q15642541';  // human-geographic territorial entity (геополитическая область)
//         $metaTypes[] = 'wd:Q486972';    // settlement (населённый пункт)

        // Join metaTypes to one string
        $metaTypes = implode(' ', array_unique($metaTypes));
    }

	if (empty($place['alias'])) {
		$place['disputedAlias'] = 'wd:';
	} else {
		unset($place['disputedAlias']);
	}
	if (empty($place['type'])) {
		$place['disputedType'] = '';
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

    if (false === strpos($query, 'BIND(')) {
        $query .= "\n  VALUES ?itemMetaType { $metaTypes }\n";
    }

    $query = "SELECT DISTINCT ?item ?itemType ?itemMetaType ?itemLabel ?location ?founding ?dissolution WHERE {
  ?item (rdfs:label|skos:altLabel) ?itemLabel;
        wdt:P31 ?itemType;
        wdt:P31/wdt:P279* ?itemMetaType.
  $query
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
        foreach (array_keys($wikidata['type']) as $type) {
            $name = PlaceTypes::getTypeName($type);
            if (null !== $name) {
                $wikidata['type'] = $name;
                $passed = true;
                break;
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
        if (! $hasAlias || ($place['alias'] !== $wikidata['item'])) {
            $place['disputedAlias'] = $wikidata['item'];
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
            $place['disputedLocation'] = $wikidata['location'];
        }
        if (! empty($wikidata['temporal'])
            && (empty($place['temporal']) || ($place['temporal'] !== $wikidata['temporal']))
        ) {
            $place['disputedTemporal'] = $wikidata['temporal'];
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



class PlaceTypes {

    private static $types = [];

    public static function registerType($name, $type, $aliases = [])
    {
        if (empty(self::$types[$name])) {
            self::$types[$name] = [];
        }
        if (! is_array($aliases)) {
            $aliases = preg_split('/[\s,;]+/', $aliases, null, PREG_SPLIT_NO_EMPTY);
        }
        self::$types[$name] = array_unique(array_merge([$type], self::$types[$name], $aliases));
    }

    public static function getMainTypes()
    {
        $res = [];
        foreach (self::$types as $list) {
            $res[] = $list[0];
        }
        return $res;
    }

    public static function getTypeName($type)
    {
        foreach (self::$types as $name => $list) {
            if (in_array($type, $list)) {
                return $name;
            }
        }
        return null;
    }
}
