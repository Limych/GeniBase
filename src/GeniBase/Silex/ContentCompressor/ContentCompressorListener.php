<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @subpackage RestApi
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @author Tobias Sjösten <tobias@tobiassjosten.net>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @copyright Copyright (C) Tobias Sjösten <tobias@tobiassjosten.net>
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
namespace GeniBase\Silex\ContentCompressor;

use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 *
 *
 * @package GeniBase
 * @subpackage Deflate
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class ContentCompressorListener implements EventSubscriberInterface
{

    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\EventDispatcher\EventSubscriberInterface::getSubscribedEvents()
     */
    public static function getSubscribedEvents()
    {
        if (! function_exists('gzdeflate')) {
            return array();
        }

        return array(
            KernelEvents::RESPONSE => array( 'onKernelResponse', Application::LATE_EVENT ),
        );
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (! $event->isMasterRequest()) {
            return;
        }

        $this->app['deflate.options.init']();
        $options = $this->app['deflate.options'];

        $request = $event->getRequest();
        $response = $event->getResponse();
        $content = $response->getContent();
        $size = strlen($content);

        if ($size < $options['threshold']) {
            return;
        }

        $encoders = is_array($options['encoders']) ? $options['encoders'] : array( $options['encoders'] );
        if (in_array('gzip', $encoders)) {
            $encoders[] = 'x-gzip';
        }

        $accept = array_keys(AcceptHeader::fromString($request->headers->get('Accept-Encoding'))->all());
        foreach ($accept as $encoding) {
            if (in_array($encoding, $encoders)) {
                switch ($encoding) {
                    case 'gzip':
                    case 'x-gzip':
                    case 'deflate':
                        $response->headers->set('Content-Encoding', $encoding);
                        $content = gzencode(
                            $content,
                            $options['compression_level'],
                            ('deflate' === $encoding ? FORCE_DEFLATE : FORCE_GZIP)
                        );
                        $response->setContent($content);
                        $response->headers->set('Content-Length', strlen($content));
                        return;
                }
            }
        }
    }
}
