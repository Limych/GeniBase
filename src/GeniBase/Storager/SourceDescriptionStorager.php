<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Identifier;
use GeniBase\Util;
use GeniBase\DBase\DBaseService;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Source\SourceReference;
use Gedcomx\Source\SourceCitation;
use Gedcomx\Agent\Agent;

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

    public function getDefaultOptions(ExtensibleData $entity = null)
    {
        $def = parent::getDefaultOptions();

        $def['makeId_unique'] = false;

        /**
 * @var SourceDescription $entity
*/
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

    protected function detectId(ExtensibleData &$entity)
    {
        /**
 * @var SourceDescription $entity
*/
        if (! empty($r = $entity->getIdentifiers())
            && ! empty($id = $this->newStorager(Identifier::class)->getIdByIdentifier($r))
        ) {
            $entity->setId($id);
            return true;
        }

        return false;
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
        if (! $entity instanceof ExtensibleData) {
            $entity = $this->getObject($entity);
        }

        $o = $this->applyDefaultOptions($o, $entity);
        $this->makeGbidIfEmpty($entity, $o);

        $t_srcs = $this->dbs->getTableName('sources');
        $t_agents = $this->dbs->getTableName('agents');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::arraySliceKeys($ent, 'id', 'about', 'sortKey');

        if (isset($ent['mediaType']) && (false !== $r = $this->getTypeId($ent['mediaType']))) {
            $data['mediaType_id'] = $r;
        }
        if (isset($ent['resourceType']) && (false !== $r = $this->getTypeId($ent['resourceType']))) {
            $data['resourceType_id'] = $r;
        }
        if (! empty($ent['created'])) {
            $data['created'] = date('Y-m-d H:i:s', strtotime($ent['created']));
        }
        if (! empty($ent['modified'])) {
            $data['modified'] = date('Y-m-d H:i:s', strtotime($ent['modified']));
        }
        // TODO: coverage
        if (! empty($ent['rights'])) {
            $data['rights_json'] = json_encode($ent['rights']);
        }

        if (isset($ent['mediator'])) {
            if (isset($ent['mediator']['resource']) && empty($ent['mediator']['resourceId'])
                && ! empty($id = DBaseService::getIdFromReference($ent['mediator']['resource']))
            ) {
                    $ent['mediator']['resourceId'] = $id;
            }
            if (isset($ent['mediator']['resourceId'])
                && ! empty($r = $this->dbs->getLidForId($t_agents, $ent['mediator']['resourceId']))
            ) {
                    $data['mediator_id'] = $r;
            }
        }

        if (isset($ent['repository'])) {
            if (isset($ent['repository']['resource']) && empty($ent['repository']['resourceId'])
                && ! empty($id = DBaseService::getIdFromReference($ent['repository']['resource']))
            ) {
                $ent['repository']['resourceId'] = $id;
            }
            if (isset($ent['repository']['resourceId'])
                && ! empty($r = $this->dbs->getLidForId($t_agents, $ent['repository']['resourceId']))
            ) {
                $data['repository_id'] = $r;
            }
        }

        if (empty($ent['attribution'])) {
            $ent['attribution'] = [];
        }
        if (! isset($ent['attribution']['contributor']) && ! empty($r = $this->dbs->getAgent())) {
            $ent['attribution']['contributor'] = [ 'resourceId' => $r->getId() ];
        }
        if (isset($ent['attribution']['contributor']) && isset($ent['attribution']['contributor']['resourceId'])
            && (false !== $r = $this->dbs->getLidForId($t_agents, $ent['attribution']['contributor']['resourceId']))
        ) {
            $data['att_contributor_id'] = $r;
        }
        if (! empty($ent['attribution']['modified'])) {
            $data['att_modified'] = date('Y-m-d H:i:s', strtotime($ent['attribution']['modified']));
        }
        if (! empty($ent['attribution']['changeMessage'])) {
            $data['att_changeMessage'] = $ent['attribution']['changeMessage'];
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_srcs, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_srcs,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_srcs, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);

        // Save childs
        if (isset($ent['componentOf'])) {
            $this->newStorager(SourceReference::class)->save(
                $ent['componentOf'],
                $entity,
                [
                'is_componentOf'    => true,
                ]
            );
        }
        if (! empty($ent['sources'])) {
            foreach ($ent['sources'] as $src) {
                $this->newStorager(SourceReference::class)->save($src, $entity);
            }
        }

        if (isset($ent['citations'])) {
            $this->saveAllTextValues(self::GROUP_CITATIONS, $ent['citations'], $entity);
        }
        if (isset($ent['titles'])) {
            $this->saveAllTextValues(self::GROUP_TITLES, $ent['titles'], $entity);
        }
        if (isset($ent['titleLabel'])) {
            $this->saveTextValue(self::GROUP_TITLE_LABEL, $ent['titleLabel'], $entity);
        }
        if (isset($ent['descriptions'])) {
            $this->saveAllTextValues(self::GROUP_DESCRIPTION, $ent['descriptions'], $entity);
        }
        if (! empty($r = $entity->getIdentifiers())) {
            foreach ($r as $id) {
                $this->newStorager(Identifier::class)->save($id, $entity);
            }
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
    public function loadListGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();
        $companions = [];

        $o = $this->applyDefaultOptions($o);

        if (false === $result = $this->loadList($entity, $context, $o)) {
            return false;
        }

        foreach ($result as $sd) {
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
        /**
 * @var SourceDescription $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getMediator()) && ! empty($rid = $r->getResourceId())) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $rid ]));
        }
        if (! empty($r = $entity->getComponentOf()) && ! empty($r = $r->getDescriptionRef())
            && ! empty($rid = DBaseService::getIdFromReference($r))
        ) {
            $gedcomx->embed(
                $this->newStorager(SourceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }
        if (! empty($r = $entity->getAttribution()) && ! empty($r = $r->getContributor())
            && ! empty($rid = $r->getResourceId())
        ) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $rid ]));
        }

        if (! empty($r = $entity->getRepository()) && ! empty($r = $r->getContributor())
            && ! empty($rid = $r->getResourceId())
        ) {
            $gedcomx->embed($this->newStorager(Agent::class)->loadGedcomx([ 'id' => $rid ]));
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

    protected function loadListRaw($context, $o)
    {
        $table = $this->dbs->getTableName('sources');
        $t_refs = $this->dbs->getTableName('source_references');

        $result = false;
        if (empty($id = $context->getId())) {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE NOT EXISTS ( " .
                "SELECT 1 FROM $t_refs AS ref WHERE ref._source_id = t.id AND is_componentOf = 1 ) " .
                "ORDER BY sortKey"
            );
        } elseif (false !== $_ref = $this->dbs->getLidForId($table, $id)) {
            $result = $this->dbs->getDb()->fetchAll(
                "SELECT * FROM $table AS t WHERE id IN ( " .
                "SELECT _source_id FROM $t_refs AS ref WHERE is_componentOf = 1 AND description_id = ? ) " .
                "ORDER BY sortKey",
                [(int) $_ref]
            );
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_agents = $this->dbs->getTableName('agents');

        if (isset($result['mediaType_id'])
            && (false !== $r = $this->getType($result['mediaType_id']))
        ) {
            $result['mediaType'] = $r;
        }
        if (isset($result['resourceType_id'])
            && (false !== $r = $this->getType($result['resourceType_id']))
        ) {
            $result['resourceType'] = $r;
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
            && (false !== $r = $this->dbs->getIdForLid($t_agents, $result['repository_id']))
        ) {
            $result['repository']['resourceId'] = $r;
        }
        if (empty($result['repository'])) {
            unset($result['repository']);
        }

        $result['mediator'] = [];
        if (isset($result['mediator_id'])
            && (false !== $r = $this->dbs->getIdForLid($t_agents, $result['mediator_id']))
        ) {
            $result['mediator']['resourceId'] = $r;
        }
        if (empty($result['mediator'])) {
            unset($result['mediator']);
        }

        $result['attribution'] = [];
        if (isset($result['att_contributor_id'])
            && ! empty($r = $this->dbs->getIdForLid($t_agents, $result['att_contributor_id']))
        ) {
            $result['attribution']['contributor'] = [
                'resourceId' => $r,
            ];
        }
        if (! empty($result['att_modified'])) {
            $result['attribution']['modified'] = date(DATE_W3C, strtotime($result['att_modified']));
        }
        if (! empty($result['att_changeMessage'])) {
            $result['attribution']['changeMessage'] = $result['att_changeMessage'];
        }
        if (empty($result['attribution'])) {
            unset($result['attribution']);
        }

        $entity = parent::processRaw($entity, $result);

        $res = $this->newStorager(SourceReference::class)
            ->loadList(
                $entity,
                [
                'is_componentOf'    => true,
                ]
            );
        if (! empty($res)) {
            $entity->setComponentOf($res[0]);
        }

        $res = $this->newStorager(SourceReference::class)->loadList($entity);
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
        if (! empty($res = $this->newStorager(Identifier::class)->loadList($entity))) {
            $entity->setIdentifiers($res);
        }

        return $entity;
    }
}
