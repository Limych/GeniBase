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
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceReference;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Agent\Agent;
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

        if (! empty($confA = $a->getConfidence()) && ! empty($confB = $b->getConfidence())) {
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
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $this->makeUuidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        /** @var Conclusion $entity */
        if (! empty($res = $entity->getConfidence()) && ($res != $this->previousState->getConfidence())
            && ! empty($res = $this->dbs->getTypeId($res))
        ) {
            $data['confidence_id'] = $res;
        }
        if (! empty($res = $entity->getLang()) && ($res != $this->previousState->getLang())
            && ! empty($res = $this->dbs->getLangId($res))
        ) {
            $data['lang_id'] = $res;
        }
        $data = array_merge($data, $this->packAttribution($entity->getAttribution()));

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
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
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var Conclusion $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__ . '#Childs');
        }
        if (! empty($res = $entity->getSources()) && ($res != ($res2 = $this->previousState->getSources()))) {
            $max = count($res);
            for ($i = 0; $i < $max; $i++) {
                if (empty($res2[$i]) || ($res[$i] != $res2[$i])) {
                    $this->newStorager(SourceReference::class)->save($res[$i], $entity);
                }
            }
            $max = count($res2);
            for (; $i < $max; $i++) {
                // TODO: Delete old $res2[$i]
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#Childs');
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');
        $t_langs = $this->dbs->getTableName('languages');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "l.lang";
        $qparts['tables'][]     = "$t_langs AS l";
        $qparts['bundles'][]    = "l._id = t.lang_id";

        $qparts['fields'][]     = "tp.uri AS confidence";
        $qparts['tables'][]     = "$t_types AS tp";
        $qparts['bundles'][]    = "tp._id = t.confidence_id";

        return $qparts;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::loadRaw()
     */
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

        $t_agents = $this->dbs->getTableName('agents');

        if (isset($result['confidence_id'])
            && (false !== $type_id = $this->dbs->getType($result['confidence_id']))
        ) {
            $result['confidence'] = $type_id;
        }
        if (isset($result['lang_id'])
            && (false !== $lang_id = $this->dbs->getLang($result['lang_id']))
        ) {
            $result['lang'] = $lang_id;
        }
        $result = $this->processRawAttribution($entity, $result);

        $entity = parent::unpackLoadedData($entity, $result);
// if ($entity instanceof \Gedcomx\Conclusion\PlaceDescription && $entity->getId() != 'L7HW-ANNP-V43N') { var_dump($result, $entity);die; } // FIXME Delete me

        // Load childs
        if (! empty($res = $this->newStorager(SourceReference::class)->loadComponents($entity))) {
            $entity->setSources($res);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Conclusion $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getAttribution()) && ! empty($r = $r->getContributor())
        && ! empty($rid = $r->getResourceId())) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $rid ]));
        }

        if (! empty($list = $entity->getSources())) {
            foreach ($list as $ent) {
                if (! empty($r = $ent->getDescriptionRef())
                    && ! empty($rid = GeniBaseStorager::getIdFromReference($r))
                ) {
                    $gedcomx->embed(
                        $this->newStorager(SourceDescription::class)->loadGedcomx([ 'id' => $rid ])
                    );
                }
            }
        }
        // TODO: notes

        return $gedcomx;
    }
}
