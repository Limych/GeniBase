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
use Gedcomx\Conclusion\Person;

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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        /** @var Person $entity */

        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $data['private'] = (int) $entity->isPrivate();
        $data['living'] = (int) $entity->isLiving();

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

        $res = $entity->getGender();
        if (! empty($res) && ($res != $this->previousState->getGender())) {
            $tmp = $o;
            if (! empty($tmp['makeId_name'])) {
                $tmp['makeId_name'] = 'Gender: ' . $tmp['makeId_name'];
            }
            $st = new GenderStorager($this->dbs);
            $st->save($res, $entity, $tmp);
            unset($tmp);
        }

        $res = $entity->getNames();
        if (! empty($res) && ($res != $this->previousState->getNames())) {
            $tmp = $o;
            $tmp_cnt = 0;
            $st = new NameStorager($this->dbs);
            foreach ($res as $name) {
                if (! empty($o['makeId_name'])) {
                    $tmp['makeId_name'] = 'Name-' . (++$tmp_cnt) . ': ' . $o['makeId_name'];
                }
                $st->save($name, $entity, $tmp);
            }
            unset($tmp);
            unset($tmp_cnt);

            // TODO Delete previous names
        }

        $res = $entity->getFacts();
        if (! empty($res) && ($res != $this->previousState->getFacts())) {
            $tmp = $o;
            $tmp_cnt = 0;
            $st = new FactStorager($this->dbs);
            foreach ($res as $fact) {
                $tmp['makeId_name'] = 'Fact-' . (++$tmp_cnt) . ': ' . $entity->getId();
                $st->save($fact, $entity, $o);
            }
            unset($tmp);
            unset($tmp_cnt);

            // TODO Delete previous facts
        }

        return $entity;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $result = false;
        $id = $entity->getId();
        if (! empty($id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAssoc("$query WHERE t.id = ?", array( $id ));
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

        $st = new GenderStorager($this->dbs);
        $res = $st->load(null, $entity);
        if (! empty($res)) {
            $entity->setGender($res);
        }

        $st = new NameStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setNames($res);
        }

        $st = new FactStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setFacts($res);
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

        $res = $entity->getGender();
        if (! empty($res)) {
            $gedcomx->embed($this->newStorager($res)->loadGedcomxCompanions($res));
        }

        $res = $entity->getNames();
        if (! empty($res)) {
            foreach ($res as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }

        $res = $entity->getFacts();
        if (! empty($res)) {
            foreach ($res as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }

        return $gedcomx;
    }
}
