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
namespace GeniBase\Silex\Gedcomx;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use GeniBase\Common\Statistic;
use Silex\Application;

/**
 *
 *
 * @package GeniBase
 * @subpackage Silex
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class GedcomxServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     */
    public function register(Container $app)
    {
        $app['statistic.controller'] = $app->protect(function() use ($app) { return $app['statistic']; });

        if (! isset($app['statistic'])) {
            $app['statistic'] = function() use ($app) { return $this->statistic($app); };
        } else {
            $app->extend('statistic', function($stat, $app) {
                $stat->embed($this->statistic($app));
                return $stat;
            });
        }
    }

    /**
     *
     * @param Application $app
     * @return Statistic
     */
    public function statistic(Application $app)
    {
        return new Statistic();
    }

    /**
     *
     * @param mixed  $app
     * @param string $base
     */
    public function mountRoutes($app, $base)
    {
        // Abstract. Do nothing
    }

    /**
     *
     * @param mixed  $app
     * @param string $base
     */
    public function mountApiRoutes($app, $base)
    {
        // Abstract. Do nothing
    }
}
