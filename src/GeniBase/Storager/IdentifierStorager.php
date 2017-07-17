<?php
namespace GeniBase\Storager;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gedcomx\Common\ExtensibleData;
use GeniBase\Util;
use Gedcomx\Conclusion\Identifier;

class IdentifierStorager extends GeniBaseStorager
{

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getObject()
     */
    protected function getObject($o = null)
    {
        return new Identifier($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::save()
     * 
     * @throws \UnexpectedValueException
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof Identifier)
            $entity = $this->getObject($entity);
            
        $t_ids = $this->dbs->getTableName('identifiers');
            
        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'value');
        if (empty($id = $context->getId())) {
            throw new \UnexpectedValueException('Context ID required!');
        } else {
            $data['id'] = $id;
        }
        if (! empty($ent['type']) && (! empty($r = (int) $this->getTypeId($ent['type'])))) {
            $data['type_id'] = $r;
        }

        // Save data
        parent::save($entity, $context, $o);

        try {
            $this->dbs->getDb()->insert($t_ids, $data);
        } catch (UniqueConstraintViolationException $e) {
            unset($data['id']);
            $this->dbs->getDb()->update($t_ids, $data, ['id' => $id]);
        }
        
        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::loadListRaw()
     * 
     * @throws \UnexpectedValueException
     */
    protected function loadListRaw($context, $o)
    {
        if (empty($id = $context->getId())) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        
        $t_ids = $this->dbs->getTableName('identifiers');
        $t_types = $this->dbs->getTableName('types');

        $result = $this->dbs->getDb()->fetchAll(
            "SELECT id.*, tp.uri AS type FROM $t_ids AS id " .
            "LEFT JOIN $t_types AS tp ON (id.type_id = tp._id) ".
            "WHERE id.id = ?",
            [$id]
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
            
        $q = "SELECT id FROM $t_ids WHERE value IN (?) LIMIT 1";
        
        $result = $this->dbs->getDb()->fetchColumn($q, [$ids], 0,
            [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);
        
        return $result;
    }
    
}
