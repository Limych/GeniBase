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
namespace App\Controller\Importer;

use App\Util;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Util\FormalDate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use GeniBase\Types\PlaceTypes;
use Silex\Application;

class PlacesImporter extends GeniBaseImporter
{

    const OVERTIME_COOKIE   = 'PlacesImporter';

    protected function parsePlace($raw, $parentPlace)
    {
        $place = [
            'confidence'    => \Gedcomx\Types\ConfidenceLevel::HIGH,
        ];

        // Parse for dates of existence
        if (preg_match("/(.*?)\s*\{(.+)\}\s*(.*)/", $raw, $matches)) {
            $tmp = new FormalDate();
            $tmp->parse($matches[2]);
            $place['temporalDescription'] = [];
            if ($tmp->isValid()) {
                $place['temporalDescription']['formal'] = $matches[2];
            } else {
                $place['temporalDescription']['original'] = $matches[2];
            }
            $raw = $matches[1] . $matches[3];
        }

        // Parse for geographical coordinates
        if (preg_match("/(.*?)\s*\[([\d.]*),\s*([\d.]*)\]\s*(.*)/", $raw, $matches)) {
            if (! empty($matches[2]) && ! empty($matches[3])) {
                $place['latitude'] = floatval($matches[2]);
                $place['longitude'] = floatval($matches[3]);
            }
            $raw = $matches[1] . $matches[4];
        }

        // Parse for variations of name
        if (preg_match("/(.*?)\s*\(([^\)]+)\)(.*)/", $raw, $matches)) {
            $tmp = preg_split("/[;,]\s*/", $matches[2], null, PREG_SPLIT_NO_EMPTY);
            array_unshift($tmp, $matches[1]);
            foreach ($tmp as $y) {
                $place['names'][] = [
                    'lang'  => 'ru',
                    'value' => $y . $matches[3],
                ];
            }
        } else {
            $place['names'][] = [
                'lang'  => 'ru',
                'value' => $raw,
            ];
        }

        if (isset($parentPlace)) {
            $place['jurisdiction'] = [
                'resourceId'    => $parentPlace->getId(),
            ];
        }

        return $place;
    }

    public function import(Request $request)
    {
        $place_types = [
            PlaceTypes::COUNTRY,
            PlaceTypes::FIRST_LEVEL,
            PlaceTypes::SECOND_LEVEL,
            PlaceTypes::THIRD_LEVEL,
        ];

        $start = $request->cookies->getInt(self::OVERTIME_COOKIE);

        $places = $this->getPlaces();
        $count = count($places);

        $overtime = false;
		$pcnt = 0;
        for ($cnt = $start; $cnt < $count; $cnt++) {
            /** @var PlaceDescription $plc */
            unset($plc);
            $segments = preg_split("/\s+>\s+/", $places[$cnt], null, PREG_SPLIT_NO_EMPTY);
            $max = count($segments) - 1;
            $place_path = '';
            for ($i = 0; $i <= $max; $i++) {
                $place_path .= ' > ' . $segments[$i];
                $place = $this->parsePlace($segments[$i], $plc);
                $place['identifiers'] = [
                    \Gedcomx\Types\IdentifierType::PERSISTENT => '//GeniBase/#place_' . md5($place_path),
                ];
                if ($i == $max) {
                    $place['type'] = PlaceTypes::SETTLEMENT;
                } else {
                    $place['type'] = $place_types[$i];
                }
                $plc = $this->gbs->newStorager(PlaceDescription::class)->save($place);
            }
			$pcnt++;

            if (Util::executionTime() > 10000) {
                $overtime = true;
                break;
            }
		}

        $response = new Response("<div><progress value='$cnt' max='$count'></progress> " .
            sprintf('%d of %d places (%.2f places/sec)', $cnt, $count, ($pcnt * 1000 / Util::executionTime())) . "</div>");
        if ($overtime) {
            $response->headers->setCookie(new Cookie(self::OVERTIME_COOKIE, $cnt));
            $response->headers->set('Refresh', '0; url=' . $request->getUri());
        } else {
            $response->headers->clearCookie(self::OVERTIME_COOKIE);
        }
        return $response;
    }

    protected function getPlaces($year = 1913)
    {
        $fpath = BASE_DIR . "/var/store/places_{$year}.txt";

        $places = file_get_contents($fpath);

        if (false !== $places) {
            $places = preg_split("/[\r\n]+/", $places, null, PREG_SPLIT_NO_EMPTY);
        }

        return $places;
    }

    public function updatePlaceGeoCoordinates(Application $app, Request $request)
    {
        $plc = $this->gbs->newStorager(PlaceDescription::class);

        $t_places = $app['gb.db']->getTableName('places');

        $query = "SELECT _id FROM $t_places AS p1 " .
            "WHERE p1._calculatedGeo = 1 OR (p1.latitude IS NULL AND p1.longitude IS NULL " .
            "AND EXISTS ( SELECT 1 FROM $t_places AS p2 WHERE " .
            "p2.jurisdiction_id = p1._id AND p2.latitude AND p2.longitude " .
            ")) ORDER BY RAND()";
        $result = $app['db']->fetchAll($query);
        foreach ($result as $res) {
            $plc->updatePlaceGeoCoordinates($res['_id']);
        }

        $response = new Response('Done.');
        return $response;
    }
}
