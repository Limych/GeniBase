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

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Event;

/**
 *
 * @author Limych
 */
class EventStorager extends SubjectStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Event($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('events');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        /** @var Event $entity */

        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_events = $this->getTableName();
        $t_places = $this->dbs->getTableName('places');

        $res = $entity->getType();
        if (! empty($res)) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['type_id'] = $res;
            }
        }

        $res = $entity->getDate();
        if (! empty($res)) {
            $data = array_merge($data, self::packDateInfo($res));
        }

        $res = $entity->getPlace();
        if (! empty($res)) {
            $res2 = $res->packDescriptionRef();
            if (! empty($res2)) {
                $data['place_description_uri'] = $res2;
                $res2 = self::getIdFromReference($res2);
                if (! empty($res2)) {
                    $data['place_description_id'] = $res2;
                }
            }

            $res2 = $res->getOriginal();
            if (! empty($res2)) {
                $data['place_original'] = $res2;
            }
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
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var Event $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs

        $res = $entity->getRoles();
        if (! empty($res) && ($res != $this->previousState->getRoles())) {
            $tmp = $o;
            $tmp_cnt = 0;
            $st = new EventRoleStorager($this->dbs);
            foreach ($res as $er) {
                if (! empty($o['makeId_name'])) {
                    $tmp['makeId_name'] = 'EventRole-' . (++$tmp_cnt) . ': ' . $o['makeId_name'];
                }
                $st->save($er, $entity);
            }
            unset($tmp);
            unset($tmp_cnt);

            // TODO Delete previous roles
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
        return $entity;
    }

    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();

        $o = $this->applyDefaultOptions($o);

        if (false === $ev = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addEvent($ev);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($ev));
        }

        return $gedcomx;
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

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        // Unpack data
        $result['place'] = array();
        if (! empty($result['place_description'])) {
            $result['place']['description'] = $result['place_description'];
        }
        if (! empty($result['place_original'])) {
            $result['place']['original'] = $result['place_original'];
        }
        if (empty($result['place'])) {
            unset($result['place']);
        }

        /** @var Event $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        $res = self::unpackDateInfo($result);
        if (! empty($res)) {
            $entity->setDate($res);
        }

        // Load childs

        $st = new EventRoleStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setRoles($res);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Event $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        $res = $entity->getPlace();
        if (! empty($res)) {
            $res = $res->getDescriptionRef();
            if (! empty($res)) {
                $res = self::getIdFromReference($res);
                if (! empty($res)) {
                    $st = new PlaceDescriptionStorager($this->dbs);
                    $gedcomx->embed($st->loadGedcomx(array( 'id' => $res )));
                }
            }
        }

        $res = $entity->getRoles();
        if (! empty($res)) {
            $st = new EventRoleStorager($this->dbs);
            foreach ($res as $val) {
                $gedcomx->embed($st->loadGedcomxCompanions($val));
            }
        }

        return $gedcomx;
    }
}
