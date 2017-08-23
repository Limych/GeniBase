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

use BorderCloud\SPARQL\Endpoint;
use GeniBase\Util;
use GeniBase\Util\PlacesProcessor;



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

if (empty($src_fpath) || ('--' == $src_fpath)) {
    $input = STDIN;

} elseif (false === $input = file_get_contents($src_fpath)) {
    die2(2, 'Source stream read error.');
}

if (! empty($dst_fpath)) {
    if (is_dir($dst_fpath) || in_array(substr($dst_fpath, -1), array('/', DIRECTORY_SEPARATOR))) {
        if ('--' == $src_fpath) {
            die2(1, 'Please declare target file, not directory.');
        }
        $dst_fpath .= DIRECTORY_SEPARATOR . basename($src_fpath);
    }

    if (false === $dst_stream = fopen($dst_fpath, 'w')) {
        die2(2, 'Destination stream open error.');
    }
}

try {
    PlacesProcessor::run($input, 'updatePlace');
} catch (\Exception $ex) {
    die2(3, $ex->getMessage());
}

if (! empty($dst_fpath)) {
    fclose($dst_stream);
}

die(0);



function die2($code, $msg)
{
    fwrite(STDERR, $msg . PHP_EOL);
    die($code);
}

function write($output)
{
    global $dst_stream;

    if (empty($dst_stream)) {
        echo $output;
    } else {
        fputs($dst_stream, $output);
    }
}

function updatePlace($state, $place)
{
    global $dst_stream, $output;

    static $level = 0;

    if ($state === PlacesProcessor::LIST_START) {
        if ($level++) {
            write(" {");
        }
        return true;
    } elseif ($state === PlacesProcessor::LIST_END) {
        if (--$level) {
            write("\n" . str_repeat("\t", $level - 1) . "}");
        }
        return true;
    }

    list($state, $place, $parent, $cnt, $max) = func_get_args();

    if (! empty($dst_stream)) {
        if ($max) {
            Util::printStatus($cnt, $max, "$cnt/$max tokens processed");
        } else {
            Util::printStatus($cnt, null, "$cnt tokens processed");
        }
    }
    if ($cnt > 1) {
        write("\n");
    }

    static $country;

    $candidate = $place;
    if (empty($country)) {
        $country = $place;
    }
    if (! empty($place['owl:sameAs'])) {
        $alias = PlacesProcessor::processURI($place['owl:sameAs'], false);
        $query = "BIND( $alias AS ?item )\n  " .
            "FILTER ( LANG(?itemLabel) = \"ru\" )";
    } else {
        $tmp = [];
        foreach ($place['rdfs:label'] as $name) {
            $tmp[] = "\"$name\"@ru";
        }
        $query = "VALUES ?itemLabel { " . implode(' ', $tmp) . " }";

        $filter = [];
        if (! empty($country['owl:sameAs'])) {
            $alias = PlacesProcessor::processURI($country['owl:sameAs'], PlacesProcessor::CONTRACT_URI);
            $filter[] = "?item wd:P17 $alias .";
        }
        if (! empty($parent['owl:sameAs'])) {
            $alias = PlacesProcessor::processURI($parent['owl:sameAs'], PlacesProcessor::CONTRACT_URI);
            $filter[] = "?item wd:P131 $alias .";
        }
        if (! empty($filter)) {
            $filter = ' { ' . implode(' UNION ', $filter) . ' } ';
            $candidate = digBigData($place, $parent, "$query\n  $filter");
        }
    }

    if ($place == $candidate) {
        $candidate = digBigData($place, $parent, $query);
    }

    // Save data
    $res = str_repeat("\t", $level - 1) . trim($candidate['label']);
    if (isset($candidate['rdf:type'])) {
        $candidate['rdf:type'] = PlacesProcessor::processURI($candidate['rdf:type'], PlacesProcessor::CONTRACT_URI);
        $res .= ' %' . $candidate['rdf:type'] . '%';
    }
    if (isset($candidate['disputedType'])) {
        $candidate['disputedType'] = PlacesProcessor::processURI($candidate['disputedType'], PlacesProcessor::CONTRACT_URI);
        if (empty($candidate['rdf:type']) || $candidate['disputedType'] !== $candidate['rdf:type']) {
            $res .= ' ?%' . $candidate['disputedType'] . '%';
        }
    }
    if (isset($candidate['owl:sameAs'])) {
        $candidate['owl:sameAs'] = PlacesProcessor::processURI($candidate['owl:sameAs'], PlacesProcessor::CONTRACT_URI);
        $res .= ' #' . $candidate['owl:sameAs'];
    }
    if (isset($candidate['disputedAlias'])) {
        $alias = $candidate['disputedAlias'];
        foreach ((is_array($alias) ? $alias : array( $alias )) as $alias) {
            $alias = PlacesProcessor::processURI($alias, PlacesProcessor::CONTRACT_URI);
            if (empty($candidate['owl:sameAs']) || $alias !== $candidate['owl:sameAs']) {
                $res .= ' ?#' . $alias;
            }
        }
    }
    if (isset($candidate['gx:temporalDescription'])) {
        $res .= ' [' . $candidate['gx:temporalDescription'] . ']';
    }
    if (isset($candidate['disputedTemporal'])) {
        $temporal = $candidate['disputedTemporal'];
        foreach (is_array($temporal) ? $temporal : array( $temporal ) as $temporal) {
            if (empty($candidate['gx:temporalDescription'])
                || $temporal !== $candidate['gx:temporalDescription']
            ) {
                $res .= ' ?[' . $temporal . ']';
            }
        }
    }
    if (! empty($candidate['location'])) {
        $res .= ' @' . $candidate['location'];
    }
    if (isset($candidate['disputedLocation'])) {
        $location = $candidate['disputedLocation'];
        foreach (is_array($location) ? $location : array( $location ) as $location) {
            if (empty($candidate['location'])
                || $location !== $candidate['location']
            ) {
                $res .= ' ?@' . $location;
            }
        }
    }

    write($res);
}

