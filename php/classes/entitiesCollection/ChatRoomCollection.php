<?php
/**
 * ChatRoom Collection
 *
 * @package    EntityCollection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\EntityCollection as EntityCollection;
use classes\entities\ChatRoom as ChatRoom;

/**
 * A collection of ChatRoom entity that extends the EntityCollection pattern
 *
 *
 * @method add(ChatRoom $entity) {
 *      Add a ChatRoom entity at the end of the collection
 * }
 *
 * @method ChatRoom getEntityById(int $id) {
 *      Get a ChatRoom entity by the room ID
 *
 *      @return ChatRoom The chatRoom entity
 * }
 *
 * @method ChatRoom getEntityByIndex($index) {
 *      Get a chatRoom entity by its index
 *
 *      @return ChatRoom The chatRoom entity
 * }
 */
class ChatRoomCollection extends EntityCollection
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
