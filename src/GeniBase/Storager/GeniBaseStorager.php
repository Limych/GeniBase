<?php
namespace GeniBase\Storager;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gedcomx\Gedcomx;
use Gedcomx\Common\ExtensibleData;
use GeniBase\DBase\DBaseService;
use GeniBase\DBase\GeniBaseInternalProperties;
use GeniBase\Util;
use GeniBase\Util\UUID;
use Gedcomx\Common\TextValue;

/**
 *
 * @author Limych
 */
class GeniBaseStorager
{

    const GBID_LENGTH = 12;

    const TABLES_WITH_GBID = 'persons sources places events agents';

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
    public function getDefaultOptions(ExtensibleData $entity = null)
    {
        return [
            'makeId_name'       => null,
            'makeId_unique'     => true,
            'loadCompanions'    => false,
        ];
    }

    /**
     *
     * @param mixed          $o
     * @param ExtensibleData $entity
     * @return array
     */
    public function applyDefaultOptions($o, ExtensibleData $entity = null)
    {
        return Util::parseArgs($o, $this->getDefaultOptions($entity));
    }

    public static function hash($type = 'alnum', $length = 8, $data = null)
    {
        switch ($type) {
            case 'alnum':
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alpha':
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec':
                $pool = '0123456789abcdef';
                break;
            case 'numeric':
                $pool = '0123456789';
                break;
            case 'nozero':
                $pool = '123456789';
                break;
            case 'distinct':
                $pool = '234679ACDEFHJKLMNPRTUVWXYZ';
                break;
            default:
                $pool = (string) $type;
                break;
        }

        if (empty($pool)) {
            return '';
        }

        $token = '';
        $max = strlen($pool);
        $log = log($max, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        for ($i = 0; $i < $length; $i ++) {
            if (! empty($data)) {
                // Predefined hash
                $rnd = hexdec(substr(md5($data.$i), 0, $bytes*2));
                $rnd = $rnd & $filter; // discard irrelevant bits
                while ($rnd >= $max) {
                    $rnd -= $max;
                }
            } else {
                // Random data
                do {
                    $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
                    $rnd = $rnd & $filter; // discard irrelevant bits
                } while ($rnd >= $max);
            }
            $token .= $pool[$rnd];
        }
        return $token;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @return boolean
     */
    protected function detectId(ExtensibleData &$entity)
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
        return join('-', array_reverse(str_split(self::hash('distinct', self::GBID_LENGTH, $name), 4)));
    }

    /**
     *
     * @param string $name
     * @return string
     */
    public function makeGbidUnique($name = null)
    {
        static $tables;

        if (! isset($tables)) {
            // Initialization
            $tables = array_map(
                function ($v) {
                    return $this->dbs->getTableName($v);
                },
                preg_split('/[\s,]+/', self::TABLES_WITH_GBID, null, PREG_SPLIT_NO_EMPTY)
            );
        }

        $suffix = '';
        do {
            $gbid = self::makeGbid(empty($name) ? null : $name . $suffix);
            $suffix += 1;

            $q = "";
            $qid = $this->dbs->getDb()->quote($gbid);
            $cnt = 0;
            foreach ($tables as $t) {
                if (1 != ++$cnt) {
                    $q  .= " UNION ";
                }
                $q  .= "SELECT 1 FROM $t WHERE id = " . $qid;
            }
        } while (! empty($q) && (false !== $r = $this->dbs->getDb()->fetchColumn($q)));

        return $gbid;
    }

    /**
     *
     * @param ExtensibleData $entity
     * @param array          $o
     */
    public function makeGbidIfEmpty(ExtensibleData &$entity, $o = null)
    {
        if (empty($entity->getId()) && ! $this->detectId($entity)) {
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
        if (empty($entity->getId()) && ! $this->detectId($entity)) {
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
    public function loadListGedcomx($entity, $context = null, $o = null)
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
        return $this->processRaw($this->getObject(), $result);
    }

    /**
     *
     * @param mixed      $context
     * @param array|null $o
     * @return object[]|false
     */
    public function loadList($context = null, $o = null)
    {
        if (! $context instanceof ExtensibleData) {
            $context = $this->getObject($context);
        }

        if (is_array($result = $this->loadListRaw($context, $o))) {
            foreach ($result as $k => $r) {
                $result[$k] = $this->processRaw($this->getObject(), $r);
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

        foreach ($qparts['tables'] as $k => $v) {
            $qparts['joins'][$k] = $v . (empty($b = $qparts['bundles'][$k]) ? '' : " ON $b");
        }
        $qparts['fields'][] = array_shift($qparts['fields']);
        if (! empty($fields)) {
            if (! is_array($fields)) {
                $fields = preg_split('/,\s*/', $fields, null, PREG_SPLIT_NO_EMPTY);
            }
            $qparts['fields'] = array_merge($qparts['fields'], $fields);
        }

        return $head . ' ' . join(', ', $qparts['fields']) . ' FROM ' . join(' LEFT JOIN ', $qparts['joins']) . ' ';
    }

    /**
     *
     * @return array[]
     */
    protected function getSqlQueryParts()
    {
        $qparts = [
            'fields'    => [],  'tables'    => [],  'bundles'   => [],
        ];

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
    protected function loadListRaw($context, $o)
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
    protected function processRaw($entity, $result)
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
     *
     * @param mixed          $entity
     * @param ExtensibleData $context
     * @param array|null     $o
     * @return ExtensibleData|false
     *
     * @throws \BadMethodCallException
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        $this->garbageCleaning();

        $class = get_class($this);
        if ((new \ReflectionClass($class))->getMethod(__FUNCTION__)->getDeclaringClass()->name === __CLASS__) {
            throw new \BadMethodCallException('Error: Method ' . __METHOD__ . ' should be redefined for class ' . $class);
        }
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
     * @return number
     */
    public function getTypeId($uri)
    {
        static $cache = [];

        if (isset($cache[$uri])) {
            return $cache[$uri];
        }

        $t_types = $this->dbs->getTableName('types');

        $result = $this->dbs->getDb()->fetchColumn(
            "SELECT _id FROM $t_types WHERE uri = ?",
            [$uri]
        );
        if (false !== $result) {
            $cache[$uri] = (int) $result;
            return $cache[$uri];
        }

        $this->dbs->getDb()->insert(
            $t_types,
            [
            'uri' => $uri
            ]
        );
        $cache[$uri] = (int) $this->dbs->getDb()->lastInsertId();
        return $cache[$uri];
    }

    /**
     *
     * @param number $id
     * @return string|false
     */
    public function getType($id)
    {
        static $cache = [];

        if (isset($cache[$id])) {
            return $cache[$id];
        }

        $t_types = $this->dbs->getTableName('types');

        $result = $this->dbs->getDb()->fetchColumn(
            "SELECT uri FROM $t_types WHERE _id = ?",
            [(int) $id]
        );

        $cache[$id] = $result;
        return $result;
    }

    /**
     *
     * @param string $lang
     * @return number|false
     */
    public function getLangId($lang)
    {
        static $cache = [];

        if (isset($cache[$lang])) {
            return $cache[$lang];
        }

        $t_langs = $this->dbs->getTableName('languages');

        $result = $this->dbs->getDb()->fetchColumn(
            "SELECT _id FROM $t_langs WHERE lang = ?",
            [$lang]
        );
        if (false !== $result) {
            $cache[$lang] = (int) $result;
            return $cache[$lang];
        }

        $this->dbs->getDb()->insert(
            $t_langs,
            [
            'lang' => $lang
            ]
        );
        $cache[$lang] = (int) $this->dbs->getDb()->lastInsertId();
        return $cache[$lang];
    }

    /**
     *
     * @param number $id
     * @return string|false
     */
    public function getLang($id)
    {
        static $cache = [];

        if (isset($cache[$id])) {
            return $cache[$id];
        }

        $t_langs = $this->dbs->getTableName('languages');

        $result = $this->dbs->getDb()->fetchColumn(
            "SELECT lang FROM $t_langs WHERE _id = ?",
            [(int) $id]
        );

        $cache[$id] = $result;
        return $result;
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

        if (! empty($group)) {
            $text_value['_group'] = $this->getTypeId($group);
        }

        $t_tvs = $this->dbs->getTableName('text_values');

        $ent = $text_value;
        $data = Util::arraySliceKeys($ent, 'value', '_group');

        if (! empty($ent['lang']) && (false !== $result = $this->getLangId($ent['lang']))) {
            $data['lang_id'] = $result;
        }
        if (empty($r = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $data['_ref'] = $r;

        if (isset($ent['_id'])) {
            $this->dbs->getDb()->update(
                $t_tvs,
                $data,
                [
                '_id' => (int) $ent['_id']
                ]
            );
            $result = (int) $ent['_id'];
        } else {
            try {
                $this->dbs->getDb()->insert($t_tvs, $data);
                $result = (int) $this->dbs->getDb()->lastInsertId();
            } catch (UniqueConstraintViolationException $e) {
                // Do nothing
            }
        }

        return $result;
    }

    /**
     *
     * @param string         $group
     * @param array[]        $text_values
     * @param ExtensibleData $context
     *
     * @throws \UnexpectedValueException
     */
    public function saveAllTextValues($group, $text_values, ExtensibleData $context)
    {
        if (empty($text_values)) {
            return;
        }

        $t_tvs = $this->dbs->getTableName('text_values');

        $_group = $this->getTypeId($group);
        if (empty($_ref = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }

        $tvs = $this->dbs->getDb()->fetchAll(
            "SELECT _id FROM $t_tvs WHERE _group = ? AND _ref = ? ORDER BY _id",
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
            if ($tv instanceof TextValue) {
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
        $_group = $this->getTypeId($group);
        if (empty($_ref = (int) GeniBaseInternalProperties::getPropertyOf($context, '_id'))) {
            throw new \UnexpectedValueException('Context local ID required!');
        }

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv.*, l.lang FROM $t_tvs AS tv " .
            "LEFT JOIN $t_langs AS l ON (tv.lang_id = l._id) ".
            "WHERE tv._group = ? AND tv._ref = ? ORDER BY tv._id LIMIT 1";

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
            throw new \UnexpectedValueException('Context local ID required!');
        }
        $_group = $this->getTypeId($group);

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv.*, l.lang FROM $t_tvs AS tv " .
            "LEFT JOIN $t_langs AS l ON (tv.lang_id = l._id) ".
            "WHERE tv._group = ? AND tv._ref = ? ORDER BY tv._id";

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
        $_group = $this->getTypeId($group);
        if (! is_array($textValues))    $textValues = [$textValues];

        $t_tvs = $this->dbs->getTableName('text_values');
        $t_langs = $this->dbs->getTableName('languages');

        $q = "SELECT tv._ref FROM $t_tvs AS tv ";
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
        if (! empty($qwtv)) $qw .= ' AND (' . implode(' OR ', $qwtv). ')';

        if (false !== $result = $this->dbs->getDb()->fetchAll($q . $qw, $data)) {
            foreach ($result as $k => $v) {
                $result[$k] = (int) $v['_ref'];
            }
        }

        return $result;
    }
}
