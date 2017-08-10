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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use GeniBase\DBase\DBaseService;
use GeniBase\DBase\GeniBaseInternalProperties;
use GeniBase\Util;
use GeniBase\Util\DateUtil;
use GeniBase\Util\IDateTime;
use GeniBase\Util\UUID;
use Gedcomx\Common\TextValue;
use Gedcomx\Common\Attribution;
use Gedcomx\Common\ResourceReference;
use Gedcomx\Conclusion\DateInfo;

/**
 *
 * @author Limych
 */
class GeniBaseStorager
{

    const GBID_LENGTH = 12;

    const TABLES_WITH_GBID = 'sources persons events places agents';

    /**
     * @var DBaseService
     */
    protected $dbs;

    /**
     *
     * @param DBaseService $dbs
     * @param mixed        $o
     */
    public function __construct(DBaseService $dbs)
    {
        $this->dbs = $dbs;
    }

    /**
     *
     * @param mixed $class
     *
     * @throws \UnexpectedValueException
     */
    public function newStorager($class)
    {
        return StoragerFactory::newStorager($this->dbs, $class);
    }

    /**
     *
     * @param mixed $o
     * @return object
     *
     * @throws \BadMethodCallException
     */
    protected function getObject($o = null)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }
    }

    /**
     *
     * @param ExtensibleData $entity
     * @return array
     */
    public function getDefaultOptions($entity = null)
    {
        return [
            'makeId_name'       => null,
            'makeId_unique'     => true,
            'loadCompanions'    => false,
            'sortComponents'    => false,
        ];
    }

    /**
     *
     * @param mixed          $o
     * @param ExtensibleData $entity
     * @return array
     */
    public function applyDefaultOptions($o, $entity = null)
    {
        return Util::parseArgs($o, $this->getDefaultOptions($entity));
    }

    protected $previousState;

    /**
     *
     * @param ExtensibleData $entity
     * @param ExtensibleData $context
     * @param mixed[] $o
     * @return boolean
     */
    protected function detectPreviousState(&$entity, $context = null, $o = null)
    {
        return false;
    }

    /**
     *
     * @param string $name
     * @return string
     *
     * @deprecated It's preferable to use GeniBaseStorager::makeGbidUnique() instead.
     * @see GeniBaseStorager::makeGbidUnique()
     */
    public static function makeGbid($name = null)
    {
        return join('-', array_reverse(str_split(Util::hash('distinct', self::GBID_LENGTH, $name), 4)));
    }

    /**
     *
     * @param string $name
     * @return string
     */
    public function makeGbidUnique($name = null)
    {
        static $query;
        static $cache;

        if (! isset($query)) {
            // Initialization
            $query = implode(' UNION ALL ', array_map(
                function($tbl) {
                    $tbl = $this->dbs->getTableName($tbl);
                    return "SELECT 1 FROM $tbl WHERE id = {id}";
                },
                preg_split('/[\s,]+/', self::TABLES_WITH_GBID, null, PREG_SPLIT_NO_EMPTY)
            ));

            $cache = [];
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $suffix = '';
        do {
            do {
                $gbid = self::makeGbid(empty($name) ? null : $name . $suffix);
                $suffix += 1;
            } while (in_array($gbid, $cache));
            $cache[] = $gbid;
        } while (false !== $this->dbs->getDb()->fetchColumn(str_replace(
            '{id}',
            $this->dbs->getDb()->quote($gbid),
            $query
        )));

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $gbid;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param array          $o
     */
    public function makeGbidIfEmpty(ExtensibleData &$entity, $o = null)
    {
        if (empty($entity->getId())) {
            $name = ! empty($o['makeId_name']) ? $o['makeId_name'] : null;
            if (! isset($o['makeId_unique']) || ! empty($o['makeId_unique'])) {
                $id = $this->makeGbidUnique($name);
            } else {
                $id = self::makeGbid($name);
            }
            $entity->setId($id);
        }
    }

    /**
     *
     * @param string $name
     *
     * @see GeniBaseStorager::makeUuidIfEmpty()
     */
    public static function makeUuid($name = null)
    {
        $UUID_NAMESPACE = '3021a916-dee1-55a4-bee8-561889bda347';   // uuid_v5(DNS, 'genibase')

        return (empty($name) ? UUID::v4() : UUID::v5($UUID_NAMESPACE, $name));
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param array          $o
     */
    public function makeUuidIfEmpty(ExtensibleData &$entity, $o = null)
    {
        if (empty($entity->getId())) {
            $name = ! empty($o['makeId_name']) ? $o['makeId_name'] : null;
            $entity->setId(self::makeUuid($name));
        }
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \BadMethodCallException
     */
    public function loadGedcomx($entity, $context = null, $o = null)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }
    }

    /**
     *
     * @param mixed      $entity
     * @param mixed      $context
     * @param array|null $o
     * @return Gedcomx|false
     *
     * @throws \BadMethodCallException
     */
    public function loadComponentsGedcomx($entity, $context = null, $o = null)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }
    }

    /**
     *
     * @param ExtensibleData $entity
     * @return Gedcomx
     */
    public function loadGedcomxCompanions(ExtensibleData $entity)
    {
        return new Gedcomx();
    }

    /**
     *
     * @param mixed          $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return object|false
     */
    public function load($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof ExtensibleData) {
            $entity = $this->getObject($entity);
        }

        $result = $this->loadRaw($entity, $context, $o);
        return $this->unpackLoadedData($this->getObject(), $result);
    }

    /**
     * Compare two objects for sorting list of components.
     *
     * @param object $a
     * @param object $b
     * @return number
     */
    protected function compareComponents($a, $b)
    {
        return 0;
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return object[]|false
     */
    public function loadComponents($context = null, $o = null)
    {
        if (! $context instanceof ExtensibleData) {
            $context = $this->getObject($context);
        }

        if (is_array($result = $this->loadComponentsRaw($context, $o))) {
            foreach ($result as $k => $res) {
                $result[$k] = $this->unpackLoadedData($this->getObject(), $res);
            }
            if (! empty($o['sortComponents'])) {
                usort($result, [$this, 'compareComponents']);
            }
        }
        return $result;
    }

    /**
     *
     * @param string $head
     * @param string $tail
     * @return string
     */
    protected function getSqlQuery($head = 'SELECT', $fields = '', $tail = '')
    {
        $qparts = $this->getSqlQueryParts();

        $qparts['joins'] = [];
        for ($i = 0; $i < count($qparts['tables']); $i++) {
            $qparts['joins'][] = $qparts['tables'][$i] . (empty($b = $qparts['bundles'][$i]) ? '' : " ON $b");
        }
        $qparts['fields'] = array_reverse($qparts['fields']);
        if (! empty($fields)) {
            if (! is_array($fields)) {
                $fields = preg_split('/,\s*/', $fields, null, PREG_SPLIT_NO_EMPTY);
            }
            $qparts['fields'] = array_merge($qparts['fields'], $fields);
        }

        return $head . ' ' . join(', ', $qparts['fields']) . ' FROM ' . join(' LEFT JOIN ', $qparts['joins']) . ' ' . $tail;
    }

    /**
     *
     * @return array[]
     */
    protected function getSqlQueryParts()
    {
        $table = $this->getTableName();

        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

        $qparts['fields'][]     = "t.*";
        $qparts['tables'][]     = "$table AS t";
        $qparts['bundles'][]    = "";

        return $qparts;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return array|false
     *
     * @throws \BadMethodCallException
     */
    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }

        return false;
    }

    /**
     *
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return array|false
     *
     * @throws \BadMethodCallException
     */
    protected function loadComponentsRaw($context, $o)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }

        return false;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param array          $result
     * @return object|false
     */
    protected function processRawAttribution($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $t_agents = $this->dbs->getTableName('agents');

        $result['attribution'] = [];
        if (isset($result['att_contributor_id'])
            && ! empty($res = $this->dbs->getPublicId($t_agents, $result['att_contributor_id']))
        ) {
            $result['attribution']['contributor'] = [
                'resourceId' => $res,
            ];
        }
        if (! empty($result['att_modified'])) {
            $result['attribution']['modified'] = date(DATE_W3C, strtotime($result['att_modified']));
        }
        if (! empty($result['att_changeMessage'])) {
            $result['attribution']['changeMessage'] = $result['att_changeMessage'];
        }
        if (empty($result['attribution'])) {
            unset($result['attribution']);
        }

        return $result;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param array          $result
     * @return object|false
     */
    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $entity->initFromArray($result);

        if (! empty($result['_id'])) {
            GeniBaseInternalProperties::setPropertyOf($entity, '_id', $result['_id']);
        }

        return $entity;
    }

    /**
     * Return table name for store entity data.
     *
     * @return string Table full name.
     *
     * @throws \BadMethodCallException
     */
    protected function getTableName()
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }

        return '';
    }

    /**
     * Return data of entity for store to database.
     *
     * @param object|array $entity
     * @param ExtensibleData $context
     * @param array|string $o
     * @return mixed[]
     *
     * @throws \BadMethodCallException
     */
    protected function packData4Save(&$entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }

        $data = [];

        if ($entity instanceof ExtensibleData) {
            if (! empty($res = GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
                $data['_id'] = (int) $res;
            }
            if (! empty($res = $entity->getId()) && ($res != $this->previousState->getId())) {
                $data['id'] = $res;
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $data;
    }

    /**
     * @param Attribution $attribution
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected function packAttribution($attribution)
    {
        $data = [];

        /** @var Attribution $prev */
        if (empty($attribution)) {
            $attribution = new Attribution();
        } elseif (! $attribution instanceof Attribution) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of ' . Attribution::class);
        } elseif ($attribution == ($prev = $this->previousState->getAttribution())) {
            return [];
        }
        if (empty($prev)) {
            $prev = new Attribution();
        }

        $t_agents = $this->dbs->getTableName('agents');

        if (empty($res = $attribution->getContributor()) && ! empty($res2 = $this->dbs->getAgent())) {
            $attribution->setContributor(new ResourceReference([ 'resourceId' => $res2->getId() ]));
        }

        if (! empty($res = $attribution->getContributor()) && ($res != $prev->getContributor())
            && ! empty($res = $res->getResourceId())
            && ! empty($res = $this->dbs->getInternalId($t_agents, $res))
        ) {
            $data['att_contributor_id'] = $res;
        }
        if (! empty($res = $attribution->getModified())) {
            $data['att_modified'] = date(IDateTime::SQL, strtotime($res));
        }
        if (! empty($res = $attribution->getChangeMessage())) {
            $data['att_changeMessage'] = $res;
        }

        return $data;
    }

    protected function unpackAttribution($data)
    {
        $result = [];

        $t_agents = $this->dbs->getTableName('agents');

        if (! empty($data['att_contributor_id'])
            && ! empty($res = $this->dbs->getPublicId($t_agents, $data['att_contributor_id']))
        ) {
            $result['contributor'] = [  'resourceId' => $res    ];
        }
        if (! empty($data['att_modified'])) {
            $result['modified'] = date(DATE_W3C, strtotime($data['att_modified']));
        }
        if (! empty($data['att_changeMessage'])) {
            $result['changeMessage'] = $data['att_changeMessage'];
        }

        return (empty($result) ? null : new Attribution($result));
    }

    /**
     *
     * @param DateInfo $dateInfo
     * @param string $fieldsPrefix
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected static function packDateInfo($dateInfo, $fieldsPrefix = 'date_')
    {
        $data = [];

        if (empty($dateInfo)) {
            return null;
        } elseif (! $dateInfo instanceof DateInfo) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of ' . DateInfo::class);
        }

        if (! empty($res = $dateInfo->getOriginal())) {
            $data[$fieldsPrefix.'original'] = $res;
        }
        if (! empty($res = $dateInfo->getFormal())) {
            $data[$fieldsPrefix.'formal'] = $res;
            $period = DateUtil::calcPeriodInDays($res);
            $data[$fieldsPrefix.'_from_day']   = $period[0];
            $data[$fieldsPrefix.'_to_day']     = $period[1];
        }

        return $data;
    }

    /**
     *
     * @param ResourceReference $resourceReference
     * @param string $fieldsPrefix
     * @param string|null $table
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected static function packResourceReference($resourceReference, $fieldsPrefix, $table)
    {
        $data = [];

        if (empty($resourceReference)) {
            return [];
        } elseif (! $resourceReference instanceof ResourceReference) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of ' . ResourceReference::class);
        }

        if (! empty($res = $resourceReference->getResource())) {
            $data[$fieldsPrefix . 'uri'] = $res;
            if (! empty($res = GeniBaseStorager::getIdFromReference($res))) {
                $resourceReference->setResourceId($res);
            }
        }
        if (! empty($res = $resourceReference->getResourceId())
            && ! empty($table) && ! empty($res = $this->dbs->getInternalId($table, $res))
        ) {
            $data[$fieldsPrefix . 'id'] = $res;
        }

        return $data;
    }

    protected function unpackDateInfo($data, $fieldsPrefix = 'date_')
    {
        $result = [];

        if (! empty($res = $data[$fieldsPrefix.'original'])) {
            $result['original'] = $res;
        }
        if (! empty($res = $data[$fieldsPrefix.'formal'])) {
            $result['formal'] = $res;
        }

        return (empty($result) ? null : new DateInfo($result));
    }

    protected function getResourceIdFromUri($table, ResourceReference $reference)
    {
        if (! empty($res = $reference->getResourceId())
            || (! empty($res = $reference->getResource()) && empty($reference->getResourceId())
                && ! empty($res = GeniBaseStorager::getIdFromReference($res))
            )
        ) {
            if (! empty($res = $this->dbs->getInternalId($table, $res))) {
                return $res;
            }
        }
        return null;
    }

    /**
     *
     * @param mixed          $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return ExtensibleData|false
     *
     * @throws UniqueConstraintViolationException
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $this->garbageCleaning();

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__ . '#getObject');
        }
        /** @var ExtensibleData $ent */
        $this->previousState = $this->getObject();
        if (! is_a($entity, get_class($this->previousState))) {
            $entity = $this->getObject($entity);
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#getObject');
        }

        $o = $this->applyDefaultOptions($o, $entity);
        $table = $this->getTableName();

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__ . '#detectPreviousState');
        }
        $this->detectPreviousState($entity, $context, $o);
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#detectPreviousState');
            \App\Util\Profiler::startTimer(__METHOD__ . '#packData4Save');
        }
        $data = $this->packData4Save($entity, $context, $o);
