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

namespace Shoofly\Cli\Websocket;

use Shoofly\Cli\Websocket;

//! RFC6455 remote socket
class Agent
{
    protected $server;
    protected $id;
    protected $socket;
    protected $flag;
    protected $verb;
    protected $uri;
    protected $headers;

    /**
    *   @param $server WS
    *   @param $socket resource
    *   @param $verb string
    *   @param $uri string
    *   @param $hdrs array
    **/
    public function __construct($server, $socket, $verb, $uri, array $hdrs)
    {
        $this->server = $server;
        $this->id = stream_socket_get_name($socket, true);
        $this->socket = $socket;
        $this->verb = $verb;
        $this->uri = $uri;
        $this->headers = $hdrs;

        if (
            isset($server->events['connect']) &&
            is_callable($func = $server->events['connect'])
        ) {
            $func($this);
        }
    }

    /**
    *   Destroy object
    **/
    public function __destruct()
    {
        if (
            isset($this->server->events['disconnect']) &&
            is_callable($func = $this->server->events['disconnect'])
        ) {
            $func($this);
        }
    }

    /**
    *   Return server instance
    *   @return WS
    **/
    public function server()
    {
        return $this->server;
    }

    /**
    *   Return socket ID
    *   @return string
    **/
    public function id()
    {
        return $this->id;
    }

    /**
    *   Return socket
    *   @return resource
    **/
    public function socket()
    {
        return $this->socket;
    }

    /**
    *   Return request method
    *   @return string
    **/
    public function verb()
    {
        return $this->verb;
    }

    /**
    *   Return request URI
    *   @return string
    **/
    public function uri()
    {
        return $this->uri;
    }

    /**
    *   Return socket headers
    *   @return array
    **/
    public function headers()
    {
        return $this->headers;
    }

    /**
    *   Frame and transmit payload
    *   @return string|FALSE
    *   @param $op int
    *   @param $data string
    **/
    public function send($op, $data = '')
    {
        $server = $this->server;
        $mask = Websocket::Finale | $op & Websocket::OpCode;
        $len = strlen($data);
        $buf = '';
        if ($len > 0xffff) {
            $buf = pack('CCNN', $mask, 0x7f, $len);
        } elseif ($len > 0x7d) {
            $buf = pack('CCn', $mask, 0x7e, $len);
        } else {
            $buf = pack('CC', $mask, $len);
        }
        $buf .= $data;
        if (is_bool($server->write($this->socket, $buf))) {
            return false;
        }
        if (
            !in_array($op, [Websocket::Pong,Websocket::Close]) &&
            isset($this->server->events['send']) &&
            is_callable($func = $this->server->events['send'])
        ) {
            $func($this, $op, $data);
        }
        return $data;
    }

    /**
    *   Retrieve and unmask payload
    *   @return bool|NULL
    **/
    public function fetch()
    {
        // Unmask payload
        $server = $this->server;
        if (is_bool($buf = $server->read($this->socket))) {
            return false;
        }
        while ($buf) {
            $op = ord($buf[0]) & Websocket::OpCode;
            $len = ord($buf[1]) & Websocket::Length;
            $pos = 2;
            if ($len == 0x7e) {
                $len = ord($buf[2]) * 256 + ord($buf[3]);
                $pos += 2;
            } elseif ($len == 0x7f) {
                for ($i = 0,$len = 0; $i < 8; ++$i) {
                    $len = $len * 256 + ord($buf[$i + 2]);
                }
                $pos += 8;
            }
            for ($i = 0,$mask = []; $i < 4; ++$i) {
                $mask[$i] = ord($buf[$pos + $i]);
            }
            $pos += 4;
            if (strlen($buf) < $len + $pos) {
                return false;
            }
            for ($i = 0,$data = ''; $i < $len; ++$i) {
                $data .= chr(ord($buf[$pos + $i]) ^ $mask[$i % 4]);
            }
            // Dispatch
            switch ($op & Websocket::OpCode) {
                case Websocket::Ping:
                    $this->send(Websocket::Pong);
                    break;
                case Websocket::Close:
                    $server->close($this->socket);
                    break;
                case Websocket::Text:
                    $data = trim($data);
                    // I hope this is intentional
                case Websocket::Binary:
                    if (
                        isset($this->server->events['receive']) &&
                        is_callable($func = $this->server->events['receive'])
                    ) {
                        $func($this, $op, $data);
                    }
                    break;
            }
            $buf = substr($buf, $len + $pos);
        }
    }
}
