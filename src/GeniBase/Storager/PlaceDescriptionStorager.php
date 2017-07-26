<?php
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

        $def['neighboring_distance'] = 100;
        $def['makeId_unique'] = false;
        $def['loadJurisdictions'] = true;
        $def['sortComponents'] = true;

        if (! empty($entity)) {
            /**
             * @var PlaceDescription $entity
             */
            if (! empty($entity->getLatitude()) && ! empty($entity->getLongitude())) {
                $def['makeId_name'] = $entity->getLatitude() . ',' . $entity->getLongitude();
            } elseif (! empty($res = $entity->getNames())) {
                $def['makeId_name'] = $res[0]->getValue();
                if (! empty($res = $entity->getType())) {
                    $def['makeId_name'] .= "\t" . $res;
                }
                if (! empty($res = $entity->getJurisdiction())) {
                    $def['makeId_name'] .= "\t" . $res->getResourceId();
                }
                if (! empty($res = $entity->getTemporalDescription())) {
                    $def['makeId_name'] .= "\t" . ($res->getFormal() ? $res->getFormal() : $res->getOriginal());
                }
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $def;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\SubjectStorager::detectId()
     */
    protected function detectId(ExtensibleData &$entity)
    {
        if (parent::detectId($entity)) {
            return true;
        }

        /** @var PlaceDescription $entity */

        $t_places = $this->dbs->getTableName('places');

        if (! empty($refs = $this->searchRefByTextValues(self::GROUP_NAMES, $entity->getNames()))) {
            $q = $this->getSqlQuery('SELECT', '', 'WHERE t._id IN (?)');
            if (! empty($jur = $entity->getJurisdiction())) {
                if (! empty($ref = $jur->getResourceId())) {
                    $q .= ' AND jurisdiction_id = ' . $this->dbs->getInternalId($t_places, $ref);
                } elseif (! empty($ref = $jur->getResource())) {
                    $q .= ' AND jurisdiction_uri = ' . $this->dbs->getDb()->quote($ref);
                }
            } else {
                $q .= ' AND jurisdiction_id IS NULL AND jurisdiction_uri IS NULL';
            }
            $result = $this->dbs->getDb()->fetchAll($q, [$refs], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            if (is_array($result)) {
                $candidate = null;
                foreach ($result as $k => $r) {
                    $place = $this->unpackLoadedData($this->getObject(), $r);
                    if (empty($entity->getId()) || (self::confidenceCmp($entity, $place) < 0)) {
                        $candidate = clone $entity;
                        $candidate->embed($place);
                        $candidate->setId($place->getId());
                    }
                }
                if (! empty($candidate)) {
                    $entity = $candidate;
                }
            }
            return ! empty($entity->getId());
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
        if (! empty($res = $entity->getLatitude())) {
            $data['latitude'] = $res;
        }
        if (! empty($res = $entity->getLongitude())) {
            $data['longitude'] = $res;
        }
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getSpatialDescription())) {
            if (! empty($res2 = $res->getResource())) {
                $data['spatialDescription_uri'] = $res2;
            }
            if (! empty($res2 = $res->getResourceId())
                && ! empty($res2 = $this->dbs->getInternalId($t_sources, $res2))
            ) {
                $data['spatialDescription_id'] = $res;
            }
        }
        if (! empty($res = $entity->getJurisdiction())) {
            if (! empty($res2 = $res->getResource())) {
                $data['jurisdiction_uri'] = $res2;
            }
            if (! empty($res2 = $res->getResourceId())
                && ! empty($res2 = $this->dbs->getInternalId($t_places, $res2))
            ) {
                $data['jurisdiction_id'] = $res2;
            }
        }
        if (! empty($res = $entity->getTemporalDescription())) {
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
        if (! empty($res = $entity->getNames())) {
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

        if (! empty($res = self::unpackDateInfo($result, 'temporalDescription_'))) {
            $entity->setTemporalDescription($res);
        }

        // Load childs
        if (isset($result['temporalDescription_id'])) {
            $dt = new DateInfo();
            GeniBaseInternalProperties::setPropertyOf($dt, '_id', $result['temporalDescription_id']);
            if (false !== $dt = $this->newStorager($dt)->load($dt)) {
                $entity->setTemporalDescription($dt);
            }
        }
        $res = $this->loadAllTextValues(self::GROUP_NAMES, $entity);
        if (! empty($res)) {
            $entity->setNames($res);
        }
        if (! empty($result['_zoom'])) {
            GeniBaseInternalProperties::setPropertyOf($entity, '_zoom', $result['_zoom']);
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
            "ORDER BY _dist",
            [
                $context->getLatitude(),
                $context->getLongitude(),
                $this->dbs->getInternalId($this->getTableName(), $context->getId()),
                $o['neighboring_distance'],
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
