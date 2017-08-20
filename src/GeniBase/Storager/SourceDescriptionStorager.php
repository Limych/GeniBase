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
use Gedcomx\Source\SourceDescription;
use Gedcomx\Source\SourceCitation;

/**
 *
 * @author Limych
 */
class SourceDescriptionStorager extends GeniBaseStorager
{

    const GROUP_TITLES      = 'http://genibase.net/SourceTitle';
    const GROUP_CITATIONS   = 'http://genibase.net/SourceCitation';
    const GROUP_TITLE_LABEL = 'http://genibase.net/SourceTitleLabel';
    const GROUP_DESCRIPTION = 'http://genibase.net/SourceDescription';
    const GROUP_IDENTIFIERS = 'http://genibase.net/SourceIdentifier';

    protected function getObject($o = null)
    {
        return new SourceDescription($o);
    }

    public function getDefaultOptions($entity = null)
    {
        $def = parent::getDefaultOptions();

        $def['makeId_unique'] = false;
        $def['loadUpstreamSources'] = true;

        /** @var SourceDescription $entity */
        if (! empty($entity)) {
            $name = array();
            $res = $entity->getIdentifiers();
            if (! empty($res)) {
                $name[] = $res[0]->getValue();
            } else {
                $res = $entity->getTitles();
                if (! empty($res)) {
                    $name[] = $res[0]->getValue();
                } else{
                    $res = $entity->getCitations();
                    if (! empty($res)) {
                        $name[] = $res[0]->getValue();
                    }
                }

                $res = $entity->getComponentOf();
                if (! empty($res)) {
                    $name[] = $res->getDescriptionRef();
                }
                $res = $entity->getResourceType();
                if (! empty($res)) {
                    $name[] = $res;
                }
                $res = $entity->getSources();
                if (! empty($res)) {
                    $name[] = $res[0]->getDescriptionRef();
                }
                $res = $entity->getRepository();
                if (! empty($res)) {
                    $name[] = $res->getResourceId();
                }
                $res = $entity->getAbout();
                if (! empty($res)) {
                    $name[] = $res;
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
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        if (parent::detectPreviousState($entity, $context, $o)) {
            if (defined('DEBUG_PROFILE')) {
                \GeniBase\Util\Profiler::stopTimer(__METHOD__);
            }
            return true;
        }

        /** @var SourceDescription $entity */

        $res = $entity->getIdentifiers();
        if (! empty($res)) {
            $id = $this->searchIdByIdentifiers($res);
            if (! empty($id)) {
                $candidate = $this->load(array( 'id' => $id ));
                if (! empty($candidate)) {
                    $this->previousState = clone $candidate;
                    $candidate->initFromArray($entity->toArray());
                    $entity = $candidate;
                    if (defined('DEBUG_PROFILE')) {
                        \GeniBase\Util\Profiler::stopTimer(__METHOD__);
                    }
                    return true;
                }
            }
        }

        $refs = $this->searchRefByTextValues(self::GROUP_CITATIONS, $entity->getCitations());
        if (! empty($refs)) {
            $query = $this->getSqlQuery('SELECT', '', 'WHERE t.id IN (?) LIMIT 1');
            $result = $this->dbs->getDb()->fetchAssoc($query, array($refs), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
            if (! empty($result)) {
                $candidate = $this->unpackLoadedData($this->getObject(), $result);
                $this->previousState = clone $candidate;
                $candidate->initFromArray($entity->toArray());
                $entity = $candidate;

                if (defined('DEBUG_PROFILE')) {
                    \GeniBase\Util\Profiler::stopTimer(__METHOD__);
                }
                return true;
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_agents = $this->dbs->getTableName('agents');

        /** @var SourceDescription $entity */
        $res = $entity->getAbout();
        if (! empty($res) && ($res != $this->previousState->getAbout())) {
            $data['about'] = $res;
        }

        $res = $entity->getSortKey();
        if (! empty($res) && ($res != $this->previousState->getSortKey())) {
            $data['sortKey'] = $res;
        }

        $res = $entity->getMediaType();
        if (! empty($res) && ($res != $this->previousState->getMediaType())) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['mediaType_id'] = $res;
            }
        }

        $res = $entity->getResourceType();
        if (! empty($res) && ($res != $this->previousState->getResourceType())) {
            $res = $this->getTypeId($res);
            if (! empty($res)) {
                $data['resourceType_id'] = $res;
            }
        }

        $res = $entity->getCreated();
        if (! empty($res) && ($res != $this->previousState->getCreated())) {
            $data['created'] = date(self::DATE_SQL, strtotime($res));
        }

        $res = $entity->getModified();
        if (! empty($res) && ($res != $this->previousState->getModified())) {
            $data['modified'] = date(self::DATE_SQL, strtotime($res));
        }

        // TODO: coverage

        $res = $entity->getRights();
        if (! empty($res) && ($res != $this->previousState->getRights())) {
            $data['rights_json'] = json_encode($res);
        }

        $res = $entity->getMediator();
        if (! empty($res) && ($res != $this->previousState->getMediator())) {
            $res = self::packResourceReference($res, 'mediator');
            if (! empty($res['mediator_id'])) {
                $data['mediator_id'] = $res['mediator_id'];
            }
        }

        $res = $entity->getRepository();
        if (! empty($res) && ($res != $this->previousState->getRepository())) {
            $res = self::packResourceReference($res, 'repository');
            if (! empty($res['repository_id'])) {
                $data['repository_id'] = $res['repository_id'];
            }
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
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var SourceDescription $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs

        $res = $entity->getComponentOf();
        if (! empty($res) && ($res != $this->previousState->getComponentOf())) {
            $st = new SourceReferenceStorager($this->dbs);
            $st->save($res, $entity, array(
                'is_componentOf'    => true,
            ));
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

        $res = $entity->getCitations();
        if (! empty($res) && ($res != $this->previousState->getCitations())) {
            $this->saveTextValues(self::GROUP_CITATIONS, $res, $entity);
        }

        $res = $entity->getTitles();
        if (! empty($res) && ($res != $this->previousState->getTitles())) {
            $this->saveTextValues(self::GROUP_TITLES, $res, $entity);
        }

        $res = $entity->getTitleLabel();
        if (! empty($res) && ($res != $this->previousState->getTitleLabel())) {
            $this->saveTextValue(self::GROUP_TITLE_LABEL, $res, $entity);
        }

        $res = $entity->getDescriptions();
        if (! empty($res) && ($res != $this->previousState->getDescriptions())) {
            $this->saveTextValues(self::GROUP_DESCRIPTION, $res, $entity);
        }

        $res = $entity->getIdentifiers();
        if (! empty($res) && ($res != $this->previousState->getIdentifiers())) {
            $this->saveIdentifiers($res, $entity);
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
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
        } elseif ($o['loadUpstreamSources']) {
            $gedcomx->embed($this->loadGedcomxUpstreamSources($sd));
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
        $companions = array();

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

        foreach ($companions as $cp) {
            $gedcomx->embed($cp);
        }

        return $gedcomx;
    }

    public function loadGedcomxUpstreamSources(ExtensibleData $entity)
    {
        /** @var SourceDescription $entity */

        $gedcomx = new Gedcomx();

        $res = $entity->getComponentOf();
        if (! empty($res)) {
            $res = $res->getDescriptionRef();
            if (! empty($res)) {
                $res = self::getIdFromReference($res);
                if (! empty($res)) {
                    $st = new SourceDescriptionStorager($this->dbs);
                    $gedcomx->embed($st->loadGedcomx(array( 'id' => $res )));
                }
            }
        }

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var SourceDescription $entity */

        $gedcomx = parent::loadGedcomxCompanions($entity);

        $res = $entity->getMediator();
        if (! empty($res)) {
            $res = $res->getResourceId();
            if (! empty($res)) {
                $st = new AgentStorager($this->dbs);
                $gedcomx->embed($st->loadGedcomx(array( 'id' => $res )));
            }
        }

        $gedcomx->embed($this->loadGedcomxUpstreamSources($entity));

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

        $res = $entity->getRepository();
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

        return $gedcomx;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $result = false;
        $id = $entity->getId();
        if (! empty($id)) {
            $query = $this->getSqlQuery();
            $result = $this->dbs->getDb()->fetchAssoc("$query WHERE t.id = ?", array($id));
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $table = $this->dbs->getTableName('sources');
        $t_refs = $this->dbs->getTableName('source_references');

        $result = false;
        $id = $context->getId();
        if (empty($id)) {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE NOT EXISTS ( " .
                "SELECT 1 FROM $t_refs AS ref WHERE ref.parent_id = t.id AND is_componentOf = 1 ) " .
                "ORDER BY sortKey"
            );
        } else {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE id IN ( " .
                "SELECT _source_id FROM $t_refs AS ref WHERE is_componentOf = 1 AND description_id = ? ) " .
                "ORDER BY sortKey",
                array( $id )
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
            && (false !== $res = $this->getType($result['mediaType_id']))
        ) {
            $result['mediaType'] = $res;
        }
        if (isset($result['resourceType_id'])
            && (false !== $res = $this->getType($result['resourceType_id']))
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

        $result['repository'] = array();
        if (! empty($result['repository_id'])) {
            $result['repository']['resourceId'] = $result['repository_id'];
        }
        if (empty($result['repository'])) {
            unset($result['repository']);
        }

        $result['mediator'] = array();
        if (! empty($result['mediator_id'])) {
            $result['mediator']['resourceId'] = $result['mediator_id'];
        }
        if (empty($result['mediator'])) {
            unset($result['mediator']);
        }

        /** @var SourceDescription $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        // Load childs

        $res = self::unpackAttribution($result);
        if (! empty($res)) {
            $entity->setAttribution($res);
        }

        $st = new SourceReferenceStorager($this->dbs);

        $res = $st->loadComponents(
            $entity,
            array( 'is_componentOf' => true, )
        );
        if (! empty($res)) {
            $entity->setComponentOf($res[0]);
        }

        $res = $st->loadComponents($entity);
        if (! empty($res)) {
            $entity->setSources($res);
        }

        $res = $this->loadTextValues(self::GROUP_CITATIONS, $entity);
        if (! empty($res)) {
            foreach ($res as $k => $v) {
                $res[$k] = new SourceCitation($v->toArray());
            }
            $entity->setCitations($res);
        }

        $res = $this->loadTextValues(self::GROUP_TITLES, $entity);
        if (! empty($res)) {
            $entity->setTitles($res);
        }

        $res = $this->loadTextValues(self::GROUP_TITLE_LABEL, $entity);
        if (! empty($res)) {
            $entity->setTitleLabel($res[0]);
        }

        $res = $this->loadTextValues(self::GROUP_DESCRIPTION, $entity);
        if (! empty($res)) {
            $entity->setDescriptions($res);
        }

        $res = $this->loadIdentifiers($entity);
        if (! empty($res)) {
            $entity->setIdentifiers($res);
        }

        return $entity;
    }
}
