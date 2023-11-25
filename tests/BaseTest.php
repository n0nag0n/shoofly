<?php

namespace Shoofly\Tests;

use PHPUnit\Framework\TestCase;
use Shoofly\Base;
use Shoofly\Registry;
use Shoofly\Basket;

class BaseTest extends TestCase
{
	/**
	 * @var Base
	 */
	protected $f3;

	public function setUp(): void
	{
		// Some basic server variables
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['SERVER_PORT'] = 80;
		$_SERVER['SERVER_NAME'] = 'localhost';
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$_SERVER['SCRIPT_FILENAME'] = 'index.php';
		$_SERVER['PHP_SELF'] = '/index.php';
		$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/..';
		$this->f3 = Base::instance();

	}
    public function tearDown(): void
    {
        if (!is_dir('tmp/')) {
            mkdir('tmp/', Base::MODE, true);
        }
        Base::instance()->SESSION = [];
		Base::instance()->COOKIE = [];
		Base::instance()->SERVER = [];
		Base::instance()->GET = [];
		Base::instance()->POST = [];
        Registry::clear(Base::class);
    }

    public function testErrorIsNull()
	{
		$this->assertNull($this->f3->get('ERROR'), 'No errors expected at this point');
	}

	public function testPackageAndVersion()
	{
		$this->assertNotNull($package = $this->f3->get('PACKAGE'), 'PACKAGE: ' . $package);
		$this->assertNotNull($version = $this->f3->get('VERSION'), 'VERSION: ' . $version);
	}

	public function testGlobalVariableReset()
	{
		$package = $this->f3->get('PACKAGE');
		$version = $this->f3->get('VERSION');
		$this->f3->clear('PACKAGE');
		$this->f3->clear('VERSION');
		$this->assertTrue($this->f3->get('PACKAGE') == $package && $this->f3->get('VERSION') == $version, 'Clearing global variable resets to default value');
	}

	public function testDirectoryRootIpRealmVerbSchemeHostPortBaseUriAgentAjaxPatternEncodingLanguageTz()
	{
		$this->assertTrue(is_dir($root = $this->f3->get('ROOT')), 'ROOT (document root): ' . $this->f3->stringify($root));
		$this->assertNotNull($ip = $this->f3->get('IP'), 'IP (Remote IP address): ' . $this->f3->stringify($ip));
		$this->assertNotNull($realm = $this->f3->get('REALM'), 'REALM (Full canonical URI): ' . $this->f3->stringify($realm));
		$this->assertEquals($this->f3->get('VERB'), $_SERVER['REQUEST_METHOD'], 'VERB (request method): ' . $this->f3->stringify($this->f3->get('VERB')));
		$this->assertNotNull($scheme = $this->f3->get('SCHEME'), 'SCHEME (Web protocol): ' . $this->f3->stringify($scheme));
		$this->assertNotNull($scheme = $this->f3->get('HOST'), 'HOST (Web host/domain): ' . $this->f3->stringify($scheme));
		$this->assertNotNull($port = $this->f3->get('PORT'), 'PORT (HTTP port): ' . $port);
		$this->assertTrue(is_string($base = $this->f3->get('BASE')), 'BASE (path to index.php relative to ROOT): ' . $this->f3->stringify($base));
		$this->assertEquals($this->f3->get('URI'), $_SERVER['REQUEST_URI'], 'URI (request URI): ' . $this->f3->stringify($this->f3->get('URI')));
		$this->assertNotNull($agent = $this->f3->get('AGENT'), 'AGENT (user agent): ' . $this->f3->stringify($agent));
		$this->assertFalse($ajax = $this->f3->get('AJAX'), 'AJAX: ' . $this->f3->stringify($ajax));
		// null because no route is defined
		$this->assertNull($pattern = $this->f3->get('PATTERN'), 'PATTERN (matching route): ' . $this->f3->stringify($pattern));
		$this->assertEquals($this->f3->get('ENCODING'), 'UTF-8', 'ENCODING (character set): ' . $this->f3->stringify($this->f3->get('ENCODING')));
		$this->assertNotNull($language = $this->f3->get('LANGUAGE'), 'LANGUAGE: ' . $this->f3->stringify($language));
		$this->assertNotNull($tz = $this->f3->get('TZ'), 'TZ (time zone): ' . $this->f3->stringify($tz));
		$this->f3->set('TZ', 'America/New_York');
		$this->assertEquals($this->f3->get('TZ'), date_default_timezone_get(), 'Time zone adjusted: ' . $this->f3->stringify($this->f3->get('TZ')));
	}

    public function testSerializerAndSession()
	{
		$this->assertNotNull($serializer = $this->f3->get('SERIALIZER'), 'SERIALIZER: ' . $this->f3->stringify($serializer));
		
		$this->f3->set('SESSION', []);
		$this->assertEmpty(session_id(), 'No active session');
		
		$this->f3->set('SESSION[hello]', 'world');
		$this->assertTrue($_SESSION['hello'] == 'world', 'Session auto-started by set()');
		
		$this->f3->set('SESSION', []);
		$this->assertTrue(empty($_SESSION), 'Session destroyed by clear()');
		
		$result = $this->f3->get('SESSION[hello]');
		$this->assertTrue(empty($_SESSION['hello']) && is_null($result), 'Session restarted by get()');
		
		$this->f3->set('SESSION', []);
		$result = $this->f3->exists('SESSION.hello');
		$this->assertFalse($result, 'No session variable instantiated by exists()');
		
		$this->f3->set('SESSION.foo', 'bar');
		$this->f3->set('SESSION.baz', 'qux');
		unset($_SESSION['foo']);
		$result = $this->f3->exists('SESSION.foo');
		$this->assertTrue(empty($_SESSION['foo']) && $result === FALSE && !empty($_SESSION['baz']), 'Specific session variable created/erased');
	}

