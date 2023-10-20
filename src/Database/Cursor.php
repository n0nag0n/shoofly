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

use Shoofly\Magic;

//! Simple cursor implementation
abstract class Cursor extends Magic implements \IteratorAggregate
{
	// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    //@{ Error messages
    public const E_Field = 'Undefined field %s';
    //@}
	// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    //! Query results
    protected $query = [];
    //! Current position
    protected $ptr = 0;
    //! Event listeners
    protected $trigger = [];

    /**
    *   Return database type
    *   @return string
    **/
    abstract public function dbtype();

    /**
    *   Return field names
    *   @return array
    **/
    abstract public function fields();

    /**
    *   Return fields of mapper object as an associative array
    *   @return array
    *   @param $obj object
    **/
    abstract public function cast($obj = null);

    /**
    *   Return records (array of mapper objects) that match criteria
    *   @return array
    *   @param $filter string|array
    *   @param $options array
    *   @param $ttl int
    **/
    abstract public function find($filter = null, array $options = null, $ttl = 0);

    /**
    *   Count records that match criteria
    *   @return int
    *   @param $filter array
    *   @param $options array
    *   @param $ttl int
    **/
    abstract public function count($filter = null, array $options = null, $ttl = 0);

    /**
    *   Insert new record
    *   @return array
    **/
    abstract public function insert();

    /**
    *   Update current record
    *   @return array
    **/
    abstract public function update();

    /**
    *   Hydrate mapper object using hive array variable
    *   @return NULL
    *   @param $var array|string
    *   @param $func callback
    **/
    abstract public function copyfrom($var, $func = null);

    /**
    *   Populate hive array variable with mapper fields
    *   @return NULL
    *   @param $key string
    **/
    abstract public function copyto($key);

    /**
    *   Get cursor's equivalent external iterator
    *   Causes a fatal error in PHP 5.3.5 if uncommented
    *   return ArrayIterator
    **/
    #[\ReturnTypeWillChange]
    abstract public function getiterator();


    /**
    *   Return TRUE if current cursor position is not mapped to any record
    *   @return bool
    **/
    public function dry()
    {
        return empty($this->query[$this->ptr]);
    }

    /**
    *   Return first record (mapper object) that matches criteria
    *   @return static|FALSE
    *   @param $filter string|array
    *   @param $options array
    *   @param $ttl int
    **/
    public function findone($filter = null, array $options = null, $ttl = 0)
    {
        if (!$options) {
            $options = [];
        }
        // Override limit
        $options['limit'] = 1;
        return ($data = $this->find($filter, $options, $ttl)) ? $data[0] : false;
    }

    /**
    *   Return array containing subset of records matching criteria,
    *   total number of records in superset, specified limit, number of
    *   subsets available, and actual subset position
    *   @return array
    *   @param $pos int
    *   @param $size int
    *   @param $filter string|array
    *   @param $options array
    *   @param $ttl int
    *   @param $bounce bool
    **/
    public function paginate(
        $pos = 0,
        $size = 10,
        $filter = null,
        array $options = null,
        $ttl = 0,
        $bounce = true
    ) {
        $total = $this->count($filter, $options, $ttl);
        $count = (int)ceil($total / $size);
        if ($bounce) {
            $pos = max(0, min($pos, $count - 1));
        }
        return [
            'subset' => ($bounce || $pos < $count) ? $this->find(
                $filter,
                array_merge(
                    $options ?: [],
                    ['limit' => $size,'offset' => $pos * $size]
                ),
                $ttl
            ) : [],
            'total' => $total,
            'limit' => $size,
            'count' => $count,
            'pos' => $bounce ? ($pos < $count ? $pos : 0) : $pos
        ];
    }

    /**
    *   Map to first record that matches criteria
    *   @return array|FALSE
    *   @param $filter string|array
    *   @param $options array
    *   @param $ttl int
    **/
    public function load($filter = null, array $options = null, $ttl = 0)
    {
        $this->reset();
        return ($this->query = $this->find($filter, $options, $ttl)) &&
            $this->skip(0) ? $this->query[$this->ptr] : false;
    }

    /**
    *   Return the count of records loaded
    *   @return int
    **/
    public function loaded()
    {
        return count($this->query);
    }

