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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../vendor/autoload.php';



// === Initialize framework ====================================================

// Create the Application instance
$app = new \Silex\Application();

// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
$dev_mode = ! isset($_REQUEST['nodev'])
        && ! isset($_SERVER['HTTP_CLIENT_IP'])
        && ! isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        && in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1'))
        && file_exists(__DIR__ . '/_configs/.allow_dev_mode');

// Load configs
$root_dir = dirname(__DIR__);
include __DIR__ . '/_configs/prod.php';
if ($dev_mode) {
    include __DIR__ . '/_configs/dev.php';
}
unset($dev_mode);   // Use below $app['debug'] instead!

// Initialize error reporting
if ($app['debug']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Delete expired log files
if (! empty($app['monolog.logfile.expired']) && file_exists($app['monolog.logfile.expired'])) {
    @unlink($app['monolog.logfile.expired']);
}



// === Register service providers =============================================

{ // Patch for using Twig configs
    $keys = array_filter($app->keys(), function ($key) use ($app) {
        return preg_match('/^twig./', $key) && ! is_callable($app[$key]);
    });
    $cfg = array();
    foreach ($keys as $key) {
        $cfg[$key] = $app[$key];
    }
}
$app->register(new \Silex\Provider\TwigServiceProvider(), $cfg);
if ($app['debug']) {
    $app->register(new \WhoopsSilex\WhoopsServiceProvider());
    $app->register(new \Silex\Provider\WebProfilerServiceProvider());
    $app->register(new \Silex\Provider\VarDumperServiceProvider());
}
$app->register(new \Silex\Provider\MonologServiceProvider());
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
$app->register(new \Silex\Provider\AssetServiceProvider());
$app->register(new \Silex\Provider\HttpFragmentServiceProvider());
$app->register(new \Silex\Provider\SerializerServiceProvider());
$app->register(new \Silex\Provider\DoctrineServiceProvider());
if ($app['debug']) {
    // SQL-queries logger
    $logger = new \Doctrine\DBAL\Logging\DebugStack();
    $app['db.config']->setSQLLogger($logger);
    $app->error(
        function (\Exception $ex, $code) use ($app, $logger) {
            if ($ex instanceof \PDOException and count($logger->queries)) {
                // We want to log the query as an ERROR for PDO exceptions!
                $query = array_pop($logger->queries);
                $app['monolog']->err(
                    $query['sql'],
                    array(
                        'params' => $query['params'],
                        'types' => $query['types']
                    )
                );
            }
        }
    );
    $app->after(
        function (Request $request, Response $response) use ($app, $logger) {
            // Log all queries as DEBUG.
            foreach ($logger->queries as $query) {
                $app['monolog']->debug(
                    $query['sql'],
                    array(
                        'params' => $query['params'],
                        'types' => $query['types']
                    )
                );
            }
        }
    );
}

$app->register(new \GeniBase\Provider\Silex\GeniBaseServiceProvider());

$app->register(new \GeniBase\Provider\Silex\PlaceMapProvider());
$app->register(new \GeniBase\Provider\Silex\RestApi\RestApiServiceProvider());
$app->register(new \GeniBase\Provider\Silex\ContentCompressor\ContentCompressorServiceProvider());

$app->register(new \GeniBase\Provider\Silex\TestServiceProvider()); // FIXME: Remove TestServiceProvider from, production
$app->register(new \GeniBase\Provider\Silex\Gedcomx\AgentsServiceProvider());
$app->register(new \GeniBase\Provider\Silex\Gedcomx\PlaceDescriptionServiceProvider());
$app->register(new \GeniBase\Provider\Silex\Gedcomx\SourceDescriptionServiceProvider());
$app->register(new \GeniBase\Provider\Silex\Gedcomx\EventServiceProvider());
$app->register(new \GeniBase\Provider\Silex\Gedcomx\PersonServiceProvider());



// === Register controllers ===================================================

// Errors controller

$app->error(
    function (\Exception $ex, Request $request, $code) use ($app) {
        if ($app['debug']) {
            return;
        }

        // Check for REST API error
        $response = $app['rest_api.error.listener']($ex, $request, $code);
        if ($response) {
            return $response;
        }

        // 404.html, or 40x.html, or 4xx.html, or default.html
        $templates = array(
            'errors/'.$code.'.html.twig',
            'errors/'.substr($code, 0, 2).'x.html.twig',
            'errors/'.substr($code, 0, 1).'xx.html.twig',
            'errors/default.html.twig',
        );

        return new Response(
            $app['twig']->resolveTemplate($templates)->render(array(
                'status_code' => $code,
                'status_text' => $ex->getMessage()
            )),
            $code
        );
    }
);

// Configs for REST API provider
$app['rest_api.options'] = array(
    'serializers' => array(
        'json' => \GeniBase\Provider\Silex\Encoder\GeniBaseJsonEncoder::class,
        'xml' => \GeniBase\Provider\Silex\Encoder\GeniBaseXmlEncoder::class,
    ),
    'controllers' => array(
        'v1' => array(
            '/test' => 'test.controller:test',  // FIXME: Remove me from, production

            '/' => 'statistic.controller',
            '/agents' => array( 'agent.controller:mountApiRoutes' ),
            '/places' => array( 'place_description.controller:mountApiRoutes' ),
            '/sources' => array( 'source_description.controller:mountApiRoutes' ),
            '/events' => array( 'event.controller:mountApiRoutes' ),
            '/persons' => array( 'person.controller:mountApiRoutes' ),
        ),
    ),
);

$app['place_description.controller']->mountRoutes($app, '/places');

// Register routes
// FIXME: Remove this block from production version
$app->get(
    '/',
    function () use ($app) {
        if (! $app['debug']) {
            return $app->redirect($app['url_generator']->generate('places-root'));
        }
        return $app['twig']->render('index.html.twig', array());
    }
)
->bind('homepage');



// === Run framework ==========================================================

$app->run();
