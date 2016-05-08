<?php
/**
 * RoomRight Collection
 *
 * @package    EntityCollection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\EntityCollection as EntityCollection;
use classes\entities\RoomRight as RoomRight;

/**
 * A collection of RoomRight entity that extends the EntityCollection pattern
 *
 * @method add(RoomRight $entity) {
 *      Add a room right entity at the end of the collection
 * }
 *
 * @method RoomRight getEntityById(int $roomId) {
 *      Get a room right entity by the room ID
 *
 *      @return RoomRight|null The room right entity
 * }
 *
 * @method RoomRight getEntityByIndex($index) {
 *      Get a room right entity by its index
 *
 *      @return RoomRight|null The room right entity
 * }
 */
class RoomRightCollection extends EntityCollection
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * constructor
     */
    public function __construct()
    {
    }

    /*-----  End of Magic methods  ------*/
}
