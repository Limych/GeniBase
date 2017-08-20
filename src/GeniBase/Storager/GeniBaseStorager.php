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
use GeniBase\Util;
use GeniBase\Silex\DBaseService;
use GeniBase\Util\DateUtil;
use GeniBase\Util\UUID;
use Gedcomx\Common\TextValue;
use Gedcomx\Common\ResourceReference;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\Identifier;
use Gedcomx\Common\Attributable;
use GeniBase\DBase\GeniBaseInternalProperties;
use Gedcomx\Common\Attribution;

/**
 *
 * @author Limych
 */
class GeniBaseStorager
{

    const DATE_SQL = 'Y-m-d H:i:s';

    const GBID_LENGTH = 8;

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
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
        return array(
            'makeId_name'       => null,
            'makeId_unique'     => true,
            'loadCompanions'    => false,
            'sortComponents'    => false,
        );
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

            $cache = array();
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
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
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
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
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
            foreach ($result as $key => $row) {
                $result[$key] = $this->unpackLoadedData($this->getObject(), $row);
            }
            if (! empty($o['sortComponents'])) {
                usort($result, array($this, 'compareComponents'));
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

        $qparts['joins'] = array();
        for ($i = 0; $i < count($qparts['tables']); $i++) {
            $b = $qparts['bundles'][$i];
            $qparts['joins'][] = $qparts['tables'][$i] . (empty($b) ? '' : " ON $b");
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

        $qparts = array(
            'fields'    => array(),  'tables'    => array(),  'bundles'   => array(),
        );

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
        // TODO Check for ability to replace by standard code. See: ConclusionStorager::loadRaw()

        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
        // TODO Check for ability to replace by standard code. See: ConclusionStorager::loadComponentsRaw()

        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
    protected function unpackLoadedData($entity, $result)
    {
        if (! is_array($result)) {
            return $result;
        }

        $entity->initFromArray($result);

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
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
    protected function packData4Save(&$entity, $context = null, $o = null)
    {
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }

        $data = array();

        if ($entity instanceof ExtensibleData) {
            $res = $entity->getId();
            if (! empty($res)) {
                $data['id'] = $res;
            }else {
                $res = GeniBaseInternalProperties::getPropertyOf($entity, '_id');
                if (! empty($res)) {
                    $data['_id'] = $res;
                }
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
        return $data;
    }

    /**
     *
     * @param DateInfo $dateInfo
     * @param string $fieldsPrefix
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected static function packDateInfo($dateInfo, $fieldsPrefix = 'date')
    {
        if (empty($dateInfo)) {
            return null;
        } elseif (! $dateInfo instanceof DateInfo) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of \Gedcomx\Conclusion\DateInfo');
        }

        $data = array();

        $res = $dateInfo->getOriginal();
        if (! empty($res)) {
            $data[$fieldsPrefix.'_original'] = $res;
        }

        $res = $dateInfo->getFormal();
        if (! empty($res)) {
            $data[$fieldsPrefix.'_formal'] = $res;

            $period = DateUtil::calcPeriodInDays($res);
            $data[$fieldsPrefix.'_eday_from']   = $period[0];
            $data[$fieldsPrefix.'_eday_to']     = $period[1];
        }

        return $data;
    }

    /**
     *
     * @param mixed[] $data
     * @param string $fieldsPrefix
     * @return NULL|\Gedcomx\Conclusion\DateInfo
     */
    protected function unpackDateInfo($data, $fieldsPrefix = 'date')
    {
        $result = array();

        $res = $data[$fieldsPrefix.'_original'];
        if (! empty($res)) {
            $result['original'] = $res;
        }

        $res = $data[$fieldsPrefix.'_formal'];
        if (! empty($res)) {
            $result['formal'] = $res;
        }

        return (empty($result) ? null : new DateInfo($result));
    }

    /**
     *
     * @param ResourceReference $resourceReference
     * @param string $fieldsPrefix
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected static function packResourceReference($resourceReference, $fieldsPrefix)
    {
        if (empty($resourceReference)) {
            return array();
        } elseif (! $resourceReference instanceof ResourceReference) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of \Gedcomx\Common\ResourceReference');
        }

        $data = array();

        $res = $resourceReference->getResource();
        if (! empty($res)) {
            $data[$fieldsPrefix . '_uri'] = $res;
            $res = self::getIdFromReference($res);
            if (! empty($res)) {
                $resourceReference->setResourceId($res);
            }
        }

        $res = $resourceReference->getResourceId();
        if (! empty($res)) {
            $data[$fieldsPrefix . '_id'] = $res;
        }

        return $data;
    }

    /**
     *
     * @param mixed[] $data
     * @param string $fieldsPrefix
     * @return NULL|\Gedcomx\Common\ResourceReference
     */
    protected static function unpackResourceReference($data, $fieldsPrefix)
    {
        $result = array();

        if (! empty($data[$fieldsPrefix.'_uri'])) {
            $result['resource'] = $data[$fieldsPrefix.'_uri'];
        }
        if (! empty($data[$fieldsPrefix.'_id'])) {
            $result['resourceId'] = $data[$fieldsPrefix.'_id'];
        }

        return (empty($result) ? null : new ResourceReference($result));
    }

    /**
     *
     * @param Attribution $attribution
     * @return mixed[]
     *
     * @throws \InvalidArgumentException
     */
    protected function packAttribution($attribution)
    {
        if (empty($attribution)) {
            $attribution = new Attribution();
        } elseif (! $attribution instanceof Attribution) {
            throw new \InvalidArgumentException('Argument 1 must be an instance of \Gedcomx\Common\Attribution');
        }

        $data = array();

        $res = $attribution->getContributor();
        if (empty($res)) {
            $res2 = $this->dbs->getAgent();
            if (! empty($res2)) {
                $data['att_contributor'] = $res2->getId();
            }
        } else {
            $prev = $this->previousState->getAttribution();
            if (empty($prev) || ($res != $prev->getContributor())) {
                $res = $res->getResourceId();
                if (! empty($res)) {
                    $data['att_contributor'] = $res;
                }
            }
        }


        $res = $attribution->getModified();
        if (empty($res)) {
            $res = time();
        }
        $data['att_modified'] = date(self::DATE_SQL, $res);

        $res = $attribution->getChangeMessage();
        if (! empty($res)) {
            $data['att_changeMessage'] = $res;
        }

        return $data;
    }

    /**
     *
     * @param mixed[] $data
     * @return NULL|\Gedcomx\Common\Attribution
     */
    protected static function unpackAttribution($result)
    {
        $data = array();

        if (! empty($result['att_contributor'])) {
            $data['contributor'] = array( 'resourceId' => $result['att_contributor'] );
        }
        if (! empty($result['att_modified'])) {
            $data['modified'] = strtotime($result['att_modified']);
        }
        if (! empty($result['att_changeMessage'])) {
            $data['changeMessage'] = $result['att_changeMessage'];
        }

        return (empty($data) ? null : new Attribution($data));
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
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        $this->garbageCleaning();

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__ . '#getObject');
        }
        /** @var ExtensibleData $ent */
        $this->previousState = $this->getObject();
        if (! is_a($entity, get_class($this->previousState))) {
            $entity = $this->getObject($entity);
        }
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__ . '#getObject');
        }

        $o = $this->applyDefaultOptions($o, $entity);
        $table = $this->getTableName();

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__ . '#detectPreviousState');
        }
        $this->detectPreviousState($entity, $context, $o);
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__ . '#detectPreviousState');
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::startTimer(__METHOD__ . '#packData4Save');
        }
        $data = $this->packData4Save($entity, $context, $o);
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__ . '#packData4Save');
        }

        if (1 < count($data)) {
            if (defined('DEBUG_PROFILE')) {
                \GeniBase\Util\Profiler::startTimer(__METHOD__ . '#SQL');
            }
            try {
                $this->dbs->getDb()->insert($table, $data);
            } catch (UniqueConstraintViolationException $ex) {
                $id = $data['id'];
                if (! empty($id)) {
                    unset($data['id']);
                    $this->dbs->getDb()->update($table, $data, array( 'id' => $id ));
                } else {
                    $_id = $data['_id'];
                    unset($data['_id']);
                    $this->dbs->getDb()->update($table, $data, array( '_id' => $_id ));
                }
            }
            if (defined('DEBUG_PROFILE')) {
                \GeniBase\Util\Profiler::stopTimer(__METHOD__ . '#SQL');
            }

            if ($entity instanceof Attributable) {
                $res = $entity->getAttribution();
                if (empty($res)) {
                    $res = new Attribution();
                    $entity->setAttribution($res);
                }
                $res->setModified(time());
                $rref = new ResourceReference();
                $rref->setResourceId($this->dbs->getAgent()->getId());
                $res->setContributor($rref);
            }
        }
        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
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
        $ref = new \ReflectionClass($class);
        if ($ref->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
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
     * @param string                $group
     * @param array[]|TextValue[]   $text_values
     * @param ExtensibleData        $context
     *
     * @throws \UnexpectedValueException
     */
    public function saveTextValues($group, array $text_values, ExtensibleData $context)
    {
        $t_tvs = $this->dbs->getTableName('text_values');

        $_ref = $context->getId();
        if (empty($_ref)) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        // TODO: Add type_id check
        $_group = $this->getTypeId($group);

        $tv_ids = $this->dbs->getDb()->fetchAll(
            "SELECT id FROM $t_tvs WHERE group_type_id = ? AND parent_id = ? ORDER BY id",
            array( $_group, $_ref )
        );
        if (! empty($tv_ids)) {
            $tv_ids = array_map(
                function ($v) {
                    return (int) $v['id'];
                },
                $tv_ids
            );
        }
        foreach ($text_values as $tv) {
            if (is_object($tv)) {
                $tv = $tv->toArray();
            }

            $data = Util::arraySliceKeys($tv, 'value', 'lang');
            $data['parent_id'] = $_ref;
            $data['group_type_id'] = $_group;

            $id = array_shift($tv_ids);
            if (! empty($id)) {
                $this->dbs->getDb()->update($t_tvs, $data, array( 'id' => (int) $id ));
            } else {
                $this->dbs->getDb()->insert($t_tvs, $data);
            }
        }
        if (! empty($tv_ids)) {
            $this->dbs->getDb()->executeQuery(
                "DELETE FROM $t_tvs WHERE id IN (?)",
                array( $tv_ids ),
                array( \Doctrine\DBAL\Connection::PARAM_INT_ARRAY )
            );
        }
    }

    /**
     *
     * @param string         $group
     * @param ExtensibleData $context
     * @return array[]|false
     *
     * @throws \UnexpectedValueException
     */
    public function loadTextValues($group, ExtensibleData $context)
    {
        $_ref = $context->getId();
        if (empty($_ref)) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        // TODO: Add type_id check
        $_group = $this->getTypeId($group);

        $t_tvs = $this->dbs->getTableName('text_values');

        $query = "SELECT * FROM $t_tvs WHERE group_type_id = ? AND parent_id = ? ORDER BY id";
        if (false !== $result = $this->dbs->getDb()->fetchAll($query, array( $_group, $_ref ))) {
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
            \GeniBase\Util\Profiler::startTimer(__METHOD__);
        }
        // TODO: Add type_id check
        $_group = $this->getTypeId($group);

        if (! is_array($textValues)) {
            $textValues = array( $textValues );
        }

        $t_tvs = $this->dbs->getTableName('text_values');

        $query = "SELECT parent_id FROM $t_tvs WHERE group_type_id = ?";
        $qwtv = array();
        $data = array( $_group );

        /** @var TextValue $tv */
        foreach ($textValues as $tv) {
            $qwtv[] = 'value = ?';
            $data[] = $tv->getValue();
        }
        if (! empty($qwtv)) {
            $query .= ' AND (' . implode(' OR ', $qwtv). ')';
        }

        if (false !== $result = $this->dbs->getDb()->fetchAll($query, $data)) {
            foreach ($result as $key => $val) {
                $result[$key] = $val['parent_id'];
            }
        }

        if (defined('DEBUG_PROFILE')) {
            \GeniBase\Util\Profiler::stopTimer(__METHOD__);
        }
        return $result;
    }

    /**
     *
     * @param Identifier[]|array[] $identifiers
     * @param ExtensibleData $context
     *
     * @throws \UnexpectedValueException
     */
    public function saveIdentifiers(array $identifiers, ExtensibleData $context)
    {
        $_ref = $context->getId();
        if (empty($_ref)) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        $t_ids = $this->dbs->getTableName('identifiers');

        $old_ids = $this->dbs->getDb()->fetchAll(
            "SELECT type_id, value FROM $t_ids WHERE id = ?",
            array( $_ref )
        );

        $processed = array();
        foreach ($identifiers as $id) {
            if ($id instanceof Identifier) {
                $id = $id->toArray();
            }

            $data = array(
                'type_id'   => $this->getTypeId($id['type']),
                'value'     => $id['value'],
            );

            if (in_array($data, $processed)) {
                continue;
            }
            $processed[] = $data;
            foreach ($old_ids as $key => $val) {
                if (($val['type_id'] == $data['type_id']) && ($val['value'] == $data['value'])) {
                    unset($old_ids[$key]);
                    continue 2;
                }
            }

            $data['id'] = $_ref;
            try {
                $this->dbs->getDb()->insert($t_ids, $data);
            } catch (\Exception $ex) {
var_dump($processed, $ex);die;
            }
        }

        foreach ($old_ids as $data) {
            $data['id'] = $_ref;
            $this->dbs->getDb()->delete($t_ids, $data);
        }
    }

    /**
     *
     * @param ExtensibleData $context
     * @param string $type_uri
     * @return \Gedcomx\Conclusion\Identifier[]
     *
     * @throws \UnexpectedValueException
     */
    public function loadIdentifiers(ExtensibleData $context, $type_uri = null)
    {
        $_ref = $context->getId();
        if (empty($_ref)) {
            throw new \UnexpectedValueException('Context ID required!');
        }

        $t_ids = $this->dbs->getTableName('identifiers');
        $t_types = $this->dbs->getTableName('types');

        $query = "SELECT t.uri AS type, id.value FROM $t_ids AS id " .
            "LEFT JOIN $t_types AS t ON ( t.id = id.type_id ) " .
            "WHERE id.id = ?";
        $data = array( $_ref );
        if (! empty($type_uri)) {
            $query .= ' AND id.type_id = ?';
            $data[] = $this->getTypeId($type_uri);
        }
        $result = $this->dbs->getDb()->fetchAll($query, $data);
        if (! empty($result)) {
            foreach ($result as $key => $val) {
                $result[$key] = new Identifier($val);
            }
        }

        return $result;
    }

    /**
     *
     * @param array $identifiers
     * @return string|NULL
     */
    public function searchIdByIdentifiers(array $identifiers)
    {
        $t_ids = $this->dbs->getTableName('identifiers');

        if (! is_array($identifiers)) {
            $identifiers = array($identifiers);
        }

        $ids = array();
        foreach ($identifiers as $id) {
            if ($id instanceof Identifier) {
                $id = $id->toArray();
            }
            $ids[] = $id['value'];
        }

        $result = $this->dbs->getDb()->fetchArray(
            "SELECT id FROM $t_ids WHERE value IN (?) LIMIT 1",
            array($ids),
            array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
        );
        if (! empty($result)) {
            return $result[0];
        }
        return null;
    }

    /**
     *
     * @param string $type_uri
     * @return number|null
     */
    protected function getTypeId($type_uri)
    {
        return $this->dbs->getTypeId($type_uri);
    }

    /**
     *
     * @param number $type_id
     * @return string|null
     */
    protected function getType($type_id)
    {
        return $this->dbs->getType($type_id);
    }
}
