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
namespace GeniBase\DBase;

use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Gedcomx\Agent\Agent;
use Pimple\Container;

/**
 *
 * @author Limych
 */
class DBaseService extends Container
{

    /**
     * @var Agent
     */
    protected $agent;

    /**
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this['app'] = $app;
    }

    /**
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDb()
    {
        return $this['app']['db'];
    }

    /**
     *
     * @param string $table
     * @return string
     */
    public function getTableName($table)
    {
        $prefix = isset($this['app']['dbs.options']['default']['prefix'])
                ? $this['app']['dbs.options']['default']['prefix'] : '';
        return $prefix.$table;
    }

    /**
     *
     * @param Agent $agent
     */
    public function setAgent(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     *
     * @return \Gedcomx\Agent\Agent
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     *
     * @param string $id
     * @param boolean $makeIfNew
     * @return number|null
     */
    public function getInternalId($table, $id)
    {
        if (empty($id)) {
            return null;
        }

        static $cache = [];

        if (! empty($cache[$table]) && ! empty($cache[$table][$id])) {
            return $cache[$table][$id];
        }

        if (empty($cache[$table])) {
            $cache[$table] = [];
        }

        try {
            $res = $this['app']['db']->fetchColumn("SELECT _id FROM $table WHERE id = ?", [$id]);
            if (false !== $res) {
                $cache[$table][$id] = (int) $res;
                return (int) $res;
            }
        } catch (InvalidFieldNameException $e) {
            // Do nothing
        }
        return null;
    }

    /**
     *
     * @param number $_id
     * @return string|false
     */
    public function getPublicId($table, $_id)
    {
        if (empty($_id)) {
            return false;
        }

        static $cache = [];

        if (! empty($cache[$table]) && ! empty($cache[$table][$_id])) {
            return $cache[$table][$_id];
        }

        if (empty($cache[$table])) {
            $cache[$table] = [];
        }

        try {
            $res = $this['app']['db']->fetchColumn("SELECT id FROM $table WHERE _id = ?", [(int) $_id]);
            if (false !== $res) {
                $cache[$table][$_id] = $res;
                return $res;
            }
        } catch (InvalidFieldNameException $e) {
            // Do nothing
        }

        return false;
    }

    /**
     *
     * @param string $uri
     * @return number|false
     */
    public function getTypeId($uri)
    {
        if (empty($uri)) {
            return false;
        }

        static $cache = [];

        if (isset($cache[$uri])) {
            return $cache[$uri];
        }

        $t_types = $this->getTableName('types');

        $type_id = $this['app']['db']->fetchColumn("SELECT _id FROM $t_types WHERE uri = ?", [$uri]);
        if (false !== $type_id) {
            $cache[$uri] = (int) $type_id;
            return $cache[$uri];
        }

        if (0 === $this['app']['db']->insert($t_types, [  'uri' => $uri   ])) {
            return false;
        }
        $cache[$uri] = (int) $this['app']['db']->lastInsertId();
        return $cache[$uri];
    }

    /**
     *
     * @param number $type_id
     * @return string|false
     */
    public function getType($type_id)
    {
        if (empty($type_id)) {
            return false;
        }

        static $cache = [];

        if (isset($cache[$type_id])) {
            return $cache[$type_id];
        }

        $t_types = $this->getTableName('types');

        $type = $this['app']['db']->fetchColumn("SELECT uri FROM $t_types WHERE _id = ?", [(int) $type_id]);
        if (false !== $type) {
            $cache[$type_id] = $type;
        }
        return $type;
    }

    /**
     *
     * @param string $lang
     * @return number|false
     */
    public function getLangId($lang)
    {
        if (empty($lang)) {
            return false;
        }

        static $cache = [];

        if (isset($cache[$lang])) {
            return $cache[$lang];
        }

        $t_langs = $this->getTableName('languages');

        $lang_id = $this['app']['db']->fetchColumn("SELECT _id FROM $t_langs WHERE lang = ?", [$lang]);
        if (false !== $lang_id) {
            $cache[$lang] = (int) $lang_id;
            return $cache[$lang];
        }

        if (0 === $this['app']['db']->insert($t_langs, [  'lang' => $lang     ])) {
            return false;
        }
        $cache[$lang] = (int) $this['app']['db']->lastInsertId();
        return $cache[$lang];
    }

    /**
     *
     * @param number $lang_id
     * @return string|false
     */
    public function getLang($lang_id)
    {
        if (empty($lang_id)) {
            return false;
        }

        static $cache = [];

        if (isset($cache[$lang_id])) {
            return $cache[$lang_id];
        }

        $t_langs = $this->getTableName('languages');

        $lang = $this['app']['db']->fetchColumn("SELECT lang FROM $t_langs WHERE _id = ?", [(int) $lang_id]);
        if (false !== $lang) {
            $cache[$lang_id] = $lang;
        }
        return $lang;
    }
}
