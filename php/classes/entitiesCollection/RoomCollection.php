<?php
/**
 * Room Collection
 *
 * @package    EntityCollection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\EntityCollection as EntityCollection;
use classes\entities\Room as Room;

/**
 * A collection of Room entity that extends the EntityCollection pattern
 *
 * @method Room current() {
 *     Returns the current element
 * }
 *
 * @method add(Room $entity) {
 *      Add a Room entity at the end of the collection
 * }
 *
 * @method remove(Room $entity) {
 *      Remove a room from the collection
 * }
 *
 * @method Room getEntityById(int $id) {
 *      Get a Room entity by the room ID
 *
 *      @return Room The Room entity
 * }
 *
 * @method Room getEntityByIndex($index) {
 *      Get a Room entity by its index
 *
 *      @return Room The Room entity
 * }
 */
class RoomCollection extends EntityCollection
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*-----  End of Magic methods  ------*/

    /**
     * Determine if a room exist
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if room exist, false otherwise.
     */
    public function isRoomExist(int $roomId): bool
    {
        return in_array($roomId, $this->indexId);
    }
}
