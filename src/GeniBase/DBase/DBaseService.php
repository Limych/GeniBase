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

    private $types_cache = array();

    /**
     *
     * @param string $type_uri
     * @return number|null
     */
    public function getTypeId($type_uri)
    {
        if (empty($type_uri)) {
            return null;
        }

        if (isset($this->types_cache[$type_uri])) {
            return $this->types_cache[$type_uri];
        }

        $t_types = $this->getTableName('types');

        $type_id = $this['app']['db']->fetchColumn("SELECT id FROM $t_types WHERE uri = ?", array( $type_uri ));
        if (false !== $type_id) {
            $this->types_cache[$type_uri] = (int) $type_id;
            return $this->types_cache[$type_uri];
        }

        if (0 === $this['app']['db']->insert($t_types, array( 'uri' => $type_uri ))) {
            return null;
        }
        $this->types_cache[$type_uri] = (int) $this['app']['db']->lastInsertId();
        return $this->types_cache[$type_uri];
    }

    /**
     *
     * @param number $type_id
     * @return string|null
     */
    public function getType($type_id)
    {
        if (empty($type_id)) {
            return null;
        }

        $tmp = array_flip($this->types_cache);
        if (isset($tmp[$type_id])) {
            return $tmp[$type_id];
        }

        $t_types = $this->getTableName('types');

        $type = $this['app']['db']->fetchColumn(
            "SELECT uri FROM $t_types WHERE id = ?",
            array( (int) $type_id )
        );
        if (false !== $type) {
            $this->types_cache[$type] = $type_id;
        }
        return $type;
    }
}
