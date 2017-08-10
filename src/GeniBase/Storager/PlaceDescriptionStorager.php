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
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\PlaceDescription;
use GeniBase\DBase\GeniBaseInternalProperties;
use App\Provider\PlaceMapProvider;

/**
 *
 * @author Limych
 */
class PlaceDescriptionStorager extends SubjectStorager
{

    const GROUP_NAMES   = 'http://genibase/PlaceName';

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
        $def = parent::getDefaultOptions();

        $this->updateGeoCoordinates();

        $def['makeId_unique'] = false;
        $def['loadJurisdictions'] = true;
        $def['sortComponents'] = true;
        //
        $def['neighboringDistance'] = 100;
        $def['neighboringLimit'] = 30;

        if (empty($def['makeId_name']) && ! empty($entity)) {
            /**
             * @var PlaceDescription $entity
             */
            $tmp = [];
            if (! empty($entity->getLatitude()) && ! empty($entity->getLongitude())) {
                $tmp[] = $entity->getLatitude() . ',' . $entity->getLongitude();
            } elseif (! empty($res = $entity->getNames())) {
                $tmp[] = $res[0]->getValue();
                if (! empty($res = $entity->getType())) {
                    $tmp[] = $res;
                }
                if (! empty($res = $entity->getJurisdiction())) {
                    $tmp[] = $res->getResourceId();
                }
                if (! empty($res = $entity->getTemporalDescription())) {
                    $tmp[] = ($res->getFormal() ? $res->getFormal() : $res->getOriginal());
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

        if (! empty($refs = $this->searchRefByTextValues(self::GROUP_NAMES, $entity->getNames()))) {
            $query = $this->getSqlQuery('SELECT', '', 'WHERE t._id IN (?)');
            if (! empty($jur = $entity->getJurisdiction())) {
                if (! empty($res = $jur->getResourceId())
                    && ! empty($res = $this->dbs->getInternalId($t_places, $res))
                ) {
                    $query .= ' AND jurisdiction_id = ' . $res;
                } elseif (! empty($res = $jur->getResource())) {
                    $query .= ' AND jurisdiction_uri = ' . $this->dbs->getDb()->quote($res);
                }
            } else {
                $query .= ' AND jurisdiction_id IS NULL AND jurisdiction_uri IS NULL';
            }
            $result = $this->dbs->getDb()->fetchAll($query, [$refs], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            if (is_array($result)) {
                $candidate = $entity;
                foreach ($result as $res) {
                    $place = $this->unpackLoadedData($this->getObject(), $res);
                    if (empty($candidate->getId()) || (self::confidenceCmp($candidate, $place) > 0)) {
                        $this->previousState = clone $place;
                        $candidate = $place;
                        $candidate->embed($entity);
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
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_places = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        /** @var PlaceDescription $entity */
        if (! empty($res = $entity->getLatitude()) && ($res != $this->previousState->getLatitude())) {
            $data['latitude'] = $res;
        }
        if (! empty($res = $entity->getLongitude()) && ($res != $this->previousState->getLongitude())) {
            $data['longitude'] = $res;
        }
        if (! empty($res = $entity->getType()) && ($res != $this->previousState->getType())
            && ! empty($res = $this->dbs->getTypeId($res))
        ) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getSpatialDescription())
            && ($res != $this->previousState->getSpatialDescription())
        ) {
            if (! empty($res2 = $res->getResource())) {
                $data['spatialDescription_uri'] = $res2;
            }
            if (! empty($res2 = $res->getResourceId())
                && ! empty($res2 = $this->dbs->getInternalId($t_sources, $res2))
            ) {
                $data['spatialDescription_id'] = $res;
            }
        }
        if (! empty($res = $entity->getJurisdiction()) && ($res != $this->previousState->getJurisdiction())) {
            if (! empty($res2 = $res->getResource())) {
                $data['jurisdiction_uri'] = $res2;
            }
            if (! empty($res2 = $res->getResourceId())
                && ! empty($res2 = $this->dbs->getInternalId($t_places, $res2))
            ) {
                $data['jurisdiction_id'] = $res2;
            }
        }
        if (! empty($res = $entity->getTemporalDescription())
            && ($res != $this->previousState->getTemporalDescription())
        ) {
            $data = array_merge($data, self::packDateInfo($res, 'temporalDescription_'));
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
        if (! empty($res = $entity->getNames()) && ($res != $this->previousState->getNames())) {
            $names = [];
            foreach ($res as $name) {
                $names[] = $name->toArray();
            }
            $this->saveAllTextValues(self::GROUP_NAMES, $names, $entity);
        }

        return $entity;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (empty($id = $context->getId())) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE jurisdiction_uri IS NULL AND jurisdiction_id IS NULL " .
                "ORDER BY t._id"
            );
        } elseif (! empty($_ref = $this->dbs->getInternalId($this->getTableName(), $id))) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE jurisdiction_id = ?",
                [(int) $_ref]
            );
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
        $aName = mb_strtolower($a->getNames()[0]->getValue(), 'UTF-8');
        $bName = mb_strtolower($b->getNames()[0]->getValue(), 'UTF-8');
        return strcmp($aName, $bName);
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['type_id'])
            && (false !== $type_id = $this->dbs->getType($result['type_id']))
        ) {
            $result['type'] = $type_id;
        }

        $result['spatialDescription'] = [];
        if (isset($result['spatialDescription_uri'])) {
            $result['spatialDescription']['resource'] = $result['spatialDescription_uri'];
        }
        if (isset($result['spatialDescription_id'])
            && (false !== $res = $this->dbs->getIdForInternalId($result['spatialDescription_id']))
        ) {
            $result['spatialDescription']['resourceId'] = $res;
        }
        if (empty($result['spatialDescription'])) {
            unset($result['spatialDescription']);
        }

        $result['jurisdiction'] = [];
        if (isset($result['jurisdiction_uri'])) {
            $result['jurisdiction']['resource'] = $result['jurisdiction_uri'];
        }
        if (isset($result['jurisdiction_id'])
            && (false !== $res = $this->dbs->getPublicId($this->getTableName(), $result['jurisdiction_id']))
        ) {
            $result['jurisdiction']['resourceId'] = $res;
        }
        if (empty($result['jurisdiction'])) {
            unset($result['jurisdiction']);
        }

        /** @var PlaceDescription $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = $this->loadAllTextValues(self::GROUP_NAMES, $entity))) {
            $entity->setNames($res);
        }
        if (! empty($res = self::unpackDateInfo($result, 'temporalDescription_'))) {
            $entity->setTemporalDescription($res);
        }
        if (! empty($result['_zoom'])) {
            GeniBaseInternalProperties::setPropertyOf($entity, '_zoom', $result['_zoom']);
        }

        // Load childs
        if (isset($result['temporalDescription_id'])) {
            $dt = new DateInfo();
            GeniBaseInternalProperties::setPropertyOf($dt, '_id', $result['temporalDescription_id']);
            if (false !== $dt = $this->newStorager($dt)->load($dt)) {
                $entity->setTemporalDescription($dt);
            }
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

        $this->dbs->getDb()->executeQuery(
            "CALL geobox_pt(POINT(?, ?), ?, @top_lft, @bot_rgt)",
            [$context->getLatitude(), $context->getLongitude(), $o['neighboring_distance']]
        );

        $q = $this->getSqlQuery('SELECT', ['geodist(?, ?, latitude, longitude) AS _dist']);
        $result = $this->dbs->getDb()->fetchAll(
            "$q WHERE t._id != ? AND latitude AND longitude " .
            "AND latitude BETWEEN X(@bot_rgt) AND X(@top_lft) " .
            "AND longitude BETWEEN Y(@top_lft) AND Y(@bot_rgt) " .
            "HAVING _dist < ? " .
            "ORDER BY _dist " .
            "LIMIT " . ((int) $o['neighboringLimit']),
            [
                $context->getLatitude(),
                $context->getLongitude(),
                $this->dbs->getInternalId($this->getTableName(), $context->getId()),
                (int) $o['neighboringDistance'],
            ]
        );

        if (is_array($result)) {
            foreach ($result as $k => $r) {
                $result[$k] = $this->unpackLoadedData($this->getObject(), $r);
                GeniBaseInternalProperties::setPropertyOf($result[$k], '_dist', $r['_dist']);
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
        $companions = [];

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
        $companions = [];

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

        if (! empty($r = $entity->getJurisdiction()) && ! empty($rid = $r->getResourceId())) {
            $gedcomx->embed(
                $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }

        return $gedcomx;
    }

    public function loadGedcomxJurisdictions(ExtensibleData $entity)
    {
        /** @var PlaceDescription $entity */

        $gedcomx = new Gedcomx();

        if (! empty($r = $entity->getJurisdiction()) && ! empty($rid = $r->getResourceId())) {
            $gedcomx->embed(
                $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }

        return $gedcomx;
    }

    public function updatePlaceGeoCoordinates($place_id)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $t_places = $this->getTableName();

        $geo = $this->dbs->getDb()->fetchArray(
            "SELECT MIN(latitude), MIN(longitude), MAX(latitude), MAX(longitude) " .
            "FROM $t_places WHERE jurisdiction_id = ?",
            [$place_id]
        );
        if (empty($geo) && (count($geo) === array_sum(array_map('empty', $geo)))) {
            $this->dbs->getDb()->executeQuery(
                "UPDATE $t_places SET _calculatedGeo = 0, latitude = 0, longitude = 0 WHERE _id = ?",
                [$place_id]
            );
        } else {
            $zoom = 11;
            if ($geo[0] !== $geo[2] && $geo[1] !== $geo[3]) {
                $zoom = PlaceMapProvider::getBoundsZoomLevel($geo[0], $geo[1], $geo[2], $geo[3]);
            }
            $this->dbs->getDb()->executeQuery(
                "UPDATE $t_places SET _calculatedGeo = 1, latitude = ?, longitude = ?, _zoom = ? " .
                "WHERE _id = ?",
                [($geo[0] + $geo[2]) / 2, ($geo[1] + $geo[3]) / 2, $zoom, $place_id]
            );
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
    }

    public function updateGeoCoordinates()
    {
        if (! defined('DEBUG_SECONDARY') && (mt_rand(1, 10000) > self::UPDATE_GEO_PROBABILITY)) {
            return; // Skip updating now
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $t_places = $this->getTableName();

        $place_id = $this->dbs->getDb()->fetchColumn(
            "SELECT _id FROM $t_places AS p1 " .
            "WHERE p1._calculatedGeo = 1 OR (p1.latitude IS NULL AND p1.longitude IS NULL " .
            "AND EXISTS ( SELECT 1 FROM $t_places AS p2 WHERE " .
            "p2.jurisdiction_id = p1._id AND p2.latitude AND p2.longitude " .
            ")) ORDER BY RAND() LIMIT 1"
        );
        $this->updatePlaceGeoCoordinates($place_id);
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
    }
}
