<?php
/**
 * GeniBase
 *
 * Copyright (c) 2017 by Andrey Khrolenok <andrey@khrolenok.ru>
 *
 * This file is part of some open source application.
 *
 * Some open source application is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * Some open source application is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license     GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 * @copyright   Copyright (c) 2017 by Andrey Khrolenok <andrey@khrolenok.ru>
 *
 * @package     GeniBase
 */

namespace App;

use Gedcomx\Gedcomx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (! defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}

require_once BASE_DIR.'/vendor/autoload.php';

// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
$dev_mode = $allow_dev_mode && ! isset($_REQUEST['nodev'])
            && ! isset($_SERVER['HTTP_CLIENT_IP'])
            && ! isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1'))
            && file_exists( BASE_DIR.'/app/configs/.allow_dev_mode');

if (! $dev_mode) {
    ini_set('display_errors', 0);
}

// Initialize Silex framework
$app = new \Silex\Application();

// Twig templates
$app->register(new \Silex\Provider\TwigServiceProvider());
$app['twig'] = $app->extend('twig', function ($twig, $app) {
    // add custom globals, filters, tags, ...
    $twig->addGlobal('min', $app['debug'] ? '' : '.min');

    return $twig;
});

// Load configs
require BASE_DIR.'/app/configs/prod.php';
if ($dev_mode) {
    require BASE_DIR.'/app/configs/dev.php';
}

// Register providers
require BASE_DIR.'/app/providers.php';

// Register services
$app['gb.db']  = function() use ($app) { return new \GeniBase\DBase\DBaseService($app); };

if ($dev_mode) {
    // Register SQL logger
    $logger = new \Doctrine\DBAL\Logging\DebugStack();
    $app['db.config']->setSQLLogger($logger);
    $app->error(function(\Exception $e, $code) use ($app, $logger) {
        if ( $e instanceof \PDOException and count($logger->queries) ) {
            // We want to log the query as an ERROR for PDO exceptions!
            $query = array_pop($logger->queries);
            $app['monolog']->err($query['sql'], array(
                'params' => $query['params'],
                'types' => $query['types']
            ));
        }
    });
    $app->after(function(Request $request, Response $response) use ($app, $logger) {
        // Log all queries as DEBUG.
        foreach ( $logger->queries as $query ) {
            $app['monolog']->debug('SQL: ' . $query['sql'], array(
                'params' => $query['params'],
                'types' => $query['types']
            ));
        }
    });
}

// Register controlers
require BASE_DIR.'/app/controllers.php';

// Accepting JSON and Gedcomx (JSON and XML)
$app['rest.mode'] = $app['gedcomx.mode'] = false;
$app->before(function (Request $request) use ($app) {
    // Add Gedcomx formats
    $tmp = $request->getMimeTypes('json');
    $tmp[] = Gedcomx::JSON_MEDIA_TYPE;
    $request->setFormat('json', $tmp);
    //
    $tmp = $request->getMimeTypes('xml');
    $tmp[] = Gedcomx::XML_MEDIA_TYPE;
    $request->setFormat('xml', $tmp);

    $content_type = $request->headers->get('Content-Type');
    $app['rest.mode'] = ('json' === $request->getFormat($content_type));
    $app['gedcomx.mode'] = (false !== strpos($content_type, 'gedcomx'));
    if ($app['gedcomx.mode']) {
        $serializer = ($app['rest.mode']
            ? new \Gedcomx\GedcomxFile\DefaultJsonSerialization()
            : new \Gedcomx\GedcomxFile\DefaultXMLSerialization() );
        $gedcomx = $serializer->deserialize($request->getContent());
        $request->request->replace(['gedcomx' => $gedcomx]);
    } elseif ($app['rest.mode']) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

// Run framework
$app->run();
