<?php

declare(strict_types=1);

namespace Shoofly;

//! Cache engine
class Cache extends Prefab
{
    //! Cache DSN
    protected $dsn;
    //! Prefix for cache entries
    protected $prefix;
    /** @var \Redis|\Memcached MemCache or Redis object */
    protected $ref;

    /**
    *   Class constructor
    *   @param $dsn bool|string
    **/
    public function __construct($dsn = false)
    {
        if ($dsn) {
            $this->load($dsn);
        }
    }

    /**
    *   Load/auto-detect cache backend
    *   @return string
    *   @param $dsn bool|string
    *   @param $seed bool|string
    **/
    public function load($dsn, $seed = null)
    {
        $fw = Base::instance();
        if ($dsn = trim((string) $dsn)) {
            if (preg_match('/^redis=(.+)/', $dsn, $parts) &&
                extension_loaded('redis')
            ) {
                list($host,$port,$db,$password) = explode(':', $parts[1]) + [1 => 6379,2 => null,3 => null];
                $this->ref = new Redis();
                if (!$this->ref->connect($host, $port, 2)) {
                    $this->ref = null;
                }
                if (!empty($password)) {
                    $this->ref->auth($password);
                }
                if (isset($db)) {
                    $this->ref->select($db);
                }
            } elseif (preg_match('/^memcache=(.+)/', $dsn, $parts) &&
                extension_loaded('memcache')
            ) {
                foreach ($fw->split($parts[1]) as $server) {
                    list($host,$port) = explode(':', $server) + [1 => 11211];
                    if (empty($this->ref)) {
                        $this->ref = @memcache_connect($host, $port) ?: null;
                    } else {
                        memcache_add_server($this->ref, $host, $port);
                    }
                }
            } elseif (preg_match('/^memcached=(.+)/', $dsn, $parts) &&
                extension_loaded('memcached')
            ) {
                foreach ($fw->split($parts[1]) as $server) {
                    list($host,$port) = explode(':', $server) + [1 => 11211];
                    if (empty($this->ref)) {
                        $this->ref = new \Memcached();
                    }
                    $this->ref->addServer($host, $port);
                }
            }
            if (empty($this->ref) && !preg_match('/^folder\h*=/', $dsn)) {
                $dsn = ($grep = preg_grep(
                    '/^(apc|wincache|xcache)/',
                    array_map('strtolower', get_loaded_extensions())
                )) ?
                    // Auto-detect
                    current($grep) :
                    // Use filesystem as fallback
                    ('folder=' . $fw->TEMP . 'cache/');
            }
            if (preg_match('/^folder\h*=\h*(.+)/', $dsn, $parts) &&
                    !is_dir($parts[1])
            ) {
                mkdir($parts[1], Base::MODE, true);
            }
        }
        $this->prefix = $seed ?: $fw->SEED;
        return $this->dsn = $dsn;
    }

    /**
    *   Return timestamp and TTL of cache entry or FALSE if not found
    *   @return array|FALSE
    *   @param $key string
    *   @param $val mixed
    **/
    public function exists($key, &$val = null)
    {
        $fw = Base::instance();
        if (!$this->dsn) {
            return false;
        }
        $ndx = $this->prefix . '.' . $key;
        $parts = explode('=', $this->dsn, 2);
        switch ($parts[0]) {
            case 'apc':
            case 'apcu':
                $raw = call_user_func($parts[0] . '_fetch', $ndx);
                break;
            case 'redis':
                $raw = $this->ref->get($ndx);
                break;
            case 'memcache':
                $raw = memcache_get($this->ref, $ndx);
                break;
            case 'memcached':
                $raw = $this->ref->get($ndx);
                break;
            case 'wincache':
                $raw = wincache_ucache_get($ndx);
                break;
            case 'xcache':
                $raw = xcache_get($ndx);
                break;
            case 'folder':
                if (file_exists($parts[1] . $ndx) === true) {
                    $raw = $fw->read($parts[1] . $ndx);
                }
                break;
        }
        if (!empty($raw)) {
            list($val,$time,$ttl) = (array)$fw->unserialize($raw);
            if ($ttl === 0 || $time + $ttl > microtime(true)) {
                return [$time,$ttl];
            }
            $val = null;
            $this->clear($key);
        }
        return false;
    }

