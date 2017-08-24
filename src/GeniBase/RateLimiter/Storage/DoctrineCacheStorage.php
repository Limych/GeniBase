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
namespace GeniBase\RateLimiter\Storage;

use Doctrine\Common\Cache\Cache;
use GeniBase\RateLimiter\RateLimiterStorageInterface;

/**
 *
 *
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class DoctrineCacheStorage implements RateLimiterStorageInterface
{

    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    protected $cache;

    /**
     * Class constructor
     *
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \GeniBase\RateLimiter\RateLimiterStorageInterface::set()
     */
    public function set($id, $amount)
    {
        return $this->cache->save($id, $amount);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \GeniBase\RateLimiter\RateLimiterStorageInterface::get()
     */
    public function get($id, $default = null)
    {
        return $this->cache->fetch($id) ?: $default;
    }
}
