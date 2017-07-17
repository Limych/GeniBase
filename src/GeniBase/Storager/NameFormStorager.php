<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\NameForm;
use Gedcomx\Conclusion\NamePart;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 *
 */
class NameFormStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new NameForm($o);
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

        $t_nfs = $this->dbs->getTableName('name_forms');
        $t_nps = $this->dbs->getTableName('name_parts');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = [];

        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_name_id'] = $r;
        if (! empty($ent['lang']) && (false !== $r = $this->getLangId($ent['lang']))) {
            $data['lang_id'] = $r;
        }
        if (isset($ent['fullText'])) {
            $data['full_text'] = $ent['fullText'];
        }

        // Save data
        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $this->dbs->getDb()->update($t_nfs, $data, [
                '_id' => $_id
            ]);

        } else {
            $this->dbs->getDb()->insert($t_nfs, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);

        // Save childs
        $nps = $this->dbs->getDb()->fetchAll(
            "SELECT _id FROM $t_nps WHERE _name_form_id = ?",
            [$_id]
            );
        if (! empty($nps)) {
            $nps = array_map(function ($v) {
                return (int) $v['_id'];
            }, $nps);
        }
        foreach ($ent['parts'] as $np) {
            $np = new NamePart($np);
            if (! empty($nps))
                GeniBaseInternalProperties::setPropertyOf($np, '_id', array_shift($nps));
            $this->newStorager($np)->save($np, $entity);
        }
        if (! empty($nps)) {
            $this->dbs->getDb()->executeQuery("DELETE FROM $t_nps WHERE _id IN (?)", [$nps],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_nforms = $this->dbs->getTableName('name_forms');
        $t_langs = $this->dbs->getTableName('languages');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "nf.*";
        $qparts['tables'][]     = "$t_nforms AS nf";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "lg.lang";
        $qparts['tables'][]     = "$t_langs AS lg";
        $qparts['bundles'][]    = "lg._id = nf.lang_id";

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
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE nf._id = ?", [$_id]);
        }

        return $result;
    }

    protected function loadListRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_name_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE nf._name_id = ?", [$_name_id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result))
            return $result;

        if (isset($result['full_text'])) {
            $result['fullText'] = $result['full_text'];
        }

        /** @var NameForm $entity */
        $entity = parent::processRaw($entity, $result);

        if (! empty($r = $this->newStorager(NamePart::class)->loadList($entity))) {
            $entity->setParts($r);
        }

        return $entity;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY)   return; // Skip cleaning now

        $t_nfs = $this->dbs->getTableName('name_forms');
        $t_names = $this->dbs->getTableName('names');

        $q  = "DELETE LOW_PRIORITY nf FROM $t_nfs AS nf WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_names AS nm WHERE nm._id = nf._name_id )";

        $this->dbs->getDb()->query($q);
    }

}