	public function testSyncOfGlobalsAndHeaders()
	{
		$this->f3->set('GET["bar"]', 'foo');
		$this->f3->set('POST.baz', 'qux');
		$this->assertTrue(
			$this->f3->get('GET.bar') == 'foo' && $_GET['bar'] == 'foo' &&
			$this->f3->get('REQUEST.bar') == 'foo' && $_REQUEST['bar'] == 'foo' &&
			$this->f3->get('POST.baz') == 'qux' && $_POST['baz'] == 'qux' &&
			$this->f3->get('REQUEST.baz') == 'qux' && $_REQUEST['baz'] == 'qux',
			'PHP global variables in sync'
		);

		$this->f3->clear('GET["bar"]');
		$this->assertFalse(
			$this->f3->exists('GET["bar"]') && empty($_GET['bar']) &&
			$this->f3->exists('REQUEST["bar"]') && empty($_REQUEST['bar']),
			'PHP global variables cleared'
		);

		$ok = true;
		foreach ($this->f3->get('HEADERS') as $hdr => $val) {
			if (isset($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $hdr))]) &&
				$_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $hdr))] != $val) {
				$ok = false;
			}
		}
		$this->assertTrue($ok, 'HTTP headers match HEADERS variable');
	}

	public function testCookieHandlingAndRelativeLinks()
	{
		// if ($this->f3->exists('COOKIE.baz')) {
		// 	unset($_COOKIE['baz']);
		// 	// $this->f3->clear('COOKIE.baz');
		// 	$this->assertEmpty($this->f3->get('COOKIE.baz'), 'HTTP cookie cleared');
		// } else {
		// 	$this->f3->set('COOKIE.baz', 'qux');
		// 	$this->assertEquals($this->f3->get('COOKIE.baz'), 'qux', 'HTTP cookie set');
		// }

		$this->assertEquals(
			$this->f3->rel(rtrim($_SERVER['REQUEST_URI'], '/') . '/hello/world'),
			'/hello/world',
			'Relative links correct'
		);
	}

	public function testMultibyteEncoding()
	{
		if (extension_loaded('mbstring')) {
			$charset = mb_internal_encoding();
			$this->assertEquals($charset, 'UTF-8', 'Multibyte encoding: ' . $charset);
		}
	}

	public function testGlobalsConsistency()
	{
		$ok = true;
		$list = '';
		foreach (explode('|', Base::GLOBALS) as $global) {
			if ($GLOBALS['_'.$global] != $this->f3->get($global)) {
				$ok = false;
				$list .= ($list ? ',' : '') . $global;
			}
		}
		$this->assertTrue($ok, 'PHP globals same as hive globals' . ($list ? (': ' . $list) : ''));
	}

	public function testAlterHiveGlobals()
	{
		$ok = true;
		$list = '';
		foreach (explode('|', Base::GLOBALS) as $global) {
			if($global === 'SESSION' || $global === 'COOKIE') {
				continue;
			}
			$this->f3->set($global.'.foo', 'bar');
			if ($GLOBALS['_'.$global] !== $this->f3->get($global)) {
				$ok = false;
				$list .= ($list ? ',' : '') . $global;
			}
		}
		$this->assertTrue($ok, 'Altering hive globals affects PHP globals' . ($list ? (': ' . $list) : ''));
	}

	public function testAlterPhpGlobals()
	{
		$ok = true;
		$list = '';
		foreach (explode('|', Base::GLOBALS) as $global) {
			$GLOBALS['_'.$global]['bar'] = 'foo';
			if ($GLOBALS['_'.$global] !== $this->f3->get($global)) {
				$ok = false;
				$list .= ($list ? ',' : '') . $global;
			}
		}
		$this->assertTrue($ok, 'Altering PHP globals affects hive globals' . ($list ? (': ' . $list) : ''));
	}

	public function testAlteringHttpHeaders()
	{
		$ok = true;
		foreach (array_keys($this->f3->get('HEADERS')) as $hdr) {
			$this->f3->set('HEADERS["'.$hdr.'"]', 'foo');
			$hdr = strtoupper(str_replace('-', '_', $hdr));
			if (isset($_SERVER['HTTP_'.$hdr]) && $_SERVER['HTTP_'.$hdr] != 'foo') {
				$ok = false;
			}
		}
		$this->assertTrue($ok, 'Altering HEADERS variable affects HTTP headers');

		$ok = true;
		foreach (array_keys($this->f3->get('HEADERS')) as $hdr) {
			$tmp = strtoupper(strtr($hdr, '-', '_'));
			if (isset($_SERVER['HTTP_'.$tmp])) {
				$_SERVER['HTTP_'.$tmp] = 'bar';
				if ($this->f3->get('HEADERS["'.$hdr.'"]') != $_SERVER['HTTP_'.$tmp]) {
					$ok = false;
				}
			}
		}
		$this->assertTrue($ok, 'Altering HTTP headers affects HEADERS variable');
	}

}
