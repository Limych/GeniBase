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

use App\Handler\AuthenticationHandler;
use Silex\Application;

$app->register(new \Silex\Provider\CsrfServiceProvider());

$app->register(new \Gigablah\Silex\OAuth\OAuthServiceProvider(), array(
    'oauth.services' => array(
        'Facebook' => array(
            'key' => $app['facebook_api.key'],
            'secret' => $app['facebook_api.secret'],
            'scope' => array(
                'public_profile',
                'email',
                'user_birthday',
            ),
            'user_endpoint' => 'https://graph.facebook.com/me',
        ),
        'Google' => array(
            'key' => $app['google_api.key'],
            'secret' => $app['google_api.secret'],
            'scope' => array(
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ),
            'user_endpoint' => 'https://www.googleapis.com/oauth2/v1/userinfo',
        ),
        // TODO: GitHub
//         'GitHub' => array(
//             'key' => GITHUB_API_KEY,
//             'secret' => GITHUB_API_SECRET,
//             'scope' => array('user:email'),
//             'user_endpoint' => 'https://api.github.com/user',
//         )
        // TODO: Yandex
        // TODO: vKontakte
        // TODO: Mail.ru
        // TODO: FamilySearch
        // TODO: Geni
        // TODO: MyHeritage
        // TODO: Dropbox
//         'Dropbox' => array(
//             'key' => DROPBOX_API_KEY,
//             'secret' => DROPBOX_API_SECRET,
//             'scope' => array(),
//             'user_endpoint' => 'https://api.dropbox.com/1/account/info'
//         ),
    )
));

$app->register(new \Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'oauth' => array(
                'login_path' => '/login/{service}',
                'callback_path' => '/login/{service}/callback',
                'check_path' => '/login/{service}/check',
//                 'failure_path' => '/login',
                'with_csrf' => true,
                'use_referer' => true,
                'failure_handler' => new AuthenticationHandler(),
            ),
            'logout' => array(
                'logout_path' => '/logout',
                'target_url' => '/',
                'with_csrf' => true,
                'success_handler' => new AuthenticationHandler(),
            ),
            'switch_user' => array('parameter' => '_switch_user', 'role' => 'ROLE_ALLOWED_TO_SWITCH'),
            // FIXME: OAuthInMemoryUserProvider returns a StubUser and is intended only for testing.
            // Replace this with your own UserProvider and User class.
            'users' => new \Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider(),
        ),
    ),
    'security.role_hierarchy' => array(
        'ROLE_SUPER_ADMIN' => array(
            'ROLE_ADMIN',
            'ROLE_ALLOWED_TO_SWITCH',
        ),
        'ROLE_ADMIN' => 'ROLE_USER',
    ),
    'security.access_rules' => array(
        array('^/login/', 'ROLE_USER'),
    ),
));

$app->extend('twig', function (Twig_Environment $twig, Application $app) {
    $twig->addFunction(new \Twig_SimpleFunction(
        'logout_path',
        function () use ($app) {
            $parameters = array();
            if (isset($app['oauth.csrf_token'])) {
                $parameters['_csrf_token'] = $app['oauth.csrf_token']('logout');
            }
            return $app['url_generator']->generate('logout', $parameters);
        }
    ));
    $twig->addFunction(new \Twig_SimpleFunction(
        'login_dialog',
        function ($id = 'dlgLogin') use ($app) {
            return $app['twig']->render('dashboard/dialog_login.html.twig', array(
                'login_id' => $id,
                'login_paths' => $app['oauth.login_paths'],
            ));
        },
        array('is_safe' => array('all'))
    ));

    return $twig;
});

$app->register(new \OAuthUser\UserServiceProvider());
