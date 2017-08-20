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

use Silex\Application;

/** @var Application $app */

// === Register providers =====================================================

// Logging
$app->register(new \Silex\Provider\MonologServiceProvider([
    'monolog.level' => ($dev_mode ? \Monolog\Logger::DEBUG : \Monolog\Logger::ERROR),
]));
// Delete old logs
@unlink(dirname($app['monolog.logfile']) . '/' . \Carbon\Carbon::now()->subWeeks(7)->format('Y-m-d') . '.log');

if ($dev_mode) {
    $app->register(new \WhoopsSilex\WhoopsServiceProvider());
}

// Register Twig templates
$keys = array_filter($app->keys(), function ($key) use ($app) {
    return preg_match('/^twig./', $key) && ! is_callable($app[$key]);
});
$cfg = [];
foreach ($keys as $key) {
    $cfg[$key] = $app[$key];
}
$app->register(new \Silex\Provider\TwigServiceProvider(), $cfg);
$app->extend('twig', function (Twig_Environment $twig, Application $app) {
    $twig->addGlobal('min', !empty($app['debug']) ? '' : '.min');
    $twig->addGlobal('google_api_key', !empty($app['google_api_key']) ? $app['google_api_key'] : '');

    return $twig;
});

// Register security providers
include BASE_DIR.'/app/security_providers.php';

if ($dev_mode) {
    $app->register(new \Silex\Provider\WebProfilerServiceProvider());
}

$app->register(new \Euskadi31\Silex\Provider\CorsServiceProvider());
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
$app->register(new \Silex\Provider\HttpCacheServiceProvider());
$app->register(new \Silex\Provider\AssetServiceProvider());
$app->register(new \Silex\Provider\LocaleServiceProvider());
$app->register(new \Silex\Provider\TranslationServiceProvider());
$app->register(new \Silex\Provider\HttpFragmentServiceProvider());
$app->register(new \Silex\Provider\RoutingServiceProvider());
$app->register(new \Silex\Provider\FormServiceProvider());
$app->register(new \Silex\Provider\SessionServiceProvider());

$app->register(new \App\Provider\ResponsibleServiceProvider());
$app->register(new \App\Provider\PlaceMapProvider());
