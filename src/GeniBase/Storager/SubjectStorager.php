<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Identifier;
use Gedcomx\Conclusion\Subject;

/**
 *
 * @author Limych
 */
class SubjectStorager extends ConclusionStorager
{

    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\ConclusionStorager::getObject()
     */
    protected function getObject($o = null)
    {
        return new Subject($o);
    }
    
    /**
     * {@inheritDoc}
     *
     * @see \GeniBase\Storager\GeniBaseStorager::detectId()
     */
    protected function detectId(ExtensibleData &$entity)
    {
        /**
 * @var Subject $entity
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
        $this->makeUuidIfEmpty($entity, $o);

        // Save data
        parent::save($entity, $context, $o);

        // Save childs
        if (! empty($r = $entity->getIdentifiers())) {
            foreach ($r as $id) {
                $this->newStorager(Identifier::class)->save($id, $entity);
            }
        }
        
        return $entity;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        /**
 * @var Subject $entity
*/
        $entity = parent::processRaw($entity, $result);

        if (! empty($res = $this->newStorager(Identifier::class)->loadList($entity))) {
            $entity->setIdentifiers($res);
        }
        
        return $entity;
    }
}
