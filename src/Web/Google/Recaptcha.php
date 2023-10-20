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

namespace Shoofly\Web\Google;

use Shoofly\Base;
use Shoofly\Web;

//! Google ReCAPTCHA v2 plug-in
class Recaptcha
{
    //! API URL
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    public const URL_Recaptcha = 'https://www.google.com/recaptcha/api/siteverify';
	// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    /**
     *  Verify reCAPTCHA response
     *  @param string $secret
     *  @param string $response
     *  @return bool
     **/
    public static function verify($secret, $response = null)
    {
        $fw = Base::instance();
        if (!isset($response)) {
            $response = $fw->{'POST.g-recaptcha-response'};
        }
        if (!$response) {
            return false;
        }
        $web = Web::instance();
        $out = $web->request(self::URL_Recaptcha, [
            'method' => 'POST',
            'content' => http_build_query([
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $fw->IP
            ]),
        ]);
        return isset($out['body']) &&
            ($json = json_decode($out['body'], true)) &&
            isset($json['success']) && $json['success'];
    }
}
