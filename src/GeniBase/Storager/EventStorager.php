<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Event;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\EventRole;
use Gedcomx\Conclusion\PlaceDescription;

/**
 *
 * @author Limych
 */
class EventStorager extends SubjectStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Event($o);
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

        $t_events = $this->dbs->getTableName('events');
        $t_eroles = $this->dbs->getTableName('event_roles');
        $t_places = $this->dbs->getTableName('places');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::arraySliceKeys($ent, 'id');

        if (isset($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }
        if (isset($ent['date'])) {
            $r = $this->dbs->getDb()->fetchColumn(
                "SELECT date_id FROM $t_events WHERE id = ?",
                [$data['id']]
            );
            $dt = new DateInfo($ent['date']);
            if (false !== $r) {
                GeniBaseInternalProperties::setPropertyOf($dt, '_id', $r);
            }
            if (false !== $dt = $this->newStorager($dt)->save($dt)
                && ! empty($r = (int) GeniBaseInternalProperties::getPropertyOf($dt, '_id'))
            ) {
                $data['date_id'] = $r;
            }
        }
        if (isset($ent['place'])) {
            if (isset($ent['place']['description'])) {
                $data['place_description'] = $ent['place']['description'];

                if (! empty($id = $this->dbs->getIdFromReference($ent['place']['description']))
                    && (! empty($r = (int) $this->dbs->getLidForId($t_places, $id)))
                ) {
                    $data['place_description_id'] = $r;
                }
            }
            if (isset($ent['place']['original'])) {
                $data['place_original'] = $ent['place']['original'];
            }
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_events, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_events,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_events, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        // Save childs
        if (! empty($ent['roles'])) {
            $ers = $this->dbs->getDb()->fetchAll(
                "SELECT _id FROM $t_eroles WHERE _event_id = ?",
                [$_id]
            );
            if (! empty($ers)) {
                $ers = array_map(
                    function ($v) {
                        return $v['_id'];
                    },
                    $ers
                );
            }
            foreach ($ent['roles'] as $er) {
                $er = new EventRole($er);
                if (! empty($ers)) {
                    GeniBaseInternalProperties::setPropertyOf($er, '_id', array_shift($ers));
                }
                $this->newStorager($er)->save($er, $entity);
            }
            if (! empty($ers)) {
                $this->dbs->getDb()->executeQuery(
                    "DELETE FROM $t_eroles WHERE _id IN (?)",
                    [$ers],
                    [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
                );
            }
        }

        return $entity;
    }

    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();

        $o = $this->applyDefaultOptions($o);

        if (false === $ev = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addEvent($ev);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($ev));
        }

        return $gedcomx;
    }

    protected function getSqlQueryParts()
    {
        $t_events = $this->dbs->getTableName('events');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "ev.*";
        $qparts['tables'][]     = "$t_events AS ev";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = ev.type_id";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = ev.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE ev._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE ev.id = ?", [$id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        // Unpack data
        $result['place'] = [];
        if (! empty($result['place_description'])) {
            $result['place']['description'] = $result['place_description'];
        }
        if (! empty($result['place_original'])) {
            $result['place']['original'] = $result['place_original'];
        }
        if (empty($result['place'])) {
            unset($result['place']);
        }

        /**
 * @var Event $entity
*/
        $entity = parent::processRaw($entity, $result);

        // Load childs
        if (isset($result['date_id'])) {
            $dt = new DateInfo();
            GeniBaseInternalProperties::setPropertyOf($dt, '_id', $result['date_id']);
            if (false !== $dt = $this->newStorager($dt)->load($dt)) {
                $entity->setDate($dt);
            }
        }
        if (! empty($r = $this->newStorager(EventRole::class)->loadList($entity))) {
            $entity->setRoles($r);
        }

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /**
 * @var Event $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getPlace()) && ! empty($r = $r->getDescriptionRef())
            && ! empty($rid = $this->dbs->getIdFromReference($r))
        ) {
            $gedcomx->embed(
                $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }
        if (! empty($r = $entity->getRoles())) {
            foreach ($r as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }

        return $gedcomx;
    }
}
