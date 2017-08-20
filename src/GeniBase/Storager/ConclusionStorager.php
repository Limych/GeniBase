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
use Gedcomx\Conclusion\Conclusion;
use Gedcomx\Types\ConfidenceLevel;

/**
 *
 * @author Limych
 */
class ConclusionStorager extends GeniBaseStorager
{

    protected function getObject($o = null)
    {
        return new Conclusion($o);
    }

    protected static function confidenceCmp(Conclusion $a, Conclusion $b)
    {
        $result = 0;

        $confA = $a->getConfidence();
        $confB = $b->getConfidence();
        if (! empty($confA) && ! empty($confB)) {
            switch ($confA) {
                default:
                case ConfidenceLevel::LOW:
                    $confA = 1;
                    break;
                case ConfidenceLevel::MEDIUM:
                    $confA = 2;
                    break;
                case ConfidenceLevel::HIGH:
                    $confA = 3;
                    break;
            }
            switch ($confB) {
                default:
                case ConfidenceLevel::LOW:
                    $confB = 1;
                    break;
                case ConfidenceLevel::MEDIUM:
                    $confB = 2;
                    break;
                case ConfidenceLevel::HIGH:
                    $confB = 3;
                    break;
            }
            $result = ($confA < $confB ? -1 : ($confA > $confB ? 1 : 0));
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        $this->makeUuidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        /** @var Conclusion $entity */
        $res = $entity->getConfidence();
        if (! empty($res) && ($res != $this->previousState->getConfidence())) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['confidence_type_id'] = $res;
            }
        }

        $res = $entity->getLang();
        if (! empty($res) && ($res != $this->previousState->getLang())) {
            $data['lang'] = $res;
        }

        $data = array_merge($data, $this->packAttribution($entity->getAttribution()));

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
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
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var Conclusion $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__ . '#Childs');
        }

        $res = $entity->getSources();
        if (! empty($res) && ($res != ($res2 = $this->previousState->getSources()))) {
            $max = count($res);
            $st = new SourceReferenceStorager($this->dbs);
            for ($i = 0; $i < $max; $i++) {
                if (empty($res2[$i]) || ($res[$i] != $res2[$i])) {
                    $st->save($res[$i], $entity);
                }
            }
            $max = count($res2);
            for (; $i < $max; $i++) {
                // TODO: Delete old $res2[$i]
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__ . '#Childs');
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getSqlQueryParts()
     */
    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp.uri AS confidence";
        $qparts['tables'][]     = "$t_types AS tp";
        $qparts['bundles'][]    = "tp.id = t.confidence_type_id";

        return $qparts;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::loadRaw()
     */
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

        $t_agents = $this->dbs->getTableName('agents');

        if (isset($result['confidence_id'])
            && (false !== $type_id = $this->getType($result['confidence_id']))
        ) {
            $result['confidence'] = $type_id;
        }
        if (isset($result['lang_id'])
            && (false !== $lang_id = $this->dbs->getLang($result['lang_id']))
        ) {
            $result['lang'] = $lang_id;
        }

        /** @var Conclusion $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        // Load childs

        $res = self::unpackAttribution($result);
        if (! empty($res)) {
            $entity->setAttribution($res);
        }

        $st = new SourceReferenceStorager($this->dbs);
        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setSources($res);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Conclusion $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        $res = $entity->getAttribution();
        if (! empty($res)) {
            $res = $res->getContributor();
            if (! empty($res)) {
                $res = $res->getResourceId();
                if (! empty($res)) {
                    $st = new AgentStorager($this->dbs);
                    $gedcomx->embed($st->loadGedcomx(array( 'id' => $res )));
                }
            }
        }

        $list = $entity->getSources();
        if (! empty($list)) {
            foreach ($list as $ent) {
                $res = $ent->getDescriptionRef();
                if (! empty($res)) {
                    $res = self::getIdFromReference($res);
                    if (! empty($res)) {
                        $st = new SourceDescriptionStorager($this->dbs);
                        $gedcomx->embed(
                            $st->loadGedcomx(array( 'id' => $rid ))
                        );
                    }
                }
            }
        }
        // TODO: notes

        return $gedcomx;
    }
}
