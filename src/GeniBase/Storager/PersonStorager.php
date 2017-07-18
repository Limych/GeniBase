<?php
namespace GeniBase\Storager;

use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Conclusion\Name;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\Gender;
use Gedcomx\Conclusion\Fact;

/**
 *
 * @author Limych
 */
class PersonStorager extends SubjectStorager
{

    protected function getObject($o = null)
    {
        return new Person($o);
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

        $t_persons = $this->dbs->getTableName('persons');
        $t_pgs = $this->dbs->getTableName('genders');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'id');

        if (isset($ent['private'])) {
            $data['private'] = (int) $ent['private'];
        }
        if (isset($ent['living'])) {
            $data['living'] = (int) $ent['living'];
        }

        // Save data
        $_id = (int) $this->dbs->getLidForId($t_persons, $data['id']);
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $result = $this->dbs->getDb()->update(
                $t_persons,
                $data,
                [
                '_id' => $_id
                ]
            );
        } else {
            $this->dbs->getDb()->insert($t_persons, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        // Save childs
        if (isset($ent['gender'])) {
            $tmp = $o;
            if (! empty($tmp['makeId_name'])) {
                $tmp['makeId_name'] = 'Gender: ' . $tmp['makeId_name'];
            }
            $this->newStorager(Gender::class)->save($ent['gender'], $entity, $tmp);
            unset($tmp);
        }
        if (! empty($ent['names'])) {
            $tmp = $o;
            $tmp_cnt = 0;
            foreach ($ent['names'] as $name) {
                if (! empty($o['makeId_name'])) {
                    $tmp['makeId_name'] = 'Name-' . (++$tmp_cnt) . ': ' . $o['makeId_name'];
                }
                $this->newStorager(Name::class)->save($name, $entity, $tmp);
            }
            unset($tmp);
            unset($tmp_cnt);
        }
        if (! empty($ent['facts'])) {
            $tmp = $o;
            $tmp_cnt = 0;
            foreach ($ent['facts'] as $name) {
                $tmp['makeId_name'] = 'Fact-' . (++$tmp_cnt) . ': ' . $entity->getId();
                $this->newStorager(Fact::class)->save($name, $entity, $o);
            }
            unset($tmp);
            unset($tmp_cnt);
        }

        return $entity;
    }

    protected function getSqlQueryParts()
    {
        $t_persons = $this->dbs->getTableName('persons');

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "pn.*";
        $qparts['tables'][]     = "$t_persons AS pn";
        $qparts['bundles'][]    = "";

        $qp = parent::getSqlQueryParts();
        $qp['bundles'][0]   = "cn.id = pn.id";
        $qparts = array_merge_recursive($qparts, $qp);

        return $qparts;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $q = $this->getSqlQuery();
        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE pn._id = ?", [$_id]);
        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc("$q WHERE pn.id = ?", [$id]);
        }

        return $result;
    }

    protected function processRaw($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        if (! empty($result['private'])) {
            settype($result['private'], 'boolean');
        }
        if (! empty($result['living'])) {
            settype($result['living'], 'boolean');
        }

        /**
 * @var Person $entity
*/
        $entity = parent::processRaw($entity, $result);

        if (! empty($r = $this->newStorager(Gender::class)->load(null, $entity))) {
            $entity->setGender($r);
        }
        if (! empty($r = $this->newStorager(Name::class)->loadList($entity))) {
            $entity->setNames($r);
        }
        if (! empty($r = $this->newStorager(Fact::class)->loadList($entity))) {
            $entity->setFacts($r);
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

        $o = $this->applyDefaultOptions($o);

        if (false === $psn = $this->load($entity, $context, $o)) {
            return false;
        }

        $gedcomx->addPerson($psn);
        if ($o['loadCompanions']) {
            $gedcomx->embed($this->loadGedcomxCompanions($psn));
        }

        return $gedcomx;
    }

    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        /**
 * @var Person $entity
*/
        $gedcomx = parent::loadGedcomxCompanions($entity);

        if (! empty($r = $entity->getGender())) {
            $gedcomx->embed($this->newStorager($r)->loadGedcomxCompanions($r));
        }
        if (! empty($r = $entity->getNames())) {
            foreach ($r as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }
        if (! empty($r = $entity->getFacts())) {
            foreach ($r as $v) {
                $gedcomx->embed($this->newStorager($v)->loadGedcomxCompanions($v));
            }
        }

        return $gedcomx;
    }
}
