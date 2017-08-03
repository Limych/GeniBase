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

use Silex\Provider\WebProfilerServiceProvider;
use WhoopsSilex\WhoopsServiceProvider;

// === Register providers =====================================================

// Logging
$app->register(new \Silex\Provider\MonologServiceProvider([
    'monolog.level' => ($dev_mode ? \Monolog\Logger::DEBUG : \Monolog\Logger::ERROR),
]));
// Delete old logs
@unlink(dirname($app['monolog.logfile']) . '/' . \Carbon\Carbon::now()->subWeeks(7)->format('Y-m-d') . '.log');

if ($dev_mode) {
    $app->register(new WhoopsServiceProvider);
}

// Register Twig templates
$app->register(new \Silex\Provider\TwigServiceProvider());
$app['twig'] = $app->extend(
    'twig',
    function ($twig, $app) {
        // add custom globals, filters, tags, ...
        $twig->addGlobal('min', !empty($app['debug']) ? '' : '.min');
        $twig->addGlobal('google_api_key', !empty($app['google_api_key']) ? $app['google_api_key'] : '');

        return $twig;
    }
);

if ($dev_mode) {
    $app->register(new WebProfilerServiceProvider);
}

$app->register(new \Euskadi31\Silex\Provider\CorsServiceProvider);
$app->register(new \Silex\Provider\ServiceControllerServiceProvider);
$app->register(new \Silex\Provider\HttpCacheServiceProvider());
$app->register(new \Silex\Provider\AssetServiceProvider());
$app->register(new \Silex\Provider\LocaleServiceProvider());
$app->register(new \Silex\Provider\TranslationServiceProvider());
$app->register(new \Silex\Provider\HttpFragmentServiceProvider());
$app->register(new \Silex\Provider\RoutingServiceProvider());

$app->register(new Provider\ResponsibleServiceProvider());
$app->register(new Provider\PlaceMapProvider());
