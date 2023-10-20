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

//! Unit test kit
class Test
{
    //@{ Reporting level
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    public const FLAG_False = 0;
    public const FLAG_True = 1;
    public const FLAG_Both = 2;
	// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    //@}

    /** @var array Test results */
    protected $data = [];
    /** @var array Success indicator */
    protected $passed = true;
    /** @var array Reporting level */
    protected $level;

    /**
    *   Class constructor
    *   @return NULL
    *   @param $level int
    **/
    public function __construct($level = self::FLAG_Both)
    {
        $this->level = $level;
    }

    /**
    *   Return test results
    *   @return array
    **/
    public function results()
    {
        return $this->data;
    }

    /**
    *   Return FALSE if at least one test case fails
    *   @return bool
    **/
    public function passed()
    {
        return $this->passed;
    }

    /**
    *   Evaluate condition and save test result
    *   @return object
    *   @param $cond bool
    *   @param $text string
    **/
    public function expect($cond, $text = null)
    {
        $out = (bool)$cond;
        if ($this->level == $out || $this->level == self::FLAG_Both) {
            $data = ['status' => $out,'text' => $text,'source' => null];
            foreach (debug_backtrace() as $frame) {
                if (isset($frame['file'])) {
                    $data['source'] = Base::instance()->
                    fixslashes($frame['file']) . ':' . $frame['line'];
                    break;
                }
            }
            $this->data[] = $data;
        }
        if (!$out && $this->passed) {
            $this->passed = false;
        }
        return $this;
    }

    /**
    *   Append message to test results
    *   @return NULL
    *   @param $text string
    **/
    public function message($text)
    {
        $this->expect(true, $text);
    }
}
