<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Identifier;
use GeniBase\DBase\GeniBaseInternalProperties;

class IdentifierStorager extends GeniBaseStorager
{

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\GeniBaseStorager::getObject()
     */
    protected function getObject($o = null)
    {
        return new Identifier($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('identifiers');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $data = parent::packData4Save($entity, $context, $o);

        /** @var Identifier $entity */
        if (empty($res = $context->getId())) {
            throw new \UnexpectedValueException('Context public ID required!');
        } else {
            $data['_ref_id'] = $res;
        }
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getValue())) {
            $data['value'] = $res;
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\GeniBaseStorager::loadComponentsRaw()
     *
     * @throws \UnexpectedValueException
     */
    protected function loadComponentsRaw($context, $o)
    {
        if (empty($_ref_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }

        $t_ids = $this->dbs->getTableName('identifiers');
        $t_types = $this->dbs->getTableName('types');

        $result = $this->dbs->getDb()->fetchAll(
            "SELECT id.*, tp.uri AS type FROM $t_ids AS id " .
            "LEFT JOIN $t_types AS tp ON (id.type_id = tp._id) ".
            "WHERE id._ref_id = ?",
            [$_ref_id]
        );

        return $result;
    }

    public function getIdByIdentifier($identifiers)
    {
        if (! is_array($identifiers)) {
            $identifiers = [$identifiers];
        }

        $ids = [];
        foreach ($identifiers as $x) {
            if ($x instanceof Identifier) {
                $ids[] = $x->getValue();
            } elseif (is_array($x)) {
                foreach ($x as $y) {
                    if ($y instanceof Identifier) {
                        $ids[] = $y->getValue();
                    } else {
                        $ids[] = $y;
                    }
                }
            } else {
                $ids[] = $x;
            }
        }

        $t_ids = $this->dbs->getTableName('identifiers');

        $q = "SELECT _ref_id FROM $t_ids WHERE value IN (?) LIMIT 1";
        $result = $this->dbs->getDb()->fetchColumn($q, [$ids], 0, [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

        return $result;
    }
}
