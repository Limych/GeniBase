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
namespace App;

use App\Controller\AgentsController;
use App\Controller\EventsController;
use App\Controller\PersonsController;
use App\Controller\PlacesController;
use App\Controller\SourcesController;
use App\Controller\Importer\PlacesImporter;
use App\Controller\Importer\SvrtImporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

/** @var \Silex\Application $app */

// === Register controllers ===================================================

$app['svrt.importer'] = function () use ($app) {
    return new SvrtImporter($app);
};
$app['places.importer'] = function () use ($app) {
    return new PlacesImporter($app);
};
//
$app['places.controller'] = function () use ($app) {
    return new PlacesController();
};
$app['sources.controller'] = function () {
    return new SourcesController();
};
$app['persons.controller'] = function () use ($app) {
    return new PersonsController();
};
$app['events.controller'] = function () use ($app) {
    return new EventsController();
};
$app['agents.controller'] = function () use ($app) {
    return new AgentsController();
};

// Register routes
// $routesLoader = new RoutesLoader($app);
// $routesLoader->bindApiRoutesToControllers();

$app->error(
    function (\Exception $e, Request $request, $code) use ($app) {
        if ($app['debug']) {
            return;
        }

        $msg = $e->getMessage();

        if ($app['rest.mode']) {
            // REST error
            $app['monolog']->addError($msg);
            $app['monolog']->addError($e->getTraceAsString());

            return array(
            'statusCode'    => $code,
            'message'       => $msg
            );
        } else {
            // Common error

            // 404.html, or 40x.html, or 4xx.html, or default.html
            $templates = array(
            'errors/'.$code.'.html.twig',
            'errors/'.substr($code, 0, 2).'x.html.twig',
            'errors/'.substr($code, 0, 1).'xx.html.twig',
            'errors/default.html.twig',
            );

            return new Response(
                $app['twig']->resolveTemplate($templates)
                ->render(array('status_code' => $code, 'status_text' => $msg)),
                $code
            );
        }
    }
);

// Register routes
$app->get(
    '/',
    function () use ($app) {
        if (! defined('DEBUG')) {
            return $app->redirect($app['url_generator']->generate('places-root'));

        }
        return $app['twig']->render('index.html.twig', array());
    }
)
->bind('homepage');

$app->get('/import/places', "places.importer:import");
$app->get('/import/places/geo_update', "places.importer:updatePlaceGeoCoordinates");
$app->get('/import/svrt', "svrt.importer:import");
//
PlacesController::bindRoutes($app, '/places');
SourcesController::bindRoutes($app, '/sources');
PersonsController::bindRoutes($app, '/persons');
EventsController::bindRoutes($app, '/events');
AgentsController::bindRoutes($app, '/agents');
//
// TODO: For testing only. Remove me ↓↓↓
if (defined('DEBUG')) {
    $app->get('/test', 'App\\Controller\\TestController::test');
}

// Register API routes
$api = $app["controllers_factory"];
//
$api->get(
    '/',
    function () use ($app) {
        $controllers = explode(' ', 'persons sources places events agents');

        foreach ($controllers as $c) {
            if (! isset($stat)) {
                $stat = $app["{$c}.controller"]->statistic($app);
            } else {
                $stat->embed($app["{$c}.controller"]->statistic($app));
            }
        }

        return $stat;
    }
)
->bind('api_statistic');
//
PlacesController::bindApiRoutes($api, '/places');
SourcesController::bindApiRoutes($api, '/sources');
PersonsController::bindApiRoutes($api, '/persons');
EventsController::bindApiRoutes($api, '/events');
AgentsController::bindApiRoutes($api, '/agents');
//
$app->mount($app["api.endpoint"].'/'.$app["api.version"], $api);
