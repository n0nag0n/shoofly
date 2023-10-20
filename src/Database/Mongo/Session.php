<?php

declare(strict_types=1);

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

namespace Shoofly\Database\Mongo;

use Shoofly\Base;
use Shoofly\Database\Mongo;

//! MongoDB-managed session handler
class Session extends Mapper
{
    //! Session ID
    protected $sid;
    //! Anti-CSRF token
    protected $csrf;
    //! User agent
    protected $agent;
    //! IP,
    protected $ip;
    //! Suspect callback
    protected $onsuspect;

    /**
    *   Instantiate class
    *   @param $db \DB\Mongo
    *   @param $table string
    *   @param $onsuspect callback
    *   @param $key string
    **/
    public function __construct(Mongo $db, $table = 'sessions', $onsuspect = null, $key = null)
    {
        parent::__construct($db, $table);
        $this->onsuspect = $onsuspect;
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
        $this->reset();
        $this->sid = null;
        return true;
    }

    /**
    *   Return session data in serialized format
    *   @return string
    *   @param $id string
    **/
    public function read($id)
    {
        $this->load(['session_id' => $this->sid = $id]);
        if ($this->dry()) {
            return '';
        }
        if ($this->get('ip') != $this->ip || $this->get('agent') != $this->agent) {
            $fw = Base::instance();
            if (
                !isset($this->onsuspect) ||
                $fw->call($this->onsuspect, [$this,$id]) === false
            ) {
                // NB: `session_destroy` can't be called at that stage;
                // `session_start` not completed
                $this->destroy($id);
                $this->close();
                unset($fw->{'COOKIE.' . session_name()});
                $fw->error(403);
            }
        }
        return $this->get('data');
    }

    /**
    *   Write session data
    *   @return TRUE
    *   @param $id string
    *   @param $data string
    **/
    public function write($id, $data)
    {
        $this->set('session_id', $id);
        $this->set('data', $data);
        $this->set('ip', $this->ip);
        $this->set('agent', $this->agent);
        $this->set('stamp', time());
        $this->save();
        return true;
    }

    /**
    *   Destroy session
    *   @return TRUE
    *   @param $id string
    **/
    public function destroy($id)
    {
        $this->erase(['session_id' => $id]);
        return true;
    }

    /**
    *   Garbage collector
    *   @return TRUE
    *   @param $max int
    **/
    public function cleanup($max)
    {
        $this->erase(['$where' => 'this.stamp+' . $max . '<' . time()]);
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
        return $this->dry() ? false : $this->get('stamp');
    }

    /**
     *  Return HTTP user agent
     *  @return string
     **/
    public function agent()
    {
        return $this->agent;
    }
}
