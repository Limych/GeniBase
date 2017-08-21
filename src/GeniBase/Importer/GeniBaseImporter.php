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
namespace GeniBase\Importer;

use GeniBase\Storager\Agents;
use GeniBase\Storager\GeniBaseStorager;
use Silex\Application;

/**
 *
 * @author Limych
 *
 */
class GeniBaseImporter
{
    // TODO: Remove Silex classes

    protected $app;
    protected $gbs;
    protected $agent;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->gbs = new GeniBaseStorager($app['gb.db']);
        $this->setAgent(Agents::getGeniBaseAgent($this->gbs));
    }

    protected function setAgent($agent)
    {
        $this->agent = $agent;
        $this->app['gb.db']->setAgent($this->agent);
    }
}
