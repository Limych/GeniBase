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
use Gedcomx\Conclusion\NamePart;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 */
class NamePartStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new NamePart($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('name_parts');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     *
     * @throws \UnexpectedValueException
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        /** @var NamePart $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_name_form_id'] = (int) $res;
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getValue())) {
            $data['value'] = $res;
        }

        return $data;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp";
        $qparts['bundles'][]    = "tp._id = t.type_id";

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

    protected function loadComponentsRaw($context, $o)
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

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_nps = $this->dbs->getTableName('name_parts');
        $t_nfs = $this->dbs->getTableName('name_forms');

        $q  = "DELETE LOW_PRIORITY np FROM $t_nps AS np WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_nfs AS nf WHERE nf._id = np._name_form_id )";

        $this->dbs->getDb()->query($q);
    }
}
