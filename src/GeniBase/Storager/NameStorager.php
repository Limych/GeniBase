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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        /** @var Name $entity */

        $this->makeUuidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_names = $this->getTableName();

        if (empty($context) || empty($context->getId())) {
            throw new \UnexpectedValueException('Context ID required!');
        }
        $data['person_id'] = $context->getId();

        $res = $entity->getType();
        if (! empty($res)) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['type_id'] = $res;
            }
        }

        $res = $entity->getPreferred();
        if (! empty($res)) {
            $data['preferred'] = (int) $res;
        }

        $res = $entity->getDate();
        if (! empty($res)) {
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

        // Save childs

        $t_nfs = $this->dbs->getTableName('name_forms');
        $nfs = $this->dbs->getDb()->fetchAll(
            "SELECT _id FROM $t_nfs WHERE name_id = ?",
            array( $entity->getId() )
        );
        if (! empty($nfs)) {
            $nfs = array_map(
                function ($v) {
                    return (int) $v['_id'];
                },
                $nfs
            );
        }
        $st = new NameFormStorager($this->dbs);
        foreach ($entity->getNameForms() as $nf) {
            if (! empty($nfs)) {
                GeniBaseInternalProperties::setPropertyOf($nf, '_id', array_shift($nfs));
            }
            $st->save($nf, $entity);
        }
        if (! empty($nfs)) {
            $this->dbs->getDb()->executeQuery(
                "DELETE FROM $t_nfs WHERE _id IN (?)",
                array( $nfs ),
                array( \Doctrine\DBAL\Connection::PARAM_INT_ARRAY )
            );
        }

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\ConclusionStorager::getSqlQueryParts()
     */
    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = t.type_id";

        return $qparts;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::loadComponentsRaw()
     */
    protected function loadComponentsRaw($context, $o)
    {
        $result = false;

        $person_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id');
        if (! empty($person_id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAll(
                "$query WHERE t.person_id = ? ORDER BY t.id",
                array( $person_id )
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

        $res = self::unpackDateInfo($result);
        if (! empty($res)) {
            $entity->setDate($res);
        }

        // Load childs

        $st = new NameFormStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setNameForms($res);
        }

        return $entity;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $table = $this->getTableName();
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY t FROM $table AS t WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps.id = t.person_id )";

        $this->dbs->getDb()->query($q);
    }
}
