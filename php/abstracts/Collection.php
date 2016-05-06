<?php
/**
 * Abstarct Collection pattern
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\ExceptionManager as Exception;

/**
 * Abstract Collection pattern
 *
 * @abstract
 *
 * @todo PHP7 defines object return OR null with method(...): ?Class
 * @see https://wiki.php.net/rfc/nullable_types
 * @see https://wiki.php.net/rfc/union_types
 */
abstract class Collection implements \Iterator, \ArrayAccess, \Countable, \SeekableIterator
{
    use \traits\PrettyOutputTrait;

    /**
     * @var        Object[]  $collection    An array of object
     */
    protected $collection = array();
    /**
     * @var        int[]|string[]  $indexId     An array of id key
     */
    protected $indexId = array();
    /**
     * @var        integer  $current    Current position of the pointer in the $collection
     */
    protected $current = 0;

    /*=====================================
    =            Magic mathods            =
    =====================================*/

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Pretty print the Collection
     *
     * @return     string  String output
     */
    public function __toString(): string
    {
        $string = PHP_EOL . 'Collection of (' . $this->count() . ') '
            . str_replace('Collection', '', get_class($this))
            . PHP_EOL . implode(array_fill(0, 116, '-')) . PHP_EOL;

        foreach ($this->collection as $object) {
            $string .= $object . implode(array_fill(0, 116, '-')) . PHP_EOL;
        }

        return $string;
    }

    /*-----  End of Magic mathods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get the current Collection
     *
     * @return     array  The current collection as an array
     */
    public function getCollection(): array
    {
        return $this->collection;
    }

    /**
     * Add an object at the end of the collection
     *
     * @param      Object  $object  The object
     * @param      string  $key     A key to save the object DEFAULT null (auto generated)
     *
     * @throws     Exception  If the object ID is already in the collection
     */
    public function add($object, $key = null)
    {
        $id = $key ?? $this->count() - 1;

        if (array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This object id(' . $this->formatVariable($id) .') is already in the collection ' . $this,
                Exception::$WARNING
            );
        } else {
            $this->collection[] = $object;
            $this->indexId[$id] = $this->count() - 1;
        }
    }

    /**
     * Set an object which is already in the collection
     *
     * @param      Object      $object  The object
     * @param      string|int  $id      The object ID
     *
     * @throws     Exception  If the object ID is not already in the collection
     */
    public function set($object, $id)
    {
        if (!array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This object id(' . $this->formatVariable($id) .') is not already in the collection ' . $this,
                Exception::$WARNING
            );
        } else {
            $this->collection[$this->indexId[$id]] = $object;
        }
    }

    /**
     * Get an object by its id or null if there is no object at the given id
     *
     * @param      string|int   $id     The object ID
     *
     * @return     Object|null  The object or null if the object is not in the collection
     */
    public function getObjectById($id)
    {
        $object = null;

        if (array_key_exists($id, $this->indexId)) {
            $object = $this->collection[$this->indexId[$id]];
        }

        return $object;
    }

    /**
     * Get an object by its index or null if there is no object at the given index
     *
     * @param      int|string   $index  The object index in the Collection
     *
     * @return     Object|null  The object or null if the object is not in the collection
     */
    public function getObjectByIndex($index)
    {
        $object = null;

        if (isset($this->collection[$index])) {
            $object = $this->collection[$index];
        }

        return $object;
    }

    /*==========  Iterator interface  ==========*/

    /**
     * Returns the current element
     *
     * @return     Object  The current object
     */
    public function current()
    {
        return $this->collection[$this->current];
    }

    /**
     * Returns the key of the current object
     *
     * @return     int|null  Returns the key on success, or NULL on failure
     */
    public function key()
    {
        return $this->current;
    }

    /**
     * Moves the current position to the next element
     */
    public function next()
    {
        $this->current++;
    }

    /**
     * Rewinds back to the first element of the Iterator
     */
    public function rewind()
    {
        $this->current = 0;
    }

    /**
     * Checks if current position is valid
     *
     * @return     bool  Returns true on success or false on failure
     */
    public function valid()
    {
        return isset($this->collection[$this->current]);
    }

    /*==========  ArrayAccess interface  ==========*/

    /**
     * Whether an offset exists
     *
     * @param      int|string  $offset  An offset to check for
     *
     * @return     bool        True if the offset exists, else false
     */
    public function offsetExists($offset)
    {
        return isset($this->collection[$offset]);
    }

    /**
     * Returns the object at specified offset
     *
     * @param      int|string  $offset  The offset to retrieve
     *
     * @return     Object      Return the matching object
     */
    public function offsetGet($offset)
    {
        return $this->collection[$offset];
    }

    /**
     * Assigns an object to the specified offset
     *
     * @param      int|string  $offset  The offset to assign the object to
     * @param      Object      $object  The object to set
     */
    public function offsetSet($offset, $object)
    {
        $this->collection[$offset] = $object;
    }

    /**
     * Unset an offset
     *
     * @param      int|string  $offset  The offset to unset
     */
    public function offsetUnset($offset)
    {
        unset($this->collection[$offset]);
    }

    /*==========  Countable interface  ==========*/

    /**
     * Count elements of an object
     *
     * @return     int   The custom count as an integer
     */
    public function count()
    {
        return count($this->collection);
    }

    /*==========  SeekableIterator interface  ==========*/

    /**
     * Seeks to a position
     *
     * @param      int        $position  The position to seek to
     *
     * @throws     Exception  If the position is not seekable
     *
     * @todo PHP7 type int $position not possible
     */
    public function seek($position)
    {
        if (!isset($this->collection[$position])) {
            throw new Exception('There is no data in this iterator at index ' . $position, Exception::$ERROR);
        } else {
            $this->current = $position;
        }
    }

    /*-----  End of Public methods  ------*/
}
