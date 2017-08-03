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
use Gedcomx\Conclusion\Identifier;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Source\SourceReference;
use Gedcomx\Source\SourceCitation;
use Gedcomx\Agent\Agent;
use GeniBase\Util\IDateTime;

/**
 *
 * @author Limych
 */
class SourceDescriptionStorager extends GeniBaseStorager
{

    const GROUP_TITLES      = 'http://genibase/SourceTitle';
    const GROUP_CITATIONS   = 'http://genibase/SourceCitation';
    const GROUP_TITLE_LABEL = 'http://genibase/SourceTitleLabel';
    const GROUP_DESCRIPTION = 'http://genibase/SourceDescription';
    const GROUP_IDENTIFIERS = 'http://genibase/SourceIdentifier';

    protected function getObject($o = null)
    {
        return new SourceDescription($o);
    }

    public function getDefaultOptions($entity = null)
    {
        $def = parent::getDefaultOptions();

        $def['makeId_unique'] = false;

        /** @var SourceDescription $entity */
        if (! empty($entity)) {
            $name = [];
            if (! empty($r = $entity->getIdentifiers())) {
                $name[] = $r[0]->getValue();
            } else {
                if (! empty($r = $entity->getTitles())) {
                    $name[] = $r[0]->getValue();
                } elseif (! empty($r = $entity->getCitations())) {
                    $name[] = $r[0]->getValue();
                }

                if (! empty($r = $entity->getComponentOf())) {
                    $name[] = $r->getDescriptionRef();
                }
                if (! empty($r = $entity->getResourceType())) {
                    $name[] = $r;
                }
                if (! empty($r = $entity->getSources())) {
                    $name[] = $r[0]->getDescriptionRef();
                }
                if (! empty($r = $entity->getRepository())) {
                    $name[] = $r->getResourceId();
                }
                if (! empty($r = $entity->getAbout())) {
                    $name[] = $r;
                }
            }
            if (! empty($name)) {
                $def['makeId_name'] = join("\t", $name);
            }
        }

        return $def;
    }

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

        /** @var SourceDescription $entity */