    /**
    *   Map to first record in cursor
    *   @return mixed
    **/
    public function first()
    {
        return $this->skip(-$this->ptr);
    }

    /**
    *   Map to last record in cursor
    *   @return mixed
    **/
    public function last()
    {
        return $this->skip(($ofs = count($this->query) - $this->ptr) ? $ofs - 1 : 0);
    }

    /**
    *   Map to nth record relative to current cursor position
    *   @return mixed
    *   @param $ofs int
    **/
    public function skip($ofs = 1)
    {
        $this->ptr += $ofs;
        return $this->ptr > -1 && $this->ptr < count($this->query) ?
            $this->query[$this->ptr] : false;
    }

    /**
    *   Map next record
    *   @return mixed
    **/
    public function next()
    {
        return $this->skip();
    }

    /**
    *   Map previous record
    *   @return mixed
    **/
    public function prev()
    {
        return $this->skip(-1);
    }

    /**
     * Return whether current iterator position is valid.
     */
    public function valid()
    {
        return !$this->dry();
    }

    /**
    *   Save mapped record
    *   @return mixed
    **/
    public function save()
    {
        return $this->query ? $this->update() : $this->insert();
    }

    /**
    *   Delete current record
    *   @return int|bool
    **/
    public function erase()
    {
        $this->query = array_slice($this->query, 0, $this->ptr, true) +
            array_slice($this->query, $this->ptr, null, true);
        $this->skip(0);
    }

    /**
    *   Define onload trigger
    *   @return callback
    *   @param $func callback
    **/
    public function onload($func)
    {
        return $this->trigger['load'] = $func;
    }

    /**
    *   Define beforeinsert trigger
    *   @return callback
    *   @param $func callback
    **/
    public function beforeinsert($func)
    {
        return $this->trigger['beforeinsert'] = $func;
    }

    /**
    *   Define afterinsert trigger
    *   @return callback
    *   @param $func callback
    **/
    public function afterinsert($func)
    {
        return $this->trigger['afterinsert'] = $func;
    }

    /**
    *   Define oninsert trigger
    *   @return callback
    *   @param $func callback
    **/
    public function oninsert($func)
    {
        return $this->afterinsert($func);
    }

    /**
    *   Define beforeupdate trigger
    *   @return callback
    *   @param $func callback
    **/
    public function beforeupdate($func)
    {
        return $this->trigger['beforeupdate'] = $func;
    }

    /**
    *   Define afterupdate trigger
    *   @return callback
    *   @param $func callback
    **/
    public function afterupdate($func)
    {
        return $this->trigger['afterupdate'] = $func;
    }

    /**
    *   Define onupdate trigger
    *   @return callback
    *   @param $func callback
    **/
    public function onupdate($func)
    {
        return $this->afterupdate($func);
    }

    /**
    *   Define beforesave trigger
    *   @return callback
    *   @param $func callback
    **/
    public function beforesave($func)
    {
        $this->trigger['beforeinsert'] = $func;
        $this->trigger['beforeupdate'] = $func;
        return $func;
    }

    /**
    *   Define aftersave trigger
    *   @return callback
    *   @param $func callback
    **/
    public function aftersave($func)
    {
        $this->trigger['afterinsert'] = $func;
        $this->trigger['afterupdate'] = $func;
        return $func;
    }

    /**
    *   Define onsave trigger
    *   @return callback
    *   @param $func callback
    **/
    public function onsave($func)
    {
        return $this->aftersave($func);
    }

    /**
    *   Define beforeerase trigger
    *   @return callback
    *   @param $func callback
    **/
    public function beforeerase($func)
    {
        return $this->trigger['beforeerase'] = $func;
    }

    /**
    *   Define aftererase trigger
    *   @return callback
    *   @param $func callback
    **/
    public function aftererase($func)
    {
        return $this->trigger['aftererase'] = $func;
    }

    /**
    *   Define onerase trigger
    *   @return callback
    *   @param $func callback
    **/
    public function onerase($func)
    {
        return $this->aftererase($func);
    }

    /**
    *   Reset cursor
    *   @return NULL
    **/
    public function reset()
    {
        $this->query = [];
        $this->ptr = 0;
    }
}
