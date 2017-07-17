<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\NamePart;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 *
 */
class NamePartStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new NamePart($o);
    }

    /**
     *
     * @param mixed $entity
     * @param ExtensibleData $context
     * @param array|null $o
     * @return ExtensibleData|false
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof ExtensibleData)
            $entity = $this->getObject($entity);

        $t_nps = $this->dbs->getTableName('name_parts');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'value');
        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_name_form_id'] = (int) $r;
        if (isset($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }

        // Save data
        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $this->dbs->getDb()->update($t_nps, $data, [
                '_id' => $_id
            ]);

        } else {
            $this->dbs->getDb()->insert($t_nps, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_nparts = $this->dbs->getTableName('name_parts');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "np.*";
        $qparts['tables'][]     = "$t_nparts AS np";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp";
        $qparts['bundles'][]    = "tp._id = np.type_id";

        $qp = parent::getSqlQueryParts();
//         $qp['bundles'][0]   = "cn.id = gn.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE np._id = ?", [$_id]);
        }

        return $result;
    }

    protected function loadListRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_name_form_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE np._name_form_id = ?", [$_name_form_id]);
        }

        return $result;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY)   return; // Skip cleaning now

        $t_nps = $this->dbs->getTableName('name_parts');
        $t_nfs = $this->dbs->getTableName('name_forms');

        $q  = "DELETE LOW_PRIORITY np FROM $t_nps AS np WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_nfs AS nf WHERE nf._id = np._name_form_id )";

        $this->dbs->getDb()->query($q);
    }

}