    /**
    *   Store value in cache
    *   @return mixed|FALSE
    *   @param $key string
    *   @param $val mixed
    *   @param $ttl int
    **/
    public function set($key, $val, $ttl = 0)
    {
        $fw = Base::instance();
        if (!$this->dsn) {
            return true;
        }
        $ndx = $this->prefix . '.' . $key;
        if ($cached = $this->exists($key)) {
            $ttl = $cached[1];
        }
        $data = $fw->serialize([$val,microtime(true),$ttl]);
        $parts = explode('=', $this->dsn, 2);
        switch ($parts[0]) {
            case 'apc':
            case 'apcu':
                return call_user_func($parts[0] . '_store', $ndx, $data, $ttl);
            case 'redis':
                return $this->ref->set($ndx, $data, $ttl ? ['ex' => $ttl] : []);
            case 'memcache':
                return memcache_set($this->ref, $ndx, $data, 0, $ttl);
            case 'memcached':
                return $this->ref->set($ndx, $data, $ttl);
            case 'wincache':
                return wincache_ucache_set($ndx, $data, $ttl);
            case 'xcache':
                return xcache_set($ndx, $data, $ttl);
            case 'folder':
                return $fw->write($parts[1] .
                    str_replace(['/','\\'], '', $ndx), $data);
        }
        return false;
    }

    /**
    *   Retrieve value of cache entry
    *   @return mixed|FALSE
    *   @param $key string
    **/
    public function get($key)
    {
        return $this->dsn && $this->exists($key, $data) ? $data : false;
    }

    /**
    *   Delete cache entry
    *   @return bool
    *   @param $key string
    **/
    public function clear($key)
    {
        if (!$this->dsn) {
            return;
        }
        $ndx = $this->prefix . '.' . $key;
        $parts = explode('=', $this->dsn, 2);
        switch ($parts[0]) {
            case 'apc':
            case 'apcu':
                return call_user_func($parts[0] . '_delete', $ndx);
            case 'redis':
                return $this->ref->del($ndx);
            case 'memcache':
                return memcache_delete($this->ref, $ndx);
            case 'memcached':
                return $this->ref->delete($ndx);
            case 'wincache':
                return wincache_ucache_delete($ndx);
            case 'xcache':
                return xcache_unset($ndx);
            case 'folder':
                return @unlink($parts[1] . $ndx);
        }
        return false;
    }

    /**
    *   Clear contents of cache backend
    *   @return bool
    *   @param $suffix string
    **/
    public function reset($suffix = null)
    {
        if (!$this->dsn) {
            return true;
        }
        $regex = '/' . preg_quote($this->prefix . '.', '/') . '.*' .
            preg_quote($suffix ?: '', '/') . '/';
        $parts = explode('=', $this->dsn, 2);
        switch ($parts[0]) {
            case 'apc':
            case 'apcu':
                $info = call_user_func(
                    $parts[0] . '_cache_info',
                    $parts[0] == 'apcu' ? false : 'user'
                );
                if (!empty($info['cache_list'])) {
                    $key = array_key_exists(
                        'info',
                        $info['cache_list'][0]
                    ) ? 'info' : 'key';
                    foreach ($info['cache_list'] as $item) {
                        if (preg_match($regex, $item[$key])) {
                            call_user_func($parts[0] . '_delete', $item[$key]);
                        }
                    }
                }
                return true;
            case 'redis':
                $keys = $this->ref->keys($this->prefix . '.*' . $suffix);
                foreach ($keys as $key) {
                    $this->ref->del($key);
                }
                return true;
            case 'memcache':
                foreach (memcache_get_extended_stats($this->ref, 'slabs') as $slabs) {
                    foreach (array_filter(array_keys($slabs), 'is_numeric') as $id) {
                        foreach (memcache_get_extended_stats($this->ref, 'cachedump', $id) as $data) {
                            if (is_array($data)) {
                                foreach (array_keys($data) as $key) {
                                    if (preg_match($regex, $key)) {
                                        memcache_delete($this->ref, $key);
                                    }
                                }
                            }
                        }
                    }
                }
                return true;
            case 'memcached':
                // not actually guaranteed to delete all the keys
                // https://www.php.net/manual/en/memcached.getallkeys.php
                foreach ($this->ref->getAllKeys() ?: [] as $key) {
                    if (preg_match($regex, $key)) {
                        $this->ref->delete($key);
                    }
                }
                return true;
            case 'wincache':
                $info = wincache_ucache_info();
                foreach ($info['ucache_entries'] as $item) {
                    if (preg_match($regex, $item['key_name'])) {
                        wincache_ucache_delete($item['key_name']);
                    }
                }
                return true;
            case 'xcache':
                if ($suffix && !ini_get('xcache.admin.enable_auth')) {
                    $cnt = xcache_count(XC_TYPE_VAR);
                    for ($i = 0; $i < $cnt; ++$i) {
                        $list = xcache_list(XC_TYPE_VAR, $i);
                        foreach ($list['cache_list'] as $item) {
                            if (preg_match($regex, $item['name'])) {
                                xcache_unset($item['name']);
                            }
                        }
                    }
                } else {
                    xcache_unset_by_prefix($this->prefix . '.');
                }
                return true;
            case 'folder':
                if ($glob = @glob($parts[1] . '*')) {
                    foreach ($glob as $file) {
                        if (preg_match($regex, basename($file))) {
                            @unlink($file);
                        }
                    }
                }
                return true;
        }
        return false;
    }
}
