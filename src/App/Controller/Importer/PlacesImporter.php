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
use Gedcomx\Util\FormalDate;
use Silex\Application;

class PlacesImporter extends GeniBaseImporter
{

    const OVERTIME_COOKIE   = 'PlacesImporter';

    private $tokensCnt;
    private $tokensSkip;
    private $tokensTotal;

    private $placesCnt;
    private $placesProcessed;
    private $placesStack;
    private $lastPlace;

    public function import(Request $request)
    {
        $this->tokensSkip = 0;
        $this->placesStack = array();
        $this->placesCnt = 0;
        $this->placesProcessed = 0;
        $this->lastPlace = null;

        $res = $request->cookies->get(self::OVERTIME_COOKIE);
        if (! empty($res)) {
            $res = json_decode($res, true);
            $this->tokensSkip = (int) $res['skip'];
            $this->placesStack = $res['stack'];
        }

        $places = $this->getPlaces();
        $done = PlacesProcessor::run($places, array($this, 'processPlace'));

        $response = new Response("<div><progress value='{$this->tokensCnt}' max='{$this->tokensTotal}'></progress> " .
            sprintf('Imported %d places (%.2f places/sec)', $this->placesCnt, ($this->placesProcessed * 1000 / Util::executionTime())) . "</div>");
        if ($done) {
            $response->headers->clearCookie(self::OVERTIME_COOKIE);
        } else {
            $response->headers->setCookie(new Cookie(self::OVERTIME_COOKIE, json_encode(array(
                'stack' => $this->placesStack,
                'skip'  => $this->tokensCnt,
            ))));
            $response->headers->set('Refresh', '0; url=' . $request->getUri());
        }
        return $response;
    }

    public function processPlace($state, $input)
    {
        if ($state === PlacesProcessor::LIST_START) {
            if ($this->tokensCnt >= $this->tokensSkip) {
                array_unshift($this->placesStack, $this->lastPlace);
            }
            return true;
        } elseif ($state === PlacesProcessor::LIST_END) {
            if ($this->tokensCnt >= $this->tokensSkip) {
                array_shift($this->placesStack);
            }
            return true;
        }

        list($state, $input, $parentPlace, $this->tokensCnt, $this->tokensTotal) = func_get_args();

        $this->placesCnt++;

        if ($this->tokensCnt < $this->tokensSkip) {
            return true;
        }

        $place = array(
            'confidence'    => \Gedcomx\Types\ConfidenceLevel::HIGH,
        );

        $tmp = new FormalDate();
        $tmp->parse($input['gx:temporalDescription']);
        $place['temporalDescription'] = array();
        if ($tmp->isValid()) {
            $place['temporalDescription']['formal'] = $input['gx:temporalDescription'];
        } else {
            $place['temporalDescription']['original'] = $input['gx:temporalDescription'];
        }

        if (! empty($input['owl:sameAs'])) {
            $place['identifiers'] = array(
                \Gedcomx\Types\IdentifierType::PERSISTENT => $input['owl:sameAs'],
            );
        }

        if (! empty($input['location'])) {
            $tmp = explode(',', $input['location']);
            $place['latitude'] = floatval($tmp[0]);
            $place['longitude'] = floatval($tmp[1]);
        }

        foreach ($input['rdfs:label'] as $name) {
            $place['names'][] = array(
                'lang'  => 'ru',
                'value' => $name,
            );
        }

        if (! empty($this->placesStack[0])) {
            $place['jurisdiction'] = array(
                'resourceId'    => $this->placesStack[0],
            );
        }

        $plc = $this->gbs->newStorager('Gedcomx\Conclusion\PlaceDescription')->save($place);

        $this->placesProcessed++;
        $this->lastPlace = $plc->getId();

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
        $plc = $this->gbs->newStorager('Gedcomx\Conclusion\PlaceDescription');

        $t_places = $app['gb.db']->getTableName('places');

        $query = "SELECT id FROM $t_places ORDER BY RAND()";
        $result = $app['db']->fetchAll($query);
        foreach ($result as $res) {
            $plc->updatePlaceGeoCoordinates($res['id']);
        }

        $response = new Response('Done.');
        return $response;
    }
}
