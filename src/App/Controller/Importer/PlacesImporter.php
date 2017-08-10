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
use App\Util\PlacesProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Util\FormalDate;
use GeniBase\Types\PlaceTypes;
use Silex\Application;
use GeniBase\Storager\GeniBaseStorager;

class PlacesImporter extends GeniBaseImporter
{

    const OVERTIME_COOKIE   = 'PlacesImporter';

    private $skipPlaces;
    private $processedCnt;
    private $placesCnt;
    private $cnt;
    private $total;
    private $placesStack;
    private $lastPlace;

    public function import(Request $request)
    {
        $place_types = [
            PlaceTypes::COUNTRY,
            PlaceTypes::FIRST_LEVEL,
            PlaceTypes::SECOND_LEVEL,
            PlaceTypes::THIRD_LEVEL,
        ];

        $this->skipPlaces = $request->cookies->getInt(self::OVERTIME_COOKIE);
        $this->processedCnt = 0;
        $this->placesStack = [];
        $this->lastPlace = null;

        $places = $this->getPlaces();
        $done = PlacesProcessor::run($places, [$this, 'processPlace']);

        $response = new Response("<div><progress value='{$this->cnt}' max='{$this->total}'></progress> " .
            sprintf('Imported %d places (%.2f places/sec)', $this->placesCnt, ($this->processedCnt * 1000 / Util::executionTime())) . "</div>");
        if ($done) {
            $response->headers->clearCookie(self::OVERTIME_COOKIE);
        } else {
            $response->headers->setCookie(new Cookie(self::OVERTIME_COOKIE, $this->placesCnt));
            $response->headers->set('Refresh', '0; url=' . $request->getUri());
        }
        return $response;
    }

    public function processPlace($state, $input)
    {
        if ($state === PlacesProcessor::LIST_START) {
            array_unshift($this->placesStack, $this->lastPlace);
            return true;
        } elseif ($state === PlacesProcessor::LIST_END) {
            array_shift($this->placesStack);
            return true;
        }

        if (++$this->placesCnt < $this->skipPlaces) {
            return true;
        }
        list($state, $input, $parentPlace, $this->cnt, $this->total) = func_get_args();

        $place = [
            'confidence'    => \Gedcomx\Types\ConfidenceLevel::HIGH,
        ];

        $tmp = new FormalDate();
        $tmp->parse($input['gx:temporalDescription']);
        $place['temporalDescription'] = [];
        if ($tmp->isValid()) {
            $place['temporalDescription']['formal'] = $input['gx:temporalDescription'];
        } else {
            $place['temporalDescription']['original'] = $input['gx:temporalDescription'];
        }

        if (! empty($input['owl:sameAs'])) {
            $place['identifiers'] = [
                \Gedcomx\Types\IdentifierType::PERSISTENT => $input['owl:sameAs'],
            ];
        }

        if (! empty($input['location'])) {
            $tmp = explode(',', $input['location']);
            $place['latitude'] = floatval($tmp[0]);
            $place['longitude'] = floatval($tmp[1]);
        }

        foreach ($input['rdfs:label'] as $name) {
            $place['names'][] = [
                'lang'  => 'ru',
                'value' => $name,
            ];
        }

        if (! empty($this->placesStack[0])) {
            $place['jurisdiction'] = [
                'resourceId'    => $this->placesStack[0],
            ];
        }

        $plc = $this->gbs->newStorager(PlaceDescription::class)->save($place);

        $this->processedCnt++;
        $this->skipPlaces = $this->placesCnt;
        $this->lastPlace = $plc->getId();
var_dump($place, $this->placesStack);
if (3 === $this->placesCnt) die;

		return (Util::executionTime() <= 10000);
    }

    protected function getPlaces($fname = 'russia_1913')
    {
        $fpath = BASE_DIR . "/var/store/{$fname}.plc";
        $places = file_get_contents($fpath);
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
