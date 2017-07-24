<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\PlaceDescription;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;

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

    public function getDefaultOptions(ExtensibleData $entity = null)
    {
        $def = parent::getDefaultOptions();

        $this->updateGeoCoordinates();

        $def['neighboring_distance'] = 100;

        $def['makeId_unique'] = false;

        $def['loadJurisdictions'] = true;

        if (! empty($entity)) {
            /**
             * @var PlaceDescription $entity
             */
            if (! empty($entity->getLatitude()) && ! empty($entity->getLongitude())) {
                $def['makeId_name'] = $entity->getLatitude() . ',' . $entity->getLongitude();
            } elseif (! empty($r = $entity->getNames())) {
                $def['makeId_name'] = $r[0]->getValue();
                if (! empty($r = $entity->getType())) {
                    $def['makeId_name'] .= "\t" . $r->getType();
                }
                if (! empty($r = $entity->getJurisdiction())) {
                    $def['makeId_name'] .= "\t" . $r->getResourceId();
                }
                if (! empty($r = $entity->getTemporalDescription())) {
                    $def['makeId_name'] .= "\t" . ($r->getFormal() ? $r->getFormal() : $r->getOriginal());
                }
            }
        }

        return $def;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\SubjectStorager::detectId()
     */
    protected function detectId(ExtensibleData &$entity)
    {
        if (parent::detectId($entity))  return true;

        /** @var PlaceDescription $entity */

        $t_places = $this->dbs->getTableName('places');

        if (! empty($refs = $this->searchRefByTextValues(self::GROUP_NAMES, $entity->getNames()))) {
            $q = $this->getSqlQuery() . ' WHERE pl._id IN (?)';
            if (! empty($jur = $entity->getJurisdiction())) {
                if (! empty($ref = $jur->getResourceId())) {
                    $q .= ' AND jurisdiction_id = ' . $this->dbs->getLidForId($t_places, $ref);
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
                    $place = $this->processRaw($this->getObject(), $r);
                    if (empty($entity->getId()) || (self::confidenceCmp($entity, $place) < 0)) {
                        $candidate = clone $entity;
                        $candidate->embed($place);
                        $candidate->setId($place->getId());
                    }
                }
                if (! empty($candidate))    $entity = $candidate;
            }
            return ! empty($entity->getId());
        }

        return false;
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
        if (! $entity instanceof ExtensibleData) {
            $entity = $this->getObject($entity);
        }

        $o = $this->applyDefaultOptions($o, $entity);
        $this->makeGbidIfEmpty($entity, $o);

        $t_places = $this->dbs->getTableName('places');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::arraySliceKeys($ent, 'id', 'latitude', 'longitude');

        if (isset($ent['type']) && (false !== $r = $this->getTypeId($ent['type_id']))) {
            $data['type_id'] = $r;
        }
        if (isset($ent['spatialDescription'])) {
            if (isset($ent['spatialDescription']['resource'])) {
                $data['spatialDescription_uri'] = $ent['spatialDescription']['resource'];
            }
            if (isset($ent['spatialDescription']['resourceId'])
                && (false !== $r = $this->dbs->getLidForId($t_places, $ent['spatialDescription']['resourceId']))
            ) {
                $data['spatialDescription_id'] = $r;
            }
        }
        if (isset($ent['jurisdiction'])) {
            if (isset($ent['jurisdiction']['resource'])) {
                $data['jurisdiction_uri'] = $ent['jurisdiction']['resource'];
            }
            if (isset($ent['jurisdiction']['resourceId'])
                && (false !== $r = $this->dbs->getLidForId($t_places, $ent['jurisdiction']['resourceId']))
            ) {
                $data['jurisdiction_id'] = $r;
            }
        }
        if (isset($ent['temporalDescription'])) {
            $r = $this->dbs->getDb()->fetchColumn(
                "SELECT temporalDescription_id FROM $t_places WHERE id = ?",
                [$data['id']]
            );
            $dt = new DateInfo($ent['temporalDescription']);
            if (false !== $r) {
                GeniBaseInternalProperties::setPropertyOf($dt, '_id', $r);
            }
            if (false !== $dt = $this->newStorager($dt)->save($dt)
                && ! empty($r = (int) GeniBaseInternalProperties::getPropertyOf($dt, '_id'))
            ) {
                $data['temporalDescription_id'] = $r;
            }
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_places, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update($t_places, $data, [
                '_id' => $_id
            ]);
        } else {
            $this->dbs->getDb()->insert($t_places, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);

        // Save childs
        foreach ($ent['names'] as $name) {
            $this->saveAllTextValues(self::GROUP_NAMES, $ent['names'], $entity);
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_places = $this->dbs->getTableName('places');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "pl.*";
        $qparts['tables'][]     = "$t_places AS pl";
        $qparts['bundles'][]    = "";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = pl.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE pl._id = ?", [(int) $_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE pl.id = ?", [$id]);
        }

        return $result;
    }

    protected function loadListRaw($context, $o)
    {
        $t_places = $this->dbs->getTableName('places');

        $q = $this->getSqlQuery();
        $result = false;
        if (empty($id = $context->getId())) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE pl.jurisdiction_uri IS NULL AND pl.jurisdiction_id IS NULL " .
                "ORDER BY pl.id"
            );
        } elseif (! empty($_ref = $this->dbs->getLidForId($t_places, $id))) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE pl.jurisdiction_id = ?",
                [(int) $_ref]
            );
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_places = $this->dbs->getTableName('places');

        if (isset($result['type_id'])
            && (false !== $r = $this->getType($result['type_id']))
        ) {
            $result['type'] = $r;
        }

        $result['spatialDescription'] = [];
        if (isset($result['spatialDescription_uri'])) {
            $result['spatialDescription']['resource'] = $result['spatialDescription_uri'];
        }
        if (isset($result['spatialDescription_id'])
            && (false !== $r = $this->dbs->getIdForLid($t_places, $result['spatialDescription_id']))
        ) {
            $result['spatialDescription']['resourceId'] = $r;
        }
        if (empty($result['spatialDescription'])) {
            unset($result['spatialDescription']);
        }

        $result['jurisdiction'] = [];
        if (isset($result['jurisdiction_uri'])) {
            $result['jurisdiction']['resource'] = $result['jurisdiction_uri'];
        }
        if (isset($result['jurisdiction_id'])
            && (false !== $r = $this->dbs->getIdForLid($t_places, $result['jurisdiction_id']))
        ) {
            $result['jurisdiction']['resourceId'] = $r;
        }
        if (empty($result['jurisdiction'])) {
            unset($result['jurisdiction']);
        }

        /** @var PlaceDescription $entity */
        $entity = parent::processRaw($entity, $result);

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

        $t_places = $this->dbs->getTableName('places');

        $this->dbs->getDb()->executeQuery(
            "CALL geobox_pt(POINT(?, ?), ?, @top_lft, @bot_rgt)",
            [$context->getLatitude(), $context->getLongitude(), $o['neighboring_distance']]
        );

        $q = $this->getSqlQuery('SELECT', ['geodist(?, ?, pl.latitude, pl.longitude) AS _dist']);
        $result = $this->dbs->getDb()->fetchAll(
            "$q WHERE pl.id != ? AND pl.latitude AND pl.longitude " .
            "AND pl.latitude BETWEEN X(@bot_rgt) AND X(@top_lft) " .
            "AND pl.longitude BETWEEN Y(@top_lft) AND Y(@bot_rgt) " .
            "HAVING _dist < ? " .
            "ORDER BY _dist",
            [$context->getLatitude(), $context->getLongitude(), $context->getId(), $o['neighboring_distance']]
        );

        if (is_array($result)) {
            foreach ($result as $k => $r) {
                $result[$k] = $this->processRaw($this->getObject(), $r);
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
    public function loadListGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();
        $companions = [];

        $o = $this->applyDefaultOptions($o);

        if (false === $result = $this->loadList($entity, $context, $o)) {
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

    public function updateGeoCoordinates()
    {
//         if (mt_rand(1, 10000) > self::UPDATE_GEO_PROBABILITY)   return; // Skip cleaning now

        $t_places = $this->dbs->getTableName('places');

        $place_id = $this->dbs->getDb()->fetchColumn(
            "SELECT _id FROM $t_places AS p1 " .
            "WHERE p1._calculatedGeo = 1 OR (p1.latitude IS NULL AND p1.longitude IS NULL) " .
            "AND EXISTS ( SELECT 1 FROM $t_places AS p2 WHERE " .
                "p2.jurisdiction_id = p1._id AND p2.latitude AND p2.longitude " .
            ") ORDER BY RAND() LIMIT 1"
        );
        $this->dbs->getDb()->executeQuery(
            "UPDATE $t_places dest, ( SELECT jurisdiction_id, AVG(latitude) AS latitude, " .
            "AVG(longitude) AS longitude FROM $t_places WHERE jurisdiction_id = ? ) src " .
            "SET dest._calculatedGeo = 1, dest.latitude = src.latitude, dest.longitude = src.longitude " .
            "WHERE _id = src.jurisdiction_id",
            [$place_id]
        );
    }
}
