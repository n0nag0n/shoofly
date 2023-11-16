<?php

namespace Shoofly;

use ArrayAccess;
use IntlDateFormatter;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/*

    Copyright (c) 2009-2023 F3::Factory/Bong Cosca, All rights reserved.

    This file is part of the Fat-Free Framework (http://fatfreeframework.com).

    This is free software: you can redistribute it and/or modify it under the
    terms of the GNU General Public License as published by the Free Software
    Foundation, either version 3 of the License, or later.

    Fat-Free Framework is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
    General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with Fat-Free Framework.  If not, see <http://www.gnu.org/licenses/>.

*/

//! Base structure
class Base extends Prefab implements ArrayAccess
{
    public const PACKAGE = 'Shoofly';
    public const VERSION = '6.0.0-beta';

    /**
     * HTTP Status Codes
     */
    public const HTTP_100 = 'Continue';
    public const HTTP_101 = 'Switching Protocols';
    public const HTTP_103 = 'Early Hints';
    public const HTTP_200 = 'OK';
    public const HTTP_201 = 'Created';
    public const HTTP_202 = 'Accepted';
    public const HTTP_203 = 'Non-Authoritative Information';
    public const HTTP_204 = 'No Content';
    public const HTTP_205 = 'Reset Content';
    public const HTTP_206 = 'Partial Content';
    public const HTTP_300 = 'Multiple Choices';
    public const HTTP_301 = 'Moved Permanently';
    public const HTTP_302 = 'Found';
    public const HTTP_303 = 'See Other';
    public const HTTP_304 = 'Not Modified';
    public const HTTP_305 = 'Use Proxy';
    public const HTTP_307 = 'Temporary Redirect';
    public const HTTP_308 = 'Permanent Redirect';
    public const HTTP_400 = 'Bad Request';
    public const HTTP_401 = 'Unauthorized';
    public const HTTP_402 = 'Payment Required';
    public const HTTP_403 = 'Forbidden';
    public const HTTP_404 = 'Not Found';
    public const HTTP_405 = 'Method Not Allowed';
    public const HTTP_406 = 'Not Acceptable';
    public const HTTP_407 = 'Proxy Authentication Required';
    public const HTTP_408 = 'Request Timeout';
    public const HTTP_409 = 'Conflict';
    public const HTTP_410 = 'Gone';
    public const HTTP_411 = 'Length Required';
    public const HTTP_412 = 'Precondition Failed';
    public const HTTP_413 = 'Request Entity Too Large';
    public const HTTP_414 = 'Request-URI Too Long';
    public const HTTP_415 = 'Unsupported Media Type';
    public const HTTP_416 = 'Requested Range Not Satisfiable';
    public const HTTP_417 = 'Expectation Failed';
    public const HTTP_421 = 'Misdirected Request';
    public const HTTP_422 = 'Unprocessable Entity';
    public const HTTP_423 = 'Locked';
    public const HTTP_429 = 'Too Many Requests';
    public const HTTP_451 = 'Unavailable For Legal Reasons';
    public const HTTP_500 = 'Internal Server Error';
    public const HTTP_501 = 'Not Implemented';
    public const HTTP_502 = 'Bad Gateway';
    public const HTTP_503 = 'Service Unavailable';
    public const HTTP_504 = 'Gateway Timeout';
    public const HTTP_505 = 'HTTP Version Not Supported';
    public const HTTP_507 = 'Insufficient Storage';
    public const HTTP_511 = 'Network Authentication Required';

    /**
     * General Constants
     */
    public const GLOBALS = 'GET|POST|COOKIE|REQUEST|SESSION|FILES|SERVER|ENV';
    public const VERBS = 'GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT|OPTIONS';
    public const MODE = 0755;
    public const CSS = 'code.css';

    /**
     * Request Types
     */
    public const REQ_SYNC = 1;
    public const REQ_AJAX = 2;
    public const REQ_CLI = 4;

    /**
     * Route Error Codes
     */
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    public const E_Pattern = 'Invalid routing pattern: %s';
    public const E_Named = 'Named route does not exist: %s';
    public const E_Alias = 'Invalid named route alias: %s';
    public const E_Fatal = 'Fatal error: %s';
    public const E_Open = 'Unable to open %s';
    public const E_Routes = 'No routes specified';
    public const E_Class = 'Invalid class %s';
    public const E_Method = 'Invalid method %s';
    public const E_Hive = 'Invalid hive key %s';
	// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    /** @var array Where vars are stored */
    protected $hive = [];
    /** @var array Initial settings */
    protected $init = [];
    /** @var array Language lookup sequence */
    protected $languages = [];
    /** @var array Mutex locks */
    protected $locks = [];
    /** @var string Default fallback language */
    protected $fallback = 'en';

