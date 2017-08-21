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
namespace GeniBase\Provider\Silex;

use Gedcomx\Gedcomx;
use GeniBase\Provider\Silex\Encoder\GeniBaseJsonEncoder;
use GeniBase\Provider\Silex\Encoder\GeniBaseXmlEncoder;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 *
 * @package GeniBase
 * @subpackage Silex
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class GeniBaseServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     */
    public function register(Container $app)
    {
        $app->before(
            function (Request $request) use ($app) {
                // Add Gedcomx formats
                $tmp = $request->getMimeTypes('json');
                $tmp[] = Gedcomx::JSON_MEDIA_TYPE;
                $request->setFormat('json', $tmp);

                $tmp = $request->getMimeTypes('xml');
                $tmp[] = Gedcomx::XML_MEDIA_TYPE;
                $request->setFormat('xml', $tmp);
            }
        );

        // Database service
        $app['gb.db']  = function () use ($app) { return new DBaseService($app); };

        // Register serializers
        $app->extend('serializer.encoders', function ($encoders) {
            $encoders = array_merge(array(
                new GeniBaseJsonEncoder(),
                new GeniBaseXmlEncoder()
            ), $encoders);
            return $encoders;
        });
    }
}
