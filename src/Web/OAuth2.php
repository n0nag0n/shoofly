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

namespace Shoofly\Web;

use Shoofly\Magic;

//! Lightweight OAuth2 client
class OAuth2 extends Magic
{
    //! Scopes and claims
    protected $args = [];
    //! Encoding
    protected $enc_type = PHP_QUERY_RFC1738;

    /**
    *   Return OAuth2 authentication URI
    *   @return string
    *   @param $endpoint string
    *   @param $query bool
    **/
    public function uri($endpoint, $query = true)
    {
        return $endpoint . ($query ? ('?' .
                http_build_query($this->args, null, '&', $this->enc_type)) : '');
    }

    /**
    *   Send request to API/token endpoint
    *   @return string|array|FALSE
    *   @param $uri string
    *   @param $method string
    *   @param $token string
    **/
    public function request($uri, $method, $token = null)
    {
        $web = Web::instance();
        $options = [
            'method' => $method,
            'content' => http_build_query($this->args, null, '&', $this->enc_type),
            'header' => ['Accept: application/json']
        ];
        if ($token) {
            array_push($options['header'], 'Authorization: Bearer ' . $token);
        } elseif ($method == 'POST' && isset($this->args['client_id'])) {
            array_push($options['header'], 'Authorization: Basic ' .
                base64_encode(
                    $this->args['client_id'] . ':' .
                    $this->args['client_secret']
                ));
        }
        $response = $web->request($uri, $options);
        if ($response['error']) {
            user_error($response['error'], E_USER_ERROR);
        }
        if (isset($response['body'])) {
            if (
                preg_grep(
                    '/^Content-Type:.*application\/json/i',
                    $response['headers']
                )
            ) {
                $token = json_decode($response['body'], true);
                if (isset($token['error_description'])) {
                    user_error($token['error_description'], E_USER_ERROR);
                }
                if (isset($token['error'])) {
                    user_error($token['error'], E_USER_ERROR);
                }
                return $token;
            } else {
                return $response['body'];
            }
        }
        return false;
    }

    /**
    *   Parse JSON Web token
    *   @return array
    *   @param $token string
    **/
    public function jwt($token)
    {
        return json_decode(
            base64_decode(
                str_replace(['-','_'], ['+','/'], explode('.', $token)[1])
            ),
            true
        );
    }

    /**
     * change default url encoding type, i.E. PHP_QUERY_RFC3986
     * @param $type
     */
    public function setEncoding($type)
    {
        $this->enc_type = $type;
    }

    /**
    *   URL-safe base64 encoding
    *   @return array
    *   @param $data string
    **/
    public function b64url($data)
    {
        return trim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
    *   Return TRUE if scope/claim exists
    *   @return bool
    *   @param $key string
    **/
    public function exists($key)
    {
        return isset($this->args[$key]);
    }

    /**
    *   Bind value to scope/claim
    *   @return string
    *   @param $key string
    *   @param $val string
    **/
    public function set($key, $val)
    {
        return $this->args[$key] = $val;
    }

    /**
    *   Return value of scope/claim
    *   @return mixed
    *   @param $key string
    **/
    public function &get($key)
    {
        if (isset($this->args[$key])) {
            $val=&$this->args[$key];
        } else {
            $val = null;
        }
        return $val;
    }

    /**
    *   Remove scope/claim
    *   @return NULL
    *   @param $key string
    **/
    public function clear($key = null)
    {
        if ($key) {
            unset($this->args[$key]);
        } else {
            $this->args = [];
        }
    }
}
