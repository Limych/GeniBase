<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @subpackage ContentCompressor
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
namespace GeniBase\Provider\Silex\ContentCompressor;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 *
 *
 * @package GeniBase
 * @subpackage ContentCompressor
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class ContentCompressorServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{

    protected $options;

    public function __construct($options = null)
    {
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     */
    public function register(Container $app)
    {

        // Default options
        $app['deflate.options.default'] = array(
            'formats' => array( 'html', 'txt', 'js', 'css', 'json', 'xml', 'rdf', 'atom', 'rss', 'form', ),
            'threshold' => 2048,
            'encoders' => array( 'gzip', 'deflate' ),
            'compression_level' => -1,
        );

        if (! empty($this->options)) {
            $app['deflate.options'] = $this->options;
        }

        // Initialize $app['deflate.options']
        $app['deflate.options.init'] = $app->protect(function() use ($app) {
            $options = $app['deflate.options.default'];
            if (! empty($app['deflate.options'])) {
                // Merge default and configured options
                $options = array_replace_recursive($options, $app['deflate.options']);
            }
            $app['deflate.options'] = $options;
        });

        // Deflate listener
        $app['deflate.listener'] = function ($app) {
            return new ContentCompressorListener($app);
        };
    }

    /**
     * Subscribe for kernel events.
     *
     * @param Container $app
     * @param EventDispatcherInterface $dispatcher
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['deflate.listener']);
    }
}
