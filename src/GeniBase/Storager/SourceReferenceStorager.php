<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
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
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('source_references');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     *
     * @throws \UnexpectedValueException
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        $t_sources = $this->dbs->getTableName('sources');

        /** @var SourceReference $entity */
        if (empty($res = $entity->getDescriptionRef())) {
            throw new \UnexpectedValueException('Description reference URI required!');
        } else {
            $data['description_uri'] = $res;
            if (! empty($res = GeniBaseStorager::getIdFromReference($res))
                && ! empty($res = $this->dbs->getInternalId($t_sources, $res))
            ) {
                $data['description_id'] = (int) $res;
            }
        }
        if (empty($res = GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        } else {
            $data['_source_id'] = $res;
        }
        if (! empty($o['is_componentOf'])) {
            $data['is_componentOf'] = (int) $o['is_componentOf'];
        }
        $data = array_merge($data, $this->packAttribution($entity->getAttribution()));

        return $data;
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return ExtensibleData[]|false
     *
     * @throws \UnexpectedValueException
     */
    public function loadComponents($context = null, $o = null)
    {
        $o = array_merge([  'is_componentOf' => false   ], (is_array($o) ? $o : []));

        if ($context instanceof ExtensibleData) {
            $context_id = GeniBaseInternalProperties::getPropertyOf($context, '_id');
        } else {
            $context_id = $this->dbs->getInternalIdForId($context['id']);
        }

        $t_srefs = $this->dbs->getTableName('source_references');
        $t_srcs = $this->dbs->getTableName('sources');

        if (empty($context_id)) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }

        $q = "SELECT * FROM $t_srefs WHERE _source_id = ? AND is_componentOf = ?";
        $result = $this->dbs->getDb()->fetchAll($q, [$context_id, $o['is_componentOf']]);

        if (is_array($result)) {
            foreach ($result as $k => $v) {
                $result[$k] = $this->unpackLoadedData($this->getObject(), $v);
            }
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['description_uri'])) {
            $result['description'] = $result['description_uri'];
        }
        $result = $this->processRawAttribution($entity, $result);

        $entity = parent::unpackLoadedData($entity, $result);

        return $entity;
    }
}
