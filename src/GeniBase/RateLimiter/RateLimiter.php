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
namespace GeniBase\RateLimiter;

/**
 *
 *
 * @see http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api#rate-limiting
 *
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class RateLimiter
{
    /**
     * @var RateLimiterStorageInterface
     */
    protected $storage;

    protected $defaultHitsLimit;
    protected $defaultTimeLimit;

    /**
     * Class constructor
     *
     * @param RateLimiterStorageInterface $storage
     * @param integer $hitsLimit
     * @param integer $timeLimit
     */
    public function __construct(RateLimiterStorageInterface $storage, $hitsLimit = 100, $timeLimit = 3600)
    {
        $this->setStorage($storage);
        $this->defaultHitsLimit = $hitsLimit;
        $this->defaultTimeLimit = $timeLimit;
    }

    /**
     * Set the RateLimiter Storage Adapter
     *
     * @param RateLimiterStorageInterface $storage
     */
    public function setStorage(RateLimiterStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function hit($userId, $hitsLimit = null, $timeLimit = null)
    {
        $hitsLimit = $hitsLimit ?: $this->defaultHitsLimit;
        $timeLimit = $timeLimit ?: $this->defaultTimeLimit;
        $now = time();

        $currentLimit = $this->storage->get($userId, "$hitsLimit|$now");

        list($credits, $time) = explode('|', $currentLimit, 2);

        // Regenerate some hit credits
        $newCredits = $credits + round($hitsLimit * ($now - $time) / $timeLimit);

        if ($newCredits > $hitsLimit) {
            $newCredits = $hitsLimit;
        }

        if ($hasCredits = ($newCredits > 0)) {
            $newCredits--;
            $this->storage->set($userId, "$newCredits|$now");
        }

        return $hasCredits ? $newCredits : false;
    }

    /**
     * Get real user ip
     *
     * Usage sample:
     * getRealUserIp();
     * getRealUserIp('ERROR', FILTER_FLAG_NO_RES_RANGE);
     *
     * @param string  $default Default return value if no valid IP found
     * @param integer $filter_options Filter options.
     *                  Default is FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
     * @return string Real user IP
     */
    public static function getRealUserIp($default = null, $filter_options = null) {
        $filter_options = $filter_options ?: FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        $headers = explode(
            ' ',
            'HTTP_X_FORWARDED_FOR HTTP_X_FORWARDED HTTP_FORWARDED_FOR HTTP_FORWARDED HTTP_X_REAL_IP ' .
            'HTTP_CLIENT_IP HTTP_CF_CONNECTING_IP CLIENT_IP HTTP_X_PROXY_ID X_FORWARDED_FOR ' .
            'FORWARDED_FOR HTTP_FORWARDED_FOR_IP FORWARDED_FOR_IP HTTP_X_CLUSTER_CLIENT_IP ' .
            'HTTP_PROXY_CONNECTION X_FORWARDED FORWARDED ' .
            'REMOTE_ADDR'
            );

        foreach ($headers as $hdr) {
            $data = isset($_SERVER) ? @$_SERVER[$hdr] : @getenv($hdr);
            $data = preg_split('/,\s*/', $data, null, PREG_SPLIT_NO_EMPTY);
            foreach ($data as $ip) {
                if ($ip = filter_var($ip, FILTER_VALIDATE_IP, $filter_options))
                    break 2;
            }
        }

        return $ip ?: $default;
    }
}
