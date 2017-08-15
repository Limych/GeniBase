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
use Gedcomx\Conclusion\NameForm;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 */
class NameFormStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new NameForm($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('name_forms');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        /** @var NameForm $entity */
        $data = parent::packData4Save($entity, $context, $o);

        if (empty($context) || empty($context->getId())) {
            throw new \UnexpectedValueException('Context ID required!');
        }
        $data['name_id'] = $context->getId();

        $res = $entity->getLang();
        if (! empty($res)) {
            $data['lang'] = $res;
        }

        $res = $entity->getFullText();
        if (! empty($res)) {
            $data['full_text'] = $res;
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
        /** @var NameForm $entity */
        $entity = parent::save($entity, $context, $o);

        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        if (empty($_id)) {
            $_id = (int) GeniBaseInternalProperties::getPropertyOf($this->previousState, '_id');
            if (empty($_id)) {
                $_id = $this->dbs->getDb()->lastInsertId();
            }
            GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        }

        // Save childs

        $t_nps = $this->dbs->getTableName('name_parts');
        $nps = $this->dbs->getDb()->fetchAll("SELECT _id FROM $t_nps WHERE _name_form_id = ?", array( $_id ));
        if (! empty($nps)) {
            $nps = array_map(
                function ($v) {
                    return (int) $v['_id'];
                },
                $nps
            );
        }
        $st = new NamePartStorager($this->dbs);
        foreach ($entity->getParts() as $np) {
            if (! empty($nps)) {
                GeniBaseInternalProperties::setPropertyOf($np, '_id', array_shift($nps));
            }
            $st->save($np, $entity);
        }
        if (! empty($nps)) {
            $this->dbs->getDb()->executeQuery(
                "DELETE FROM $t_nps WHERE _id IN (?)",
                array( $nps ),
                array( \Doctrine\DBAL\Connection::PARAM_INT_ARRAY )
            );
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_langs = $this->dbs->getTableName('languages');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "l.lang";
        $qparts['tables'][]     = "$t_langs AS l";
        $qparts['bundles'][]    = "l._id = t.lang_id";

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $result = false;

        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        if (! empty($_id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAssoc("$query WHERE nf._id = ?", array( $_id ));
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $result = false;

        $name_id = $context->getId();
        if (! empty($name_id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAll("$query WHERE nf.name_id = ?", array( $name_id ));
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['full_text'])) {
            $result['fullText'] = $result['full_text'];
        }

        /** @var NameForm $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        $st = new NamePartStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setParts($res);
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
        $t_names = $this->dbs->getTableName('names');

        $query  = "DELETE LOW_PRIORITY t FROM $table AS t WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_names AS nm WHERE nm.id = t.name_id )";

        $this->dbs->getDb()->query($query);
    }
}
