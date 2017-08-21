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

/**
 *
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
interface DBaseInterface
{

    /**
     *
     * @param string $table
     * @return string
     */
    public function getTableName($table);

    /**
     *
     * @param Agent $agent
     */
    public function setAgent(Agent $agent);

    /**
     *
     * @return \Gedcomx\Agent\Agent
     */
    public function getAgent();

    /**
     *
     * @param string $type_uri
     * @return number|null
     */
    public function getTypeId($type_uri);

    /**
     *
     * @param number $type_id
     * @return string|null
     */
    public function getType($type_id);
}
