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
        $string = PHP_EOL . 'Collection of (' . $this->count() . ') ' . $this->getEntityByIndex(0)->getEntityName()
            . ' entity' . PHP_EOL . implode(array_fill(0, 116, '-'));

        foreach ($this->collection as $entity) {
            $string .= $entity . implode(array_fill(0, 116, '-'));
        }

        return $string;
    }

    /*-----  End of Magic mathods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Add an entity at the end of the collection
     *
     * @param      Entity     $entity  The entity object
     *
     * @throws     Exception  If the entity id is already in the collection
     */
    public function add(Entity $entity)
    {
        $id = $this->parseId($entity->getIdValue());

        if (array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This entity id(' . implode(', ', $id) .') is already in the collection',
                Exception::$WARNING
            );
        } else {
            $this->collection[] = $entity;
            $this->indexId[$id] = $this->count();
        }
    }

    /**
     * Get an entity by its id
     *
     * @param      int[]|string[]  $entityId  The entity id(s) in a array
     *
     * @throws     Exception       If the entity id is not in the collection
     *
     * @return     Entity          The entity
     */
    public function getEntityById(array $entityId): Entity
    {
        $id = $this->parseId($entityId);

        if (!array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This entity id(' . implode(', ', $id) . ') is not in the collection',
                Exception::$PARAMETER
            );
        }

        return $this->collection[$this->indexId[$id]];
    }

    /**
     * Get an entity by its index
     *
     * @param      int     $index  The entity index in the Collection
     *
     * @return     Entity  The entity
     */
    public function getEntityByIndex(int $index): Entity
    {
        if (!isset($this->collection[$index])) {
            throw new Exception('There is no entity at index ' . $index, Exception::$PARAMETER);
        }

        return $this->collection[$index];
    }

    /*==========  Iterator interface  ==========*/

    /**
     * Returns the current element
     *
     * @return     Entity  The current entity
     */
    public function current(): Entity
    {
        return $this->collection[$this->current];
    }

    /**
     * Returns the key of the current entity
     *
     * @return     int|null  Returns the key on success, or NULL on failure
     */
    public function key(): int
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
    public function valid(): bool
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
    public function offsetExists($offset): bool
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
    public function offsetGet($offset): Entity
    {
        return $this->collection[$offset];
    }

    /**
     * Assigns an entity to the specified offset
     *
     * @param      int|string  $offset  The offset to assign the entity to
     * @param      Entity      $entity  The entity to set
     */
    public function offsetSet($offset, $entity): Entity
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
    public function count(): int
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
     */
    public function seek(int $position)
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
     * Parse the id(s) sent in array to get his key
     *
     * @param      int[]|string[]  $id     The id(s) in an array
     *
     * @return     string          The id(s) key
     */
    private function parseId(array $id): string
    {
        if (count($id) > 1) {
            $id = $this->md5Array($id);
        } else {
            $id = $id[0];
        }

        return $id;
    }

    /*-----  End of Private method  ------*/
}
