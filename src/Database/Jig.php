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

namespace Shoofly\Database;

use Shoofly\Base;

//! In-memory/flat-file DB wrapper
class Jig
{
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    //@{ Storage formats
    public const FORMAT_JSON = 0;
    public const FORMAT_Serialized = 1;
    //@}
	// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

        //! UUID
    protected $uuid;
    //! Storage location
    protected $dir;
    //! Current storage format
    protected $format;
    //! Jig log
    protected $log;
    //! Memory-held data
    protected $data;
    //! lazy load/save files
    protected $lazy;

    /**
    *   Instantiate class
    *   @param $dir string
    *   @param $format int
    **/
    public function __construct($dir = null, $format = self::FORMAT_JSON, $lazy = false)
    {
        if ($dir && !is_dir($dir)) {
            mkdir($dir, Base::MODE, true);
        }
        $this->uuid = Base::instance()->hash($this->dir = $dir);
        $this->format = $format;
        $this->lazy = $lazy;
    }

    /**
    *   save file on destruction
    **/
    public function __destruct()
    {
        if ($this->lazy) {
            $this->lazy = false;
            foreach ($this->data ?: [] as $file => $data) {
                $this->write($file, $data);
            }
        }
    }

    /**
    *   Read data from memory/file
    *   @return array
    *   @param $file string
    **/
    public function &read($file)
    {
        if (!$this->dir || !is_file($dst = $this->dir . $file)) {
            if (!isset($this->data[$file])) {
                $this->data[$file] = [];
            }
            return $this->data[$file];
        }
        if ($this->lazy && isset($this->data[$file])) {
            return $this->data[$file];
        }
        $fw = Base::instance();
        $raw = $fw->read($dst);
        switch ($this->format) {
            case self::FORMAT_JSON:
                $data = json_decode($raw, true);
                break;
            case self::FORMAT_Serialized:
                $data = $fw->unserialize($raw);
                break;
        }
        $this->data[$file] = $data;
        return $this->data[$file];
    }

    /**
    *   Write data to memory/file
    *   @return int
    *   @param $file string
    *   @param $data array
    **/
    public function write($file, array $data = null)
    {
        if (!$this->dir || $this->lazy) {
            return count($this->data[$file] = $data);
        }
        $fw = Base::instance();
        switch ($this->format) {
            case self::FORMAT_JSON:
                if (version_compare(PHP_VERSION, '7.2.0') >= 0) {
                    $out = json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_IGNORE);
                } else {
                    $out = json_encode($data, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
                }
                break;
            case self::FORMAT_Serialized:
                $out = $fw->serialize($data);
                break;
        }
        return $fw->write($this->dir . $file, $out);
    }

    /**
    *   Return directory
    *   @return string
    **/
    public function dir()
    {
        return $this->dir;
    }

    /**
    *   Return UUID
    *   @return string
    **/
    public function uuid()
    {
        return $this->uuid;
    }

    /**
    *   Return profiler results (or disable logging)
    *   @param $flag bool
    *   @return string
    **/
    public function log($flag = true)
    {
        if ($flag) {
            return $this->log;
        }
        $this->log = false;
    }

    /**
    *   Jot down log entry
    *   @return NULL
    *   @param $frame string
    **/
    public function jot($frame)
    {
        if ($frame) {
            $this->log .= date('r') . ' ' . $frame . PHP_EOL;
        }
    }

    /**
    *   Clean storage
    *   @return NULL
    **/
    public function drop()
    {
        if ($this->lazy) { // intentional
            $this->data = [];
        }
        if (!$this->dir) {
            $this->data = [];
        } elseif ($glob = @glob($this->dir . '/*', GLOB_NOSORT)) {
            foreach ($glob as $file) {
                @unlink($file);
            }
        }
    }

    //! Prohibit cloning
    private function __clone()
    {
    }
}
