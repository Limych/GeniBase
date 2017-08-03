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

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Name;
use Gedcomx\Conclusion\NameForm;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 */
class NameStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Name($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('names');
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

        $t_names = $this->getTableName();

        /** @var Name $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_person_id'] = (int) $res;
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getPreferred())) {
            $data['preferred'] = (int) $res;
        }
        if (! empty($res = $entity->getDate())) {
            $data = array_merge($data, self::packDateInfo($res));
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
        /** @var Name $entity */
        $entity = parent::save($entity, $context, $o);

        $t_nfs = $this->dbs->getTableName('name_forms');

        // Save childs
        $nfs = $this->dbs->getDb()->fetchAll("SELECT _id FROM $t_nfs WHERE _name_id = ?", [$_id]);
        if (! empty($nfs)) {
            $nfs = array_map(
                function ($v) {
                    return (int) $v['_id'];
                },
                $nfs
            );
        }
        foreach ($entity->getNameForms() as $nf) {
            if (! empty($nfs)) {
                GeniBaseInternalProperties::setPropertyOf($nf, '_id', array_shift($nfs));
            }
            $this->newStorager($nf)->save($nf, $entity);
        }
        if (! empty($nfs)) {
            $this->dbs->getDb()->executeQuery(
                "DELETE FROM $t_nfs WHERE _id IN (?)",
                [$nfs],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );
        }

        return $entity;
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
        } elseif (! empty($_person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t._person_id = ?", [$_person_id]);
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll(
                "$q WHERE t._person_id = ? ORDER BY t._id",
                [$_person_id]
            );
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['preferred'])) {
            settype($result['preferred'], 'integer');
        }

        /** @var Name $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = self::unpackDateInfo($result))) {
            $entity->setDate($res);
        }

        // Load childs
        if (! empty($r = $this->newStorager(NameForm::class)->loadComponents($entity))) {
            $entity->setNameForms($r);
        }

        return $entity;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_names = $this->dbs->getTableName('names');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY nm FROM $t_names AS nm WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = nm._person_id )";

        $this->dbs->getDb()->query($q);
    }
}
