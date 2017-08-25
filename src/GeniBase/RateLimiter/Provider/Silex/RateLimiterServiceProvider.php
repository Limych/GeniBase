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
use Silex\Api\BootableProviderInterface;

/**
 *
 *
 * @see http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#rate-limiting
 *
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class RateLimiterServiceProvider implements ServiceProviderInterface, BootableProviderInterface
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
        if (! isset($app['rate_limiter.autocheck'])) {
            $app['rate_limiter.autocheck'] = true;
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
         * Compose full whitelist of IPs
         * from $app['rate_limiter.whitelist'] and files in $app['rate_limiter.whitelist_dir']
         */
        $app['rate_limiter.whitelist.compose'] = $app->protect(function() use ($app) {
            $whitelist = isset($app['rate_limiter.whitelist']) ? $app['rate_limiter.whitelist'] : array();
            if (isset($app['rate_limiter.whitelist_dir'])) {
                $dir = $app['rate_limiter.whitelist_dir'];
                if (false !== ($fnames = @scandir($dir))) {
                    foreach ($fnames as $fname) {
                        $fpath = "$dir/$fname";
                        if (! is_dir($fpath) && @is_readable($fpath)) {
                            $content = file_get_contents($fpath);
                            $content = explode("\n", $content);
                            foreach ($content as $range) {
                                $range = trim($range);
                                if (preg_match('!\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}!', $range)) {
                                    $whitelist[] = $range;
                                }
                            }
                        }
                    }
                }
            }
            return $whitelist;
        });

        /**
         * Rate limiter
         */
        $app['rate_limiter'] = function(Application $app) {
            $rl = new RateLimiter(
                $app['rate_limiter.storage'],
                $app['rate_limiter.hits_limit'],
                $app['rate_limiter.time_limit']
            );
            $whitelist = $app['rate_limiter.whitelist.compose']();
            if (! empty($whitelist)) {
                $rl->setWhitelist($whitelist);
            }
            return $rl;
        };

        /**
         * Rate limits checker
         */
        $app['rate_limiter.checker'] = $app->protect(function(Request $request) use ($app) {
            if (isset($app['rate_limiter.checked'])) {
                return;
            }
            $app['rate_limiter.checked'] = true;

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

            $timeReset = round($timeLimit * $hitsLeft / $hitsLimit);
            $app['rate_limiter.user_hits_left'] = $hitsLeft;
            $app['rate_limiter.user_hits_limit'] = $hitsLimit;
            $app['rate_limiter.user_time_limit'] = $timeLimit;
            $app['rate_limiter.user_time_reset'] = $timeReset;

            $app->after(function(Request $request, Response $response) use ($app, $hitsLeft, $hitsLimit, $timeReset) {
                $response->headers->add(array(
                    'X-Rate-Limit-Limit' => $hitsLimit,
                    'X-Rate-Limit-Remaining' => $hitsLeft,
                    'X-Rate-Limit-Reset' => $timeReset,
                ));
            });
        });

        /**
         * Register rate limits checker
         */
        $app['rate_limiter.checker.register'] = $app->protect(function() use ($app) {
            if (! isset($app['rate_limiter.checker.registered'])) {
                $app->before($app['rate_limiter.checker'], self::CHECK_LIMIT_PRIORITY);
                $app['rate_limiter.checker.registered'] = true;
            }
        });
    }

    /**
     * {@inheritDoc}
     * @see \Silex\Api\BootableProviderInterface::boot()
     */
    public function boot(Application $app)
    {
        if ($app['rate_limiter.autocheck']) {
            $app['rate_limiter.checker.register']();
        }
    }
}
