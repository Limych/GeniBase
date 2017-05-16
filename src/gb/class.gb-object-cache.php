<?php

/**
 * Object Cache API
 *
 * @package GeniBase
 * @subpackage Cache
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

/**
 * GeniBase Object Cache
 *
 * The GeniBase Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the gb-content folder. If that file exists, then this file will not be
 * included.
 *
 * @package GeniBase
 * @subpackage Cache
 * @since 2.3.0
 */
class GB_Object_Cache
{

    /**
     * Holds the cached objects
     *
     * @var array
     * @access private
     * @since 2.3.0
     */
    private static $cache = array();

    /**
     * The amount of times the cache data was already stored in the cache.
     *
     * @since 2.3.0
     * @access private
     * @var int
     */
    private static $cache_hits = 0;

    /**
     * Amount of times the cache did not have the request in cache
     *
     * @var int
     * @access public
     * @since 2.3.0
     */
    public static $cache_misses = 0;

    /**
     * List of global groups
     *
     * @var array
     * @access protected
     * @since 2.3.0
     */
    private static $global_groups = array();

    /**
     * Adds data to the cache if it doesn't already exist.
     *
     * @uses GB_Object_Cache::_exists Checks to see if the cache already has data.
     * @uses GB_Object_Cache::set Sets the data after the checking the cache
     *       contents existence.
     *      
     * @since 2.3.0
     *       
     * @param int|string $key
     *            What to call the contents in the cache
     * @param mixed $data
     *            The contents to store in the cache
     * @param string $group
     *            Where to group the cache contents
     * @param int $expire
     *            When to expire the cache contents
     * @return bool False if cache key and group already exist, true on success
     */
    static public function add($key, $data, $group = '', $expire = 0)
    {
        if (function_exists('gb_suspend_cache_addition') && gb_suspend_cache_addition())
            return false;
        
        if (empty($group))
            $group = '';
        
        if (GB_Object_Cache::_exists($key, $group))
            return false;
        
        return GB_Object_Cache::set($key, $data, $group, (int) $expire);
    }

    /**
     * Sets the list of global groups.
     *
     * @since 2.3.0
     *       
     * @param array $groups
     *            List of groups that are global.
     */
    static public function add_global_groups($groups)
    {
        $groups = (array) $groups;
        
        $groups = array_fill_keys($groups, true);
        GB_Object_Cache::$global_groups = array_merge(GB_Object_Cache::$global_groups, $groups);
    }

    /**
     * Decrement numeric cache item's value
     *
     * @since 2.3.0
     *       
     * @param int|string $key
     *            The cache key to increment
     * @param int $offset
     *            The amount by which to decrement the item's value. Default is 1.
     * @param string $group
     *            The group the key is in.
     * @return false|int False on failure, the item's new value on success.
     */
    static public function decr($key, $offset = 1, $group = '')
    {
        if (empty($group))
            $group = '';
        
        if (! GB_Object_Cache::_exists($key, $group))
            return false;
        
        if (! is_numeric(GB_Object_Cache::$cache[$group][$key]))
            GB_Object_Cache::$cache[$group][$key] = 0;
        
        $offset = (int) $offset;
        
        GB_Object_Cache::$cache[$group][$key] -= $offset;
        
        if (GB_Object_Cache::$cache[$group][$key] < 0)
            GB_Object_Cache::$cache[$group][$key] = 0;
        
        return GB_Object_Cache::$cache[$group][$key];
    }

    /**
     * Remove the contents of the cache key in the group
     *
     * If the cache key does not exist in the group, then nothing will happen.
     *
     * @since 2.3.0
     *       
     * @param int|string $key
     *            What the contents in the cache are called
     * @param string $group
     *            Where the cache contents are grouped
     * @param bool $deprecated
     *            Deprecated.
     *            
     * @return bool False if the contents weren't deleted and true on success
     */
    static public function delete($key, $group = '', $deprecated = false)
    {
        if (empty($group))
            $group = '';
        
        if (! GB_Object_Cache::_exists($key, $group))
            return false;
        
        unset(GB_Object_Cache::$cache[$group][$key]);
        return true;
    }

    /**
     * Clears the object cache of all data
     *
     * @since 2.3.0
     *       
     * @return bool Always returns true
     */
    static public function flush()
    {
        GB_Object_Cache::$cache = array();
        
        return true;
    }

