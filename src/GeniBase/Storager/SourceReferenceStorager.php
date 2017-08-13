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

        $o = Util::parseArgs($o, array( 'is_componentOf' => false ));

        $t_srefs = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        $context_id = ($context instanceof ExtensibleData ? $context->getId() : $context['id']);
        $res = $entity->getDescriptionRef();
        if (!empty($context_id) && ! empty($res)) {
            $query = "SELECT * FROM $t_srefs WHERE parent_id = ? AND is_componentOf = ? AND description_uri = ?";
            $result = $this->dbs->getDb()->fetchAssoc($query, array($context_id, $o['is_componentOf'], $res));
            if (! empty($result)) {
                $candidate = $this->unpackLoadedData($this->getObject(), $result);
                $this->previousState = clone $candidate;
                $candidate->initFromArray($entity->toArray());
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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        $t_sources = $this->dbs->getTableName('sources');

        /** @var SourceReference $entity */
        $res = $entity->getDescriptionRef();
        if (empty($res)) {
            throw new \UnexpectedValueException('Description reference URI required!');
        } elseif ($res != $this->previousState->getDescriptionRef()) {
            $data['description_uri'] = $res;
            $res = self::getIdFromReference($res);
            if (! empty($res)) {
                $data['description_id'] = $res;
            }
        }

        if (! empty($data['description_uri'])) {
            $data['parent_id'] = $context->getId();
            if (! empty($o['is_componentOf'])) {
                $data['is_componentOf'] = (int) $o['is_componentOf'];
            }
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::save()
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var Conclusion $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__ . '#Childs');
        }
        AttributionStorager::saveAttribution($this->dbs, $entity->getAttribution(), $entity);

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#Childs');
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $entity;
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
        $o = Util::parseArgs($o, array( 'is_componentOf' => false ));

        $t_srefs = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        $context_id = ($context instanceof ExtensibleData ? $context->getId() : $context['id']);
        if (empty($context_id)) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        $query = "SELECT * FROM $t_srefs WHERE parent_id = ? AND is_componentOf = ?";
        $result = $this->dbs->getDb()->fetchAll($query, array($context_id, $o['is_componentOf']));
        if (is_array($result)) {
            foreach ($result as $key => $val) {
                $result[$key] = $this->unpackLoadedData($this->getObject(), $val);
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
