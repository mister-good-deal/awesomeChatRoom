<?php
/**
 * Manager for he entity ChatRooms
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\Manager as Manager;
use \classes\entities\ChatRooms as ChatRooms;
use \classes\entities\ChatRoomsBan as ChatRoomsBan;
use \classes\entitiesManager\ChatRoomsEntityManager as ChatRoomsEntityManager;
use \classes\entitiesManager\ChatRoomsBanEntityManager as ChatRoomsBanEntityManager;

/**
 * Perform action relative to the ChatRooms and ChatRoomsBan entities classes
 */
class ChatManager extends Manager
{
    /**
     * @var        ChatRoomsEntityManager  $chatRoomsEntityManager  A chat rooms entity manager
     */
    private $chatRoomsEntityManager;
    /**
     * @var        ChatRoomsBanEntityManager  $chatRoomsBanEntityManager    A chat rooms ban entity manager
     */
    private $chatRoomsBanEntityManager;

    /*=====================================
    =            Magic Methods            =
    =====================================*/

    /**
     * Constructor that can take a User entity as first parameter and a Collection as second parameter
     *
     * @param      ChatRooms        $entity      A user entity object DEFAULT null
     * @param      Collection  $collection  A colection oject DEFAULT null
     */
    public function __construct(User $entity = null, Collection $collection = null)
    {
        parent::__construct();

        $this->chatRoomsEntityManager    = new ChatRoomsEntityManager($entity, $collection);
        $this->chatRoomsBanEntityManager = new ChatRoomsBanEntityManager();
    }

    /*=====  End of Magic Methods  ======*/
}
