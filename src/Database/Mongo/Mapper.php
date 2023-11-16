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
use Shoofly\Cache;
use Shoofly\Database\Cursor;
use Shoofly\Database\Mongo;

//! MongoDB mapper
class Mapper extends Cursor
{
    //! MongoDB wrapper
    protected $db;
    //! Legacy flag
    protected $legacy;
    //! Mongo collection
    protected $collection;
    //! Mongo document
    protected $document = [];
    //! Mongo cursor
    protected $cursor;
    //! Defined fields
    protected $fields;

    /**
    *   Instantiate class
    *   @return void
    *   @param $db object
    *   @param $collection string
    *   @param $fields array
    **/
    public function __construct(Mongo $db, $collection, $fields = null)
    {
        $this->db = $db;
        $this->legacy = $db->legacy();
        $this->collection = $db->selectcollection($collection);
        $this->fields = $fields;
        $this->reset();
    }

    /**
    *   Return database type
    *   @return string
    **/
    public function dbtype()
    {
        return 'Mongo';
    }

    /**
    *   Return TRUE if field is defined
    *   @return bool
    *   @param $key string
    **/
    public function exists($key)
    {
        return array_key_exists($key, $this->document);
    }

    /**
    *   Assign value to field
    *   @return scalar|FALSE
    *   @param $key string
    *   @param $val scalar
    **/
    public function set($key, $val)
    {
        return $this->document[$key] = $val;
    }

    /**
    *   Retrieve value of field
    *   @return scalar|FALSE
    *   @param $key string
    **/
    public function &get($key)
    {
        if ($this->exists($key)) {
            return $this->document[$key];
        }
        user_error(sprintf(self::E_Field, $key), E_USER_ERROR);
    }

    /**
    *   Delete field
    *   @return NULL
    *   @param $key string
    **/
    public function clear($key)
    {
        unset($this->document[$key]);
    }

    /**
    *   Convert array to mapper object
    *   @return static
    *   @param $row array
    **/
    public function factory($row)
    {
        $mapper = clone($this);
        $mapper->reset();
        foreach ($row as $key => $val) {
            $mapper->document[$key] = $val;
        }
        $mapper->query = [clone($mapper)];
        if (isset($mapper->trigger['load'])) {
            Base::instance()->call($mapper->trigger['load'], $mapper);
        }
        return $mapper;
    }

    /**
    *   Return fields of mapper object as an associative array
    *   @return array
    *   @param $obj object
    **/
    public function cast($obj = null)
    {
        if (!$obj) {
            $obj = $this;
        }
        return $obj->document;
    }

    /**
    *   Build query and execute
    *   @return static[]
    *   @param $fields string
    *   @param $filter array
    *   @param $options array
    *   @param $ttl int|array
    **/
    public function select($fields = null, $filter = null, array $options = null, $ttl = 0)
    {
        if (!$options) {
            $options = [];
        }
        $options += [
            'group' => null,
            'order' => null,
            'limit' => 0,
            'offset' => 0
        ];
        $tag = '';
        if (is_array($ttl)) {
            list($ttl,$tag) = $ttl;
        }
        $fw = Base::instance();
        $cache = Cache::instance();
        if (!($cached = $cache->exists(
            $hash = $fw->hash($this->db->dsn() .
                $fw->stringify([$fields,$filter,$options])) . ($tag ? '.' . $tag : '') . '.mongo',
            $result
        )) || !$ttl || $cached[0] + $ttl < microtime(true)
        ) {
            if ($options['group']) {
                $grp = $this->collection->group(
                    $options['group']['keys'],
                    $options['group']['initial'],
                    $options['group']['reduce'],
                    [
                        'condition' => $filter,
                        'finalize' => $options['group']['finalize']
                    ]
                );
                $tmp = $this->db->selectcollection(
                    $fw->HOST . '.' . $fw->BASE . '.' .
                    uniqid('', true) . '.tmp'
                );
                $tmp->batchinsert($grp['retval'], ['w' => 1]);
                $filter = [];
                $collection = $tmp;
            } else {
                $filter = $filter ?: [];
                $collection = $this->collection;
            }
            if ($this->legacy) {
                $this->cursor = $collection->find($filter, $fields ?: []);
                if ($options['order']) {
                    $this->cursor = $this->cursor->sort($options['order']);
                }
                if ($options['limit']) {
                    $this->cursor = $this->cursor->limit($options['limit']);
                }
                if ($options['offset']) {
                    $this->cursor = $this->cursor->skip($options['offset']);
                }
                $result = [];
                while ($this->cursor->hasnext()) {
                    $result[] = $this->cursor->getnext();
                }
            } else {
                $this->cursor = $collection->find($filter, [
                    'sort' => $options['order'],
                    'limit' => $options['limit'],
                    'skip' => $options['offset']
                ]);
                $result = $this->cursor->toarray();
            }
            if ($options['group']) {
                $tmp->drop();
            }
            if ($fw->CACHE && $ttl) {
                // Save to cache backend
                $cache->set($hash, $result, $ttl);
            }
        }
        $out = [];
        foreach ($result as $doc) {
            $out[] = $this->factory($doc);
        }
        return $out;
    }

    /**
    *   Return records that match criteria
    *   @return static[]
    *   @param $filter array
    *   @param $options array
    *   @param $ttl int|array
    **/
    public function find($filter = null, array $options = null, $ttl = 0)
    {
        if (!$options) {
            $options = [];
        }
        $options += [
            'group' => null,
            'order' => null,
            'limit' => 0,
            'offset' => 0
        ];
        return $this->select($this->fields, $filter, $options, $ttl);
    }

