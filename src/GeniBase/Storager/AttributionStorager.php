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

use Gedcomx\Common\Attribution;
use GeniBase\DBase\DBaseService;

/**
 *
 * @author Limych
 *
 */
class AttributionStorager extends GeniBaseStorager
{

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\GeniBaseStorager::getObject()
     */
    protected function getObject($o = null)
    {
        return new Attribution($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('attributions');
    }

    /**
     *
     * @param DBaseService $dbs
     * @param array[] $qparts
     * @return array[]
     */
    public static function addAttributionSqlQueryParts(DBaseService $dbs, array $qparts)
    {
        $t_attributions = $dbs->getTableName('attributions');

        $qparts['fields'][]     = "att.*";
        $qparts['tables'][]     = "$t_attributions AS att";
        $qparts['bundles'][]    = "att.id = t.id";

        return $qparts;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        if ($entity === $this->previousState) {
            return $data;
        }

        $data = parent::packData4Save($entity, $context, $o);

        /** @var Attribution $entity */
        $res = $context->getId();
        if (! empty($res)) {
            $data['id'] = $res;
        }

        $res = $entity->getContributor();
        if (empty($res)) {
            $res2 = $this->dbs->getAgent();
            if (! empty($res2)) {
                $data['att_contributor'] = $res2->getId();
            }
        } elseif (($res != $prev->getContributor())) {
            $res = $res->getResourceId();
            if (! empty($res)) {
                $data['att_contributor'] = $res;
            }
        }

        $res = $entity->getModified();
        if (! empty($res)) {
            $data['att_modified'] = date(self::DATE_SQL, strtotime($res));
        }

        $res = $entity->getChangeMessage();
        if (! empty($res)) {
            $data['att_changeMessage'] = $res;
        }

        return $data;
    }

    /**
     *
     * @param DBaseService $dbs
     * @param Attribution $attribution
     * @param object $context
     * @return \Gedcomx\Common\ExtensibleData|false
     */
    public static function saveAttribution(DBaseService $dbs, $attribution, $context)
    {
        $att = new self($dbs);
        return $att->save($attribution, $context);
    }

    /**
     *
     * @param mixed[] $result
     * @return \Gedcomx\Common\Attribution
     */
    public static function unpackAttribution($result)
    {
        $data = array(
            'id' => $result['id'],
        );

        if (! empty($result['att_contributor'])) {
            $data['contributor'] = array( 'resourceId' => $result['att_contributor'] );
        }
        if (! empty($result['att_modified'])) {
            $data['modified'] = $result['att_modified'];
        }
        if (! empty($result['att_changeMessage'])) {
            $data['changeMessage'] = $result['att_changeMessage'];
        }

        return new Attribution($data);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::unpackLoadedData()
     */
    protected function unpackLoadedData($entity, $result)
    {
        /** @var Attribution $entity */
        return parent::unpackLoadedData();
    }
}
