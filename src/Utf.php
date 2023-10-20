<?php

/**
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

/**
 * Unicode string manager
 */
class Utf extends Prefab
{
    /**
     *    Get string length
     *
     * @return integer
     * @param  $str string
     **/
    public function strlen($str)
    {
        preg_match_all('/./us', $str, $parts);
        return count($parts[0]);
    }


    /**
     *    Reverse a string
     *
     * @return string
     * @param  $str string
     **/
    public function strrev($str)
    {
        preg_match_all('/./us', $str, $parts);
        return implode('', array_reverse($parts[0]));
    }


    /**
     *    Find position of first occurrence of a string (case-insensitive)
     *
     * @return integer|FALSE
     * @param  $stack  string
     * @param  $needle string
     * @param  $ofs    int
     **/
    public function stripos($stack, $needle, $ofs = 0)
    {
        return $this->strpos($stack, $needle, $ofs, true);
    }


    /**
     *    Find position of first occurrence of a string
     *
     * @return integer|FALSE
     * @param  $stack  string
     * @param  $needle string
     * @param  $ofs    int
     * @param  $case   bool
     **/
    public function strpos($stack, $needle, $ofs = 0, $case = false)
    {
        return preg_match(
            '/^(.{' . $ofs . '}.*?)' . preg_quote($needle, '/') . '/us' . ($case ? 'i' : ''),
            $stack,
            $match
        ) ? $this->strlen($match[1]) : false;
    }


    /**
     *    Returns part of haystack string from the first occurrence of
     *    needle to the end of haystack (case-insensitive)
     *
     * @return string|FALSE
     * @param  $stack  string
     * @param  $needle string
     * @param  $before bool
     **/
    public function stristr($stack, $needle, $before = false)
    {
        return $this->strstr($stack, $needle, $before, true);
    }


    /**
     *    Returns part of haystack string from the first occurrence of
     *    needle to the end of haystack
     *
     * @return string|FALSE
     * @param  $stack  string
     * @param  $needle string
     * @param  $before bool
     * @param  $case   bool
     **/
    public function strstr($stack, $needle, $before = false, $case = false)
    {
        if (!$needle) {
            return false;
        }

        preg_match(
            '/^(.*?)' . preg_quote($needle, '/') . '/us' . ($case ? 'i' : ''),
            $stack,
            $match
        );
        return isset($match[1]) ? ($before ? $match[1] : $this->substr($stack, $this->strlen($match[1]))) : false;
    }


    /**
     *    Return part of a string
     *
     * @return string|FALSE
     * @param  $str   string
     * @param  $start int
     * @param  $len   int
     **/
    public function substr($str, $start, $len = 0)
    {
        if ($start < 0) {
            $start = ($this->strlen($str) + $start);
        }

        if (!$len) {
            $len = ($this->strlen($str) - $start);
        }

        return preg_match('/^.{' . $start . '}(.{0,' . $len . '})/us', $str, $match) ? $match[1] : false;
    }


    /**
     *    Count the number of substring occurrences
     *
     * @return integer
     * @param  $stack  string
     * @param  $needle string
     **/
    public function substrCount($stack, $needle)
    {
        preg_match_all(
            '/' . preg_quote($needle, '/') . '/us',
            $stack,
            $matches,
            PREG_SET_ORDER
        );
        return count($matches);
    }


    /**
     *    Strip whitespaces from the beginning of a string
     *
     * @return string
     * @param  $str string
     **/
    public function ltrim($str)
    {
        return preg_replace('/^[\pZ\pC]+/u', '', $str);
    }


    /**
     *    Strip whitespaces from the end of a string
     *
     * @return string
     * @param  $str string
     **/
    public function rtrim($str)
    {
        return preg_replace('/[\pZ\pC]+$/u', '', $str);
    }


    /**
     *    Strip whitespaces from the beginning and end of a string
     *
     * @return string
     * @param  $str string
     **/
    public function trim($str)
    {
        return preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $str);
    }


    /**
     *    Return UTF-8 byte order mark
     *
     * @return string
     **/
    public function bom()
    {
        return chr(0xef) . chr(0xbb) . chr(0xbf);
    }


    /**
     *    Convert code points to Unicode symbols
     *
     * @return string
     * @param  $str string
     **/
    public function translate($str)
    {
        return html_entity_decode(
            preg_replace('/\\\\u([[:xdigit:]]+)/i', '&#x\1;', $str)
        );
    }


    /**
     *    Translate emoji tokens to Unicode font-supported symbols
     *
     * @return string
     * @param  $str string
     **/
    public function emojify($str)
    {
        $map = ([
            ':(' => '\u2639',
        // frown
            ':)' => '\u263a',
        // smile
            '<3' => '\u2665',
        // heart
            ':D' => '\u1f603',
        // grin
            'XD' => '\u1f606',
        // laugh
            ';)' => '\u1f609',
        // wink
            ':P' => '\u1f60b',
        // tongue
            ':,' => '\u1f60f',
        // think
            ':/' => '\u1f623',
        // skeptic
            '8O' => '\u1f632',
        // oops
        ] + Base::instance()->EMOJI);
        return $this->translate(
            str_replace(
                array_keys($map),
                array_values($map),
                $str
            )
        );
    }
}
