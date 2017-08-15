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
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\PlaceDescription;
use GeniBase\DBase\GeniBaseInternalProperties;
use GeniBase\Util\Geo;

/**
 *
 * @author Limych
 */
class PlaceDescriptionStorager extends SubjectStorager
{

    const GROUP_NAMES   = 'http://genibase.net/PlaceName';

    const UPDATE_GEO_PROBABILITY    = 1;    // of 10 000

    protected function getObject($o = null)
    {
        return new PlaceDescription($o);
    }

    public function getDefaultOptions($entity = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $def = parent::getDefaultOptions($entity);

        $this->updateGeoCoordinates();

        $def['loadJurisdictions'] = true;
        $def['sortComponents'] = true;
        //
        $def['neighboringDistance'] = 100;
        $def['neighboringLimit'] = 30;

        if (empty($def['makeId_name']) && ! empty($entity)) {
            /**
             * @var PlaceDescription $entity
             */
            $tmp = array();
            $res = $entity->getJurisdiction();
            if (! empty($res)) {
                $tmp[] = $res->getResourceId();
            }
            if (! empty($entity->getLatitude()) && ! empty($entity->getLongitude())) {
                $tmp[] = $entity->getLatitude() . ',' . $entity->getLongitude();
            } else {
                $res = $entity->getNames();
                if (! empty($res)) {
                    $tmp[] = $res[0]->getValue();
                    $res = $entity->getType();
                    if (! empty($res)) {
                        $tmp[] = $res;
                    }
                    $res = $entity->getTemporalDescription();
                    if (! empty($res)) {
                        $tmp[] = ($res->getFormal() ? $res->getFormal() : $res->getOriginal());
                    }
                }
            }
            $def['makeId_name'] = implode("\t", array_filter($tmp));
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $def;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\SubjectStorager::detectPreviousState()
     */
    protected function detectPreviousState(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        if (parent::detectPreviousState($entity, $context, $o)) {
            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::stopTimer(__METHOD__);
            }
            return true;
        }

        /** @var PlaceDescription $entity */

        $t_places = $this->dbs->getTableName('places');

        $refs = $this->searchRefByTextValues(self::GROUP_NAMES, $entity->getNames());
        if (! empty($refs)) {
            $query = $this->getSqlQuery('SELECT', '', 'WHERE t.id IN (?)');
            $jur = $entity->getJurisdiction();
            $data = array($refs);
            if (! empty($jur)) {
                $res = $jur->getResourceId();
                if (! empty($res)) {
                    $query .= ' AND jurisdiction_id = ?';
                    $data[] = $res;
                } else {
                    $res = $jur->getResource();
                    if (! empty($res)) {
                        $query .= ' AND jurisdiction_uri = ?';
                        $data[] = $res;
                    }
                }
            } else {
                $query .= ' AND jurisdiction_id IS NULL AND jurisdiction_uri IS NULL';
            }
            $result = $this->dbs->getDb()->fetchAll($query, $data, array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
            if (is_array($result)) {
                $candidate = $entity;
                foreach ($result as $res) {
                    $place = $this->unpackLoadedData($this->getObject(), $res);
                    if (empty($candidate->getId()) || (self::confidenceCmp($candidate, $place) > 0)) {
                        $this->previousState = clone $place;
                        $candidate = $place;
                        $candidate->initFromArray($entity->toArray());
                    }
                }
                if ($candidate !== $entity) {
                    $entity = $candidate;
                }
            }
            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::stopTimer(__METHOD__);
            }
            return ! empty($entity->getId());
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('places');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_places = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        /** @var PlaceDescription $entity */
        $res = $entity->getLatitude();
        if (! empty($res) && ($res != $this->previousState->getLatitude())) {
            $data['latitude'] = $res;
            $data['geo_calculated'] = false;
        }

        $res = $entity->getLongitude();
        if (! empty($res) && ($res != $this->previousState->getLongitude())) {
            $data['longitude'] = $res;
            $data['geo_calculated'] = false;
        }

        $res = $entity->getType();
        if (! empty($res) && ($res != $this->previousState->getType())) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['type_id'] = $res;
            }
        }

        $res = $entity->getSpatialDescription();
        if (! empty($res) && ($res != $this->previousState->getSpatialDescription())) {
            $data = array_merge($data, self::packResourceReference($res, 'spatialDescription'));
        }

        $res = $entity->getJurisdiction();
        if (! empty($res) && ($res != $this->previousState->getJurisdiction())) {
            $data = array_merge($data, self::packResourceReference($res, 'jurisdiction'));
        }

        $res = $entity->getTemporalDescription();
        if (! empty($res) && ($res != $this->previousState->getTemporalDescription())) {
            $data = array_merge($data, self::packDateInfo($res, 'temporalDescription'));
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        return $data;
    }

    /**
     *
     * @param mixed          $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return ExtensibleData|false
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        /** @var PlaceDescription $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        $res = $entity->getNames();
        if (! empty($res) && ($res != $this->previousState->getNames())) {
            $this->saveTextValues(self::GROUP_NAMES, $res, $entity);
        }

        return $entity;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        $id = $context->getId();
        if (empty($id)) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE jurisdiction_uri IS NULL AND jurisdiction_id IS NULL " .
                "ORDER BY t.id"
            );
        } else {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE jurisdiction_id = ?", array($id));
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::compareComponents()
     */
    protected function compareComponents($a, $b)
    {
        /** @var PlaceDescription $a */
        /** @var PlaceDescription $b */
        $an = $a->getNames();
        $aName = mb_strtolower($an[0]->getValue(), 'UTF-8');
        $bn = $b->getNames();
        $bName = mb_strtolower($bn[0]->getValue(), 'UTF-8');
        return strcmp($aName, $bName);
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        /** @var PlaceDescription $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (isset($result['type_id'])) {
            $type = $this->getType($result['type_id']);
            if (! empty($type)) {
                $entity->setType($type);
            }
        }

        $res = $this->loadTextValues(self::GROUP_NAMES, $entity);
        if (! empty($res)) {
            $entity->setNames($res);
        }

        $res = self::unpackDateInfo($result, 'temporalDescription');
        if (! empty($res)) {
            $entity->setTemporalDescription($res);
        }

        $res = self::unpackResourceReference($result, 'spatialDescription');
        if (! empty($res)) {
            $entity->setSpatialDescription($res);
        }

        $res = self::unpackResourceReference($result, 'jurisdiction');
        if (! empty($res)) {
            $entity->setJurisdiction($res);
        }

        if (! empty($result['geo_lat_min'])) {
            GeniBaseInternalProperties::setPropertyOf($entity, 'geo_lat_min', $result['geo_lat_min']);
            GeniBaseInternalProperties::setPropertyOf($entity, 'geo_lon_min', $result['geo_lon_min']);
            GeniBaseInternalProperties::setPropertyOf($entity, 'geo_lat_max', $result['geo_lat_max']);
            GeniBaseInternalProperties::setPropertyOf($entity, 'geo_lon_max', $result['geo_lon_max']);
            GeniBaseInternalProperties::setPropertyOf($entity, 'geo_bbox', array(
                $result['geo_lat_min'],
                $result['geo_lon_min'],
                $result['geo_lat_max'],
                $result['geo_lon_max'],
            ));
        }

        return $entity;
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return object[]|false
     */
    public function loadNeighboringPlaces($context, $o = null)
    {
        $o = $this->applyDefaultOptions($o);

        if (! $context instanceof ExtensibleData) {
            $context = $this->getObject($context);
        }

        $bbox = Geo::box($context->getLatitude(), $context->getLongitude(), $o['neighboringDistance']);

        $query = $this->getSqlQuery();
        $result = $this->dbs->getDb()->fetchAll(
            "$query WHERE t.id != ? AND latitude AND longitude " .
            "AND latitude BETWEEN ? AND ? " .
            "AND longitude BETWEEN ? AND ? ",
            array(
                $context->getId(),
                $bbox[2],   // Bottom
                $bbox[0],   // Top
                $bbox[1],   // Left
                $bbox[3],   // Right
            )
        );

        if (is_array($result) && ! empty($result)) {
            // Calculate distance
            foreach ($result as $key => $res) {
                $result[$key]['geo_dist'] = Geo::dist(
                    $context->getLatitude(),
                    $context->getLongitude(),
                    $res['latitude'],
                    $res['longitude']
                );
            }

            // Sort by distance
            usort($result, function ($a, $b) {
                return ($a['geo_dist'] == $b['geo_dist'] ? 0
                    : $a['geo_dist'] < $b['geo_dist'] ? -1 : 1
                );
            });

            // Limit array
            $result = array_slice($result, 0, (int) $o['neighboringLimit']);

            // Unpack objects
            foreach ($result as $key => $res) {
                $result[$key] = $this->unpackLoadedData($this->getObject(), $res);
                GeniBaseInternalProperties::setPropertyOf($result[$key], 'geo_dist', $res['geo_dist']);
            }
        }
        return $result;
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \LogicException
     */
    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();

        $o = $this->applyDefaultOptions($o);

        if (false === $pd = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addPlace($pd);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($pd));
        } elseif ($o['loadJurisdictions']) {
            $gedcomx->embed($this->loadGedcomxJurisdictions($pd));
        }

        return $gedcomx;
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \LogicException
     */
    public function loadComponentsGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();
        $companions = array();

        $o = $this->applyDefaultOptions($o);

        if (false === $result = $this->loadComponents($entity, $o)) {
            return false;
        }

        foreach ($result as $sd) {
            $gedcomx->addPlace($sd);
            if ($o['loadCompanions']) {
                $companions[] = $this->loadGedcomxCompanions($sd);
            }
        }

        foreach ($companions as $c) {
            $gedcomx->embed($c);
        }

        return $gedcomx;
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \LogicException
     */
    public function loadNeighboringPlacesGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();
        $companions = array();

        $o = $this->applyDefaultOptions($o);

        if (false === $result = $this->loadNeighboringPlaces($entity, $context, $o)) {
            return false;
        }

        foreach ($result as $sd) {
            $gedcomx->addPlace($sd);
            if ($o['loadCompanions']) {
                $companions[] = $this->loadGedcomxCompanions($sd);
            }
        }

        foreach ($companions as $c) {
            $gedcomx->embed($c);
        }

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var PlaceDescription $entity */

        $gedcomx = parent::loadGedcomxCompanions($entity);

        $gedcomx->embed($this->loadGedcomxJurisdictions($entity));

        return $gedcomx;
    }

    public function loadGedcomxJurisdictions(ExtensibleData $entity)
    {
        /** @var PlaceDescription $entity */

        $gedcomx = new Gedcomx();

        $res = $entity->getJurisdiction();
        if (! empty($res)) {
            $rid = $res->getResourceId();
            if (! empty($rid)) {
                $st = new PlaceDescriptionStorager($this->dbs);
                $gedcomx->embed($st->loadGedcomx(array( 'id' => $rid )));
            }
        }

        return $gedcomx;
    }

    public function updatePlaceGeoCoordinates($place_id)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $t_places = $this->getTableName();

        $place = $this->dbs->getDb()->fetchAssoc(
            "SELECT geo_calculated, latitude, longitude FROM $t_places WHERE id = ?",
            array($place_id)
        );
        if (! empty($place)) {
            $place['geo_calculated'] = $place['geo_calculated'] || is_null($place['latitude']);
        }

        $geo = $this->dbs->getDb()->fetchArray(
            "SELECT MIN(latitude), MIN(longitude), MAX(latitude), MAX(longitude), " .
            "MIN(geo_lat_min), MIN(geo_lon_min), MAX(geo_lat_max), MAX(geo_lon_max) " .
            "FROM $t_places WHERE jurisdiction_id = ?",
            array($place_id)
        );
        if (count($geo) !== array_sum(array_map(function($val) { return empty($val); }, $geo))) {
            $zoom = 11;
            $geo[0] = empty($geo[4]) ? $geo[0] : min($geo[0], $geo[4]);
            $geo[1] = empty($geo[5]) ? $geo[1] : min($geo[1], $geo[5]);
            $geo[2] = empty($geo[6]) ? $geo[2] : max($geo[2], $geo[6]);
            $geo[3] = empty($geo[7]) ? $geo[3] : max($geo[3], $geo[7]);
            if (! $place['geo_calculated']) {
                $geo[0] = min($geo[0], $place['latitude']);
                $geo[1] = min($geo[1], $place['longitude']);
                $geo[2] = max($geo[2], $place['latitude']);
                $geo[3] = max($geo[3], $place['longitude']);
            }
            $data = array(
                'geo_lat_min'   => $geo[0],
                'geo_lon_min'   => $geo[1],
                'geo_lat_max'   => $geo[2],
                'geo_lon_max'   => $geo[3],
            );
            if ($place['geo_calculated']) {
                $data['geo_calculated'] = 1;
                $data['latitude']   = ($geo[0] + $geo[2]) / 2;
                $data['longitude']  = ($geo[1] + $geo[3]) / 2;
            }
            $this->dbs->getDb()->update($t_places, $data, array( 'id' => $place_id ));
        } elseif ($place['geo_calculated']) {
            $this->dbs->getDb()->executeQuery(
                "UPDATE $t_places SET geo_calculated = 0, latitude = NULL, longitude = NULL WHERE id = ?",
                array($place_id)
            );
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
    }

    public function updateGeoCoordinates()
    {
        if (! defined('DEBUG_PLACES') && (mt_rand(1, 10000) > self::UPDATE_GEO_PROBABILITY)) {
            return; // Skip updating now
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }

        $t_places = $this->getTableName();
        $place_id = $this->dbs->getDb()->fetchColumn(
            "SELECT id FROM $t_places ORDER BY RAND() LIMIT 1"
        );
        $this->updatePlaceGeoCoordinates($place_id);

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
    }
}
