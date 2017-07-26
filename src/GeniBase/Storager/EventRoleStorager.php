<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\EventRole;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Person;

/**
 *
 * @author Limych
 */
class EventRoleStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new EventRole($o);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('event_roles');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     *
     * @throws \UnexpectedValueException
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $data = parent::packData4Save($entity, $context, $o);

        $t_persons = $this->dbs->getTableName('persons');

        /** @var EventRole $entity */
        if (empty($context) || empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_event_id'] = $res;
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (empty($res = $entity->getPerson()) || empty($res = $res->getResourceId())) {
            throw new \UnexpectedValueException('Person reference ID required!');
        } elseif (! empty($res = $this->dbs->getInternalId($t_persons, $res))) {
            $data['person_id'] = $res;
        }
        if (! empty($res = $entity->getDetails())) {
            $data['details'] = $res;
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $data;
    }

    protected function getSqlQueryParts()
    {
        $t_types = $this->dbs->getTableName('types');

        $qparts = parent::getSqlQueryParts();

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = t.type_id";

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t.id = ?", [$id]);
        }

        return $result;
    }

    protected function loadComponentsRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE t._event_id = ?", [$_id]);
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_persons = $this->dbs->getTableName('persons');

        // Unpack data
        $result['person'] = [];
        if (! empty($result['person_id'])
            && ! empty($res = $this->dbs->getPublicId($t_persons, $result['person_id']))
        ) {
            $result['person']['resourceId'] = $res;
        }
        if (empty($result['person'])) {
            unset($result['person']);
        }

        /** @var EventRole $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var EventRole $entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($res = $entity->getPerson()) && ! empty($res = $res->getResourceId())) {
            $gedcomx->embed($this->newStorager(Person::class)->loadGedcomx([ 'id' => $res ]));
        }

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        parent::garbageCleaning();

        if (! defined('DEBUG_SECONDARY') && mt_rand(1, 10000) > self::GC_PROBABILITY) {
            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::stopTimer(__METHOD__);
            }
            return; // Skip cleaning now
        }

        $t_eroles = $this->dbs->getTableName('event_roles');
        $t_events = $this->dbs->getTableName('events');

        $q  = "DELETE LOW_PRIORITY er FROM $t_eroles AS er WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_events AS ev WHERE ev._id = er._event_id )";

        $this->dbs->getDb()->query($q);
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
    }
}