        if (! empty($r = $entity->getIdentifiers())
            && ! empty($id = $this->newStorager(Identifier::class)->getIdByIdentifier($r))
        ) {
            $candidate = $this->load([ 'id' => $id ]);
            $this->previousState = clone $candidate;
            $candidate->embed($entity);
            $entity = $candidate;

            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::stopTimer(__METHOD__);
            }
            return true;
        }
        if (! empty($refs = $this->searchRefByTextValues(self::GROUP_CITATIONS, $entity->getCitations()))) {
            $query = $this->getSqlQuery('SELECT', '', 'WHERE t._id IN (?) LIMIT 1');
            $result = $this->dbs->getDb()->fetchAssoc($query, [$refs], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            if (! empty($result)) {
                $candidate = $this->unpackLoadedData($this->getObject(), $result);
                $this->previousState = clone $candidate;
                $candidate->embed($entity);
                $entity = $candidate;

                if (defined('DEBUG_PROFILE')) {
                    \App\Util\Profiler::stopTimer(__METHOD__);
                }
                return true;
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('sources');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_agents = $this->dbs->getTableName('agents');

        /** @var SourceDescription $entity */
        if (! empty($res = $entity->getAbout()) && ($res != $this->previousState->getAbout())) {
            $data['about'] = $res;
        }
        if (! empty($res = $entity->getSortKey()) && ($res != $this->previousState->getSortKey())) {
            $data['sortKey'] = $res;
        }
        if (! empty($res = $entity->getMediaType()) && ($res != $this->previousState->getMediaType())
            && ! empty($res = $this->dbs->getTypeId($res))
        ) {
            $data['mediaType_id'] = $res;
        }
        if (! empty($res = $entity->getResourceType()) && ($res != $this->previousState->getResourceType())
            && ! empty($res = $this->dbs->getTypeId($res))
        ) {
            $data['resourceType_id'] = $res;
        }
        if (! empty($res = $entity->getCreated()) && ($res != $this->previousState->getCreated())) {
            $data['created'] = date(IDateTime::SQL, strtotime($res));
        }
        if (! empty($res = $entity->getModified()) && ($res != $this->previousState->getModified())) {
            $data['modified'] = date(IDateTime::SQL, strtotime($res));
        }
        // TODO: coverage
        if (! empty($res = $entity->getRights()) && ($res != $this->previousState->getRights())) {
            $data['rights_json'] = json_encode($res);
        }
        if (! empty($res = $entity->getMediator()) && ($res != $this->previousState->getMediator())
            && ! empty($res = $this->getResourceIdFromUri($t_agents, $res))
        ) {
            $data['mediator_id'] = $res;
        }
        if (! empty($res = $entity->getRepository() && ($res != $this->previousState->getRepository()))
            && ! empty($res = $this->getResourceIdFromUri($t_agents, $res))
        ) {
            $data['repository_id'] = $res;
        }
        $data = array_merge($data, $this->packAttribution($entity->getAttribution()));

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
        /** @var SourceDescription $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (! empty($res = $entity->getComponentOf()) && ($res != $this->previousState->getComponentOf())) {
            $this->newStorager(SourceReference::class)->save($res, $entity, [
                'is_componentOf'    => true,
            ]);
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
        if (! empty($res = $entity->getCitations()) && ($res != $this->previousState->getCitations())) {
            $this->saveAllTextValues(self::GROUP_CITATIONS, $res, $entity);
        }
        if (! empty($res = $entity->getTitles()) && ($res != $this->previousState->getTitles())) {
            $this->saveAllTextValues(self::GROUP_TITLES, $res, $entity);
        }
        if (! empty($res = $entity->getTitleLabel()) && ($res != $this->previousState->getTitleLabel())) {
            $this->saveTextValue(self::GROUP_TITLE_LABEL, $res, $entity);
        }
        if (! empty($res = $entity->getDescriptions()) && ($res != $this->previousState->getDescriptions())) {
            $this->saveAllTextValues(self::GROUP_DESCRIPTION, $res, $entity);
        }
        if (! empty($res = $entity->getIdentifiers()) && ($res != $this->previousState->getIdentifiers())) {
            foreach ($res as $id) {
                $this->newStorager(Identifier::class)->save($id, $entity);
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
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

        if (false === $sd = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addSourceDescription($sd);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($sd));
        }

        return $gedcomx;
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
    public function loadComponentsGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();
        $companions = [];

        $o = $this->applyDefaultOptions($o);

        if (false === $res = $this->loadComponents($entity, $o)) {
            return false;
        }

        foreach ($res as $sd) {
            $gedcomx->addSourceDescription($sd);
            if ($o['loadCompanions']) {
                $companions[] = $this->loadGedcomxCompanions($sd);
            }
        }

        foreach ($companions as $c) {
            $gedcomx->embed($c);
        }

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var SourceDescription $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($res = $entity->getMediator()) && ! empty($res = $res->getResourceId())) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $res ]));
        }
        if (! empty($res = $entity->getComponentOf()) && ! empty($res = $res->getDescriptionRef())
            && ! empty($res = GeniBaseStorager::getIdFromReference($res))
        ) {
            $gedcomx->embed(
                $this->newStorager(SourceDescription::class)->loadGedcomx([ 'id' => $res ])
            );
        }
        if (! empty($res = $entity->getAttribution()) && ! empty($res = $res->getContributor())
            && ! empty($res = $res->getResourceId())
        ) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $res ]));
        }

        if (! empty($res = $entity->getRepository()) && ! empty($res = $res->getContributor())
            && ! empty($res = $res->getResourceId())
        ) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $res ]));
        }

        return $gedcomx;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $table = $this->dbs->getTableName('sources');

        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE _id = ?",
                [$_id]
            );
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE id = ?",
                [$id]
            );
        }

        if ((false !== $result) && ! empty($r = parent::loadRaw($entity, $context, $o))) {
            $result = array_merge($result, $r);
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $table = $this->dbs->getTableName('sources');
        $t_refs = $this->dbs->getTableName('source_references');

        $result = false;
        if (empty($id = $context->getId())) {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE NOT EXISTS ( " .
                "SELECT 1 FROM $t_refs AS ref WHERE ref._source_id = t._id AND is_componentOf = 1 ) " .
                "ORDER BY sortKey"
            );
        } elseif (! empty($_ref = $this->dbs->getInternalId($table, $id))) {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE _id IN ( " .
                "SELECT _source_id FROM $t_refs AS ref WHERE is_componentOf = 1 AND description_id = ? ) " .
                "ORDER BY sortKey",
                [(int) $_ref]
            );
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_agents = $this->dbs->getTableName('agents');

        if (isset($result['mediaType_id'])
            && (false !== $res = $this->dbs->getType($result['mediaType_id']))
        ) {
            $result['mediaType'] = $res;
        }
        if (isset($result['resourceType_id'])
            && (false !== $res = $this->dbs->getType($result['resourceType_id']))
        ) {
            $result['resourceType'] = $res;
        }
        if (! empty($result['created'])) {
            $result['created'] = date(DATE_W3C, strtotime($result['created']));
        }
        if (! empty($result['modified'])) {
            $result['modified'] = date(DATE_W3C, strtotime($result['modified']));
        }
        // TODO: coverage
        if (! empty($result['rights_json'])) {
            $data['rights'] = json_decode($result['rights_json'], true);
        }

        $result['repository'] = [];
        if (isset($result['repository_id'])
            && (false !== $res = $this->dbs->getPublicId($t_agents, $result['repository_id']))
        ) {
            $result['repository']['resourceId'] = $res;
        }
        if (empty($result['repository'])) {
            unset($result['repository']);
        }

        $result['mediator'] = [];
        if (isset($result['mediator_id'])
            && (false !== $res = $this->dbs->getPublicId($t_agents, $result['mediator_id']))
        ) {
            $result['mediator']['resourceId'] = $res;
        }
        if (empty($result['mediator'])) {
            unset($result['mediator']);
        }

        /** @var SourceDescription $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = self::unpackAttribution($result))) {
            $entity->setAttribution($res);
        }

        // Load childs
        $res = $this->newStorager(SourceReference::class)
            ->loadComponents(
                $entity,
                [
                'is_componentOf'    => true,
                ]
            );
        if (! empty($res)) {
            $entity->setComponentOf($res[0]);
        }

        $res = $this->newStorager(SourceReference::class)->loadComponents($entity);
        if (! empty($res)) {
            $entity->setSources($res);
        }

        $res = $this->loadAllTextValues(self::GROUP_CITATIONS, $entity);
        if (! empty($res)) {
            foreach ($res as $k => $v) {
                $res[$k] = new SourceCitation($v->toArray());
            }
            $entity->setCitations($res);
        }

        if (! empty($res = $this->loadAllTextValues(self::GROUP_TITLES, $entity))) {
            $entity->setTitles($res);
        }
        if (! empty($res = $this->loadTextValue(self::GROUP_TITLE_LABEL, $entity))) {
            $entity->setTitleLabel($res);
        }
        if (! empty($res = $this->loadAllTextValues(self::GROUP_DESCRIPTION, $entity))) {
            $entity->setDescriptions($res);
        }
        if (! empty($res = $this->newStorager(Identifier::class)->loadComponents($entity))) {
            $entity->setIdentifiers($res);
        }

        return $entity;
    }
}
