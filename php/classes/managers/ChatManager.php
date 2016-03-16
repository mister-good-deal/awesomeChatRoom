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
}
