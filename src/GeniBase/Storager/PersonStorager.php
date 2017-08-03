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
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Name;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\Gender;
use Gedcomx\Conclusion\Fact;

/**
 *
 * @author Limych
 */
class PersonStorager extends SubjectStorager
{

    protected function getObject($o = null)
    {
        return new Person($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('persons');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_places = $this->getTableName();
        $t_sources = $this->dbs->getTableName('sources');

        /** @var Person $entity */
        if (! empty($res = $entity->isPrivate())) {
            $data['private'] = (int) $res;
        }
        if (! empty($res = $entity->isLiving())) {
            $data['living'] = (int) $res;
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
        /** @var Person $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (! empty($res = $entity->getGender()) && ($res != $this->previousState->getGender())) {
            $tmp = $o;
            if (! empty($tmp['makeId_name'])) {
                $tmp['makeId_name'] = 'Gender: ' . $tmp['makeId_name'];
            }
            $this->newStorager(Gender::class)->save($res, $entity, $tmp);
            unset($tmp);
        }
        if (! empty($res = $entity->getNames()) && ($res != $this->previousState->getNames())) {
            $tmp = $o;
            $tmp_cnt = 0;
            foreach ($res as $name) {
                if (! empty($o['makeId_name'])) {
                    $tmp['makeId_name'] = 'Name-' . (++$tmp_cnt) . ': ' . $o['makeId_name'];
                }
                $this->newStorager(Name::class)->save($name, $entity, $tmp);
            }
            unset($tmp);
            unset($tmp_cnt);
        }
        if (! empty($res = $entity->getFacts()) && ($res != $this->previousState->getFacts())) {
            $tmp = $o;
            $tmp_cnt = 0;
            foreach ($res as $fact) {
                $tmp['makeId_name'] = 'Fact-' . (++$tmp_cnt) . ': ' . $entity->getId();
                $this->newStorager(Fact::class)->save($fact, $entity, $o);
            }
            unset($tmp);
            unset($tmp_cnt);
        }

        return $entity;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t.id = ?", [$id]);
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (! empty($result['private'])) {
            settype($result['private'], 'boolean');
        }
        if (! empty($result['living'])) {
            settype($result['living'], 'boolean');
        }

        /**
 * @var Person $entity
*/
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($r = $this->newStorager(Gender::class)->load(null, $entity))) {
            $entity->setGender($r);
        }
        if (! empty($r = $this->newStorager(Name::class)->loadComponents($entity))) {
            $entity->setNames($r);
        }
        if (! empty($r = $this->newStorager(Fact::class)->loadComponents($entity))) {
            $entity->setFacts($r);
        }

        return $entity;
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \LogicException
     */
    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();

        $o = $this->applyDefaultOptions($o);

        if (false === $psn = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addPerson($psn);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($psn));
        }

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /**
 * @var Person $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getGender())) {
            $gedcomx->embed($this->newStorager($r)->loadGedcomxCompanions($r));
        }
        if (! empty($r = $entity->getNames())) {
            foreach ($r as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }
        if (! empty($r = $entity->getFacts())) {
            foreach ($r as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }

        return $gedcomx;
    }
}
