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
use Gedcomx\Conclusion\EventRole;

/**
 *
 * @author Limych
 */
class EventRoleStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new EventRole($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('event_roles');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     *
     * @throws \UnexpectedValueException
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var EventRole $entity */

        $data = parent::packData4Save($entity, $context, $o);

        if (empty($context) || empty($context->getId())) {
            throw new \UnexpectedValueException('Context ID required!');
        }
        $data['event_id'] = $context->getId();

        $res = $entity->getType();
        if (! empty($res)) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['type_id'] = $res;
            }
        }

        $res = $entity->getPerson();
        if (empty($res)) {
            throw new \UnexpectedValueException('Person reference ID required!');
        }
        $res = $res->getResourceId();
        if (empty($res)) {
            throw new \UnexpectedValueException('Person reference ID required!');
        }
        $data['person_id'] = $res;

        $res = $entity->getDetails();
        if (! empty($res)) {
            $data['details'] = $res;
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
        return $data;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2.id = t.type_id";

        return $qparts;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $result = false;

        $event_id = $context->getId();
        if (! empty($event_id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAll("$query WHERE t.event_id = ?", array( $event_id ));
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_persons = $this->dbs->getTableName('persons');

        // Unpack data
        $result['person'] = array();
        if (! empty($result['person_id'])) {
            $result['person']['resourceId'] = $result['person_id'];
        }
        if (empty($result['person'])) {
            unset($result['person']);
        }

        /** @var EventRole $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var EventRole $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        $res = $entity->getPerson();
        if (! empty($res)) {
            $res = $res->getResourceId();
            if (! empty($res)) {
                $st = new PersonStorager($this->dbs);
                $gedcomx->embed($st->loadGedcomx(array( 'id' => $res )));
            }
        }

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            if (defined('DEBUG_PROFILE')) {
                \GeniBase\Util\Profiler::stopTimer(__METHOD__);
            }
            return; // Skip cleaning now
        }

        $table = $this->getTableName();
        $t_events = $this->dbs->getTableName('events');

        $query  = "DELETE LOW_PRIORITY t FROM $table AS t WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_events AS ev WHERE ev.id = t.event_id )";

        $this->dbs->getDb()->query($query);
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
    }
}
