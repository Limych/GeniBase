<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Conclusion;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceReference;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Agent\Agent;

/**
 *
 * @author Limych
 *
 */
class ConclusionStorager extends GeniBaseStorager
{

    protected function getObject($o = null)
    {
        return new Conclusion($o);
    }

    /**
     *
     * @param mixed $entity
     * @param ExtensibleData $context
     * @param array|null $o
     * @return ExtensibleData|false
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof ExtensibleData)
            $entity = $this->getObject($entity);

        $o = $this->applyDefaultOptions($o, $entity);
        $this->makeUuidIfEmpty($entity, $o);

        $t_cons = $this->dbs->getTableName('conclusions');
        $t_agents = $this->dbs->getTableName('agents');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id');
        if (isset($ent['confidence']) && (false !== $r = $this->getTypeId($ent['confidence']))) {
            $data['confidence_id'] = $r;
        }
        if (! empty($ent['lang']) && (false !== $r = $this->getLangId($ent['lang']))) {
            $data['lang_id'] = $r;
        }

        if (empty($ent['attribution'])) {
            $ent['attribution'] = [];
        }
        if (! isset($ent['attribution']['contributor']) && ! empty($r = $this->dbs->getAgent())) {
            $ent['attribution']['contributor'] = [ 'resourceId' => $r->getId() ];
        }
        if (isset($ent['attribution']['contributor']) && isset($ent['attribution']['contributor']['resourceId'])
        && (false !== $r = $this->dbs->getLidForId($t_agents, $ent['attribution']['contributor']['resourceId']))) {
            $data['att_contributor_id'] = $r;
        }
        if (! empty($ent['attribution']['modified'])) {
            $data['att_modified'] = date('Y-m-d H:i:s', strtotime($ent['attribution']['modified']));
        }
        if (! empty($ent['attribution']['changeMessage'])) {
            $data['att_changeMessage'] = $ent['attribution']['changeMessage'];
        }

        // Save data
        $_id = $this->dbs->getLidForId($t_cons, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update($t_cons, $data, [
                '_id' => $_id
            ]);

        } else {
            $this->dbs->getDb()->insert($t_cons, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        // Save childs
        if (!empty($ent['sources'])) {
            foreach ($ent['sources'] as $src) {
                $this->newStorager(SourceReference::class)->save($src, $entity);
            }
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_cons = $this->dbs->getTableName('conclusions');
        $t_types = $this->dbs->getTableName('types');
        $t_langs = $this->dbs->getTableName('languages');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "cn.*";
        $qparts['tables'][]     = "$t_cons AS cn";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "lg.lang";
        $qparts['tables'][]     = "$t_langs AS lg";
        $qparts['bundles'][]    = "lg._id = cn.lang_id";

        $qparts['fields'][]     = "tp.uri AS confidence";
        $qparts['tables'][]     = "$t_types AS tp";
        $qparts['bundles'][]    = "tp._id = cn.confidence_id";

        $qparts = array_merge_recursive($qparts, parent::getSqlQueryParts());

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE cn._id = ?", [$_id]);

        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE cn.id = ?", [$id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result))
            return $result;

        $t_agents = $this->dbs->getTableName('agents');

        if (isset($result['confidence_id'])
        && (false !== $r = $this->getType($result['confidence_id']))) {
            $result['confidence'] = $r;
        }
        if (isset($result['lang_id'])
        && (false !== $r = $this->getLang($result['lang_id']))) {
            $result['lang'] = $r;
        }

        $result['attribution'] = [];
        if (isset($result['att_contributor_id'])
        && ! empty($r = $this->dbs->getIdForLid($t_agents, $result['att_contributor_id']))) {
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
        if (empty($result['attribution']))
            unset($result['attribution']);

        $entity = parent::processRaw($entity, $result);

        $res = $this->newStorager(SourceReference::class)->loadList($entity);
        if (! empty($res))
            $entity->setSources($res);

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
                && ! empty($rid = $this->dbs->getIdFromReference($r))) {
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
