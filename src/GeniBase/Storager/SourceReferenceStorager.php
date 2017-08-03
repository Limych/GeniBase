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
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceReference;

/**
 *
 * @author Limych
 */
class SourceReferenceStorager extends GeniBaseStorager
{

    protected function getObject($o = null)
    {
        return new SourceReference($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('source_references');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::detectPreviousState()
     */
    protected function detectPreviousState(&$entity, $context = null, $o = null)
    {
        if (parent::detectPreviousState($entity, $context, $o)) {
            return true;
        }

        /** @var SourceReference $entity */

        $o = Util::parseArgs($o, [  'is_componentOf' => false   ]);

        $t_srefs = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        if ($context instanceof ExtensibleData) {
            $context_id = GeniBaseInternalProperties::getPropertyOf($context, '_id');
        } else {
            $context_id = $this->dbs->getInternalId($t_sources, $context['id']);
        }
        if (!empty($context_id) && ! empty($res = $entity->getDescriptionRef())) {
            $query = "SELECT * FROM $t_srefs WHERE _source_id = ? AND is_componentOf = ? AND description_uri = ?";
            $result = $this->dbs->getDb()->fetchAssoc($query, [$context_id, $o['is_componentOf'], $res]);

            if (! empty($result)) {
                $candidate = $this->unpackLoadedData($this->getObject(), $result);
                $this->previousState = clone $candidate;
                $candidate->embed($entity);
                $entity = $candidate;
            }
        }
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

        $t_sources = $this->dbs->getTableName('sources');

        /** @var SourceReference $entity */
        if (empty($res = $entity->getDescriptionRef())) {
            throw new \UnexpectedValueException('Description reference URI required!');
        } elseif ($res != $this->previousState->getDescriptionRef()) {
            $data['description_uri'] = $res;
            if (! empty($res = GeniBaseStorager::getIdFromReference($res))
                && ! empty($res = $this->dbs->getInternalId($t_sources, $res))
            ) {
                $data['description_id'] = (int) $res;
            }
        }
        $data = array_merge($data, $this->packAttribution($entity->getAttribution()));

        if (! empty($data['description_uri'])) {
            if (empty($res = GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
                throw new \UnexpectedValueException('Context internal ID required!');
            } else {
                $data['_source_id'] = $res;
            }
            if (! empty($o['is_componentOf'])) {
                $data['is_componentOf'] = (int) $o['is_componentOf'];
            }
        }

        return $data;
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return ExtensibleData[]|false
     *
     * @throws \UnexpectedValueException
     */
    public function loadComponents($context = null, $o = null)
    {
        $o = Util::parseArgs($o, [  'is_componentOf' => false   ]);

        $t_srefs = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        if ($context instanceof ExtensibleData) {
            $context_id = GeniBaseInternalProperties::getPropertyOf($context, '_id');
        } else {
            $context_id = $this->dbs->getInternalId($t_sources, $context['id']);
        }

        if (empty($context_id)) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }

        $q = "SELECT * FROM $t_srefs WHERE _source_id = ? AND is_componentOf = ?";
        $result = $this->dbs->getDb()->fetchAll($q, [$context_id, $o['is_componentOf']]);

        if (is_array($result)) {
            foreach ($result as $k => $v) {
                $result[$k] = $this->unpackLoadedData($this->getObject(), $v);
            }
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['description_uri'])) {
            $result['description'] = $result['description_uri'];
        }
        $result = $this->processRawAttribution($entity, $result);

        $entity = parent::unpackLoadedData($entity, $result);

        return $entity;
    }
}
