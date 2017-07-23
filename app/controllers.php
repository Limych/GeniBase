<?php
namespace App;

use App\Controller\EventsController;
use App\Controller\PersonsController;
use App\Controller\PlacesController;
use App\Controller\PlacesImporterController;
use App\Controller\SourcesController;
use App\Controller\StatisticController;
use App\Controller\SvrtImporterController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

/** @var \Silex\Application $app */

// === Register controllers ===================================================

$app['importer.svrt.controller'] = function () use ($app) {
    return new SvrtImporterController($app);
};
$app['importer.places.controller'] = function () use ($app) {
    return new PlacesImporterController($app);
};
//
$app['statistic.controller'] = function () use ($app) {
    return new StatisticController();
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
        return $app['twig']->render('index.html.twig', array());
    }
)
->bind('homepage');

$app->get('/import/places', "importer.places.controller:import");
$app->get('/import/svrt', "importer.svrt.controller:import");
//
PlacesController::bindRoutes($app, '/places');
SourcesController::bindRoutes($app, '/sources');
PersonsController::bindRoutes($app, '/persons');
EventsController::bindRoutes($app, '/events');

// Register API routes
$api = $app["controllers_factory"];
//
$api->get(
    '/',
    function () use ($app) {
        $controllers = explode(' ', 'statistic persons sources places events');

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
//
$app->mount($app["api.endpoint"].'/'.$app["api.version"], $api);
