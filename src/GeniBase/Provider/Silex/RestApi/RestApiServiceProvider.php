<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @subpackage RestApi
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
namespace GeniBase\Provider\Silex\RestApi;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ServiceControllerResolver;
use Silex\Api\BootableProviderInterface;
use Silex\Api\ControllerProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 *
 * @package GeniBase
 * @subpackage RestApi
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class RestApiServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface, ControllerProviderInterface, BootableProviderInterface
{

    protected $options;

    public function __construct($options = null)
    {
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     *
     * @throws \LogicException if SerializerServiceProvider is not registered.
     */
    public function register(Container $app)
    {
        if (empty($app['serializer']) || (! $app['serializer'] instanceof Serializer)) {
            throw new \LogicException('You MUST enable the SerializerServiceProvider to be able to use RestApiServiceProvider.');
        }

        $app['rest_api.rest_mode'] = false;

        /**
         * Default options
         */
        $app['rest_api.options.default'] = array(
            'endpoint' => '/api',
            'require_versioning' => true,
            'controllers' => array(),
            'serializers' => array(
                // First item is always default serializer if client Accept header is blank (only for HTTP/1.1)
                'json' => null, // Use default framework JSON serializer
                'xml' => null,  // Use default framework XML serializer
            ),
            'accepted_classes' => null, // If set, serialize only described classes. You can set it to callable or service name to check classes on the fly
            'pretty_print' => false,

            // TODO: SSL (http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#ssl)

            // TODO: Rate limiting (http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#rate-limiting)
            // https://github.com/CrazyAwesomeCompany/poc-ratelimit/tree/master/src/CAC/Component/RateLimit
        );

        /**
         * Copy options from constructor if it was set
         */
        if (! empty($this->options)) {
            $app['rest_api.options'] = $this->options;
        }

        /**
         * Initialize $app['rest_api.options']
         */
        $app['rest_api.options.init'] = $app->protect(function() use ($app) {
            $options = $app['rest_api.options.default'];
            if (! empty($app['rest_api.options'])) {
                // Merge default and configured options
                $options = array_replace_recursive($options, $app['rest_api.options']);
            }
            $app['rest_api.options'] = $options;
        });

        /**
         * The REST API serialization listener
         */
        $app['rest_api.listener'] = function ($app) {
            return new RestApiListener($app, $app['serializer']);
        };

        /**
         * Make the standart REST API error response
         *
         * @see http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#errors
         */
        $app['rest_api.error.response'] = $app->protect(function(Request $request, $http_status, $error_message,
            $error_code = null, $error_description = null) use ($app)
        {
            return $this->makeErrorResponse(
                $app,
                $request,
                $http_status,
                $error_message,
                $error_code,
                $error_description
            );
        });

        /**
         * The default REST API error listener
         */
        $app['rest_api.error.listener'] = $app->protect(
            function (\Exception $ex, Request $request, $code) use ($app) {
                if (! $app['debug'] && $app['rest_api.rest_mode']) {
                    return $app['rest_api.error.response'](
                        $request,
                        $code,
                        $ex->getMessage(),
                        $ex->getCode() ?: $code
                    );
                }
            }
        );

        /**
         * Register the default REST API error listener in the framework
         */
        $app['rest_api.error.listener.register'] = $app->protect(function() use ($app) {
            $app->error($app['rest_api.error.listener']);
        });
    }

    /**
     * Subscribe for kernel events.
     *
     * @param Container $app
     * @param EventDispatcherInterface $dispatcher
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['rest_api.listener']);
    }

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     * @return ControllerCollection A ControllerCollection instance
     *
     * @throws \LogicException if ServiceControllerServiceProvider is not registered.
     * @throws \InvalidArgumentException if versioning is required and controllers have not had versions.
     */
    public function connect(Application $app)
    {
        if (! $app['resolver'] instanceof ServiceControllerResolver) {
            // using RuntimeException crashes PHP?!
            throw new \LogicException('You MUST enable the ServiceControllerServiceProvider to be able to use these routes.');
        }

        /** @var ControllerCollection $service_controllers */
        $service_controllers = $app['controllers_factory'];

        $app['rest_api.options.init']();
        $options = $app['rest_api.options'];

        $api_controllers = $options['controllers'];

        // Define empty controller version if controller versions not defined.
        $tmp = reset($api_controllers);
        if (! is_array($tmp) || empty(array_keys($tmp)[0])) {
            if ($options['require_versioning']) {
                throw new \InvalidArgumentException('Controllers without versions are not allowed.');
            }
            $api_controllers = array( '' => $api_controllers );
        }

        foreach ($api_controllers as $ver => $controllers_list) {
            $prefix = empty($ver) ? '' : "/$ver";
            foreach ($controllers_list as $pattern => $controller) {
                $pattern = $prefix . $pattern;
                if (! is_array($controller)) {
                    // Simple GET controller
                    $service_controllers->get($pattern, $controller);
                } else {
                    try {
                        // Try to resolve callable
                        $callback = $app['callback_resolver']->convertCallback($controller[0]);

                        // Flexible controller setup through callback function
                        $controller[0] = $pattern;   // Second parameter
                        array_unshift($controller, $service_controllers);   // First parameter
                        call_user_func_array($callback, $controller);
                    } catch (\InvalidArgumentException $ex) {
                        // First argument is not callable. Use it as controller with defined method(s)
                        $service_controllers->method($controller[0])->match($pattern, $controller);
                    }
                }
            }
        }

        return $service_controllers;
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app['rest_api.options.init']();
        $options = $app['rest_api.options'];

        $app->mount($options['endpoint'], $this->connect($app));
    }

    /**
     * Make Response with REST error message
     *
     * @param Application $app
     * @param Request $request
     * @param integer $http_status
     * @param string $error_message
     * @param null|integer $error_code
     * @param null|string|array[] $error_description
     * @return Response
     */
    protected function makeErrorResponse(Application $app, Request $request, $http_status,
        $error_message, $error_code = null, $error_description = null)
    {
        if (null === $error_code) {
            $error_code = $http_status;
        }
        $error = array(
            'code' => intval($error_code),
            'message' => strval($error_message),
        );
        if (! empty($error_description)) {
            if (is_array($error_description)) {
                $error['errors'] = array();
                foreach ($error_description as $err) {
                    $row = array(
                        'code' => intval($err['code']) ?: -1,
                        'message' => strval($err['message']) ?: '',
                    );
                    if ($err['field']) {
                        $row['field'] = strval($err['field']);
                    }
                    if ($err['description']) {
                        $row['description'] = strval($err['description']);
                    }

                    $error['errors'][] = $row;
                }
            } else {
                $error['description'] = strval($error_description);
            }
        }

        $event = new GetResponseForControllerResultEvent($app, $request, Application::MASTER_REQUEST, $error);
        $app['dispatcher']->dispatch(KernelEvents::VIEW, $event);

        if ($event->hasResponse()) {
            $response = $event->getResponse();
            $response->setStatusCode(intval($http_status), strval($error_message));
            if ($response instanceof Response) {
                return $response;
            }
        }

        return new Response(json_encode($error), $http_status);
    }
}
