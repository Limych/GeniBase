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
namespace GeniBase\Provider\Silex\Gedcomx;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use GeniBase\Common\Statistic;
use Silex\Application;
use GeniBase\Common\Sitemap;
use Symfony\Component\HttpFoundation\Request;

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
        if (! isset($app['statistic'])) {
            $app['statistic'] = array();

            $app['statistic.controller'] = $app->protect(function() use ($app) {
                $result = new Statistic();
                foreach ($app['statistic'] as $stat) {
                    $callback = $app['callback_resolver']->resolveCallback($stat);
                    if (is_callable($callback)) {
                        $result->embed(call_user_func($callback, $app));
                    }
                }
                return $result;
            });
        }
        $statistic = $app['statistic'];
        $statistic[] = array($this, 'statistic');
        $app['statistic'] = $statistic;

        $sitemap = $app['sitemap'];
        $sitemap[] = array($this, 'sitemap');
        $app['sitemap'] = $sitemap;
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
     * @param Application $app
     * @param Request $request
     * @return \GeniBase\Common\Sitemap
     */
    public function sitemap(Application $app, Request $request)
    {
        return new Sitemap();
    }

    protected $routesBase;

    /**
     *
     * @param mixed  $app
     * @param string $base
     */
    public function mountRoutes($app, $base)
    {
        $this->routesBase = $base;
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
