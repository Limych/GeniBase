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
        if (parent::detectId($entity)) {
            return true;
        }

        /** @var Subject $entity */
        if (! empty($r = $entity->getIdentifiers())
        && ! empty($id = $this->newStorager(Identifier::class)->getIdByIdentifier($r))) {
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
        /** @var PlaceDescription $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (! empty($res = $entity->getIdentifiers())) {
            foreach ($res as $id) {
                $this->newStorager(Identifier::class)->save($id, $entity);
            }
        }

        return $entity;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        /** @var Subject $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = $this->newStorager(Identifier::class)->loadComponents($entity))) {
            $entity->setIdentifiers($res);
        }

        return $entity;
    }
}
