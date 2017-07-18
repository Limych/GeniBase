<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\EventRole;
use GeniBase\Util;
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

        $t_eroles = $this->dbs->getTableName('event_roles');
        $t_persons = $this->dbs->getTableName('persons');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id', 'details');
        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_event_id'] = $r;
        if (isset($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }
        if (! isset($ent['person']) || ! isset($ent['person']['resourceId'])) {
            throw new \UnexpectedValueException('Person reference URI required!');
        }
        if (! empty($r = (int) $this->dbs->getLidForId($t_persons, $ent['person']['resourceId']))) {
            $data['person_id'] = $r;
        }

        // Save data
        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $this->dbs->getDb()->update(
                $t_eroles,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_eroles, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_eroles = $this->dbs->getTableName('event_roles');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "er.*";
        $qparts['tables'][]     = "$t_eroles AS er";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = er.type_id";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = er.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE er._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE er.id = ?", [$id]);
        }

        return $result;
    }

    protected function loadListRaw($context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            $result = $this->dbs->getDb()->fetchAll("$q WHERE er._event_id = ?", [$_id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_persons = $this->dbs->getTableName('persons');

        // Unpack data
        $result['person'] = [];
        if (! empty($result['person_id'])
            && ! empty($rid = $this->dbs->getIdForLid($t_persons, $result['person_id']))
        ) {
            $result['person']['resourceId'] = $rid;
        }
        if (empty($result['person'])) {
            unset($result['person']);
        }

        /**
 * @var EventRole $entity
*/
        $entity = parent::processRaw($entity, $result);

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /**
 * @var EventRole $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getPerson()) && ! empty($rid = $r->getResourceId())) {
            $gedcomx->embed($this->newStorager(Person::class)->loadGedcomx([ 'id' => $rid ]));
        }

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_eroles = $this->dbs->getTableName('event_roles');
        $t_events = $this->dbs->getTableName('events');

        $q  = "DELETE LOW_PRIORITY er FROM $t_eroles AS er WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_events AS ev WHERE ev._id = er._event_id )";

        $this->dbs->getDb()->query($q);
    }
}