function digBigData($place, $parent, $query)
{
    static $metaTypes;

    if (empty($metaTypes)) {
        // ATE-0
        xPlaceTypes::registerType('страна', 'wd:Q6256');

        // ATE-1
//         xPlaceTypes::registerType('ATE-1', 'wd:Q10864048');
        xPlaceTypes::registerType('губерния', 'wd:Q86622', 'wd:Q217691');
        xPlaceTypes::registerType('область', 'wd:Q171308', 'wd:Q7075127');

        // ATE-2
//         xPlaceTypes::registerType('ATE-2', 'wd:Q13220204');
        xPlaceTypes::registerType('уезд', 'wd:Q18867465');

        // ATE-3
//         xPlaceTypes::registerType('ATE-3', 'wd:Q13221722');
        xPlaceTypes::registerType('волость', 'wd:Q687121', 'wd:Q20732405');
        xPlaceTypes::registerType('гмина', 'wd:Q3504085');

//         xPlaceTypes::registerType('город', 'wd:Q515');
//         xPlaceTypes::registerType('посад', 'wd:Q2989457', 'wd:Q3957');
//         xPlaceTypes::registerType('село', 'wd:Q532');
//         xPlaceTypes::registerType('посёлок', 'wd:Q2514025', 'wd:Q486972 wd:Q1989945');
//         xPlaceTypes::registerType('деревня', 'wd:Q5084');
//         xPlaceTypes::registerType('станица', 'wd:Q748331');
//         xPlaceTypes::registerType('хутор', 'wd:Q2023000');

        xPlaceTypes::registerType('', 'wd:Q486972', 'wd:Q2989457 wd:Q3957 wd:Q532 wd:Q2514025 wd:Q486972 " .
            "wd:Q1989945 wd:Q5084 wd:Q748331 wd:Q2023000');

        $metaTypes = xPlaceTypes::getMainTypes();
        $metaTypes[] = 'wd:Q15642541';  // human-geographic territorial entity (геополитическая область)
//         $metaTypes[] = 'wd:Q486972';    // settlement (населённый пункт)

        // Join metaTypes to one string
        $metaTypes = implode(' ', array_unique($metaTypes));
    }

	if (empty($place['owl:sameAs'])) {
		$place['disputedAlias'] = 'wd:';
	} else {
		unset($place['disputedAlias']);
	}
	if (empty($place['rdf:type'])) {
		$place['disputedType'] = '';
	} else {
		unset($place['disputedType']);
	}
	if (empty($place['location'])) {
		$place['disputedLocation'] = ',';
	} else {
		unset($place['disputedLocation']);
	}
	if (empty($place['gx:temporalDescription'])) {
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
        $item = PlacesProcessor::processURI($row['item'], PlacesProcessor::CONTRACT_URI);
        if (empty($candidates[$item])) {
            $candidates[$item] = [];
        }
        $candidates[$item]['item'] = $item;

        // Type
        if (empty($candidates[$item]['type'])) {
            $candidates[$item]['type'] = [];
        }
        $candidates[$item]['type'][PlacesProcessor::processURI($row['itemType'], PlacesProcessor::CONTRACT_URI)] = true;
        $candidates[$item]['type'][PlacesProcessor::processURI($row['itemMetaType'], PlacesProcessor::CONTRACT_URI)] = true;

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
            $name = xPlaceTypes::getTypeName($type);
            if (null !== $name) {
                $wikidata['type'] = $name;
                $passed = true;
                break;
            }
        }

        $wikidata['label'] = array_unique($wikidata['label']);
        $wikidata['temporal'] = array_unique($wikidata['temporal']);

        if ($passed) {
            $filtered[$item] = $wikidata;
        }
    }

    // Apply candidates
    if (1 === count($filtered)) {
        $wikidata = array_shift($filtered);
        $hasAlias = ! empty($place['owl:sameAs']);
        if (! $hasAlias
            || (PlacesProcessor::processURI($place['owl:sameAs'], PlacesProcessor::CONTRACT_URI) !== $wikidata['item'])
        ) {
            $place['disputedAlias'] = $wikidata['item'];
        }
        if (! empty($tmp) && (
            empty($place['rdf:type'])
            || (PlacesProcessor::processURI($place['rdf:type'], PlacesProcessor::CONTRACT_URI) !== $wikidata['type'])
        )) {
            $place['disputedType'] = $wikidata['type'];
        }
        if (! empty($wikidata['location'])
            && (empty($place['location']) || ($place['location'] !== $wikidata['location']))
        ) {
            $place['disputedLocation'] = $wikidata['location'];
        }
        if (! empty($wikidata['temporal'])
            && (empty($place['gx:temporalDescription']) || ($place['gx:temporalDescription'] !== $wikidata['temporal']))
        ) {
            $place['disputedTemporal'] = $wikidata['temporal'];
        }
    } elseif (! empty($filtered)) {
        if (! empty($place['owl:sameAs'])) {
            unset($filtered[$place['owl:sameAs']]);
        }
        if (! empty($filtered)) {
            $place['disputedAlias'] = array_keys($filtered);
        }
    } elseif (! empty($candidates)) {
        if (! empty($place['owl:sameAs'])) {
            unset($candidates[$place['owl:sameAs']]);
        }
        if (! empty($candidates)) {
            $place['disputedAlias'] = array_keys($candidates);
        }
    }

    return $place;
}



class xPlaceTypes {

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