    /**
     * Retrieves the cache contents, if it exists
     *
     * The contents will be first attempted to be retrieved by searching by the
     * key in the cache group. If the cache is hit (success) then the contents
     * are returned.
     *
     * On failure, the number of cache misses will be incremented.
     *
     * @since 2.3.0
     *       
     * @param int|string $key
     *            What the contents in the cache are called
     * @param string $group
     *            Where the cache contents are grouped
     * @param string $force
     *            Whether to force a refetch rather than relying on the local cache (default is false)
     * @return bool|mixed False on failure to retrieve contents or the cache
     *         contents on success
     */
    static public function get($key, $group = '', $force = false, &$found = null)
    {
        if (empty($group))
            $group = '';
        
        if (GB_Object_Cache::_exists($key, $group)) {
            $found = true;
            GB_Object_Cache::$cache_hits += 1;
            if (is_object(GB_Object_Cache::$cache[$group][$key]))
                return clone GB_Object_Cache::$cache[$group][$key];
            else
                return GB_Object_Cache::$cache[$group][$key];
        }
        
        $found = false;
        GB_Object_Cache::$cache_misses += 1;
        return false;
    }

    /**
     * Increment numeric cache item's value
     *
     * @since 2.3.0
     *       
     * @param int|string $key
     *            The cache key to increment
     * @param int $offset
     *            The amount by which to increment the item's value. Default is 1.
     * @param string $group
     *            The group the key is in.
     * @return false|int False on failure, the item's new value on success.
     */
    static public function incr($key, $offset = 1, $group = '')
    {
        if (empty($group))
            $group = '';
        
        if (! GB_Object_Cache::_exists($key, $group))
            return false;
        
        if (! is_numeric(GB_Object_Cache::$cache[$group][$key]))
            GB_Object_Cache::$cache[$group][$key] = 0;
        
        $offset = (int) $offset;
        
        GB_Object_Cache::$cache[$group][$key] += $offset;
        
        if (GB_Object_Cache::$cache[$group][$key] < 0)
            GB_Object_Cache::$cache[$group][$key] = 0;
        
        return GB_Object_Cache::$cache[$group][$key];
    }

    /**
     * Replace the contents in the cache, if contents already exist
     *
     * @since 2.3.0
     * @see GB_Object_Cache::set()
     *
     * @param int|string $key
     *            What to call the contents in the cache
     * @param mixed $data
     *            The contents to store in the cache
     * @param string $group
     *            Where to group the cache contents
     * @param int $expire
     *            When to expire the cache contents
     * @return bool False if not exists, true if contents were replaced
     */
    static public function replace($key, $data, $group = '', $expire = 0)
    {
        if (empty($group))
            $group = '';
        
        if (! GB_Object_Cache::_exists($key, $group))
            return false;
        
        return GB_Object_Cache::set($key, $data, $group, (int) $expire);
    }

    /**
     * Sets the data contents into the cache
     *
     * The cache contents is grouped by the $group parameter followed by the
     * $key. This allows for duplicate ids in unique groups. Therefore, naming of
     * the group should be used with care and should follow normal function
     * naming guidelines outside of core GeniBase usage.
     *
     * The $expire parameter is not used, because the cache will automatically
     * expire for each time a page is accessed and PHP finishes. The method is
     * more for cache plugins which use files.
     *
     * @since 2.3.0
     *       
     * @param int|string $key
     *            What to call the contents in the cache
     * @param mixed $data
     *            The contents to store in the cache
     * @param string $group
     *            Where to group the cache contents
     * @param int $expire
     *            Not Used
     * @return bool Always returns true
     */
    static public function set($key, $data, $group = '', $expire = 0)
    {
        if (empty($group))
            $group = '';
        
        if (is_object($data))
            $data = clone $data;
        
        GB_Object_Cache::$cache[$group][$key] = $data;
        return true;
    }

    /**
     * Echoes the stats of the caching.
     *
     * Gives the cache hits, and cache misses. Also prints every cached group,
     * key and the data.
     *
     * @since 2.3.0
     */
    static public function stats()
    {
        $hits = GB_Object_Cache::$cache_hits;
        $misses = GB_Object_Cache::$cache_misses;
        echo "<p>";
        echo "<strong>Cache Hits:</strong> $hits<br />";
        echo "<strong>Cache Misses:</strong> $misses<br />";
        echo "</p>";
        echo '<ul>';
        foreach (GB_Object_Cache::$cache as $group => $cache) {
            echo "<li><strong>Group:</strong> $group - ( " . number_format(strlen(serialize($cache)) / 1024, 2) . 'k )</li>';
        }
        echo '</ul>';
    }

    /**
     * Utility function to determine whether a key exists in the cache.
     *
     * @since 2.3.0
     *       
     * @access protected
     * @param string $key            
     * @param string $group            
     * @return bool
     */
    static protected function _exists($key, $group)
    {
        return isset(GB_Object_Cache::$cache[$group]) && (isset(GB_Object_Cache::$cache[$group][$key]) || array_key_exists($key, GB_Object_Cache::$cache[$group]));
    }
}

/**
 * Adds data to the cache, if the cache key doesn't already exist.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::add()
 *
 * @param int|string $key
 *            The cache key to use for retrieval later
 * @param mixed $data
 *            The data to add to the cache store
 * @param string $group
 *            The group to add the cache to
 * @param int $expire
 *            When the cache data should be expired
 * @return bool False if cache key and group already exist, true on success
 */
