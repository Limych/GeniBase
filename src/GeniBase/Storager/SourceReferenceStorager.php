<?php
namespace GeniBase\Storager;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gedcomx\Common\ExtensibleData;
use GeniBase\Util;
use GeniBase\DBase\DBaseService;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Source\SourceReference;

/**
 *
 * @author Limych
 */
class SourceReferenceStorager extends GeniBaseStorager
{

    protected function getObject($o = null)
    {
        return new SourceReference($o);
    }

    /**
     *
     * @param mixed          $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return ExtensibleData|false
     *
     * @throws \UnexpectedValueException
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof ExtensibleData) {
            $entity = $this->getObject($entity);
        }

        $t_refs = $this->dbs->getTableName('source_references');
        $t_srcs = $this->dbs->getTableName('sources');
        $t_agents = $this->dbs->getTableName('agents');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::arraySliceKeys($ent, 'description');

        if (! empty($r = $context->getId())) {
            $data['_source_id'] = $r;
        } else {
            throw new \UnexpectedValueException('Context ID required!');
        }
        if (! empty($o['is_componentOf'])) {
            $data['is_componentOf'] = (int) $o['is_componentOf'];
        }
        if (! isset($ent['description'])) {
            throw new \UnexpectedValueException('Description reference URI required!');
        }
        if (! empty($id = DBaseService::getIdFromReference($ent['description']))
            && (false !== $r = $this->dbs->getLidForId($t_srcs, $id))
        ) {
            $data['description_id'] = (int) $r;
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
        parent::save($entity, $context, $o);

        $result = $this->dbs->getDb()->update(
            $t_refs,
            $data,
            Util::arraySliceKeys($data, '_source_id', 'description')
        );
        if ($result == 0) {
            try {
                $this->dbs->getDb()->insert($t_refs, $data);
                $_id = (int) $this->dbs->getDb()->lastInsertId();
                GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
            } catch (UniqueConstraintViolationException $e) {
                // Do nothing
            }
        }

        return $entity;
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return ExtensibleData[]|false
     *
     * @throws \UnexpectedValueException
     */
    public function loadList($context = null, $o = null)
    {
        $o = array_merge(
            [
            'is_componentOf' => false,
            ],
            (is_array($o) ? $o : [])
        );

        if ($context instanceof ExtensibleData) {
            $context = $context->toArray();
        }

        $t_srefs = $this->dbs->getTableName('source_references');
        $t_srcs = $this->dbs->getTableName('sources');

        $q = "SELECT * FROM $t_srefs WHERE _source_id = ? AND is_componentOf = ?";

        if (empty($context['id'])) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        $result = $this->dbs->getDb()->fetchAll($q, [$context['id'], $o['is_componentOf']]);

        if (is_array($result)) {
            foreach ($result as $k => $v) {
                $result[$k] = $this->processRaw($this->getObject(), $v);
            }
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_agents = $this->dbs->getTableName('agents');

        $ent = $result;

        if (! isset($ent['attribution'])) {
            $ent['attribution'] = [];
        }
        if (isset($ent['att_contributor_id'])
            && ! empty($r = $this->dbs->getIdForLid($t_agents, $ent['att_contributor_id']))
        ) {
            $ent['attribution']['contributor'] = [
                'resourceId' => $r,
            ];
        }
        if (! empty($ent['att_modified'])) {
            $ent['attribution']['modified'] = date(DATE_W3C, strtotime($ent['att_modified']));
        }
        if (! empty($ent['att_changeMessage'])) {
            $ent['attribution']['changeMessage'] = $ent['att_changeMessage'];
        }

        $entity = new SourceReference($ent);
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $ent['_id']);

        return $entity;
    }
}
