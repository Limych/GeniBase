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
 * @license   GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 * @copyright Copyright (c) 2017 by Andrey Khrolenok <andrey@khrolenok.ru>
 *
 * @package GeniBase
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
        $twig->addGlobal('min', $app['debug'] ? '' : '.min');

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

$app->register(new Provider\ResponsibleServiceProvider());
