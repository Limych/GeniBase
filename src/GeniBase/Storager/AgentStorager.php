<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 * @link https://github.com/Limych/GeniBase
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Common\ResourceReference;
use Gedcomx\Agent\Agent;
use Gedcomx\Agent\Address;

class AgentStorager extends GeniBaseStorager
{

    const GROUP_NAMES       = 'http://genibase.net/AgentName';

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
            $res = $entity->getOpenid();
            if (! empty($res)) {
                $def['makeId_name'] = $res;
            } else {
                $res = $entity->getNames();
                if (! empty($res)) {
                    $def['makeId_name'] = $res[0]->getValue();
                } else {
                    $res = $entity->getEmails();
                    if (! empty($res)) {
                        $def['makeId_name'] = $res[0];
                    } else {
                        $res = $entity->getPhones();
                        if (! empty($res)) {
                            $def['makeId_name'] = $res[0];
                        } else {
                            $res = $entity->getHomepage();
                            if (! empty($res)) {
                                $def['makeId_name'] = $res;
                            }
                        }
                    }
                }
            }
        }

        return $def;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Storager\GeniBaseStorager::detectPreviousState()
     */
    protected function detectPreviousState(&$entity, $context = null, $o = null)
    {
        /** @var Agent $entity */
        $res = $entity->getIdentifiers();
        if (! empty($res)) {
            $id = $this->searchIdByIdentifiers($res);
            if (! empty($id)) {
                $candidate = $this->load(array( 'id' => $id ));
                if (! empty($candidate)) {
                    $this->previousState = clone $candidate;
                    $candidate->initFromArray($entity->toArray());
                    $entity = $candidate;
                    return true;
                }
            }
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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        $this->makeGbidIfEmpty($entity, $o);

        $data = parent::packData4Save($entity, $context, $o);

        /** @var Agent $entity */
        $res = $entity->getAddresses();
        if (! empty($res) && ($res != $this->previousState->getAddresses())) {
            $res2 = array();
            foreach ($res as $val) {
                $res2[] = $val->toArray();
            }
            $data['addresses_json'] = json_encode($res2);
        }

        // TODO: accounts

        $res = $entity->getHomepage();
        if (! empty($res) && ($res != $this->previousState->getHomepage())) {
            $res = $res->getResource();
            if (! empty($res)) {
                $data['homepage_uri'] = $res;
            }
        }

        $res = $entity->getOpenid();
        if (! empty($res) && ($res != $this->previousState->getOpenid())) {
            $res = $res->getResource();
            if (! empty($res)) {
                $data['openid_uri'] = $res;
            }
        }

        $res = $entity->getEmails();
        if (! empty($res) && ($res != $this->previousState->getEmails())) {
            $data['emails_uris'] = self::packResourceReferences($res);
        }

        $res = $entity->getPhones();
        if (! empty($res) && ($res != $this->previousState->getPhones())) {
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
        $res = $entity->getNames();
        if (! empty($res) && ($res != $this->previousState->getNames())) {
            $this->saveTextValues(self::GROUP_NAMES, $res, $entity);
        }

        $res = $entity->getIdentifiers();
        if (! empty($res) && ($res != $this->previousState->getIdentifiers())) {
            $this->saveIdentifiers($res, $entity);
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
        return join("\n", array_filter(array_map(
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
                return new ResourceReference(array( 'resource' => $v ));
            },
            explode("\n", $rrefs)
        );
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        $id = $entity->getId();
        if (! empty($id)) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE t.id = ?", array($id));
        }

        return $result;
    }

    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        /** @var Agent $entity */
        $entity = parent::unpackLoadedData($entity, $result);


        if (! empty($result['addresses_json'])) {
            $res = json_decode($result['addresses_json'], true);
            $res2 = array();
            foreach ($res as $val) {
                $res2[] = new Address($val);
            }
            $entity->setAddresses($res2);
            unset($result['addresses_json']);
        }

        // TODO accounts

        $res = $this->loadTextValues(self::GROUP_NAMES, $entity);
        if (! empty($res)) {
            $entity->setNames($res);
        }

        $res = $this->loadIdentifiers($entity);
        if (! empty($res)) {
            $entity->setIdentifiers($res);
        }

        if (! empty($result['homepage_uri'])) {
            $entity->setHomepage(new ResourceReference(array( 'resource' => $result['homepage_uri'] )));
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
            $entity->setOpenid(new ResourceReference(array( 'resource' => $result['openid_uri'] )));
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