    /**
    *   Count records that match criteria
    *   @return int
    *   @param $filter array
    *   @param $options array
    *   @param $ttl int|array
    **/
    public function count($filter = null, array $options = null, $ttl = 0)
    {
        $fw = Base::instance();
        $cache = Cache::instance();
        $tag = '';
        if (is_array($ttl)) {
            list($ttl,$tag) = $ttl;
        }
        if (!($cached = $cache->exists($hash = $fw->hash($fw->stringify(
            [$filter]
        )) . ($tag ? '.' . $tag : '') . '.mongo', $result)) || !$ttl ||
            $cached[0] + $ttl < microtime(true)
        ) {
            $result = $this->collection->count($filter ?: []);
            if ($fw->CACHE && $ttl) {
                // Save to cache backend
                $cache->set($hash, $result, $ttl);
            }
        }
        return $result;
    }

    /**
    *   Return record at specified offset using criteria of previous
    *   load() call and make it active
    *   @return array
    *   @param $ofs int
    **/
    public function skip($ofs = 1)
    {
        $this->document = ($out = parent::skip($ofs)) ? $out->document : [];
        if ($this->document && isset($this->trigger['load'])) {
            Base::instance()->call($this->trigger['load'], $this);
        }
        return $out;
    }

    /**
    *   Insert new record
    *   @return array
    **/
    public function insert()
    {
        if (isset($this->document['_id'])) {
            return $this->update();
        }
        if (isset($this->trigger['beforeinsert']) &&
            Base::instance()->call(
                $this->trigger['beforeinsert'],
                [$this,['_id' => $this->document['_id']]]
            ) === false
        ) {
            return $this->document;
        }
        if ($this->legacy) {
            $this->collection->insert($this->document);
            $pkey = ['_id' => $this->document['_id']];
        } else {
            $result = $this->collection->insertone($this->document);
            $pkey = ['_id' => $result->getinsertedid()];
        }
        if (isset($this->trigger['afterinsert'])) {
            Base::instance()->call(
                $this->trigger['afterinsert'],
                [$this,$pkey]
            );
        }
        $this->load($pkey);
        return $this->document;
    }

    /**
    *   Update current record
    *   @return array
    **/
    public function update()
    {
        $pkey = ['_id' => $this->document['_id']];
        if (isset($this->trigger['beforeupdate']) &&
            Base::instance()->call(
                $this->trigger['beforeupdate'],
                [$this,$pkey]
            ) === false
        ) {
            return $this->document;
        }
        $upsert = ['upsert' => true];
        if ($this->legacy) {
            $this->collection->update($pkey, $this->document, $upsert);
        } else {
            $this->collection->replaceone($pkey, $this->document, $upsert);
        }
        if (isset($this->trigger['afterupdate'])) {
            Base::instance()->call(
                $this->trigger['afterupdate'],
                [$this,$pkey]
            );
        }
        return $this->document;
    }

    /**
    *   Delete current record
    *   @return bool
    *   @param $quick bool
    *   @param $filter array
    **/
    public function erase($filter = null, $quick = true)
    {
        if ($filter) {
            if (!$quick) {
                foreach ($this->find($filter) as $mapper) {
                    if (!$mapper->erase()) {
                        return false;
                    }
                }
                return true;
            }
            return $this->legacy ?
                $this->collection->remove($filter) :
                $this->collection->deletemany($filter);
        }
        $pkey = ['_id' => $this->document['_id']];
        if (isset($this->trigger['beforeerase']) &&
            Base::instance()->call(
                $this->trigger['beforeerase'],
                [$this,$pkey]
            ) === false
        ) {
            return false;
        }
        $result = $this->legacy ?
            $this->collection->remove(['_id' => $this->document['_id']]) :
            $this->collection->deleteone(['_id' => $this->document['_id']]);
        parent::erase();
        if (isset($this->trigger['aftererase'])) {
            Base::instance()->call(
                $this->trigger['aftererase'],
                [$this,$pkey]
            );
        }
        return $result;
    }

    /**
    *   Reset cursor
    *   @return NULL
    **/
    public function reset()
    {
        $this->document = [];
        parent::reset();
    }

    /**
    *   Hydrate mapper object using hive array variable
    *   @return NULL
    *   @param $var array|string
    *   @param $func callback
    **/
    public function copyfrom($var, $func = null)
    {
        if (is_string($var)) {
            $var = Base::instance()->$var;
        }
        if ($func) {
            $var = call_user_func($func, $var);
        }
        foreach ($var as $key => $val) {
            $this->set($key, $val);
        }
    }

    /**
    *   Populate hive array variable with mapper fields
    *   @return NULL
    *   @param $key string
    **/
    public function copyto($key)
    {
        $var=&Base::instance()->ref($key);
        foreach ($this->document as $key => $field) {
            $var[$key] = $field;
        }
    }

    /**
    *   Return field names
    *   @return array
    **/
    public function fields()
    {
        return array_keys($this->document);
    }

    /**
    *   Return the cursor from last query
    *   @return object|NULL
    **/
    public function cursor()
    {
        return $this->cursor;
    }

    /**
    *   Retrieve external iterator for fields
    *   @return object
    **/
    public function getiterator()
    {
        return new \ArrayIterator($this->cast());
    }
}