// if ($entity instanceof \Gedcomx\Conclusion\PlaceDescription && $entity->getId() != 'L7HW-ANNP-V43N') { var_dump($data, $entity); die; }  // FIXME Delete me
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#packData4Save');
        }

        $_id = $data['_id'];
        unset($data['_id']);
        if (empty($_id) && ! empty($data['id'])) {
            $_id = $this->dbs->getInternalId($table, $data['id']);
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__ . '#SQL');
        }
        if (! empty($data)) {
            if (! empty($_id)) {
// { var_dump($data, $entity, $this->previousState); die; }  // FIXME Delete me
                $this->dbs->getDb()->update($table, $data, [   '_id' => $_id   ]);
            } else {
                try {
                    $this->dbs->getDb()->insert($table, $data);
                    $_id = $this->dbs->getDb()->lastInsertId();
                } catch (UniqueConstraintViolationException $ex) {
                    // Do nothing
                }
            }
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__ . '#SQL');
        }

        if ($entity instanceof ExtensibleData && ! empty($_id)) {
            GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $entity;
    }

    /**
     *
     * @param ExtensibleData $entity
     *
     * @throws \BadMethodCallException
     */
    public function delete(ExtensibleData $entity)
    {
        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }
    }

    /**
     *
     */
    protected function garbageCleaning()
    {
        // Do nothing
    }

    /**
     *
     * @param string $uri
     * @return NULL|string|boolean
     * @deprecated
     */
    public static function getIdFromReference($uri)
    {
        if (empty($uri)) {
            return null;
        }

        if (preg_match('/\#([\w\-]+)/', $uri, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     *
     * @see GeniBaseStorager::saveAllTextValues()
     *
     * @param string         $group
     * @param mixed          $text_value
     * @param ExtensibleData $context
     * @return number
     *
     * @throws \UnexpectedValueException
     */
    public function saveTextValue($group, $text_value, ExtensibleData $context)
    {
        if ($text_value instanceof TextValue) {
            $text_value = $text_value->toArray();
        }

        if (! empty($group) && (false !== $type_id = $this->dbs->getTypeId($ent['type']))) {
            $text_value['_group'] = $type_id;
        }

        $t_tvs = $this->dbs->getTableName('text_values');

        $ent = $text_value;
        $data = Util::arraySliceKeys($ent, 'value', '_group');

        if (! empty($ent['lang']) && (false !== $lang_id = $this->dbs->getLangId($ent['lang']))) {
            $data['lang_id'] = $lang_id;
        }
        if (empty($res = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        $data['_ref_id'] = $res;

        if (isset($ent['_id'])) {
            $this->dbs->getDb()->update($t_tvs, $data, [    '_id' => (int) $ent['_id']  ]);
            $result = (int) $ent['_id'];
        } else {
            $this->dbs->getDb()->insert($t_tvs, $data);
            $result = (int) $this->dbs->getDb()->lastInsertId();
        }

        return $result;
    }

    /**
     *
     * @param string                $group
     * @param array[]|TextValue[]   $text_values
     * @param ExtensibleData        $context
     *
     * @throws \UnexpectedValueException
     */
    public function saveAllTextValues($group, $text_values, ExtensibleData $context)
    {
        if (empty($text_values)) {
            return;
        }

        $t_tvs = $this->dbs->getTableName('text_values');

        // TODO: Add type_id check
        $_group = $this->dbs->getTypeId($group);
        if (empty($_ref = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }

        $tvs = $this->dbs->getDb()->fetchAll(
            "SELECT _id FROM $t_tvs WHERE _group = ? AND _ref_id = ? ORDER BY _id",
            [$_group, $_ref]
        );
        if (! empty($tvs)) {
            $tvs = array_map(
                function ($v) {
                    return (int) $v['_id'];
                },
                $tvs
            );
        }
        foreach ($text_values as $tv) {
            if (is_object($tv)) {
                $tv = $tv->toArray();
            }
            $tv['_group'] = $_group;
            if (! empty($tvs)) {
                $tv['_id'] = array_shift($tvs);
            }
            $this->saveTextValue(null, $tv, $context);
        }
        if (! empty($tvs)) {
            $this->dbs->getDb()->executeQuery(
                "DELETE FROM $t_tvs WHERE _id IN (?)",
                [$tvs],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );
        }
    }

    /**
     *
     * @param string         $group
     * @param ExtensibleData $context
     * @return array[]|false
     */
    public function loadTextValue($group, ExtensibleData $context)
    {
        // TODO: Add type_id check
        $_group = $this->dbs->getTypeId($group);
        if (empty($_ref = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv.*, l.lang FROM $t_tvs AS tv " .
            "LEFT JOIN $t_langs AS l ON (tv.lang_id = l._id) ".
            "WHERE tv._group = ? AND tv._ref_id = ? ORDER BY tv._id LIMIT 1";

        if (false !== $result = $this->dbs->getDb()->fetchAssoc($q, [$_group, $_ref])) {
            $result = new TextValue($result);
        }

        return $result;
    }

    /**
     *
     * @param string         $group
     * @param ExtensibleData $context
     * @return array[]|false
     */
    public function loadAllTextValues($group, ExtensibleData $context)
    {
        if (empty($_ref = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context internal ID required!');
        }
        // TODO: Add type_id check
        $_group = $this->dbs->getTypeId($group);

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv.*, l.lang FROM $t_tvs AS tv " .
            "LEFT JOIN $t_langs AS l ON (tv.lang_id = l._id) ".
            "WHERE tv._group = ? AND tv._ref_id = ? ORDER BY tv._id";

        if (false !== $result = $this->dbs->getDb()->fetchAll($q, [$_group, $_ref])) {
            foreach ($result as $k => $v) {
                $result[$k] = new TextValue($v);
            }
        }

        return $result;
    }

    /**
     *
     * @param string         $group
     * @param ExtensibleData $context
     * @return array[]|false
     */
    public function searchRefByTextValues($group, $textValues)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        // TODO: Add type_id check
        $_group = $this->dbs->getTypeId($group);
        if (! is_array($textValues)) {
            $textValues = [$textValues];
        }

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv._ref_id FROM $t_tvs AS tv ";
        $qw = "WHERE tv._group = ?";
        $data = [$_group];

        $qwtv = [];
        $qwl = false;
        /** @var TextValue $tv */
        foreach ($textValues as $tv) {
            $tmp = 'tv.value = ?';
            $data[] = $tv->getValue();
            if (! empty($lang = $tv->getLang())) {
                if (! $qwl) {
                    $q .= "LEFT JOIN $t_langs AS l ON (tv.lang_id = l._id) ";
                    $qwl = true;
                }
                $tmp = "($tmp AND l.lang = ?)";
                $data[] = $tv->getLang();
            }
            $qwtv[] = $tmp;
        }
        if (! empty($qwtv)) {
            $qw .= ' AND (' . implode(' OR ', $qwtv). ')';
        }

        if (false !== $result = $this->dbs->getDb()->fetchAll($q . $qw, $data)) {
            foreach ($result as $k => $v) {
                $result[$k] = (int) $v['_ref_id'];
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $result;
    }
}
