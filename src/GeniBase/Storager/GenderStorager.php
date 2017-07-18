<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Gender;
use GeniBase\Util;
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
        $this->makeUuidIfEmpty($entity, $o);

        $t_genders = $this->dbs->getTableName('genders');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id');

        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_person_id'] = $r;
        if (isset($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }

        // Save data
        $_id = $this->dbs->getLidForId($t_genders, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_genders,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_genders, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_genders = $this->dbs->getTableName('genders');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "gn.*";
        $qparts['tables'][]     = "$t_genders AS gn";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = gn.type_id";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = gn.id";
        $qparts = array_merge_recursive($qparts, $qp);

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
                "$q WHERE gn._person_id = ?",
                [$_person_id]
            );
        }

        return $result;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_genders = $this->dbs->getTableName('genders');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY gn FROM $t_genders AS gn WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = gn._person_id )";

        $this->dbs->getDb()->query($q);
    }
}
