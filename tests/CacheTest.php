<?php
namespace Shoofly\Tests;

use PHPUnit\Framework\TestCase;
use Shoofly\Base;
use Shoofly\Auth;
use Shoofly\Cache;
use Shoofly\Database\Jig;
use Shoofly\Registry;

class CacheTest extends TestCase
{
    public function tearDown(): void
    {
        if (!is_dir('tmp/cache/')) {
            mkdir('tmp/cache/', Base::MODE, true);
        }

        Registry::clear(Base::class);
        Registry::clear(Cache::class);
    }

    public function testSettingCacheHiveVar()
    {
        $f3 = Base::instance();
        $result = $f3->set('CACHE', false);
        $this->assertEmpty($result);
        $result = $f3->set('CACHE', 'invalid');
        $this->assertNotEquals('invalid', $result);
    }

    public function testDefaultCacheSettingIsFalse()
    {
        $f3 = Base::instance();
        $result = $f3->get('CACHE');
        $this->assertFalse($result);
    }

    public function testCacheNotSetGetValue()
    {
        $f3 = Base::instance();
        $Cache = new Cache(false);
        $cache_key = $f3->hash('foo') . '.var';
        $Cache->set($cache_key, 'bar', 1);
        $this->assertFalse($Cache->get('foo'));
    }

    public function testCacheSetGetValue()
    {
        $f3 = Base::instance();
        $cache_sources_to_test = [ 'folder=tmp/cache/' ];
        if (extension_loaded('apc')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('apcu')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('xcache')) {
            $cache_sources_to_test[] = 'xcache';
        }
        if (extension_loaded('wincache')) {
            $cache_sources_to_test[] = 'wincache';
        }
        if (extension_loaded('memcache')) {
            $cache_sources_to_test[] = 'memcache=localhost';
        }
        if (extension_loaded('memcached')) {
            $cache_sources_to_test[] = 'memcached=localhost';
        }
        if (extension_loaded('redis')) {
            $cache_sources_to_test[] = 'redis=localhost';
        }
        foreach ($cache_sources_to_test as $cache_source) {
            $Cache = new Cache($cache_source);
            $cache_key = $f3->hash($cache_source . 'foo') . '.var';
            $Cache->set($cache_key, 'bar', 1);
            $this->assertSame($Cache->get($cache_key), 'bar', 'Cache source: ' . $cache_source);
        }
    }

    public function testCacheSetValueExists()
    {
        $f3 = Base::instance();
        $cache_sources_to_test = [ 'folder=tmp/cache/' ];
        if (extension_loaded('apc')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('apcu')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('xcache')) {
            $cache_sources_to_test[] = 'xcache';
        }
        if (extension_loaded('wincache')) {
            $cache_sources_to_test[] = 'wincache';
        }
        if (extension_loaded('memcache')) {
            $cache_sources_to_test[] = 'memcache=localhost';
        }
        if (extension_loaded('memcached')) {
            $cache_sources_to_test[] = 'memcached=localhost';
        }
        if (extension_loaded('redis')) {
            $cache_sources_to_test[] = 'redis=localhost';
        }
        foreach ($cache_sources_to_test as $cache_source) {
            $Cache = new Cache($cache_source);
            $cache_key = $f3->hash($cache_source . 'foo') . '.var';
            $Cache->set($cache_key, 'bar', 1);
            $exist_result = $Cache->exists($cache_key, $result);
            $this->assertSame($result, 'bar', 'Cache source: ' . $cache_source);
            $this->assertIsFloat($exist_result[0]);
            $this->assertSame(1, $exist_result[1]);
        }
    }

    public function testCacheReset()
    {
        $f3 = Base::instance();
        $cache_sources_to_test = [ 'folder=tmp/cache/' ];
        if (extension_loaded('apc')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('apcu')) {
            $cache_sources_to_test[] = 'apcu';
        }
        if (extension_loaded('xcache')) {
            $cache_sources_to_test[] = 'xcache';
        }
        if (extension_loaded('wincache')) {
            $cache_sources_to_test[] = 'wincache';
        }
        if (extension_loaded('memcache')) {
            $cache_sources_to_test[] = 'memcache=localhost';
        }
        // not a great test, but memcached doesn't have a reset method. See Cache class.
        // if(extension_loaded('memcached')) {
        //  $cache_sources_to_test[] = 'memcached=localhost';
        // }
        if (extension_loaded('redis')) {
            $cache_sources_to_test[] = 'redis=localhost';
        }
        foreach ($cache_sources_to_test as $cache_source) {
            $Cache = new Cache($cache_source);
            $cache_key = $f3->hash($cache_source . 'foo') . '.var';
            $Cache->set($cache_key, 'bar', 1);
            $exist_result = $Cache->exists($cache_key, $result);
            $this->assertSame($result, 'bar', 'Cache source: ' . $cache_source);
            $this->assertIsFloat($exist_result[0]);
            $this->assertSame(1, $exist_result[1]);
            $did_reset = $Cache->reset();
            $this->assertTrue($did_reset);
            $Cache->exists($cache_key, $another_result);
            $this->assertNull($another_result, 'Cache source: ' . $cache_source);
        }
    }
}
