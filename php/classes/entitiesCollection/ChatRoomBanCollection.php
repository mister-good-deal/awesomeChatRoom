<?php
/**
 * ChatRoomBan Collection
 *
 * @package    CollectionEntity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\CollectionEntity as CollectionEntity;
use classes\entities\ChatRoomBan as ChatRoomBan;

/**
 * A collection of ChatRoomBan entity that extends the CollectionEntity pattern
 *
 *
 * @method add(ChatRoomBan $entity) {
 *      Add a ChatRoom ban entity at the end of the collection
 * }
 *
 * @method ChatRoomBan getEntityById([$roomId, $ip]) {
 *      Get a ChatRoom ban entity by the room ID and the ip
 *
 *      @return ChatRoomBan The chatRoom ban entity
 * }
 *
 * @method ChatRoomBan getEntityByIndex($index) {
 *      Get a chatRoom ban entity by its index
 *
 *      @return ChatRoomBan The chatRoom ban entity
 * }
 */
class ChatRoomBanCollection extends CollectionEntity
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
