<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\NameForm;
use Gedcomx\Conclusion\NamePart;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 */
class NameFormStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new NameForm($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('name_forms');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        /** @var NameForm $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_name_id'] = $res;
        if (! empty($res = $entity->getLang()) && ! empty($res = $this->dbs->getLangId($res))) {
            $data['lang_id'] = $res;
        }
        if (! empty($res = $entity->getFullText())) {
            $data['full_text'] = $res;
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
        /** @var NameForm $entity */
        $entity = parent::save($entity, $context, $o);

        $t_nps = $this->dbs->getTableName('name_parts');

        // Save childs
        $nps = $this->dbs->getDb()->fetchAll("SELECT _id FROM $t_nps WHERE _name_form_id = ?", [$_id]);
        if (! empty($nps)) {
            $nps = array_map(
                function ($v) {
                    return (int) $v['_id'];
                },
                $nps
            );
        }
        foreach ($entity->getParts() as $np) {
            if (! empty($nps)) {
                GeniBaseInternalProperties::setPropertyOf($np, '_id', array_shift($nps));
            }
            $this->newStorager($np)->save($np, $entity);
        }
        if (! empty($nps)) {
            $this->dbs->getDb()->executeQuery("DELETE FROM $t_nps WHERE _id IN (?)", [$nps],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_langs = $this->dbs->getTableName('languages');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "l.lang";
        $qparts['tables'][]     = "$t_langs AS l";
        $qparts['bundles'][]    = "l._id = t.lang_id";

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

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_name_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_rid'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE nf._name_id = ?", [$_name_id]);
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['full_text'])) {
            $result['fullText'] = $result['full_text'];
        }

        /** @var NameForm $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($r = $this->newStorager(NamePart::class)->loadComponents($entity))) {
            $entity->setParts($r);
        }

        return $entity;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_nfs = $this->dbs->getTableName('name_forms');
        $t_names = $this->dbs->getTableName('names');

        $q  = "DELETE LOW_PRIORITY nf FROM $t_nfs AS nf WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_names AS nm WHERE nm._id = nf._name_id )";

        $this->dbs->getDb()->query($q);
    }
}
