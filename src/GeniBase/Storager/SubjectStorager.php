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
use Gedcomx\Conclusion\Subject;

/**
 *
 * @author Limych
 */
class SubjectStorager extends ConclusionStorager
{

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\ConclusionStorager::getObject()
     */
    protected function getObject($o = null)
    {
        return new Subject($o);
    }

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\GeniBaseStorager::detectPreviousState()
     */
    protected function detectPreviousState(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        if (parent::detectPreviousState($entity, $context, $o)) {
            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::stopTimer(__METHOD__);
            }
            return true;
        }

        /** @var Subject $entity */
        $res = $entity->getIdentifiers();
        if (! empty($res)) {
            $id = $this->searchIdByIdentifiers($res);
            if (! empty($id)) {
                $candidate = $this->load(array( 'id' => $id ));
                if (! empty($candidate)) {
                    $this->previousState = clone $candidate;
                    $candidate->initFromArray($entity->toArray());
                    $entity = $candidate;
                    if (defined('DEBUG_PROFILE')) {
                        \App\Util\Profiler::stopTimer(__METHOD__);
                    }
                    return true;
                }
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return false;
    }

    public function getDefaultOptions($entity = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $def = parent::getDefaultOptions();

        if (empty($def['makeId_name']) && ! empty($entity)) {
            /** @var Subject $entity */
            $res = $entity->getIdentifiers();
            if (! empty($res)) {
                foreach ($res as $id) {
                    if (\Gedcomx\Types\IdentifierType::PERSISTENT === $id->getType()) {
                        $def['makeId_name'] = $res[0]->getValue();
                        break;
                    }
                }
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $def;
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
        /** @var PlaceDescription $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        $res = $entity->getIdentifiers();
        if (! empty($res) && ($res != $this->previousState->getIdentifiers())) {
            $this->saveIdentifiers($res, $entity);
        }

        return $entity;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        /** @var Subject $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        $res = $this->loadIdentifiers($entity);
        if (! empty($res)) {
            $entity->setIdentifiers($res);
        }

        return $entity;
    }
}
