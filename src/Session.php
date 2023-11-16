<?php

/*

    Copyright (c) 2009-2019 F3::Factory/Bong Cosca, All rights reserved.

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
declare(strict_types=1);

namespace Shoofly;

//! Cache-Applicationd session handler
class Session extends Magic
{
    /** @var string Session ID */
    protected $sid;
    /** @var string  Anti-CSRF token */
    protected $csrf;
    /** @var string  User agent */
    protected $agent;
    /** @var string  IP */
    protected $ip;
    /** @var callable  Suspect callback */
    protected $onsuspect;
    /** @var Cache  Cache instance */
    protected $Cache;
    /** @var array  Session meta data */
    protected $data = [];

    /**
    *   Instantiate class
    *   @param $onsuspect callback
    *   @param $key string
    **/
    public function __construct($onsuspect = null, $key = null, $cache = null)
    {
        $this->onsuspect = $onsuspect;
        $this->Cache = $cache ?: Cache::instance();
        session_set_save_handler(
            [$this,'open'],
            [$this,'close'],
            [$this,'read'],
            [$this,'write'],
            [$this,'destroy'],
            [$this,'cleanup']
        );
        register_shutdown_function('session_commit');
        $fw = Base::instance();
        $headers = $fw->HEADERS;
        $this->csrf = $fw->hash($fw->SEED .
            extension_loaded('openssl') ?
                implode(unpack('L', openssl_random_pseudo_bytes(4))) :
                mt_rand());
        if ($key) {
            $fw->$key = $this->csrf;
        }
        $this->agent = isset($headers['User-Agent']) ? $headers['User-Agent'] : '';
        $this->ip = $fw->IP;
    }

    /**
    *   Open session
    *   @return TRUE
    *   @param $path string
    *   @param $name string
    **/
    public function open($path, $name)
    {
        return true;
    }

    /**
    *   Close session
    *   @return TRUE
    **/
    public function close()
    {
        $this->sid = null;
        $this->data = [];
        return true;
    }

    /**
    *   Return session data in serialized format
    *   @return string
    *   @param $id string
    **/
    public function read($id)
    {
        $this->sid = $id;
        if (!$data = $this->Cache->get($id . '.@')) {
            return '';
        }
        $this->data = $data;
        if ($data['ip'] != $this->ip || $data['agent'] != $this->agent) {
            $fw = Base::instance();
            if (!isset($this->onsuspect) ||
                $fw->call($this->onsuspect, [$this,$id]) === false
            ) {
                //NB: `session_destroy` can't be called at that stage (`session_start` not completed)
                $this->destroy($id);
                $this->close();
                unset($fw->{'COOKIE.' . session_name()});
                $fw->error(403);
            }
        }
        return $data['data'];
    }

    /**
    *   Write session data
    *   @return TRUE
    *   @param $id string
    *   @param $data string
    **/
    public function write($id, $data)
    {
        $fw = Base::instance();
        $jar = $fw->JAR;
        $this->Cache->set(
            $id . '.@',
            [
                'data' => $data,
                'ip' => $this->ip,
                'agent' => $this->agent,
                'stamp' => time()
            ],
            $jar['expire']
        );
        return true;
    }

    /**
    *   Destroy session
    *   @return TRUE
    *   @param $id string
    **/
    public function destroy($id)
    {
        $this->Cache->clear($id . '.@');
        return true;
    }

    /**
    *   Garbage collector
    *   @return TRUE
    *   @param $max int
    **/
    public function cleanup($max)
    {
        $this->Cache->reset('.@', $max);
        return true;
    }

    /**
     *  Return session id (if session has started)
     *  @return string|NULL
     **/
    public function sid()
    {
        return $this->sid;
    }

    /**
     *  Return anti-CSRF token
     *  @return string
     **/
    public function csrf()
    {
        return $this->csrf;
    }

    /**
     *  Return IP address
     *  @return string
     **/
    public function ip()
    {
        return $this->ip;
    }

    /**
     *  Return Unix timestamp
     *  @return string|FALSE
     **/
    public function stamp()
    {
        if (!$this->sid) {
            session_start();
        }
        return $this->Cache->exists($this->sid . '.@', $data) ?
            $data['stamp'] : false;
    }

    /**
     *  Return HTTP user agent
     *  @return string
     **/
    public function agent()
    {
        return $this->agent;
    }

    /**
     * check latest meta data existence
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * get meta data from latest session
     * @param string $key
     * @return mixed
     */
    public function &get($key)
    {
        return $this->data[$key];
    }

    public function set($key, $val)
    {
        trigger_error('Unable to set data on previous session');
    }

    public function clear($key)
    {
        trigger_error('Unable to clear data on previous session');
    }
}
