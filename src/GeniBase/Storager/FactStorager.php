<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Fact;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\PlaceDescription;

/**
 *
 * @author Limych
 */
class FactStorager extends ConclusionStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    protected function getObject($o = null)
    {
        return new Fact($o);
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

        $t_facts = $this->dbs->getTableName('facts');
        $t_places = $this->dbs->getTableName('places');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id', 'value');
        if (empty($context) || empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_person_id'] = $r;
        if (isset($ent['primary'])) {
            $data['primary'] = (int) $ent['primary'];
        }
        if (isset($ent['type'])) {
            $data['type_id'] = $this->getTypeId($ent['type']);
        }
        if (isset($ent['date'])) {
            $r = $this->dbs->getDb()->fetchColumn(
                "SELECT date_id FROM $t_facts WHERE id = ?",
                [$data['id']]
            );
            $dt = new DateInfo($ent['date']);
            if (false !== $r) {
                GeniBaseInternalProperties::setPropertyOf($dt, '_id', $r);
            }
            if (false !== $dt = $this->newStorager($dt)->save($dt)) {
                $data['date_id'] = (int) GeniBaseInternalProperties::getPropertyOf($dt, '_id');
            }
        }
        if (isset($ent['place'])) {
            if (isset($ent['place']['description'])) {
                $data['place_description'] = $ent['place']['description'];

                if (! empty($id = $this->dbs->getIdFromReference($ent['place']['description']))
                    && (false !== $r = $this->dbs->getLidForId($t_places, $id))
                ) {
                    $data['place_description_id'] = (int) $r;
                }
            }
            if (isset($ent['place']['original'])) {
                $data['place_original'] = $ent['place']['original'];
            }
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_facts, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_facts,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_facts, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_facts = $this->dbs->getTableName('facts');
        $t_types = $this->dbs->getTableName('types');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "ft.*";
        $qparts['tables'][]     = "$t_facts AS ft";
        $qparts['bundles'][]    = "";

        $qparts['fields'][]     = "tp2.uri AS type";
        $qparts['tables'][]     = "$t_types AS tp2";
        $qparts['bundles'][]    = "tp2._id = ft.type_id";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = ft.id";
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
            $result = $this->dbs->getDb()->fetchAll("$q WHERE ft._person_id = ?", [$_id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        // Unpack data
        if (! empty($result['primary'])) {
            settype($result['primary'], 'boolean');
        }
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
 * @var Person $entity
*/
        $entity = parent::processRaw($entity, $result);

        return $entity;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /**
 * @var Fact $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getPlace()) && ! empty($r = $r->getDescriptionRef())
            && ! empty($rid = $this->dbs->getIdFromReference($r))
        ) {
            $gedcomx->embed(
                $this->newStorager(PlaceDescription::class)->loadGedcomx([ 'id' => $rid ])
            );
        }

        return $gedcomx;
    }

    protected function garbageCleaning()
    {
        parent::garbageCleaning();

        if (mt_rand(1, 10000) > self::GC_PROBABILITY) {
            return; // Skip cleaning now
        }

        $t_facts = $this->dbs->getTableName('facts');
        $t_psns = $this->dbs->getTableName('persons');

        $q  = "DELETE LOW_PRIORITY ft FROM $t_facts AS ft WHERE NOT EXISTS ( " .
            "SELECT 1 FROM $t_psns AS ps WHERE ps._id = ft._person_id )";

        $this->dbs->getDb()->query($q);
    }
}
