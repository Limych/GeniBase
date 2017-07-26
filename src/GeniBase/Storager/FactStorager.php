<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Fact;
use Gedcomx\Conclusion\PlaceDescription;

/**
 *
 * @author Limych
 */
class FactStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Fact($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('facts');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        $t_facts = $this->getTableName();
        $t_places = $this->dbs->getTableName('places');

        /** @var Fact $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_person_id'] = $res;
        if (! empty($res = $entity->getPrimary())) {
            $data['primary'] = (int) $res;
        }
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getValue())) {
            $data['value'] = $res;
        }
        if (! empty($res = $entity->getDate())) {
            $data = array_merge($data, self::packDateInfo($res));
        }
        if (! empty($res = $entity->getPlace())) {
            if (! empty($res2 = $res->getDescriptionRef())) {
                $data['place_description_uri'] = $res2;
                if (! empty($res2 = GeniBaseStorager::getIdFromReference($res2))
                    && ! empty($res2 = $this->dbs->getInternalId($t_places, $res2))
                ) {
                    $data['place_description_id'] = (int) $res2;
                }
            }
            if (! empty($res2 = $res->getOriginal())) {
                $data['place_original'] = $res2;
            }
        }

        return $data;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = t.type_id";

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t.id = ?", [$id]);
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE t._person_id = ?", [$_id]);
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        // Unpack data
        if (! empty($result['primary'])) {
            settype($result['primary'], 'boolean');
        }
        $result['place'] = [];
        if (! empty($result['place_description'])) {
            $result['place']['description'] = $result['place_description'];
        }
        if (! empty($result['place_original'])) {
            $result['place']['original'] = $result['place_original'];
        }
        if (empty($result['place'])) {
            unset($result['place']);
        }

        /** @var Person $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = self::unpackDateInfo($result))) {
            $entity->setDate($res);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Fact $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getPlace()) && ! empty($r = $r->getDescriptionRef())
            && ! empty($rid = GeniBaseStorager::getIdFromReference($r))
        ) {
            $gedcomx->embed(
                $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_facts = $this->dbs->getTableName('facts');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY ft FROM $t_facts AS ft WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = ft._person_id )";

        $this->dbs->getDb()->query($q);
    }
}
