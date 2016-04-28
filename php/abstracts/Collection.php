<?php
/**
 * Abstarct Collection pattern
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\ExceptionManager as Exception;
use \abstracts\Entity as Entity;

/**
 * Abstract Collection pattern to use with Entity pattern
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
     * @var        Entity[]  $collection    An array of entity object
     */
    private $collection = array();
    /**
     * @var        int[]|string[]  $indexId     An array of entity id key
     */
    private $indexId = array();
    /**
     * @var        integer  $current    Current position of the pointer in the $collection
     */
    private $current = 0;

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
            . ($this->count() > 0 ? $this->getEntityByIndex(0)->getEntityName() : 'Unknown')
            . ' entity' . PHP_EOL . implode(array_fill(0, 116, '-')) . PHP_EOL;

        foreach ($this->collection as $entity) {
            $string .= $entity . implode(array_fill(0, 116, '-')) . PHP_EOL;
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
        $arrayStyle = [];

        foreach ($this->collection as $entity) {
            $arrayStyle[] = $entity->__toArray();
        }

        return $arrayStyle;
    }

    /**
     * Add an entity at the end of the collection
     *
     * @param      Entity     $entity  The entity object
     * @param      string     $key     A key to save the entity DEFAULT null (auto generated)
     *
     * @throws     Exception  If the entity id is already in the collection
     */
    public function add($entity, $key = null)
    {
        $id = $key ?? $this->parseId($entity->getIdValue());

        if (array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This entity id(' . $this->formatVariable($id) .') is already in the collection ' . $this,
                Exception::$WARNING
            );
        } else {
            $this->collection[] = $entity;
            $this->indexId[$id] = $this->count() - 1;
        }
    }

    /**
     * Set an entity which is already in the collection
     *
     * @param      Entity     $entity  The entity object
     * @param      string     $key     A key to save the entity DEFAULT null (auto generated)
     *
     * @throws     Exception  If the entity id is noy already in the collection
     */
    public function set($entity, $key = null)
    {
        $id = $key ?? $this->parseId($entity->getIdValue());

        if (!array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This entity id(' . $this->formatVariable($id) .') is not already in the collection ' . $this,
                Exception::$WARNING
            );
        } else {
            $this->collection[$this->indexId[$id]] = $entity;
        }
    }

    /**
     * Get an entity by its id or null if there is no entity at the given id
     *
     * @param      mixed        $entityId  The entity id(s) in a array
     *
     * @return     Entity|null  The entity
     */
    public function getEntityById($entityId)
    {
        $entity = null;
        $id     = $this->parseId($entityId);

        if (array_key_exists($id, $this->indexId)) {
            $entity = $this->collection[$this->indexId[$id]];
        }

        return $entity;
    }

    /**
     * Get an entity by its index or null if there is no entity at the given index
     *
     * @param      int|string  $index  The entity index in the Collection
     *
     * @return     Entity|null      The entity
     */
    public function getEntityByIndex($index)
    {
        $entity = null;

        if (isset($this->collection[$index])) {
            $entity = $this->collection[$index];
        }

        return $entity;
    }

    /*==========  Iterator interface  ==========*/

    /**
     * Returns the current element
     *
     * @return     Entity  The current entity
     */
    public function current()
    {
        return $this->collection[$this->current];
    }

    /**
     * Returns the key of the current entity
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
     * Returns the entity at specified offset
     *
     * @param      int|string  $offset  The offset to retrieve
     *
     * @return     Entity      Return the matching entity
     */
    public function offsetGet($offset)
    {
        return $this->collection[$offset];
    }

    /**
     * Assigns an entity to the specified offset
     *
     * @param      int|string  $offset  The offset to assign the entity to
     * @param      Entity      $entity  The entity to set
     */
    public function offsetSet($offset, $entity)
    {
        $this->collection[$offset] = $entity;
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

    /*======================================
    =            Private method            =
    ======================================*/

    /**
     * Parse the id(s) sent to transform it in a string if the id is on multiple columns
     *
     * @param      mixed   $id     The id(s) in an array
     *
     * @return     string  The id(s) key
     */
    private function parseId($id): string
    {
        if (is_array($id)) {
            $id = $this->md5Array($id);
        }

        return $id;
    }

    /*-----  End of Private method  ------*/
}
