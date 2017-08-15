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
use Gedcomx\Conclusion\Fact;

/**
 *
 * @author Limych
 */
class FactStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Fact($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('facts');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        /** @var Fact $entity */
        $data = parent::packData4Save($entity, $context, $o);

        $t_facts = $this->getTableName();
        $t_places = $this->dbs->getTableName('places');

        if (empty($context) || empty($context->getId())) {
            throw new \UnexpectedValueException('Context ID required!');
        }
        $data['person_id'] = $context->getId();

        $res = $entity->getPrimary();
        if (! empty($res)) {
            $data['primary'] = (int) $res;
        }

        $res = $entity->getType();
        if (! empty($res)) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['type_id'] = $res;
            }
        }

        $res = $entity->getValue();
        if (! empty($res)) {
            $data['value'] = $res;
        }

        $res = $entity->getDate();
        if (! empty($res)) {
            $data = array_merge($data, self::packDateInfo($res));
        }

        $res = $entity->getPlace();
        if (! empty($res)) {
            $res2 = $res->getDescriptionRef();
            if (! empty($res)) {
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

        $person_id = $context->getId();
        if (! empty($person_id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAll("$query WHERE t.person_id = ?", array( $person_id ));
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        // Unpack data
        if (! empty($result['primary'])) {
            settype($result['primary'], 'boolean');
        }
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

        /** @var Person $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        $res = self::unpackDateInfo($result);
        if (! empty($res)) {
            $entity->setDate($res);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Fact $entity */
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

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $table = $this->getTableName();
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY t FROM $table AS t WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps.id = t.person_id )";

        $this->dbs->getDb()->query($q);
    }
}
