<?php
/**
 * RoomBan Collection
 *
 * @package    EntityCollection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\EntityCollection as EntityCollection;
use classes\entities\RoomBan as RoomBan;

/**
 * A collection of RoomBan entity that extends the EntityCollection pattern
 *
 *
 * @method add(RoomBan $entity) {
 *      Add a room ban entity at the end of the collection
 * }
 *
 * @method RoomBan getEntityById([$roomId, $ip]) {
 *      Get a room ban entity by the room ID and the ip
 *
 *      @return RoomBan The room ban entity
 * }
 *
 * @method RoomBan getEntityByIndex($index) {
 *      Get a room ban entity by its index
 *
 *      @return RoomBan The room ban entity
 * }
 */
class RoomBanCollection extends EntityCollection
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
