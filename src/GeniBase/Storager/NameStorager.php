<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use GeniBase\Util;
use Gedcomx\Conclusion\Name;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\NameForm;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 *
 */
class NameStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Name($o);
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

        $o = $this->applyDefaultOptions($o, $entity);
        $this->makeUuidIfEmpty($entity, $o);

        $t_names = $this->dbs->getTableName('names');
        $t_nfs = $this->dbs->getTableName('name_forms');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id');

        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_person_id'] = (int) $r;
        if (! empty($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }
        if (isset($ent['preferred'])) {
            $data['preferred'] = (int) $ent['preferred'];
        }
        if (isset($ent['date'])) {
            $r = $this->dbs->getDb()->fetchColumn(
                "SELECT date_id FROM $t_names WHERE id = ?",
                [$data['id']]
            );
            $dt = new DateInfo($ent['date']);
            if (false !== $r)
                GeniBaseInternalProperties::setPropertyOf($dt, '_id', $r);
            if (false !== $dt = $this->newStorager($dt)->save($dt)
            && ! empty($r = (int) GeniBaseInternalProperties::getPropertyOf($dt, '_id')))
                $data['date_id'] = $r;
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_names, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update($t_names, $data, [
                '_id' => $_id
            ]);

        } else {
            $this->dbs->getDb()->insert($t_names, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        // Save childs
        $nfs = $this->dbs->getDb()->fetchAll(
            "SELECT _id FROM $t_nfs WHERE _name_id = ?",
            [$_id]
        );
        if (! empty($nfs)) {
            $nfs = array_map(function ($v) {
                return (int) $v['_id'];
            }, $nfs);
        }
        foreach ($ent['nameForms'] as $nf) {
            $nf = new NameForm($nf);
            if (! empty($nfs))
                GeniBaseInternalProperties::setPropertyOf($nf, '_id', array_shift($nfs));
            $this->newStorager($nf)->save($nf, $entity);
        }
        if (! empty($nfs)) {
            $this->dbs->getDb()->executeQuery("DELETE FROM $t_nfs WHERE _id IN (?)", [$nfs],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_names = $this->dbs->getTableName('names');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "nm.*";
        $qparts['tables'][]     = "$t_names AS nm";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = nm.type_id";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = nm.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE nm._id = ?", [$_id]);

        } elseif (! empty($_person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE nm._person_id = ?", [$_person_id]);
        }

        return $result;
    }

    protected function loadListRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE nm._person_id = ? ORDER BY nm.id",
                [$_person_id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result))
            return $result;

        if (isset($result['preferred'])) {
            settype($result['preferred'], 'integer');
        }

        /** @var Name $entity */
        $entity = parent::processRaw($entity, $result);

        if (isset($result['date_id'])) {
            $dt = new DateInfo();
            GeniBaseInternalProperties::setPropertyOf($dt, '_id', $result['date_id']);
            if (! empty($dt = $this->newStorager($dt)->load($dt)))
                $entity->setDate($dt);
        }

        if (! empty($r = $this->newStorager(NameForm::class)->loadList($entity))) {
            $entity->setNameForms($r);
        }

        return $entity;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY)   return; // Skip cleaning now

        $t_names = $this->dbs->getTableName('names');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY nm FROM $t_names AS nm WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = nm._person_id )";

        $this->dbs->getDb()->query($q);
    }

}
