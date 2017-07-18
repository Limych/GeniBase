<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Agent\Agent;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Common\ResourceReference;
use GeniBase\DBase\GeniBaseInternalProperties;
use GeniBase\Util;
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

    public function getDefaultOptions(ExtensibleData $entity = null)
    {
        $def = parent::getDefaultOptions();

        $def['makeId_unique'] = false;

        if (! empty($entity)) {
            /**
 * @var Agent $entity
*/
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

    protected function detectId(ExtensibleData &$entity)
    {
        /**
 * @var Agent $entity
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
        $this->makeGbidIfEmpty($entity, $o);

        $t_agents = $this->dbs->getTableName('agents');
        $t_persons = $this->dbs->getTableName('persons');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::arraySliceKeys($ent, 'id');

        if (! empty($ent['addresses'])) {
            $data['addresses_json'] = json_encode($ent['addresses']);
        }
        // TODO: accounts
        if (isset($ent['homepage']) && isset($ent['homepage']['resource'])) {
            $data['homepage_uri'] = $ent['homepage']['resource'];
        }
        if (isset($ent['openid']) && isset($ent['openid']['resource'])) {
            $data['openid_uri'] = $ent['openid']['resource'];
        }
        if (isset($ent['emails'])) {
            $data['emails_uris'] = self::packResourceReferences($ent['emails']);
        }
        if (isset($ent['phones'])) {
            $data['phones_uris'] = self::packResourceReferences($ent['phones']);
        }
        if (isset($ent['person']) && isset($ent['person']['resourceId'])
            && (false !== $r = $this->dbs->getLidForId($t_persons, $ent['person']['resourceId']))
        ) {
            $data['person_id'] = $r;
        }

        // Save data
        $_id = $this->dbs->getLidForId($t_agents, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_agents,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_agents, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);

        // Save childs
        foreach ($ent['names'] as $name) {
            $this->saveAllTextValues(self::GROUP_NAMES, $ent['names'], $entity);
        }
        if (! empty($r = $entity->getIdentifiers())) {
            foreach ($r as $id) {
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
        return join(
            "\n",
            array_filter(
                array_map(
                    function ($v) {
                        return (isset($v['resource']) ? $v['resource'] : false);
                    },
                    $rrefs
                )
            )
        );
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
                return [ 'resource' => $v ];
            },
            explode("\n", $rrefs)
        );
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $table = $this->dbs->getTableName('agents');

        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE _id = ?",
                [$_id]
            );
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE id = ?",
                [$id]
            );
        } else {
            $result = false;
        }

        if ((false !== $result) && (false !== $r = parent::loadRaw($entity, $context, $o))) {
            $result = array_merge($result, $r);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_agents = $this->dbs->getTableName('agents');

        // TODO accounts
        if (! empty($result['addresses_json'])) {
            $result['addresses'] = json_decode($result['addresses_json'], true);
            unset($result['addresses_json']);
        }
        if (! empty($result['emails_uris'])) {
            $result['emails'] = $this->unpackResourceReferences($result['emails_uris']);
            unset($result['emails_uris']);
        }
        if (! empty($result['homepage_uri'])) {
            $result['homepage'] = [
                'resource' => $result['homepage_uri'],
            ];
            unset($result['homepage_uri']);
        }
        if (! empty($result['openid_uri'])) {
            $result['openid'] = [
                'resource' => $result['openid_uri'],
            ];
            unset($result['openid_uri']);
        }
        if (! empty($result['phones_uris'])) {
            $result['phones'] = $this->unpackResourceReferences($result['phones_uris']);
            unset($result['phones_uris']);
        }

        $entity = parent::processRaw($entity, $result);

        if (! empty($res = $this->loadAllTextValues(self::GROUP_NAMES, $entity))) {
            $entity->setNames($res);
        }
        if (! empty($res = $this->newStorager(Identifier::class)->loadList($entity))) {
            $entity->setIdentifiers($res);
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
        /**
 * @var Agent$entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        // TODO: people
        //         if (! empty($r = $entity->getPlace()) && ! empty($r = $r->getDescriptionRef())
        //         && ! empty($rid = $this->dbs->getIdFromReference($r))) {
        //             $gedcomx->embed(
        //                 $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
        //                 );
        //         }

        return $gedcomx;
    }
}
