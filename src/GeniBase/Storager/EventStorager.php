<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Event;
use GeniBase\DBase\GeniBaseInternalProperties;
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
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('events');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        $t_events = $this->getTableName();
        $t_places = $this->dbs->getTableName('places');

        /** @var Event $entity */
        if (! empty($res = $entity->getType()) && ! empty($res = $this->dbs->getTypeId($res))) {
            $data['type_id'] = $res;
        }
        if (! empty($res = $entity->getDate())) {
            $data = array_merge($data, self::packDateInfo($res));
        }
        if (! empty($res = $entity->getPlace())) {
            if (! empty($res2 = $res->packDescriptionRef())) {
                $data['place_description_uri'] = $res2;
                if (! empty($res2 = GeniBaseStorager::getIdFromReference($res2))
                    && ! empty($res2 = $this->dbs->getInternalId($t_places, $res2))
                    ) {
                        $data['place_description_id'] = (int) $res2;
                    }
            }
            if (! empty($res2 = $res->getOriginal())) {
                $data['place_original'] = $res2;
            }
        }

        return $data;
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
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        /** @var Event $entity */
        $entity = parent::save($entity, $context, $o);

        $t_eroles = $this->dbs->getTableName('event_roles');

        // Save childs
        if (! empty($res = $entity->getRoles())) {
            $ers = $this->dbs->getDb()->fetchAll("SELECT _id FROM $t_eroles WHERE _event_id = ?", [$data['_id']]);
            if (! empty($ers)) {
                $ers = array_map(
                    function ($v) {
                        return $v['_id'];
                    },
                    $ers
                );
            }
            foreach ($res as $er) {
                if (! empty($ers)) {
                    GeniBaseInternalProperties::setPropertyOf($er, '_id', array_shift($ers));
                }
                $this->newStorager($er)->save($er, $entity);
            }
            if (! empty($ers)) {
                $this->dbs->getDb()->executeQuery("DELETE FROM $t_eroles WHERE _id IN (?)", [$ers],
                    [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
                );
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
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

    protected function unpackLoadedData($entity, $result)
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

        /** @var Event $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        if (! empty($res = self::unpackDateInfo($result))) {
            $entity->setDate($res);
        }

        // Load childs
        if (! empty($r = $this->newStorager(EventRole::class)->loadComponents($entity))) {
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
            && ! empty($rid = GeniBaseStorager::getIdFromReference($r))
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
