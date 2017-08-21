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
namespace GeniBase\Provider\Silex\RestApi;

use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 *
 *
 * @package GeniBase
 * @subpackage RestApi
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class RestApiListener implements EventSubscriberInterface
{

    protected $app;

    protected $serializer;

    public function __construct(Application $app, SerializerInterface $serializer)
    {
        $this->app = $app;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\EventDispatcher\EventSubscriberInterface::getSubscribedEvents()
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array( 'onKernelRequest', Application::EARLY_EVENT ),
            KernelEvents::VIEW => array( 'onKernelView', -10 ),
        );
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $this->app['rest_api.options.init']();
        $options = $this->app['rest_api.options'];

        $serializers = $options['serializers'];
        if (empty($serializers)) {
            return;
        }

        $path = $request->getPathInfo();
        if ($options['endpoint'] !== substr($path, 0, strlen($options['endpoint']))) {
            // Not the REST API request
            return;
        }

        // Detect response format by extension
        $requestUri = $request->getRequestUri();
        $query = '';
        if ($pos = strpos($requestUri, '?')) {
            $query = substr($requestUri, $pos);
            $requestUri = substr($requestUri, 0, $pos);
        }
        if ('/' !== substr($requestUri, -1)) {
            $requestUri = pathinfo($requestUri);
            if (! empty($requestUri['extension']) && in_array($requestUri['extension'], array_keys($serializers))) {
                $this->app['rest_api.response_format'] = $requestUri['extension'];
            }
            $requestUri = $requestUri['dirname'] . '/' . $requestUri['filename'] . $query;

            // Replace request for proper routing
            if ($request->getRequestUri() !== $requestUri) {
                $request->server->set('REQUEST_URI', $requestUri);
                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all(),
                    $request->getContent()
                );
            }
        }

        $type = $request->headers->get('Content-Type');
        if (!in_array($format = $request->getFormat($type), array_keys($serializers))) {
            return;
        }

        if ($this->serializer->supportsDecoding($format)) {
            $content = $this->serializer->deserialize($request->getContent(), $type, $format);
            if (!is_array($content) || empty($content)
                || (array_keys($content) === range(0, count($content) - 1))
            ) {
                // $content is something else than JSON array…
                $content = array( 'content' => $content );
            }
            $request->request->replace($content);
        } elseif ('json' === $format) {
            $content = json_decode($request->getContent(), true);
            $request->request->replace(is_array($content) ? $content : array());
        }
    }

    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (! is_array($result) && ! is_object($result)) {
            return;
        }

        $this->app['rest_api.options.init']();
        $options = $this->app['rest_api.options'];

        $pretty_print = $options['pretty_print'] || $request->query->get('pretty');
        $jsonp = $request->query->get('callback') ?: $request->query->get('jsonp');

        $serializers = $options['serializers'];
        if (empty($serializers)) {
            return;
        }

        // If $options['accepted_classes'] is set, serialize only described classes.
        $accepted_classes = $options['accepted_classes'];
        if (is_object($result) && ! empty($accepted_classes)) {
            $class = get_class($result);
            if (! is_array($accepted_classes)) {
                try {
                    // Try to resolve callable and check class through it
                    if (! call_user_func(
                        $app['callback_resolver']->convertCallback($accepted_classes),
                        $class
                    )) {
                        return;
                    }
                    $accepted_classes = array( $class );
                } catch (\InvalidArgumentException $ex) {
                    // No callable detected. Use $accepted_classes as single class name
                    $accepted_classes = array( $accepted_classes );
                }
            }
            if (! in_array($class, $accepted_classes)) {
                return;
            }
        }

        $context = array(
            'content' => $result,
            'pretty_print' => $pretty_print,
            'jsonp_callback' => $jsonp,
        );

        if (! empty($this->app['rest_api.response_format'])
            && in_array($format = $this->app['rest_api.response_format'], array_keys($serializers))
        ) {
            // We have predefined response format. Try to serialize
            $type = $request->getMimeType($format);
            $context['content-type'] = $type;
            if ($this->serializer->supportsEncoding($format, $context)) {
                $event->setResponse(new Response(
                    $this->serializer->serialize($result, $format, $context),
                    200,
                    array( 'Content-Type' => $type, )
                ));

                return;
            }
        }

        // Detect acceptable by client format
        $default = array_keys($serializers)[0];
        $accepted = $request->getAcceptableContentTypes() ?: array(
            $request->getMimeType($default)
        );

        // If Accept header is blank, then */* is the only option available.
        // Change it to the current Content-Type to attempt returning the format received
        if (count($accepted) === 1 && $accepted[0] === '*/*') {
            $accepted[0] = $request->headers->get('Content-Type');
        }

        foreach ($accepted as $type) {
            $format = $request->getFormat($type);
            $context['content-type'] = $type;
            if ($this->serializer->supportsEncoding($format, $context)) {
                $event->setResponse(new Response(
                    $this->serializer->serialize($result, $format, $context),
                    200,
                    array( 'Content-Type' => $type, )
                ));

                return;
            }
        }

        // HTTP/1.1 recommends returning some data over giving a 406 error,
        // even if that data is not supported by the Accept header.
        $type = $request->getMimeType($default);
        $context['content-type'] = $type;
        if (('HTTP/1.1' === $request->server->get('SERVER_PROTOCOL'))
            && $this->serializer->supportsEncoding($default, $context)
        ) {
            $event->setResponse(new Response(
                $this->serializer->serialize($result, $default, $context),
                200,
                array( 'Content-Type' => $type, )
            ));

            return;
        }

        $event->setResponse(new Response('Not Acceptable', 406, array(
            'Content-Type' => 'text/plain'
        )));
    }
}
