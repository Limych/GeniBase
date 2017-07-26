<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Agent\Agent;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Common\ResourceReference;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Identifier;

/**
 *
 * @author Limych
 */
class AgentStorager extends GeniBaseStorager
{

    const GROUP_NAMES       = 'http://genibase/AgentName';

    protected function getObject($o = null)
    {
        return new Agent($o);
    }

    public function getDefaultOptions($entity = null)
    {
        $def = parent::getDefaultOptions();

        $def['makeId_unique'] = false;

        if (! empty($entity)) {
            /** @var Agent $entity */
            if (! empty($r = $entity->getOpenid())) {
                $def['makeId_name'] = $r;
            } elseif (! empty($r = $entity->getNames())) {
                $def['makeId_name'] = $r[0]->getValue();
            } elseif (! empty($r = $entity->getEmails())) {
                $def['makeId_name'] = $r[0];
            } elseif (! empty($r = $entity->getPhones())) {
                $def['makeId_name'] = $r[0];
            } elseif (! empty($r = $entity->getHomepage())) {
                $def['makeId_name'] = $r;
            }
        }

        return $def;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::detectId()
     */
    protected function detectId(ExtensibleData &$entity)
    {
        /** @var Agent $entity */
        if (! empty($r = $entity->getIdentifiers())
            && ! empty($id = $this->newStorager(Identifier::class)->getIdByIdentifier($r))
        ) {
            $entity->setId($id);
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::getTableName()
     */
    protected function getTableName()
    {
        return $this->dbs->getTableName('agents');
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::packData4Save()
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        /** @var Agent $entity */
        if (! empty($res = $entity->getAddresses()) && ('[{}]' !== $res = json_encode($res))) {
            $data['addresses_json'] = $res;
        }
        // TODO: accounts
        if (! empty($res = $entity->getHomepage()) && ! empty($res = $res->getResource())) {
            $data['homepage_uri'] = $res;
        }
        if (! empty($res = $entity->getOpenid()) && ! empty($res = $res->getResource())) {
            $data['openid_uri'] = $res;
        }
        if (! empty($res = $entity->getEmails())) {
            $data['emails_uris'] = self::packResourceReferences($res);
        }
        if (! empty($res = $entity->getPhones())) {
            $data['phones_uris'] = self::packResourceReferences($res);
        }
        // TODO: persons
//         if (! empty($res = $entity->) && isset($ent['person']['resourceId'])
//             && (false !== $res = $this->dbs->getInternalIdForId($ent['person']['resourceId']))
//         ) {
//             $data['person_id'] = $res;
//         }

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
        /** @var Agent $entity */
        $entity = parent::save($entity, $context, $o);

        // Save childs
        if (! empty($res = $entity->getNames())) {
            $names = [];
            foreach ($res as $name) {
                $names[] = $name->toArray();
            }
            $this->saveAllTextValues(self::GROUP_NAMES, $names, $entity);
        }
        if (! empty($res = $entity->getIdentifiers())) {
            foreach ($res as $id) {
                $this->newStorager(Identifier::class)->save($id, $entity);
            }
        }

        return $entity;
    }

    /**
     *
     * @param ResourceReference[] $rrefs
     * @return string
     */
    protected static function packResourceReferences($rrefs)
    {
        return join("\n", array_filter( array_map(
            function (ResourceReference $v) {
                return $res = $v->getResource();
            },
            $rrefs
        )));
    }

    /**
     *
     * @param string $rrefs
     * @return ResourceReference[]
     */
    protected static function unpackResourceReferences($rrefs)
    {
        return array_map(
            function ($v) {
                return new ResourceReference([ 'resource' => $v ]);
            },
            explode("\n", $rrefs)
        );
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t._id = ?", [(int) $_id]);
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

        $t_agents = $this->dbs->getTableName('agents');

        if (! empty($result['addresses_json'])) {
            $result['addresses'] = json_decode($result['addresses_json'], true);
            unset($result['addresses_json']);
        }

        /** @var Agent $entity */
        $entity = parent::unpackLoadedData($entity, $result);

        // TODO accounts
        if (! empty($res = $this->loadAllTextValues(self::GROUP_NAMES, $entity))) {
            $entity->setNames($res);
        }
        if (! empty($res = $this->newStorager(Identifier::class)->loadComponents($entity))) {
            $entity->setIdentifiers($res);
        }
        if (! empty($result['homepage_uri'])) {
            $entity->setHomepage(new ResourceReference([ 'resource' => $result['homepage_uri'] ]));
            unset($result['homepage_uri']);
        }
        if (! empty($result['emails_uris'])) {
            $entity->setEmails($this->unpackResourceReferences($result['emails_uris']));
            unset($result['emails_uris']);
        }
        if (! empty($result['phones_uris'])) {
            $entity->setPhones($this->unpackResourceReferences($result['phones_uris']));
            unset($result['phones_uris']);
        }
        if (! empty($result['openid_uri'])) {
            $entity->setOpenid(new ResourceReference([ 'resource' => $result['openid_uri'] ]));
            unset($result['openid_uri']);
        }

        return $entity;
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \LogicException
     */
    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $gedcomx = new Gedcomx();

        if (false === $ag = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addAgent($ag);
        // TODO: people
//         $gedcomx->embed($this->loadGedcomxCompanions($ag));

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /** @var Agent$entity */
        $gedcomx = parent::loadGedcomxCompanions($entity);

        // TODO: people
//         if (! empty($r = $entity->getPlace()) && ! empty($r = $r->getDescriptionRef())
//         && ! empty($rid = GeniBaseStorager::getIdFromReference($r))) {
//             $gedcomx->embed(
//                 $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
//                 );
//         }

        return $gedcomx;
    }
}