    public function __construct()
    {
        // Managed directives
        ini_set('default_charset', $charset = 'UTF-8');
        if (extension_loaded('mbstring')) {
            mb_internal_encoding($charset);
        }
        ini_set('display_errors', 0);
        // Deprecated directives
        @ini_set('magic_quotes_gpc', 0);
        @ini_set('register_globals', 0);
        // Intercept errors/exceptions; PHP5.3-compatible
        $check = error_reporting((E_ALL | E_STRICT) & ~(E_NOTICE | E_USER_NOTICE));
        set_exception_handler(
            function ($obj) {
                /** @var Exception $obj */
                $this->hive['EXCEPTION'] = $obj;
                $this->error(
                    500,
                    $obj->getmessage() . ' ' .
                    '[' . $obj->getFile() . ':' . $obj->getLine() . ']',
                    $obj->gettrace()
                );
            }
        );
        set_error_handler(
            function ($level, $text, $file, $line) {
                if ($level & error_reporting()) {
                    $trace = $this->trace(null, false);
                    array_unshift($trace, ['file' => $file,'line' => $line]);
                    $this->error(500, $text, $trace, $level);
                }
            }
        );
        if (!isset($_SERVER['SERVER_NAME']) || $_SERVER['SERVER_NAME'] === '') {
            $_SERVER['SERVER_NAME'] = gethostname();
        }
        $headers = [];
        if ($cli = (PHP_SAPI == 'cli')) {
            // Emulate HTTP request
            $_SERVER['REQUEST_METHOD'] = 'GET';
            if (!isset($_SERVER['argv'][1])) {
                ++$_SERVER['argc'];
                $_SERVER['argv'][1] = '/';
            }
            $req = $query = '';
            if (substr($_SERVER['argv'][1], 0, 1) == '/') {
                $req = $_SERVER['argv'][1];
                $query = parse_url($req, PHP_URL_QUERY);
            } else {
                foreach ($_SERVER['argv'] as $i => $arg) {
                    if (!$i) {
                        continue;
                    }
                    if (preg_match('/^\-(\-)?(\w+)(?:\=(.*))?$/', $arg, $m)) {
                        foreach ($m[1] ? [$m[2]] : str_split($m[2]) as $k) {
                            $query .= ($query ? '&' : '') . urlencode($k) . '=';
                        }
                        if (isset($m[3])) {
                            $query .= urlencode($m[3]);
                        }
                    } else {
                        $req .= '/' . $arg;
                    }
                }
                if (!$req) {
                    $req = '/';
                }
                if ($query) {
                    $req .= '?' . $query;
                }
            }
            $_SERVER['REQUEST_URI'] = $req;
            parse_str($query ?: '', $GLOBALS['_GET']);
        } elseif (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $val) {
                $tmp = strtoupper(strtr($key, '-', '_'));
                // TODO: use ucwords delimiters for php 5.4.32+ & 5.5.16+
                $key = strtr(ucwords(strtolower(strtr($key, '-', ' '))), ' ', '-');
                $headers[$key] = $val;
                if (isset($_SERVER['HTTP_' . $tmp])) {
                    $headers[$key]=&$_SERVER['HTTP_' . $tmp];
                }
            }
        } else {
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['Content-Length']=&$_SERVER['CONTENT_LENGTH'];
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type']=&$_SERVER['CONTENT_TYPE'];
            }
            foreach (array_keys($_SERVER) as $key) {
                if (substr($key, 0, 5) == 'HTTP_') {
                    $headers[strtr(ucwords(strtolower(strtr(
                        substr($key, 5),
                        '_',
                        ' '
                    ))), ' ', '-')]=&$_SERVER[$key];
                }
            }
        }
        if (isset($headers['X-Http-Method-Override'])) {
            $_SERVER['REQUEST_METHOD'] = $headers['X-Http-Method-Override'];
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['_method'])) {
            $_SERVER['REQUEST_METHOD'] = strtoupper($_POST['_method']);
        }
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ||
            isset($headers['X-Forwarded-Proto']) &&
            $headers['X-Forwarded-Proto'] == 'https' ? 'https' : 'http';
        // Create hive early on to expose header methods
        $this->hive = ['HEADERS' => &$headers];
        if (function_exists('apache_setenv')) {
            // Work around Apache pre-2.4 VirtualDocumentRoot bug
            $_SERVER['DOCUMENT_ROOT'] = str_replace(
                $_SERVER['SCRIPT_NAME'],
                '',
                $_SERVER['SCRIPT_FILENAME']
            );
            apache_setenv("DOCUMENT_ROOT", $_SERVER['DOCUMENT_ROOT']);
        }
        $_SERVER['DOCUMENT_ROOT'] = realpath($_SERVER['DOCUMENT_ROOT']);
        $base = '';
        if (!$cli) {
            $base = rtrim($this->fixslashes(
                dirname($_SERVER['SCRIPT_NAME'])
            ), '/');
        }
        $uri = parse_url((preg_match('/^\w+:\/\//', $_SERVER['REQUEST_URI']) ? '' :
                $scheme . '://' . $_SERVER['SERVER_NAME']) . $_SERVER['REQUEST_URI']);
        $_SERVER['REQUEST_URI'] = $uri['path'] .
            (isset($uri['query']) ? '?' . $uri['query'] : '') .
            (isset($uri['fragment']) ? '#' . $uri['fragment'] : '');
        $path = preg_replace('/^' . preg_quote($base, '/') . '/', '', $uri['path']);
        $jar = [
            'expire' => 0,
            'lifetime' => 0,
            'path' => $base ?: '/',
            'domain' => is_int(strpos($_SERVER['SERVER_NAME'], '.')) &&
                !filter_var($_SERVER['SERVER_NAME'], FILTER_VALIDATE_IP) ?
                $_SERVER['SERVER_NAME'] : '',
            'secure' => ($scheme == 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        $port = 80;
        if (!empty($headers['X-Forwarded-Port'])) {
            $port = $headers['X-Forwarded-Port'];
        } elseif (!empty($_SERVER['SERVER_PORT'])) {
            $port = $_SERVER['SERVER_PORT'];
        }
        // Default configuration
        $this->hive += [
            'AGENT' => $this->agent(),
            'AJAX' => $this->ajax(),
            'ALIAS' => null,
            'ALIASES' => [],
            'AUTOLOAD' => './',
            'BASE' => $base,
            'BITMASK' => ENT_COMPAT,
            'BODY' => null,
            'CACHE' => false,
            'CASELESS' => true,
            'CLI' => $cli,
            'CORS' => [],
            'DEBUG' => 0,
            'DIACRITICS' => [],
            'DNSBL' => '',
            'EMOJI' => [],
            'ENCODING' => $charset,
            'ERROR' => null,
            'ESCAPE' => true,
            'EXCEPTION' => null,
            'EXEMPT' => null,
            'FALLBACK' => $this->fallback,
            'FORMATS' => [],
            'FRAGMENT' => isset($uri['fragment']) ? $uri['fragment'] : '',
            'HALT' => true,
            'HIGHLIGHT' => false,
            'HOST' => $_SERVER['SERVER_NAME'],
            'IP' => $this->ip(),
            'JAR' => $jar,
            'LANGUAGE' => isset($headers['Accept-Language']) ?
                $this->language($headers['Accept-Language']) :
                $this->fallback,
            'LOCALES' => './',
            'LOCK' => LOCK_EX,
            'LOGGABLE' => '*',
            'LOGS' => './',
            'MB' => extension_loaded('mbstring'),
            'ONERROR' => null,
            'ONREROUTE' => null,
            'PACKAGE' => self::PACKAGE,
            'PARAMS' => [],
            'PATH' => $path,
            'PATTERN' => null,
            'PLUGINS' => $this->fixslashes(__DIR__) . '/',
            'PORT' => $port,
            'PREFIX' => null,
            'PREMAP' => '',
            'QUERY' => isset($uri['query']) ? $uri['query'] : '',
            'QUIET' => false,
            'RAW' => false,
            'REALM' => $scheme . '://' . $_SERVER['SERVER_NAME'] .
                (!in_array($port, [80,443]) ? (':' . $port) : '') .
                $_SERVER['REQUEST_URI'],
            'RESPONSE' => '',
            'ROOT' => $_SERVER['DOCUMENT_ROOT'],
            'ROUTES' => [],
            'SCHEME' => $scheme,
            'SEED' => $this->hash($_SERVER['SERVER_NAME'] . $base),
            'SERIALIZER' => extension_loaded($ext = 'igbinary') ? $ext : 'php',
            'TEMP' => 'tmp/',
            'TIME' => &$_SERVER['REQUEST_TIME_FLOAT'],
            'TZ' => @date_default_timezone_get(),
            'UI' => './',
            'UNLOAD' => null,
            'UPLOADS' => './',
            'URI' => &$_SERVER['REQUEST_URI'],
            'VERB' => &$_SERVER['REQUEST_METHOD'],
            'VERSION' => self::VERSION,
            'XFRAME' => 'SAMEORIGIN'
        ];
        $this->hive['CORS'] += [
            'headers' => '',
            'origin' => false,
            'credentials' => false,
            'expose' => false,
            'ttl' => 0
        ];
        if (!headers_sent() && session_status() != PHP_SESSION_ACTIVE) {
            unset($jar['expire']);
            session_cache_limiter('');
            if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                session_set_cookie_params($jar);
            } else {
                unset($jar['samesite']);
                call_user_func_array('session_set_cookie_params', $jar);
            }
        }
        if (PHP_SAPI == 'cli-server' &&
            preg_match('/^' . preg_quote($base, '/') . '$/', $this->hive['URI'])
        ) {
            $this->reroute('/');
        }
        if (ini_get('auto_globals_jit')) {
            // Override setting
            $GLOBALS['_ENV'] = $_ENV;
            $GLOBALS['_REQUEST'] = $_REQUEST;
        }
        // Sync PHP globals with corresponding hive keys
        $this->init = $this->hive;
        foreach (explode('|', self::GLOBALS) as $global) {
            $sync = $this->sync($global);
            $this->init += [
                $global => preg_match('/SERVER|ENV/', $global) ? $sync : []
            ];
        }
        if ($check && $error = error_get_last()) {
            // Error detected
            $this->error(
                500,
                sprintf(self::E_Fatal, $error['message']),
                [$error]
            );
        }
        date_default_timezone_set($this->hive['TZ']);
        // Register framework autoloader
        spl_autoload_register([$this,'autoload']);
        // Register shutdown handler
        register_shutdown_function([$this,'unload'], getcwd());
    }

    /**
    *   Sync PHP global with corresponding hive key
    *   @return array
    *   @param $key string
    **/
    public function sync($key)
    {
        return $this->hive[$key]=&$GLOBALS['_' . $key];
    }

    /**
    *   Return the parts of specified hive key
    *   @return array
    *   @param $key string
    **/
    protected function cut($key)
    {
        return preg_split(
            '/\[\h*[\'"]?(.+?)[\'"]?\h*\]|(->)|\./',
            $key,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );
    }

    /**
    *   Replace tokenized URL with available token values
    *   @return string
    *   @param $url array|string
    *   @param $addParams boolean merge default PARAMS from hive into args
    *   @param $args array
    **/
    public function build($url, $args = [], $addParams = true)
    {
        if ($addParams) {
            $args += $this->recursive($this->hive['PARAMS'], function ($val) {
                return implode('/', array_map('urlencode', explode('/', $val)));
            });
        }
        if (is_array($url)) {
            foreach ($url as &$var) {
                $var = $this->build($var, $args, false);
                unset($var);
            }
        } else {
            $i = 0;
            $url = preg_replace_callback(
                '/(\{)?@(\w+)(?(1)\})|(\*)/',
                function ($match) use (&$i, $args) {
                    if (isset($match[2]) &&
                        array_key_exists($match[2], $args)
                    ) {
                        return $args[$match[2]];
                    }
                    if (isset($match[3]) &&
                        array_key_exists($match[3], $args)
                    ) {
                        if (!is_array($args[$match[3]])) {
                            return $args[$match[3]];
                        }
                        ++$i;
                        return $args[$match[3]][$i - 1];
                    }
                    return $match[0];
                },
                $url
            );
        }
            return $url;
    }

    /**
    *   Parse string containing key-value pairs
    *   @return array
    *   @param $str string
    **/
    public function parse($str)
    {
        preg_match_all(
            '/(\w+|\*)\h*=\h*(?:\[(.+?)\]|(.+?))(?=,|$)/',
            $str,
            $pairs,
            PREG_SET_ORDER
        );
        $out = [];
        foreach ($pairs as $pair) {
            if ($pair[2]) {
                $out[$pair[1]] = [];
                foreach (explode(',', $pair[2]) as $val) {
                    array_push($out[$pair[1]], $val);
                }
            } else {
                $out[$pair[1]] = trim($pair[3]);
            }
        }
        return $out;
    }

    /**
     * Cast string variable to PHP type or constant
     * @param $val
     * @return mixed
     */
    public function cast($val)
    {
        if ($val && preg_match('/^(?:0x[0-9a-f]+|0[0-7]+|0b[01]+)$/i', $val)) {
            return intval($val, 0);
        }
        if (is_numeric($val)) {
            return $val + 0;
        }
        $val = trim($val ?: '');
        if (preg_match('/^\w+$/i', $val) && defined($val)) {
            return constant($val);
        }
        return $val;
    }

    /**
    *    Convert JS-style token to PHP expression
    *    @return string
    *    @param $str string
    *    @param $evaluate bool compile expressions as well or only convert variable access
    **/
    public function compile($str, $evaluate = true)
    {
        $compile_result = '';
        if (!$evaluate) {
            $compile_result = preg_replace_callback(
                '/^@(\w+)((?:\..+|\[(?:(?:[^\[\]]*|(?R))*)\])*)/',
                function ($expr) {
                    $str = '$' . $expr[1];
                    if (isset($expr[2])) {
                        $str .= preg_replace_callback(
                            '/\.([^.\[\]]+)|\[((?:[^\[\]\'"]*|(?R))*)\]/',
                            function ($sub) {
                                $val = isset($sub[2]) ? $sub[2] : $sub[1];
                                if (ctype_digit($val)) {
                                    $val = (int)$val;
                                }
                                $out = '[' . $this->export($val) . ']';
                                return $out;
                            },
                            $expr[2]
                        );
                    }
                    return $str;
                },
                $str
            );
        } else {
            $compile_result = preg_replace_callback(
                '/(?<!\w)@(\w+(?:(?:\->|::)\w+)?)' .
                '((?:\.\w+|\[(?:(?:[^\[\]]*|(?R))*)\]|(?:\->|::)\w+|\()*)/',
                function ($expr) {
                    $str = '$' . $expr[1];
                    if (isset($expr[2])) {
                        $str .= preg_replace_callback(
                            '/\.(\w+)(\()?|\[((?:[^\[\]]*|(?R))*)\]/',
                            function ($sub) {
                                if (empty($sub[2])) {
                                    if (ctype_digit($sub[1])) {
                                        $sub[1] = (int)$sub[1];
                                    }
                                    $out = '[' .
                                        (isset($sub[3]) ?
                                            $this->compile($sub[3]) :
                                            $this->export($sub[1])) .
                                    ']';
                                } else {
                                    $out = function_exists($sub[1]) ?
                                        $sub[0] :
                                        ('[' . $this->export($sub[1]) . ']' . $sub[2]);
                                }
                                return $out;
                            },
                            $expr[2]
                        );
                    }
                    return $str;
                },
                $str
            );
        }
        return $compile_result;
    }

    /**
    *    Get hive key reference/contents; Add non-existent hive keys,
    *    array elements, and object properties by default
    *    @return mixed
    *    @param $key string
    *    @param $add bool
    *    @param $var mixed
    **/
    public function &ref($key, $add = true, &$var = null)
    {
        $null = null;
        $parts = $this->cut($key);
        if ($parts[0] == 'SESSION') {
            if (!headers_sent() && session_status() != PHP_SESSION_ACTIVE) {
                session_start();
            }
            $this->sync('SESSION');
        } elseif (!preg_match('/^\w+$/', $parts[0])) {
            user_error(
                sprintf(self::E_Hive, $this->stringify($key)),
                E_USER_ERROR
            );
        }
        if (is_null($var)) {
            if ($add) {
                $var=&$this->hive;
            } else {
                $var = $this->hive;
            }
        }
        $obj = false;
        foreach ($parts as $part) {
            if ($part == '->') {
                $obj = true;
            } elseif ($obj) {
                $obj = false;
                if (!is_object($var)) {
                    $var = new stdClass();
                }
                if ($add || property_exists($var, $part)) {
                    $var=&$var->$part;
                } else {
                    $var=&$null;
                    break;
                }
            } else {
                if (!is_array($var)) {
                    $var = [];
                }
                if ($add || array_key_exists($part, $var)) {
                    $var=&$var[$part];
                } else {
                    $var=&$null;
                    break;
                }
            }
        }
        return $var;
    }

    /**
    *    Return TRUE if hive key is set
    *    (or return timestamp and TTL if cached)
    *    @return bool
    *    @param $key string
    *    @param $val mixed
    **/
    public function exists($key, &$val = null)
    {
        $val = $this->ref($key, false);
        return isset($val) ?
         true :
         (Cache::instance()->exists($this->hash($key) . '.var', $val) ?: false);
    }

    /**
    *    Return TRUE if hive key is empty and not cached
    *    @param $key string
    *    @param $val mixed
    *    @return bool
    **/
    public function devoid($key, &$val = null)
    {
        $val = $this->ref($key, false);
        return empty($val) &&
         (!Cache::instance()->exists($this->hash($key) . '.var', $val) ||
             !$val);
    }

    /**
    *    Bind value to hive key
    *    @return mixed
    *    @param $key string
    *    @param $val mixed
    *    @param $ttl int
    **/
    public function set($key, $val, $ttl = 0)
    {
        $time = (int)$this->hive['TIME'];
        if (preg_match('/^(GET|POST|COOKIE)\b(.+)/', $key, $expr)) {
            $this->set('REQUEST' . $expr[2], $val);
            if ($expr[1] == 'COOKIE') {
                $parts = $this->cut($key);
                $jar = $this->unserialize($this->serialize($this->hive['JAR']));
                unset($jar['lifetime']);
                if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                    unset($jar['expire']);
                    if (isset($_COOKIE[$parts[1]])) {
                        setcookie($parts[1], '', ['expires' => 0] + $jar);
                    }
                    if ($ttl) {
                        $jar['expires'] = $time + $ttl;
                    }
                    setcookie($parts[1], $val ?: '', $jar);
                } else {
                    unset($jar['samesite']);
                    if (isset($_COOKIE[$parts[1]])) {
                        call_user_func_array(
                            'setcookie',
                            array_merge([$parts[1],''], ['expire' => 0] + $jar)
                        );
                    }
                    if ($ttl) {
                        $jar['expire'] = $time + $ttl;
                    }
                    call_user_func_array('setcookie', [$parts[1],$val ?: ''] + $jar);
                }
                $_COOKIE[$parts[1]] = $val;
                return $val;
            }
        } else {
            switch ($key) {
                case 'CACHE':
                     $val = Cache::instance()->load($val);
                    break;
                case 'ENCODING':
                    ini_set('default_charset', $val);
                    if (extension_loaded('mbstring')) {
                        mb_internal_encoding($val);
                    }
                    break;
                case 'FALLBACK':
                    $this->fallback = $val;
                    $lang = $this->language($this->hive['LANGUAGE']);
                    // I think this is intention :grin-sweat:
                case 'LANGUAGE':
                    if (!isset($lang)) {
                        $val = $this->language($val);
                    }
                    $lex = $this->lexicon($this->hive['LOCALES'], $ttl);
                    // I think this is intention :grin-sweat:
                case 'LOCALES':
                    if (isset($lex) || $lex = $this->lexicon($val, $ttl)) {
                        foreach ($lex as $dt => $dd) {
                            $ref=&$this->ref($this->hive['PREFIX'] . $dt);
                            $ref = $dd;
                            unset($ref);
                        }
                    }
                    break;
                case 'TZ':
                    date_default_timezone_set($val);
                    break;
            }
        }
        $ref=&$this->ref($key);
        $ref = $val;
        if (preg_match('/^JAR\b/', $key)) {
            if ($key == 'JAR.lifetime') {
                $this->set('JAR.expire', $val == 0 ? 0 :
                 (is_int($val) ? $time + $val : strtotime($val)));
            } else {
                if ($key == 'JAR.expire') {
                    $this->hive['JAR']['lifetime'] = max(0, $val - $time);
                }
                $jar = $this->unserialize($this->serialize($this->hive['JAR']));
                unset($jar['expire']);
                if (!headers_sent() && session_status() != PHP_SESSION_ACTIVE) {
                    if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                        session_set_cookie_params($jar);
                    } else {
                        unset($jar['samesite']);
                        call_user_func_array('session_set_cookie_params', $jar);
                    }
                }
            }
        }
        if ($ttl) {
         // Persist the key-value pair
            Cache::instance()->set($this->hash($key) . '.var', $val, $ttl);
        }
        return $ref;
    }

    /**
    *    Retrieve contents of hive key
    *    @return mixed
    *    @param $key string
    *    @param $args string|array
    **/
    public function get($key, $args = null)
    {
        if (is_string($val = $this->ref($key, false)) && !is_null($args)) {
            return call_user_func_array(
                [$this,'format'],
                array_merge([$val], is_array($args) ? $args : [$args])
            );
        }
        if (is_null($val)) {
            // Attempt to retrieve from cache
            if (Cache::instance()->exists($this->hash($key) . '.var', $data)) {
                return $data;
            }
        }
        return $val;
    }

    /**
    *    Unset hive key
    *    @param $key string
    **/
    public function clear($key)
    {
     // Normalize array literal
        $cache = Cache::instance();
        $parts = $this->cut($key);
        if ($key == 'CACHE') {
         // Clear cache contents
            $cache->reset();
        } elseif (preg_match('/^(GET|POST|COOKIE)\b(.+)/', $key, $expr)) {
            $this->clear('REQUEST' . $expr[2]);
            if ($expr[1] == 'COOKIE') {
                $parts = $this->cut($key);
                $jar = $this->hive['JAR'];
                unset($jar['lifetime']);
                $jar['expire'] = 0;
                if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                    $jar['expires'] = $jar['expire'];
                    unset($jar['expire']);
                    setcookie($parts[1], '', $jar);
                } else {
                    unset($jar['samesite']);
                    call_user_func_array(
                        'setcookie',
                        array_merge([$parts[1],''], $jar)
                    );
                }
                unset($_COOKIE[$parts[1]]);
            }
        } elseif ($parts[0] == 'SESSION') {
            if (!headers_sent() && session_status() != PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (empty($parts[1])) {
                // End session
                session_unset();
                session_destroy();
                $this->clear('COOKIE.' . session_name());
            }
            $this->sync('SESSION');
        }
        if (!isset($parts[1]) && array_key_exists($parts[0], $this->init)) {
         // Reset global to default value
            $this->hive[$parts[0]] = $this->init[$parts[0]];
        } else {
            $val = preg_replace(
                '/^(\$hive)/',
                '$this->hive',
                $this->compile('@hive.' . $key, false)
            );
            eval('unset(' . $val . ');');
            if ($parts[0] == 'SESSION') {
                session_commit();
                session_start();
            }
            if ($cache->exists($hash = $this->hash($key) . '.var')) {
             // Remove from cache
                $cache->clear($hash);
            }
        }
    }

    /**
    *    Return TRUE if hive variable is 'on'
    *    @return bool
    *    @param $key string
    **/
    public function checked($key)
    {
        $ref=&$this->ref($key);
        return $ref == 'on';
    }

    /**
    *    Return TRUE if property has public visibility
    *    @return bool
    *    @param $obj object
    *    @param $key string
    **/
    public function visible($obj, $key)
    {
        if (property_exists($obj, $key)) {
            $ref = new ReflectionProperty(get_class($obj), $key);
            $out = $ref->ispublic();
            unset($ref);
            return $out;
        }
        return false;
    }

    /**
    *    Multi-variable assignment using associative array
    *    @param $vars array
    *    @param $prefix string
    *    @param $ttl int
    **/
    public function mset(array $vars, $prefix = '', $ttl = 0)
    {
        foreach ($vars as $key => $val) {
            $this->set($prefix . $key, $val, $ttl);
        }
    }

    /**
    *    Publish hive contents
    *    @return array
    **/
    public function hive()
    {
        return $this->hive;
    }

    /**
    *    Copy contents of hive variable to another
    *    @return mixed
    *    @param $src string
    *    @param $dst string
    **/
    public function copy($src, $dst)
    {
        $ref=&$this->ref($dst);
        return $ref = $this->ref($src, false);
    }

    /**
    *    Concatenate string to hive string variable
    *    @return string
    *    @param $key string
    *    @param $val string
    **/
    public function concat($key, $val)
    {
        $ref=&$this->ref($key);
        $ref .= $val;
        return $ref;
    }

    /**
    *    Swap keys and values of hive array variable
    *    @return array
    *    @param $key string
    *    @public
    **/
    public function flip($key)
    {
        $ref=&$this->ref($key);
        return $ref = array_combine(array_values($ref), array_keys($ref));
    }

    /**
    *    Add element to the end of hive array variable
    *    @return mixed
    *    @param $key string
    *    @param $val mixed
    **/
    public function push($key, $val)
    {
        $ref=&$this->ref($key);
        $ref[] = $val;
        return $val;
    }

    /**
    *    Remove last element of hive array variable
    *    @return mixed
    *    @param $key string
    **/
    public function pop($key)
    {
        $ref=&$this->ref($key);
        return array_pop($ref);
    }

    /**
    *    Add element to the beginning of hive array variable
    *    @return mixed
    *    @param $key string
    *    @param $val mixed
    **/
    public function unshift($key, $val)
    {
        $ref=&$this->ref($key);
        array_unshift($ref, $val);
        return $val;
    }

    /**
    *    Remove first element of hive array variable
    *    @return mixed
    *    @param $key string
    **/
    public function shift($key)
    {
        $ref=&$this->ref($key);
        return array_shift($ref);
    }

    /**
    *    Merge array with hive array variable
    *    @return array
    *    @param $key string
    *    @param $src string|array
    *    @param $keep bool
    **/
    public function merge($key, $src, $keep = false)
    {
        $ref=&$this->ref($key);
        if (!$ref) {
            $ref = [];
        }
        $out = array_merge($ref, is_string($src) ? $this->hive[$src] : $src);
        if ($keep) {
            $ref = $out;
        }
        return $out;
    }

    /**
    *    Extend hive array variable with default values from $src
    *    @return array
    *    @param $key string
    *    @param $src string|array
    *    @param $keep bool
    **/
    public function extend($key, $src, $keep = false)
    {
        $ref=&$this->ref($key);
        if (!$ref) {
            $ref = [];
        }
        $out = array_replace_recursive(
            is_string($src) ? $this->hive[$src] : $src,
            $ref
        );
        if ($keep) {
            $ref = $out;
        }
        return $out;
    }

    /**
    *    Convert backslashes to slashes
    *    @return string
    *    @param $str string
    **/
    public function fixslashes($str)
    {
        return $str ? strtr($str, '\\', '/') : $str;
    }

    /**
    *    Split comma-, semi-colon, or pipe-separated string
    *    @return array
    *    @param $str string
    *    @param $noempty bool
    **/
    public function split($str, $noempty = true)
    {
        return array_map(
            'trim',
            preg_split('/[,;|]/', $str ?: '', 0, $noempty ? PREG_SPLIT_NO_EMPTY : 0)
        );
    }

    /**
    *    Convert PHP expression/value to compressed exportable string
    *    @return string
    *    @param $arg mixed
    *    @param $stack array
    **/
    public function stringify($arg, array $stack = null)
    {
        if ($stack) {
            foreach ($stack as $node) {
                if ($arg === $node) {
                    return '*RECURSION*';
                }
            }
        } else {
            $stack = [];
        }
        switch (gettype($arg)) {
            case 'object':
                $str = '';
                foreach (get_object_vars($arg) as $key => $val) {
                    $str .= ($str ? ',' : '') .
                     $this->export($key) . '=>' .
                     $this->stringify(
                         $val,
                         array_merge($stack, [$arg])
                     );
                }
                return get_class($arg) . '::__set_state([' . $str . '])';
            case 'array':
                $str = '';
                $num = isset($arg[0]) &&
                 ctype_digit(implode('', array_keys($arg)));
                foreach ($arg as $key => $val) {
                    $str .= ($str ? ',' : '') .
                     ($num ? '' : ($this->export($key) . '=>')) .
                     $this->stringify($val, array_merge($stack, [$arg]));
                }
                return '[' . $str . ']';
            default:
                return $this->export($arg);
        }
    }

    /**
    *    Flatten array values and return as CSV string
    *    @return string
    *    @param $args array
    **/
    public function csv(array $args)
    {
        return implode(',', array_map(
            'stripcslashes',
            array_map([$this,'stringify'], $args)
        ));
    }

    /**
    *    Convert snakecase string to camelcase
    *    @return string
    *    @param $str string
    **/
    public function camelcase($str)
    {
        return preg_replace_callback(
            '/_(\pL)/u',
            function ($match) {
                return strtoupper($match[1]);
            },
            $str
        );
    }

    /**
    *    Convert camelcase string to snakecase
    *    @return string
    *    @param $str string
    **/
    public function snakecase($str)
    {
        return strtolower(preg_replace('/(?!^)\p{Lu}/u', '_\0', $str));
    }

    /**
    *    Return -1 if specified number is negative, 0 if zero,
    *    or 1 if the number is positive
    *    @return int
    *    @param $num mixed
    **/
    public function sign($num)
    {
        return $num ? ($num / abs($num)) : 0;
    }

    /**
    *    Extract values of array whose keys start with the given prefix
    *    @return array
    *    @param $arr array
    *    @param $prefix string
    **/
    public function extract($arr, $prefix)
    {
        $out = [];
        foreach (preg_grep('/^' . preg_quote($prefix, '/') . '/', array_keys($arr)) as $key) {
            $out[substr($key, strlen($prefix))] = $arr[$key];
        }
        return $out;
    }

    /**
    *    Convert class constants to array
    *    @return array
    *    @param $class object|string
    *    @param $prefix string
    **/
    public function constants($class, $prefix = '')
    {
        $ref = new ReflectionClass($class);
        return $this->extract($ref->getconstants(), $prefix);
    }

    /**
    *    Generate 64bit/base36 hash
    *    @return string
    *    @param $str
    **/
    public function hash($str)
    {
        return str_pad(base_convert(
            substr(sha1($str ?: ''), -16),
            16,
            36
        ), 11, '0', STR_PAD_LEFT);
    }

    /**
    *    Return Base64-encoded equivalent
    *    @return string
    *    @param $data string
    *    @param $mime string
    **/
    public function base64($data, $mime)
    {
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
    *    Convert special characters to HTML entities
    *    @return string
    *    @param $str string
    **/
    public function encode($str)
    {
        return @htmlspecialchars(
            $str,
            $this->hive['BITMASK'],
            $this->hive['ENCODING']
        ) ?: $this->scrub($str);
    }

    /**
    *    Convert HTML entities back to characters
    *    @return string
    *    @param $str string
    **/
    public function decode($str)
    {
        return htmlspecialchars_decode($str, $this->hive['BITMASK']);
    }

    /**
    *    Invoke callback recursively for all data types
    *    @return mixed
    *    @param $arg mixed
    *    @param $func callback
    *    @param $stack array
    **/
    public function recursive($arg, $func, $stack = [])
    {
        if ($stack) {
            foreach ($stack as $node) {
                if ($arg === $node) {
                    return $arg;
                }
            }
        }
        switch (gettype($arg)) {
            case 'object':
                $ref = new ReflectionClass($arg);
                if ($ref->iscloneable()) {
                    $arg = clone($arg);
                    $cast = ($it = is_a($arg, 'IteratorAggregate')) ?
                     iterator_to_array($arg) : get_object_vars($arg);
                    foreach ($cast as $key => $val) {
                        // skip inaccessible properties #350
                        if (!$it && !isset($arg->$key)) {
                            continue;
                        }
                        $arg->$key = $this->recursive(
                            $val,
                            $func,
                            array_merge($stack, [$arg])
                        );
                    }
                }
                return $arg;
            case 'array':
                $copy = [];
                foreach ($arg as $key => $val) {
                    $copy[$key] = $this->recursive(
                        $val,
                        $func,
                        array_merge($stack, [$arg])
                    );
                }
                return $copy;
        }
        return $func($arg);
    }

    /**
    *    Remove HTML tags (except those enumerated) and non-printable
    *    characters to mitigate XSS/code injection attacks
    *    @return mixed
    *    @param $arg mixed
    *    @param $tags string
    **/
    public function clean($arg, $tags = null)
    {
        return $this->recursive(
            $arg,
            function ($val) use ($tags) {
                if ($tags != '*') {
                    $val = trim(strip_tags(
                        $val ?? '',
                        '<' . implode('><', $this->split($tags)) . '>'
                    ));
                }
                return trim(preg_replace(
                    '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
                    '',
                    $val
                ));
            }
        );
    }

    /**
    *    Similar to clean(), except that variable is passed by reference
    *    @return mixed
    *    @param $var mixed
    *    @param $tags string
    **/
    public function scrub(&$var, $tags = null)
    {
        return $var = $this->clean($var, $tags);
    }

    /**
    *    Return locale-aware formatted string
    *    @return string
    **/
    public function format()
    {
        $args = func_get_args();
        $val = array_shift($args);
     // Get formatting rules
        $conv = localeconv();
        return preg_replace_callback(
            '/\{\s*(?P<pos>\d+)\s*(?:,\s*(?P<type>\w+)\s*' .
            '(?:,\s*(?P<mod>(?:\w+(?:\s*\{.+?\}\s*,?\s*)?)*)' .
            '(?:,\s*(?P<prop>.+?))?)?)?\s*\}/',
            function ($expr) use ($args, $conv) {
                /**
                 * @var string $pos
                 * @var string $mod
                 * @var string $type
                 * @var string $prop
                 */
                extract($expr);
                /**
                 * @var string $thousands_sep
                 * @var string $negative_sign
                 * @var string $positive_sign
                 * @var string $frac_digits
                 * @var string $decimal_point
                 * @var string $int_curr_symbol
                 * @var string $currency_symbol
                 */
                extract($conv);
                if (!array_key_exists($pos, $args)) {
                    return $expr[0];
                }
                if (isset($type)) {
                    if (isset($this->hive['FORMATS'][$type])) {
                        return $this->call(
                            $this->hive['FORMATS'][$type],
                            [
                             $args[$pos],
                             isset($mod) ? $mod : null,
                             isset($prop) ? $prop : null
                            ]
                        );
                    }
                    $php81 = version_compare(PHP_VERSION, '8.1.0') >= 0;
                    switch ($type) {
                        case 'plural':
                            preg_match_all(
                                '/(?<tag>\w+)' .
                                '(?:\s*\{\s*(?<data>.*?)\s*\})/',
                                $mod,
                                $matches,
                                PREG_SET_ORDER
                            );
                            $ord = ['zero','one','two'];
                            foreach ($matches as $match) {
                                   /** @var string $tag */
                                   /** @var string $data */
                                   extract($match);
                                if (isset($ord[$args[$pos]]) &&
                                       $tag == $ord[$args[$pos]] || $tag == 'other'
                                ) {
                                    return str_replace('#', $args[$pos], $data);
                                }
                            }
                            // I think this is intentional :grin-sweat:
                        case 'number':
                            if (isset($mod)) {
                                switch ($mod) {
                                    case 'integer':
                                        return number_format(
                                            $args[$pos],
                                            0,
                                            '',
                                            $thousands_sep
                                        );
                                    case 'currency':
                                        $int = $cstm = false;
                                        if (isset($prop) &&
                                            $cstm = !$int = ($prop == 'int')
                                        ) {
                                            $currency_symbol = $prop;
                                        }
                                        if (!$cstm &&
                                            function_exists('money_format') &&
                                            version_compare(PHP_VERSION, '7.4.0') < 0
                                        ) {
                                            return money_format(
                                                '%' . ($int ? 'i' : 'n'),
                                                $args[$pos]
                                            );
                                        }
                                        $fmt = [
                                           0 => '(nc)',1 => '(n c)',
                                           2 => '(nc)',10 => '+nc',
                                           11 => '+n c',12 => '+ nc',
                                           20 => 'nc+',21 => 'n c+',
                                           22 => 'nc +',30 => 'n+c',
                                           31 => 'n +c',32 => 'n+ c',
                                           40 => 'nc+',41 => 'n c+',
                                           42 => 'nc +',100 => '(cn)',
                                           101 => '(c n)',102 => '(cn)',
                                           110 => '+cn',111 => '+c n',
                                           112 => '+ cn',120 => 'cn+',
                                           121 => 'c n+',122 => 'cn +',
                                           130 => '+cn',131 => '+c n',
                                           132 => '+ cn',140 => 'c+n',
                                           141 => 'c+ n',142 => 'c +n'
                                        ];
                                        if ($args[$pos] < 0) {
                                            $sgn = $negative_sign;
                                            $pre = 'n';
                                        } else {
                                            $sgn = $positive_sign;
                                            $pre = 'p';
                                        }
                                        return str_replace(
                                            ['+','n','c'],
                                            [$sgn,number_format(
                                                abs($args[$pos]),
                                                $frac_digits,
                                                $decimal_point,
                                                $thousands_sep
                                            ),
                                            $int ? $int_curr_symbol
                                                : $currency_symbol],
                                            $fmt[(int)(
                                            (${$pre . '_cs_precedes'} % 2) .
                                            (${$pre . '_sign_posn'} % 5) .
                                            (${$pre . '_sep_by_space'} % 3)
                                            )]
                                        );
                                    case 'percent':
                                        return number_format(
                                            $args[$pos] * 100,
                                            0,
                                            $decimal_point,
                                            $thousands_sep
                                        ) . '%';
                                }
                            }
                            $frac = $args[$pos] - (int)$args[$pos];
                            return number_format(
                                $args[$pos],
                                isset($prop) ?
                                 $prop :
                                 ($frac ? strlen($frac) - 2 : 0),
                                $decimal_point,
                                $thousands_sep
                            );
                        case 'date':
                            if ($php81) {
                                $lang = $this->split($this->LANGUAGE);
                                // requires intl extension
                                $dateType = (empty($mod) || $mod == 'short') ? IntlDateFormatter::SHORT :
                                 ($mod == 'full' ? IntlDateFormatter::FULL : IntlDateFormatter::LONG);
                                $pattern = $dateType === IntlDateFormatter::SHORT
                                 ? (($ptn = \IntlDatePatternGenerator::create($lang[0]))
                                     ? $ptn->getBestPattern('yyyyMMdd') : null) : null;
                                $formatter = new IntlDateFormatter(
                                    $lang[0],
                                    $dateType,
                                    IntlDateFormatter::NONE,
                                    null,
                                    null,
                                    $pattern
                                );
                                return $formatter->format($args[$pos]);
                            } else {
                                if (empty($mod) || $mod == 'short') {
                                    $prop = '%x';
                                } elseif ($mod == 'full') {
                                    $prop = '%A, %d %B %Y';
                                } elseif ($mod != 'custom') {
                                    $prop = '%d %B %Y';
                                }
                                return strftime($prop, $args[$pos]);
                            }
                        case 'time':
                            if ($php81) {
                                      $lang = $this->split($this->LANGUAGE);
                                      // requires intl extension
                                      $formatter = new IntlDateFormatter(
                                          $lang[0],
                                          IntlDateFormatter::NONE,
                                          (empty($mod) || $mod == 'short')
                                          ? IntlDateFormatter::SHORT :
                                          ($mod == 'full' ? IntlDateFormatter::LONG : IntlDateFormatter::MEDIUM),
                                          IntlTimeZone::createTimeZone($this->hive['TZ'])
                                      );
                                      return $formatter->format($args[$pos]);
                            } else {
                                if (empty($mod) || $mod == 'short') {
                                    $prop = '%X';
                                } elseif ($mod != 'custom') {
                                    $prop = '%r';
                                }
                                return strftime($prop, $args[$pos]);
                            }
                        default:
                            return $expr[0];
                    }
                }
                return $args[$pos];
            },
            $val
        );
    }

    /**
    *    Return string representation of expression
    *    @return string
    *    @param $expr mixed
    **/
    public function export($expr)
    {
        return var_export($expr, true);
    }

    /**
    *    Assign/auto-detect language
    *    @return string
    *    @param $code string
    **/
    public function language($code)
    {
        $code = preg_replace('/\h+|;q=[0-9.]+/', '', $code ?: '');
        $code .= ($code ? ',' : '') . $this->fallback;
        $this->languages = [];
        foreach (array_reverse(explode(',', $code)) as $lang) {
            if (preg_match('/^(\w{2})(?:-(\w{2}))?\b/i', $lang, $parts)) {
                // Generic language
                array_unshift($this->languages, $parts[1]);
                if (isset($parts[2])) {
                    // Specific language
                    $parts[0] = $parts[1] . '-' . ($parts[2] = strtoupper($parts[2]));
                    array_unshift($this->languages, $parts[0]);
                }
            }
        }
        $this->languages = array_unique($this->languages);
        $locales = [];
        $windows = preg_match('/^win/i', PHP_OS);
     // Work around PHP's Turkish locale bug
        foreach (preg_grep('/^(?!tr)/i', $this->languages) as $locale) {
            if ($windows) {
                $parts = explode('-', $locale);
                if (!defined('ISO::LC_' . $parts[0])) {
                    continue;
                }
                $locale = constant('ISO::LC_' . $parts[0]);
                if (isset($parts[1]) &&
                    defined($cc = 'ISO::CC_' . strtolower($parts[1]))
                ) {
                    $locale .= '-' . constant($cc);
                }
            }
            $locale = str_replace('-', '_', $locale);
            $locales[] = $locale . '.' . ini_get('default_charset');
            $locales[] = $locale;
        }
        setlocale(LC_ALL, $locales);
        return $this->hive['LANGUAGE'] = implode(',', $this->languages);
    }

    /**
    *    Return lexicon entries
    *    @return array
    *    @param $path string
    *    @param $ttl int
    **/
    public function lexicon($path, $ttl = 0)
    {
        $languages = $this->languages ?: explode(',', $this->fallback);
        $cache = Cache::instance();
        if ($ttl && $cache->exists(
            $hash = $this->hash(implode(',', $languages) . $path) . '.dic',
            $lex
        )
        ) {
            return $lex;
        }
        $lex = [];
        foreach ($languages as $lang) {
            foreach ($this->split($path) as $dir) {
                if ((is_file($file = ($base = $dir . $lang) . '.php') ||
                    is_file($file = $base . '.php')) &&
                    is_array($dict = require($file))
                ) {
                    $lex += $dict;
                } elseif (is_file($file = $base . '.json') &&
                    is_array($dict = json_decode(file_get_contents($file), true))
                ) {
                    $lex += $dict;
                } elseif (is_file($file = $base . '.ini')) {
                    preg_match_all(
                        '/(?<=^|\n)(?:' .
                        '\[(?<prefix>.+?)\]|' .
                        '(?<lval>[^\h\r\n;].*?)\h*=\h*' .
                        '(?<rval>(?:\\\\\h*\r?\n|.+?)*)' .
                        ')(?=\r?\n|$)/',
                        $this->read($file),
                        $matches,
                        PREG_SET_ORDER
                    );
                    if ($matches) {
                        $prefix = '';
                        foreach ($matches as $match) {
                            if ($match['prefix']) {
                                $prefix = $match['prefix'] . '.';
                            } elseif (!array_key_exists(
                                $key = $prefix . $match['lval'],
                                $lex
                            )
                            ) {
                                $lex[$key] = trim(preg_replace(
                                    '/\\\\\h*\r?\n/',
                                    "\n",
                                    $match['rval']
                                ));
                            }
                        }
                    }
                }
            }
        }
        if ($ttl) {
            $cache->set($hash, $lex, $ttl);
        }
             return $lex;
    }

    /**
    *    Return string representation of PHP value
    *    @return string
    *    @param $arg mixed
    **/
    public function serialize($arg)
    {
        switch (strtolower($this->hive['SERIALIZER'])) {
            case 'igbinary':
                return igbinary_serialize($arg);
            default:
                return serialize($arg);
        }
    }

    /**
    *    Return PHP value derived from string
    *    @return string
    *    @param $arg mixed
    **/
    public function unserialize($arg)
    {
        switch (strtolower($this->hive['SERIALIZER'])) {
            case 'igbinary':
                return igbinary_unserialize($arg);
            default:
                return unserialize($arg);
        }
    }

    /**
    *    Send HTTP status header; Return text equivalent of status code
    *    @return string
    *    @param $code int
    **/
    public function status($code)
    {
        $reason = @constant('self::HTTP_' . $code);
        if (!$this->hive['CLI'] && !headers_sent()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $reason);
        }
        return $reason;
    }

    /**
    *    Send cache metadata to HTTP client
    *    @param $secs int
    **/
    public function expire($secs = 0)
    {
        if (!$this->hive['CLI'] && !headers_sent()) {
            $secs = (int)$secs;
            if ($this->hive['PACKAGE']) {
                header('X-Powered-By: ' . $this->hive['PACKAGE']);
            }
            if ($this->hive['XFRAME']) {
                header('X-Frame-Options: ' . $this->hive['XFRAME']);
            }
            header('X-XSS-Protection: 1; mode=block');
            header('X-Content-Type-Options: nosniff');
            if ($this->hive['VERB'] == 'GET' && $secs) {
                $time = microtime(true);
                header_remove('Pragma');
                header('Cache-Control: max-age=' . $secs);
                header('Expires: ' . gmdate('r', round($time + $secs)));
                header('Last-Modified: ' . gmdate('r'));
            } else {
                header('Pragma: no-cache');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Expires: ' . gmdate('r', 0));
            }
        }
    }

    /**
    *    Return HTTP user agent
    *    @return string
    **/
    public function agent()
    {
        $headers = $this->hive['HEADERS'];
        return isset($headers['X-Operamini-Phone-UA']) ?
         $headers['X-Operamini-Phone-UA'] :
         (isset($headers['X-Skyfire-Phone']) ?
             $headers['X-Skyfire-Phone'] :
             (isset($headers['User-Agent']) ?
                 $headers['User-Agent'] : ''));
    }

    /**
    *    Return TRUE if XMLHttpRequest detected
    *    @return bool
    **/
    public function ajax()
    {
        $headers = $this->hive['HEADERS'];
        return isset($headers['X-Requested-With']) &&
         $headers['X-Requested-With'] == 'XMLHttpRequest';
    }

    /**
    *    Sniff IP address
    *    @return string
    **/
    public function ip()
    {
        $headers = $this->hive['HEADERS'];
        return isset($headers['Client-IP']) ?
         $headers['Client-IP'] :
         (isset($headers['X-Forwarded-For']) ?
             explode(',', $headers['X-Forwarded-For'])[0] :
             (isset($_SERVER['REMOTE_ADDR']) ?
                 $_SERVER['REMOTE_ADDR'] : ''));
    }

    /**
    *    Return filtered stack trace as a formatted string (or array)
    *    @return string|array
    *    @param $trace array|NULL
    *    @param $format bool
    **/
    public function trace(array $trace = null, $format = true)
    {
        if (!$trace) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $frame = $trace[0];
            if (isset($frame['file']) && $frame['file'] == __FILE__) {
                array_shift($trace);
            }
        }
        $debug = $this->hive['DEBUG'];
        $trace = array_filter(
            $trace,
            function ($frame) use ($debug) {
                return isset($frame['file']) &&
                 ($debug > 1 ||
                 (($frame['file'] != __FILE__ || $debug) &&
                 (empty($frame['function']) ||
                 !preg_match('/^(?:(?:trigger|user)_error|' .
                     '__call|call_user_func)/', $frame['function']))));
            }
        );
        if (!$format) {
            return $trace;
        }
        $out = '';
        $eol = "\n";
     // Analyze stack trace
        foreach ($trace as $frame) {
            $line = '';
            if (isset($frame['class'])) {
                $line .= $frame['class'] . $frame['type'];
            }
            if (isset($frame['function'])) {
                $line .= $frame['function'] . '(' .
                 ($debug > 2 && isset($frame['args']) ?
                     $this->csv($frame['args']) : '') . ')';
            }
            $src = $this->fixslashes(str_replace($_SERVER['DOCUMENT_ROOT'] .
                '/', '', $frame['file'])) . ':' . $frame['line'];
            $out .= '[' . $src . '] ' . $line . $eol;
        }
        return $out;
    }

    /**
    *    Log error; Execute ONERROR handler if defined, else display
    *    default error page (HTML for synchronous requests, JSON string
    *    for AJAX requests)
    *    @param $code int
    *    @param $text string
    *    @param $trace array
    *    @param $level int
    **/
    public function error($code, $text = '', array $trace = null, $level = 0)
    {
        $prior = $this->hive['ERROR'];
        $header = $this->status($code);
        $req = $this->hive['VERB'] . ' ' . $this->hive['PATH'];
        if ($this->hive['QUERY']) {
            $req .= '?' . $this->hive['QUERY'];
        }
        if (!$text) {
            $text = 'HTTP ' . $code . ' (' . $req . ')';
        }
        $trace = $this->trace($trace);
        $loggable = $this->hive['LOGGABLE'];
        if (!is_array($loggable)) {
            $loggable = $this->split($loggable);
        }
        foreach ($loggable as $status) {
            if ($status == '*' ||
                preg_match('/^' . preg_replace('/\D/', '\d', $status) . '$/', (string) $code)
            ) {
                error_log($text);
                foreach (explode("\n", $trace) as $nexus) {
                    if ($nexus) {
                        error_log($nexus);
                    }
                }
                break;
            }
        }
        if ($highlight = (!$this->hive['CLI'] && !$this->hive['AJAX'] &&
            $this->hive['HIGHLIGHT'] && is_file($css = __DIR__ . '/' . self::CSS))
        ) {
            $trace = $this->highlight($trace);
        }
        $this->hive['ERROR'] = [
         'status' => $header,
         'code' => $code,
         'text' => $text,
         'trace' => $trace,
         'level' => $level
        ];
        $this->expire(-1);
        $handler = $this->hive['ONERROR'];
        $this->hive['ONERROR'] = null;
        $eol = "\n";
        if ((!$handler ||
            $this->call(
                $handler,
                [$this,$this->hive['PARAMS']],
                'beforeroute,afterroute'
            ) === false) &&
            !$prior && !$this->hive['QUIET']
        ) {
            $error = array_diff_key(
                $this->hive['ERROR'],
                $this->hive['DEBUG'] ?
                 [] :
                 ['trace' => 1]
            );
            if ($this->hive['CLI']) {
                echo PHP_EOL . '===================================' . PHP_EOL .
                 'ERROR ' . $error['code'] . ' - ' . $error['status'] . PHP_EOL .
                 $error['text'] . PHP_EOL . PHP_EOL . (isset($error['trace']) ? $error['trace'] : '');
            } else {
                echo $this->hive['AJAX'] ?
                 json_encode($error) :
                 ('<!DOCTYPE html>' . $eol .
                 '<html>' . $eol .
                 '<head>' .
                     '<title>' . $code . ' ' . $header . '</title>' .
                     ($highlight ?
                         ('<style>' . $this->read($css) . '</style>') : '') .
                 '</head>' . $eol .
                 '<body>' . $eol .
                     '<h1>' . $header . '</h1>' . $eol .
                     '<p>' . $this->encode($text ?: $req) . '</p>' . $eol .
                     ($this->hive['DEBUG'] ? ('<pre>' . $trace . '</pre>' . $eol) : '') .
                 '</body>' . $eol .
                 '</html>');
            }
        }
        if ($this->hive['HALT']) {
            die(1);
        }
    }

    /**
    *    Mock HTTP request
    *    @return mixed
    *    @param $pattern string
    *    @param $args array
    *    @param $headers array
    *    @param $body string
    **/
    public function mock(
        $pattern,
        array $args = null,
        array $headers = null,
        $body = null
    ) {
        if (!$args) {
            $args = [];
        }
        $types = ['sync','ajax','cli'];
        preg_match('/([\|\w]+)\h+(?:@(\w+)(?:(\(.+?)\))*|([^\h]+))' .
         '(?:\h+\[(' . implode('|', $types) . ')\])?/', $pattern, $parts);
        $verb = strtoupper($parts[1]);
        if ($parts[2]) {
            if (empty($this->hive['ALIASES'][$parts[2]])) {
                user_error(sprintf(self::E_Named, $parts[2]), E_USER_ERROR);
            }
            $parts[4] = $this->hive['ALIASES'][$parts[2]];
            $parts[4] = $this->build(
                $parts[4],
                isset($parts[3]) ? $this->parse($parts[3]) : []
            );
        }
        if (empty($parts[4])) {
            user_error(sprintf(self::E_Pattern, $pattern), E_USER_ERROR);
        }
        $url = parse_url($parts[4]);
        parse_str(isset($url['query']) ? $url['query'] : '', $GLOBALS['_GET']);
        if (preg_match('/GET|HEAD/', $verb)) {
            $GLOBALS['_GET'] = array_merge($GLOBALS['_GET'], $args);
        }
        $GLOBALS['_POST'] = $verb == 'POST' ? $args : [];
        $GLOBALS['_REQUEST'] = array_merge($GLOBALS['_GET'], $GLOBALS['_POST']);
        foreach ($headers ?: [] as $key => $val) {
            $_SERVER['HTTP_' . strtr(strtoupper($key), '-', '_')] = $val;
        }
        $this->hive['VERB'] = $verb;
        $this->hive['PATH'] = $url['path'];
        $this->hive['URI'] = $this->hive['BASE'] . $url['path'];
        if ($GLOBALS['_GET']) {
            $this->hive['URI'] .= '?' . http_build_query($GLOBALS['_GET']);
        }
        $this->hive['BODY'] = '';
        if (!preg_match('/GET|HEAD/', $verb)) {
            $this->hive['BODY'] = $body ?: http_build_query($args);
        }
        $this->hive['AJAX'] = isset($parts[5]) &&
         preg_match('/ajax/i', $parts[5]);
        $this->hive['CLI'] = isset($parts[5]) &&
         preg_match('/cli/i', $parts[5]);
        return $this->run();
    }

    /**
    *    Assemble url from alias name
    *    @return string
    *    @param $name string
    *    @param $params array|string
    *    @param $query string|array
    *    @param $fragment string
    **/
    public function alias($name, $params = [], $query = null, $fragment = null)
    {
        if (!is_array($params)) {
            $params = $this->parse($params);
        }
        if (empty($this->hive['ALIASES'][$name])) {
            user_error(sprintf(self::E_Named, $name), E_USER_ERROR);
        }
        $url = $this->build($this->hive['ALIASES'][$name], $params);
        if (is_array($query)) {
            $query = http_build_query($query);
        }
        return $url . ($query ? ('?' . $query) : '') . ($fragment ? '#' . $fragment : '');
    }

    /**
    *    Bind handler to route pattern
    *    @return NULL
    *    @param $pattern string|array
    *    @param $handler callback
    *    @param $ttl int
    *    @param $kbps int
    **/
    public function route($pattern, $handler, $ttl = 0, $kbps = 0)
    {
        $types = ['sync','ajax','cli'];
        $alias = null;
        if (is_array($pattern)) {
            foreach ($pattern as $item) {
                $this->route($item, $handler, $ttl, $kbps);
            }
            return;
        }
        preg_match('/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
         '(?:\h+\[(' . implode('|', $types) . ')\])?/u', $pattern, $parts);
        if (isset($parts[2]) && $parts[2]) {
            if (!preg_match('/^\w+$/', $parts[2])) {
                user_error(sprintf(self::E_Alias, $parts[2]), E_USER_ERROR);
            }
            $this->hive['ALIASES'][$alias = $parts[2]] = $parts[3];
        } elseif (!empty($parts[4])) {
            if (empty($this->hive['ALIASES'][$parts[4]])) {
                user_error(sprintf(self::E_Named, $parts[4]), E_USER_ERROR);
            }
            $parts[3] = $this->hive['ALIASES'][$alias = $parts[4]];
        }
        if (empty($parts[3])) {
            user_error(sprintf(self::E_Pattern, $pattern), E_USER_ERROR);
        }
        $type = empty($parts[5]) ? 0 : constant('self::REQ_' . strtoupper($parts[5]));
        foreach ($this->split($parts[1]) as $verb) {
            if (!preg_match('/' . self::VERBS . '/', $verb)) {
                $this->error(501, $verb . ' ' . $this->hive['URI']);
            }
            $this->hive['ROUTES'][$parts[3]][$type][strtoupper($verb)] =
             [$handler,$ttl,$kbps,$alias];
        }
    }

    /**
    *    Reroute to specified URI
    *    @return NULL
    *    @param $url array|string
    *    @param $permanent bool
    *    @param $die bool
    **/
    public function reroute($url = null, $permanent = false, $die = true)
    {
        if (!$url) {
            $url = $this->hive['REALM'];
        }
        if (is_array($url)) {
            $url = call_user_func_array([$this,'alias'], $url);
        } elseif (preg_match(
            '/^(?:@([^\/()?#]+)(?:\((.+?)\))*(\?[^#]+)*(#.+)*)/',
            $url,
            $parts
        ) && isset($this->hive['ALIASES'][$parts[1]])
        ) {
            $url = $this->build(
                $this->hive['ALIASES'][$parts[1]],
                isset($parts[2]) ? $this->parse($parts[2]) : []
            ) .
             (isset($parts[3]) ? $parts[3] : '') . (isset($parts[4]) ? $parts[4] : '');
        } else {
            $url = $this->build($url);
        }
        if (($handler = $this->hive['ONREROUTE']) &&
            $this->call($handler, [$url,$permanent,$die]) !== false
        ) {
            return;
        }
        if ($url[0] != '/' && !preg_match('/^\w+:\/\//i', $url)) {
            $url = '/' . $url;
        }
        if ($url[0] == '/' && (empty($url[1]) || $url[1] != '/')) {
            $port = $this->hive['PORT'];
            $port = in_array($port, [80,443]) ? '' : (':' . $port);
            $url = $this->hive['SCHEME'] . '://' .
             $this->hive['HOST'] . $port . $this->hive['BASE'] . $url;
        }
        if ($this->hive['CLI']) {
            $this->mock('GET ' . $url . ' [cli]');
        } else {
            header('Location: ' . $url);
            $this->status($permanent ? 301 : 302);
            if ($die) {
                die;
            }
        }
    }

    /**
    *    Provide ReST interface by mapping HTTP verb to class method
    *    @return NULL
    *    @param $url string
    *    @param $class string|object
    *    @param $ttl int
    *    @param $kbps int
    **/
    public function map($url, $class, $ttl = 0, $kbps = 0)
    {
        if (is_array($url)) {
            foreach ($url as $item) {
                $this->map($item, $class, $ttl, $kbps);
            }
            return;
        }
        foreach (explode('|', self::VERBS) as $method) {
            $this->route(
                $method . ' ' . $url,
                is_string($class) ?
                $class . '->' . $this->hive['PREMAP'] . strtolower($method) :
                [$class,$this->hive['PREMAP'] . strtolower($method)],
                $ttl,
                $kbps
            );
        }
    }

    /**
    *    Redirect a route to another URL
    *    @return NULL
    *    @param $pattern string|array
    *    @param $url string
    *    @param $permanent bool
    */
    public function redirect($pattern, $url, $permanent = true)
    {
        if (is_array($pattern)) {
            foreach ($pattern as $item) {
                $this->redirect($item, $url, $permanent);
            }
            return;
        }
        $this->route($pattern, function ($fw) use ($url, $permanent) {
            $fw->reroute($url, $permanent);
        });
    }

    /**
    *    Return TRUE if IPv4 address exists in DNSBL
    *    @return bool
    *    @param $ip string
    **/
    public function blacklisted($ip)
    {
        if ($this->hive['DNSBL'] &&
            !in_array(
                $ip,
                is_array($this->hive['EXEMPT']) ?
                 $this->hive['EXEMPT'] :
                $this->split($this->hive['EXEMPT'])
            )
        ) {
            // Reverse IPv4 dotted quad
            $rev = implode('.', array_reverse(explode('.', $ip)));
            $servers = is_array($this->hive['DNSBL']) ? $this->hive['DNSBL'] : $this->split($this->hive['DNSBL']);
            foreach ($servers as $server) {
             // DNSBL lookup
                if (checkdnsrr($rev . '.' . $server, 'A')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
    *    Applies the specified URL mask and returns parameterized matches
    *    @return $args array
    *    @param $pattern string
    *    @param $url string|NULL
    **/
    public function mask($pattern, $url = null)
    {
        if (!$url) {
            $url = $this->rel($this->hive['URI']);
        }
        $case = $this->hive['CASELESS'] ? 'i' : '';
        $wild = preg_quote($pattern, '/');
        $i = 0;
        while (is_int($pos = strpos($wild, '\*'))) {
            $wild = substr_replace($wild, '(?P<_' . $i . '>[^\?]*)', $pos, 2);
            ++$i;
        }
        preg_match('/^' .
         preg_replace(
             '/((\\\{)?@(\w+\b)(?(2)\\\}))/',
             '(?P<\3>[^\/\?]+)',
             $wild
         ) . '\/?$/' . $case . 'um', $url, $args);
        foreach (array_keys($args) as $key) {
            if (preg_match('/^_\d+$/', $key)) {
                if (empty($args['*'])) {
                    $args['*'] = $args[$key];
                } else {
                    if (is_string($args['*'])) {
                        $args['*'] = [$args['*']];
                    }
                    array_push($args['*'], $args[$key]);
                }
                unset($args[$key]);
            } elseif (is_numeric($key) && $key) {
                unset($args[$key]);
            }
        }
        return $args;
    }

    /**
    *    Match routes against incoming URI
    *    @return mixed
    **/
    public function run()
    {
        if ($this->blacklisted($this->hive['IP'])) {
         // Spammer detected
            $this->error(403);
        }
        if (!$this->hive['ROUTES']) {
         // No routes defined
            user_error(self::E_Routes, E_USER_ERROR);
        }
     // Match specific routes first
        $paths = [];
        foreach ($keys = array_keys($this->hive['ROUTES']) as $key) {
            $path = preg_replace('/@\w+/', '*@', $key);
            if (substr($path, -1) != '*') {
                $path .= '+';
            }
            $paths[] = $path;
        }
        $vals = array_values($this->hive['ROUTES']);
        array_multisort($paths, SORT_DESC, $keys, $vals);
        $this->hive['ROUTES'] = array_combine($keys, $vals);
     // Convert to BASE-relative URL
        $req = urldecode($this->hive['PATH']);
        $preflight = false;
        if ($cors = (isset($this->hive['HEADERS']['Origin']) &&
            $this->hive['CORS']['origin'])
        ) {
            $cors = $this->hive['CORS'];
            header('Access-Control-Allow-Origin: ' . $cors['origin']);
            header('Access-Control-Allow-Credentials: ' .
             $this->export($cors['credentials']));
            $preflight =
                isset($this->hive['HEADERS']['Access-Control-Request-Method']);
        }
        $allowed = [];
        foreach ($this->hive['ROUTES'] as $pattern => $routes) {
            if (!$args = $this->mask($pattern, $req)) {
                continue;
            }
            ksort($args);
            $route = null;
            $ptr = $this->hive['CLI'] ? self::REQ_CLI : $this->hive['AJAX'] + 1;
            if (isset($routes[$ptr][$this->hive['VERB']]) ||
                ($preflight && isset($routes[$ptr])) ||
                isset($routes[$ptr = 0])
            ) {
                $route = $routes[$ptr];
            }
            if (!$route) {
                continue;
            }
            if (isset($route[$this->hive['VERB']]) && !$preflight) {
                if ($this->hive['VERB'] == 'GET' &&
                    preg_match('/.+\/$/', $this->hive['PATH'])
                ) {
                    $this->reroute(substr($this->hive['PATH'], 0, -1) .
                     ($this->hive['QUERY'] ? ('?' . $this->hive['QUERY']) : ''));
                }
                list($handler,$ttl,$kbps,$alias) = $route[$this->hive['VERB']];
                // Capture values of route pattern tokens
                $this->hive['PARAMS'] = $args;
                // Save matching route
                $this->hive['ALIAS'] = $alias;
                $this->hive['PATTERN'] = $pattern;
                if ($cors && $cors['expose']) {
                    header('Access-Control-Expose-Headers: ' .
                     (is_array($cors['expose']) ?
                         implode(',', $cors['expose']) : $cors['expose']));
                }
                if (is_string($handler)) {
                    // Replace route pattern tokens in handler if any
                    $handler = preg_replace_callback(
                        '/({)?@(\w+\b)(?(1)})/',
                        function ($id) use ($args) {
                            $pid = count($id) > 2 ? 2 : 1;
                            return isset($args[$id[$pid]]) ?
                             $args[$id[$pid]] :
                             $id[0];
                        },
                        $handler
                    );
                    if (preg_match('/(.+)\h*(?:->|::)/', $handler, $match) &&
                        !class_exists($match[1])
                    ) {
                        $this->error(404);
                    }
                }
                // Process request
                $result = null;
                $body = '';
                $now = microtime(true);
                if (preg_match('/GET|HEAD/', $this->hive['VERB']) && $ttl) {
                    // Only GET and HEAD requests are cacheable
                    $headers = $this->hive['HEADERS'];
                    $cache = Cache::instance();
                    $cached = $cache->exists(
                        $hash = $this->hash($this->hive['VERB'] . ' ' .
                        $this->hive['URI']) . '.url',
                        $data
                    );
                    if ($cached) {
                        if (isset($headers['If-Modified-Since']) &&
                            strtotime($headers['If-Modified-Since']) +
                             $ttl > $now
                        ) {
                            $this->status(304);
                            die;
                        }
                        // Retrieve from cache backend
                        list($headers,$body,$result) = $data;
                        if (!$this->hive['CLI']) {
                            array_walk($headers, 'header');
                        }
                        $this->expire($cached[0] + $ttl - $now);
                    } else {                      // Expire HTTP client-cached page
                        $this->expire($ttl);
                    }
                } else {
                    $this->expire(0);
                }
                if (!strlen($body)) {
                    if (!$this->hive['RAW'] && !$this->hive['BODY']) {
                        $this->hive['BODY'] = file_get_contents('php://input');
                    }
                    ob_start();
                    // Call route handler
                    $result = $this->call(
                        $handler,
                        [$this,$args,$handler],
                        'beforeroute,afterroute'
                    );
                    $body = ob_get_clean();
                    if (isset($cache) && !error_get_last()) {
                        // Save to cache backend
                        $cache->set($hash, [
                            // Remove cookies
                            preg_grep(
                                '/Set-Cookie\:/',
                                headers_list(),
                                PREG_GREP_INVERT
                            ),$body,$result], $ttl);
                    }
                }
                $this->hive['RESPONSE'] = $body;
                if (!$this->hive['QUIET']) {
                    if ($kbps) {
                        $ctr = 0;
                        foreach (str_split($body, 1024) as $part) {
                            // Throttle output
                            ++$ctr;
                            if ($ctr / $kbps > ($elapsed = microtime(true) - $now) &&
                                !connection_aborted()
                            ) {
                                usleep(round(1e6 * ($ctr / $kbps - $elapsed)));
                            }
                            echo $part;
                        }
                    } else {
                        echo $body;
                    }
                }
                if ($result || $this->hive['VERB'] != 'OPTIONS') {
                    return $result;
                }
            }
            $allowed = array_merge($allowed, array_keys($route));
        }
        if (!$allowed) {
         // URL doesn't match any route
            $this->error(404);
        } elseif (!$this->hive['CLI']) {
            if (!preg_grep('/Allow:/', $headers_send = headers_list())) {
             // Unhandled HTTP method
                header('Allow: ' . implode(',', array_unique($allowed)));
            }
            if ($cors) {
                if (!preg_grep('/Access-Control-Allow-Methods:/', $headers_send)) {
                    header('Access-Control-Allow-Methods: OPTIONS,' .
                     implode(',', $allowed));
                }
                if ($cors['headers'] &&
                    !preg_grep('/Access-Control-Allow-Headers:/', $headers_send)
                ) {
                    header('Access-Control-Allow-Headers: ' .
                     (is_array($cors['headers']) ?
                         implode(',', $cors['headers']) :
                         $cors['headers']));
                }
                if ($cors['ttl'] > 0) {
                    header('Access-Control-Max-Age: ' . $cors['ttl']);
                }
            }
            if ($this->hive['VERB'] != 'OPTIONS') {
                $this->error(405);
            }
        }
        return false;
    }

    /**
    *    Loop until callback returns TRUE (for long polling)
    *    @return mixed
    *    @param $func callback
    *    @param $args array
    *    @param $timeout int
    **/
    public function until($func, $args = null, $timeout = 60)
    {
        if (!$args) {
            $args = [];
        }
        $time = time();
        $max = ini_get('max_execution_time');
        $limit = max(0, ($max ? min($timeout, $max) : $timeout) - 1);
        $out = '';
     // Turn output buffering on
        ob_start();
     // Not for the weak of heart
        while (
            // No error occurred
            !$this->hive['ERROR'] &&
            // Got time left?
            time() - $time + 1 < $limit &&
            // Still alive?
            !connection_aborted() &&
            // Restart session
            !headers_sent() &&
            (session_status() == PHP_SESSION_ACTIVE || session_start()) &&
            // CAUTION: Callback will kill host if it never becomes truthy!
            !$out = $this->call($func, $args)
        ) {
            if (!$this->hive['CLI']) {
                session_commit();
            }
            // Hush down
            sleep(1);
        }
        ob_flush();
        flush();
        return $out;
    }

    /**
    *    Disconnect HTTP client;
    *    Set FcgidOutputBufferSize to zero if server uses mod_fcgid;
    *    Disable mod_deflate when rendering text/html output
    **/
    public function abort()
    {
        if (!headers_sent() && session_status() != PHP_SESSION_ACTIVE) {
            session_start();
        }
        $out = '';
        while (ob_get_level()) {
            $out = ob_get_clean() . $out;
        }
        if (!headers_sent()) {
            header('Content-Length: ' . strlen($out));
            header('Connection: close');
        }
        session_commit();
        echo $out;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
    *    Grab the real route handler behind the string expression
    *    @return string|array
    *    @param $func string
    *    @param $args array
    **/
    public function grab($func, $args = null)
    {
        if (preg_match('/(.+)\h*(->|::)\h*(.+)/s', $func, $parts)) {
            // Convert string to executable PHP callback
            if (!class_exists($parts[1])) {
                user_error(sprintf(self::E_Class, $parts[1]), E_USER_ERROR);
            }
            if ($parts[2] == '->') {
                if (is_subclass_of($parts[1], 'Prefab')) {
                    $parts[1] = call_user_func($parts[1] . '::instance');
                } elseif (isset($this->hive['CONTAINER'])) {
                    $container = $this->hive['CONTAINER'];
                    if (is_object($container) && is_callable([$container,'has'])
                        && $container->has($parts[1])
                    ) { // PSR11
                        $parts[1] = call_user_func([$container,'get'], $parts[1]);
                    } elseif (is_callable($container)) {
                        $parts[1] = call_user_func($container, $parts[1], $args);
                    } elseif (is_string($container) &&
                        is_subclass_of($container, 'Prefab')
                    ) {
                        $parts[1] = call_user_func($container . '::instance')->
                         get($parts[1]);
                    } else {
                        user_error(
                            sprintf(
                                self::E_Class,
                                $this->stringify($parts[1])
                            ),
                            E_USER_ERROR
                        );
                    }
                } else {
                    $ref = new ReflectionClass($parts[1]);
                    $parts[1] = method_exists($parts[1], '__construct') && $args ?
                     $ref->newinstanceargs($args) :
                     $ref->newinstance();
                }
            }
            $func = [$parts[1],$parts[3]];
        }
        return $func;
    }

    /**
    *    Execute callback/hooks (supports 'class->method' format)
    *    @return mixed|FALSE
    *    @param $func callback
    *    @param $args mixed
    *    @param $hooks string
    **/
    public function call($func, $args = null, $hooks = '')
    {
        if (!is_array($args)) {
            $args = [$args];
        }
     // Grab the real handler behind the string representation
        if (is_string($func)) {
            $func = $this->grab($func, $args);
        }
     // Execute function; abort if callback/hook returns FALSE
        if (!is_callable($func)) {
         // No route handler
            if ($hooks == 'beforeroute,afterroute') {
                $allowed = [];
                if (is_array($func)) {
                    $allowed = array_intersect(
                        array_map('strtoupper', get_class_methods($func[0])),
                        explode('|', self::VERBS)
                    );
                }
                header('Allow: ' . implode(',', $allowed));
                $this->error(405);
            } else {
                user_error(
                    sprintf(
                        self::E_Method,
                        is_string($func) ? $func : $this->stringify($func)
                    ),
                    E_USER_ERROR
                );
            }
        }
        $obj = false;
        if (is_array($func)) {
            $hooks = $this->split($hooks);
            $obj = true;
        }
     // Execute pre-route hook if any
        if ($obj && $hooks && in_array($hook = 'beforeroute', $hooks) &&
            method_exists($func[0], $hook) &&
            call_user_func_array([$func[0],$hook], $args) === false
        ) {
            return false;
        }
     // Execute callback
        $out = call_user_func_array($func, $args ?: []);
        if ($out === false) {
            return false;
        }
     // Execute post-route hook if any
        if ($obj && $hooks && in_array($hook = 'afterroute', $hooks) &&
            method_exists($func[0], $hook) &&
            call_user_func_array([$func[0],$hook], $args) === false
        ) {
            return false;
        }
        return $out;
    }

    /**
    *    Execute specified callbacks in succession; Apply same arguments
    *    to all callbacks
    *    @return array
    *    @param $funcs array|string
    *    @param $args mixed
    **/
    public function chain($funcs, $args = null)
    {
        $out = [];
        foreach (is_array($funcs) ? $funcs : $this->split($funcs) as $func) {
            $out[] = $this->call($func, $args);
        }
        return $out;
    }

    /**
    *    Execute specified callbacks in succession; Relay result of
    *    previous callback as argument to the next callback
    *    @return array
    *    @param $funcs array|string
    *    @param $args mixed
    **/
    public function relay($funcs, $args = null)
    {
        foreach (is_array($funcs) ? $funcs : $this->split($funcs) as $func) {
            $args = [$this->call($func, $args)];
        }
        return array_shift($args);
    }

    /**
    *    Configure framework according to .ini-style file settings;
    *    If optional 2nd arg is provided, template strings are interpreted
    *    @return object
    *    @param $source string|array
    *    @param $allow bool
    **/
    public function config($source, $allow = false)
    {
        if (is_string($source)) {
            $source = $this->split($source);
        }
        if ($allow) {
            $preview = Preview::instance();
        }
        foreach ($source as $file) {
            preg_match_all(
                '/(?<=^|\n)(?:' .
                 '\[(?<section>.+?)\]|' .
                 '(?<lval>[^\h\r\n;].*?)\h*=\h*' .
                 '(?<rval>(?:\\\\\h*\r?\n|.+?)*)' .
                ')(?=\r?\n|$)/',
                $this->read($file),
                $matches,
                PREG_SET_ORDER
            );
            if ($matches) {
                $sec = 'globals';
                $cmd = [];
                foreach ($matches as $match) {
                    if ($match['section']) {
                        $sec = $match['section'];
                        if (preg_match(
                            '/^(?!(?:global|config|route|map|redirect)s\b)' .
                                '(.*?)(?:\s*[:>])/i',
                            $sec,
                            $msec
                        ) &&
                            !$this->exists($msec[1])
                        ) {
                            $this->set($msec[1], null);
                        }
                        preg_match('/^(config|route|map|redirect)s\b|' .
                         '^(.+?)\s*\>\s*(.*)/i', $sec, $cmd);
                        continue;
                    }
                    if ($allow) {
                        foreach (['lval','rval'] as $ndx) {
                            $match[$ndx] = $preview->
                            resolve($match[$ndx], null, 0, false, false);
                        }
                    }
                    if (!empty($cmd)) {
                        isset($cmd[3]) ?
                        $this->call(
                            $cmd[3],
                            [$match['lval'],$match['rval'],$cmd[2]]
                        ) :
                        call_user_func_array(
                            [$this,$cmd[1]],
                            array_merge(
                                [$match['lval']],
                                str_getcsv($cmd[1] == 'config' ?
                                $this->cast($match['rval']) :
                                $match['rval'])
                            )
                        );
                    } else {
                        $rval = preg_replace(
                            '/\\\\\h*(\r?\n)/',
                            '\1',
                            $match['rval']
                        );
                        $ttl = null;
                        if (preg_match('/^(.+)\|\h*(\d+)$/', $rval, $tmp)) {
                            array_shift($tmp);
                            list($rval,$ttl) = $tmp;
                        }
                        $args = array_map(
                            function ($val) {
                                $val = $this->cast($val);
                                if (is_string($val)) {
                                    $val = strlen($val) ?
                                     preg_replace('/\\\\"/', '"', $val) :
                                     null;
                                }
                                return $val;
                            },
                            // Mark quoted strings with 0x00 whitespace
                            str_getcsv(preg_replace(
                                '/(?<!\\\\)(")(.*?)\1/',
                                "\\1\x00\\2\\1",
                                trim($rval)
                            ))
                        );
                        preg_match(
                            '/^(?<section>[^:]+)(?:\:(?<func>.+))?/',
                            $sec,
                            $parts
                        );
                        $func = isset($parts['func']) ? $parts['func'] : null;
                        $custom = (strtolower($parts['section']) != 'globals');
                        if ($func) {
                            $args = [$this->call($func, $args)];
                        }
                        if (count($args) > 1) {
                            $args = [$args];
                        }
                        if (isset($ttl)) {
                            $args = array_merge($args, [$ttl]);
                        }
                        call_user_func_array(
                            [$this,'set'],
                            array_merge(
                                [
                                 ($custom ? ($parts['section'] . '.') : '') .
                                 $match['lval']
                                ],
                                $args
                            )
                        );
                    }
                }
            }
        }
        return $this;
    }

    /**
    *    Create mutex, invoke callback then drop ownership when done
    *    @return mixed
    *    @param $id string
    *    @param $func callback
    *    @param $args mixed
    **/
    public function mutex($id, $func, $args = null)
    {
        if (!is_dir($tmp = $this->hive['TEMP'])) {
            mkdir($tmp, self::MODE, true);
        }
     // Use filesystem lock
        if (is_file($lock = $tmp .
            $this->hive['SEED'] . '.' . $this->hash($id) . '.lock') &&
            filemtime($lock) + ini_get('max_execution_time') < microtime(true)
        ) {
         // Stale lock
            @unlink($lock);
        }
        while (!($handle = @fopen($lock, 'x')) && !connection_aborted()) {
            usleep(mt_rand(0, 100));
        }
        $this->locks[$id] = $lock;
        $out = $this->call($func, $args);
        fclose($handle);
        @unlink($lock);
        unset($this->locks[$id]);
        return $out;
    }

    /**
    *    Read file (with option to apply Unix LF as standard line ending)
    *    @return string
    *    @param $file string
    *    @param $lf bool
    **/
    public function read($file, $lf = false)
    {
        if (file_exists($file) === false) {
            throw new \Exception('File not found: ' . $file);
        }
        $out = file_get_contents($file);
        return $lf ? preg_replace('/\r\n|\r/', "\n", $out) : $out;
    }

    /**
    *    Exclusive file write
    *    @return int|FALSE
    *    @param $file string
    *    @param $data mixed
    *    @param $append bool
    **/
    public function write($file, $data, $append = false)
    {
        return file_put_contents($file, $data, $this->hive['LOCK'] | ($append ? FILE_APPEND : 0));
    }

    /**
    *    Apply syntax highlighting
    *    @return string
    *    @param $text string
    **/
    public function highlight($text)
    {
        $out = '';
        $pre = false;
        $text = trim($text);
        if ($text && !preg_match('/^<\?php/', $text)) {
            $text = '<?php ' . $text;
            $pre = true;
        }
        foreach (token_get_all($text) as $token) {
            if ($pre) {
                $pre = false;
            } else {
                $out .= '<span' .
                (is_array($token) ?
                    (' class="' .
                        substr(strtolower(token_name($token[0])), 2) . '">' .
                        $this->encode($token[1]) . '') :
                    ('>' . $this->encode($token))) .
                '</span>';
            }
        }
        return $out ? ('<code>' . $out . '</code>') : $text;
    }

    /**
    *    Dump expression with syntax highlighting
    *    @param $expr mixed
    **/
    public function dump($expr)
    {
        echo $this->highlight($this->stringify($expr));
    }

    /**
    *    Return path (and query parameters) relative to the base directory
    *    @return string
    *    @param $url string
    **/
    public function rel($url)
    {
        return preg_replace('/^(?:https?:\/\/)?' .
         preg_quote($this->hive['BASE'], '/') . '(\/.*|$)/', '\1', $url);
    }

    /**
    *    Namespace-aware class autoloader
    *    @return mixed
    *    @param $class string
    **/
    protected function autoload($class)
    {
        $class = $this->fixslashes(ltrim($class, '\\'));
     /** @var callable $func */
        $func = null;
        if (is_array($path = $this->hive['AUTOLOAD']) &&
            isset($path[1]) && is_callable($path[1])
        ) {
            list($path,$func) = $path;
        }
        foreach ($this->split($this->hive['PLUGINS'] . ';' . $path) as $auto) {
            if (($func && is_file($file = $func($auto . $class) . '.php')) ||
                is_file($file = $auto . $class . '.php') ||
                is_file($file = $auto . strtolower($class) . '.php') ||
                is_file($file = strtolower($auto . $class) . '.php')
            ) {
                return require($file);
            }
        }
    }

    /**
    *    Execute framework/application shutdown sequence
    *    @param $cwd string
    **/
    public function unload($cwd)
    {
        chdir($cwd);
        if (!($error = error_get_last()) &&
            session_status() == PHP_SESSION_ACTIVE
        ) {
            session_commit();
        }
        foreach ($this->locks as $lock) {
            @unlink($lock);
        }
        $handler = $this->hive['UNLOAD'];
        if ((!$handler || $this->call($handler, $this) === false) &&
            $error && in_array(
                $error['type'],
                [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR]
            )
        ) {
         // Fatal error detected
            $this->error(
                500,
                sprintf(self::E_Fatal, $error['message']),
                [$error]
            );
        }
    }

    /**
    *    Convenience method for checking hive key
    *    @return mixed
    *    @param $key string
    **/
    #[\ReturnTypeWillChange]
    public function offsetexists($key)
    {
        return $this->exists($key);
    }

    /**
    *    Convenience method for assigning hive value
    *    @return mixed
    *    @param $key string
    *    @param $val mixed
    **/
    #[\ReturnTypeWillChange]
    public function offsetset($key, $val)
    {
        return $this->set($key, $val);
    }

    /**
    *    Convenience method for retrieving hive value
    *    @return mixed
    *    @param $key string
    **/
    #[\ReturnTypeWillChange]
    public function &offsetget($key)
    {
        $val=&$this->ref($key);
        return $val;
    }

    /**
    *    Convenience method for removing hive key
    *    @param $key string
    **/
    #[\ReturnTypeWillChange]
    public function offsetunset($key)
    {
        $this->clear($key);
    }

    /**
    *    Alias for offsetexists()
    *    @return mixed
    *    @param $key string
    **/
    public function __isset($key)
    {
        return $this->offsetexists($key);
    }

    /**
    *    Alias for offsetset()
    *    @return mixed
    *    @param $key string
    *    @param $val mixed
    **/
    public function __set($key, $val)
    {
        return $this->offsetset($key, $val);
    }

    /**
    *    Alias for offsetget()
    *    @return mixed
    *    @param $key string
    **/
    public function &__get($key)
    {
        $val=&$this->offsetget($key);
        return $val;
    }

    /**
    *    Alias for offsetunset()
    *    @param $key string
    **/
    public function __unset($key)
    {
        $this->offsetunset($key);
    }

    /**
    *    Call function identified by hive key
    *    @return mixed
    *    @param $key string
    *    @param $args array
    **/
    public function __call($key, array $args)
    {
        if ($this->exists($key, $val)) {
            return call_user_func_array($val, $args);
        }
        user_error(sprintf(self::E_Method, $key), E_USER_ERROR);
    }
}
