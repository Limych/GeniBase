<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Gender;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 */
class GenderStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Gender($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('genders');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     *
     * @throws \UnexpectedValueException
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $this->makeUuidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_places = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        /** @var Gender $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_person_id'] = $res;
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }

        return $data;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp._id = t.type_id";

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE gn._id = ?", [$_id]);
        } elseif (! empty($_person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "$q WHERE t._person_id = ?",
                [$_person_id]
            );
        }

        return $result;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_genders = $this->dbs->getTableName('genders');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY gn FROM $t_genders AS gn WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = gn._person_id )";

        $this->dbs->getDb()->query($q);
    }
}