function gb_cache_add($key, $data, $group = '', $expire = 0)
{
    return GB_Object_Cache::add($key, $data, $group, (int) $expire);
}

/**
 * Closes the cache.
 *
 * The functionality was removed along with the rest of the persistent cache. This
 * does not mean that plugins can't implement this function when they need to
 * make sure that the cache is cleaned up after GeniBase no longer needs it.
 *
 * @since 2.3.0
 *       
 * @return bool Always returns True
 */
function gb_cache_close()
{
    return true;
}

/**
 * Decrement numeric cache item's value
 *
 * @since 2.3.0
 * @see GB_Object_Cache::decr()
 *
 * @param int|string $key
 *            The cache key to increment
 * @param int $offset
 *            The amount by which to decrement the item's value. Default is 1.
 * @param string $group
 *            The group the key is in.
 * @return false|int False on failure, the item's new value on success.
 */
function gb_cache_decr($key, $offset = 1, $group = '')
{
    return GB_Object_Cache::decr($key, $offset, $group);
}

/**
 * Removes the cache contents matching key and group.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::delete()
 *
 * @param int|string $key
 *            What the contents in the cache are called
 * @param string $group
 *            Where the cache contents are grouped
 * @return bool True on successful removal, false on failure
 */
function gb_cache_delete($key, $group = '')
{
    return GB_Object_Cache::delete($key, $group);
}

/**
 * Removes all cache items.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::flush()
 *
 * @return bool False on failure, true on success
 */
function gb_cache_flush()
{
    return GB_Object_Cache::flush();
}

/**
 * Retrieves the cache contents from the cache by key and group.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::get()
 *
 * @param int|string $key
 *            What the contents in the cache are called
 * @param string $group
 *            Where the cache contents are grouped
 * @param bool $force
 *            Whether to force an update of the local cache from the persistent cache (default is false)
 * @param &bool $found
 *            Whether key was found in the cache. Disambiguates a return of false, a storable value.
 * @return bool|mixed False on failure to retrieve contents or the cache
 *         contents on success
 */
function gb_cache_get($key, $group = '', $force = false, &$found = null)
{
    return GB_Object_Cache::get($key, $group, $force, $found);
}

/**
 * Increment numeric cache item's value
 *
 * @since 2.3.0
 * @see GB_Object_Cache::incr()
 *
 * @param int|string $key
 *            The cache key to increment
 * @param int $offset
 *            The amount by which to increment the item's value. Default is 1.
 * @param string $group
 *            The group the key is in.
 * @return false|int False on failure, the item's new value on success.
 */
function gb_cache_incr($key, $offset = 1, $group = '')
{
    return GB_Object_Cache::incr($key, $offset, $group);
}

/**
 * Sets up Object Cache.
 *
 * @since 2.3.0
 */
function gb_cache_init()
{}

/**
 * Replaces the contents of the cache with new data.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::replace()
 *
 * @param int|string $key
 *            What to call the contents in the cache
 * @param mixed $data
 *            The contents to store in the cache
 * @param string $group
 *            Where to group the cache contents
 * @param int $expire
 *            When to expire the cache contents
 * @return bool False if not exists, true if contents were replaced
 */
function gb_cache_replace($key, $data, $group = '', $expire = 0)
{
    return GB_Object_Cache::replace($key, $data, $group, (int) $expire);
}

/**
 * Saves the data to the cache.
 *
 * @since 2.3.0
 * @see GB_Object_Cache::set()
 *
 * @param int|string $key
 *            What to call the contents in the cache
 * @param mixed $data
 *            The contents to store in the cache
 * @param string $group
 *            Where to group the cache contents
 * @param int $expire
 *            When to expire the cache contents
 * @return bool False on failure, true on success
 */
function gb_cache_set($key, $data, $group = '', $expire = 0)
{
    return GB_Object_Cache::set($key, $data, $group, (int) $expire);
}

/**
 * Adds a group or set of groups to the list of global groups.
 *
 * @since 2.3.0
 *       
 * @param string|array $groups
 *            A group or an array of groups to add
 */
function gb_cache_add_global_groups($groups)
{
    return GB_Object_Cache::add_global_groups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @since 2.3.0
 *       
 * @param string|array $groups
 *            A group or an array of groups to add
 */
function gb_cache_add_non_persistent_groups($groups)
{
    // Default cache doesn't persist so nothing to do here.
    return;
}

/**
 * Retrieve ids that are not already present in the cache.
 *
 * @since 3.0.0
 * @access private
 *        
 * @param array $object_ids
 *            ID list.
 * @param string $cache_key
 *            The cache bucket to check against.
 *            
 * @return array List of ids not present in the cache.
 */
function _get_non_cached_ids($object_ids, $cache_key)
{
    $clean = array();
    foreach ($object_ids as $id) {
        $id = (int) $id;
        if (! gb_cache_get($id, $cache_key)) {
            $clean[] = $id;
        }
    }
    
    return $clean;
}
