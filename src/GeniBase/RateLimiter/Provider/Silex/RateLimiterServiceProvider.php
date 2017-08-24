<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @subpackage RateLimiter
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2017 Andrey Khrolenok
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
namespace GeniBase\RateLimiter\Provider\Silex;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\Common\Cache\FilesystemCache;
use GeniBase\RateLimiter\Storage\DoctrineCacheStorage;
use GeniBase\RateLimiter\RateLimiter;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 *
 * @see http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#rate-limiting
 *
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class RateLimiterServiceProvider implements ServiceProviderInterface
{

    const CHECK_LIMIT_PRIORITY = 256;

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     *
     * @throws \LogicException if SerializerServiceProvider is not registered.
     */
    public function register(Container $app)
    {
        /** @var \Silex\Application $app */

        /**
         * Default options
         */
        if (! isset($app['rate_limiter.hits_limit'])) {
            $app['rate_limiter.hits_limit'] = 200;
        }
        if (! isset($app['rate_limiter.time_limit'])) {
            $app['rate_limiter.time_limit'] = 3600; // Seconds
        }

        /**
         * Storage of rate limits usage
         */
        $app['rate_limiter.storage'] = function(Application $app) {
            if (empty($app['rate_limiter.cache_dir'])) {
                throw new \InvalidArgumentException("Please define 'rate_limiter.cache_dir' value.");
            }
            $cache = new FilesystemCache($app['rate_limiter.cache_dir']);
            return new DoctrineCacheStorage($cache);
        };

        /**
         * Rate limiter
         */
        $app['rate_limiter'] = function(Application $app) {
            return new RateLimiter(
                $app['rate_limiter.storage'],
                $app['rate_limiter.hits_limit'],
                $app['rate_limiter.time_limit']
            );
        };

        /**
         * Check rate limits
         */
        $app->before(function(Request $request) use ($app) {
            $userId = RateLimiter::getRealUserIp();
            $hitsLimit = $app['rate_limiter.hits_limit'];
            $timeLimit = $app['rate_limiter.time_limit'];

            if (isset($app['security.token_storage'])) {
                $token = $app['security.token_storage']->getToken();
                if (null !== $token) {
                    /** @var \Symfony\Component\Security\Core\User\User $user */
                    $user = $token->getUser();
                    $userId = $user->getUsername();
                }
            }

            if (isset($app['rate_limiter.user_limit'])) {
                list($hitsLimit, $timeLimit) = $app['rate_limiter.user_limit']($userId, $user);
            }

            $hitsLeft = $app['rate_limiter']->hit($userId, $hitsLimit, $timeLimit);
            if (false === $hitsLeft) {
                $app->abort(429, 'Too Many Requests', array(
                    'Retry-After' => $timeLimit,
                ));
            };

            $app['rate_limiter.user_hits_left'] = $hitsLeft;
            $app['rate_limiter.user_hits_limit'] = $hitsLimit;
            $app['rate_limiter.user_time_limit'] = $timeLimit;
            $app['rate_limiter.user_time_reset'] = round($timeLimit * $hitsLeft / $hitsLimit);
        }, self::CHECK_LIMIT_PRIORITY);

        /**
         * Add rate limits information headers to the Response
         */
        $app->after(function(Request $request, Response $response) use ($app) {
            $response->headers->add(array(
                'X-Rate-Limit-Limit' => $app['rate_limiter.user_hits_limit'],
                'X-Rate-Limit-Remaining' => $app['rate_limiter.user_hits_left'],
                'X-Rate-Limit-Reset' => $app['rate_limiter.user_time_reset'],
            ));
        });
    }
}
