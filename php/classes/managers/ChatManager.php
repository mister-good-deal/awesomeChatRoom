<?php
/**
 * Manager for he entity ChatRooms
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\managers;

use \abstracts\Manager as Manager;
use \classes\entities\User as User;
use \classes\entities\ChatRoom as ChatRoom;
use \classes\entities\ChatRoomBan as ChatRoomBan;
use \classes\entitiesCollection\ChatRoomBanCollection as ChatRoomBanCollection;
use \classes\entitiesManager\ChatRoomEntityManager as ChatRoomEntityManager;
use \classes\entitiesManager\ChatRoomBanEntityManager as ChatRoomBanEntityManager;

/**
 * Perform action relative to the ChatRoom and ChatRoomBan entities classes
 */
class ChatManager extends Manager
{
    /**
     * @var        ChatRoom  $chatRoomEntity    A ChatRoom entity to work with
     */
    private $chatRoomEntity;
    /**
     * @var        ChatRoomEntityManager  $chatRoomEntityManager    A chat room entity manager
     */
    private $chatRoomEntityManager;
    /**
     * @var        ChatRoomBanEntityManager  $chatRoomBanEntityManager  A chat room ban entity manager
     */
    private $chatRoomBanEntityManager;

    /*=====================================
    =            Magic Methods            =
    =====================================*/

    /**
     * Constructor that can take a ChatRoom entity as first parameter and a ChatRoomBanCollection as second parameter
     *
     * @param      ChatRoom               $entity      A user entity object
     * @param      ChatRoomBanCollection  $collection  A ChatRoomBanCollection object DEFAULT null
     */
    public function __construct(ChatRoom $entity, ChatRoomBanCollection $collection = null)
    {
        parent::__construct();

        $this->chatRoomEntity           = $entity;
        $this->chatRoomEntityManager    = new ChatRoomEntityManager($entity, $collection);
        $this->chatRoomBanEntityManager = new ChatRoomBanEntityManager($entity, $collection);
    }

    /*=====  End of Magic Methods  ======*/

    public function setChatRoomEntity(ChatRoom $chatRoom)
    {
        $this->chatRoomEntity = $chatRoom;
    }

    public function getChatRoomEntity(): ChatRoom
    {
        return $this->chatRoomEntity;
    }

    /**
     * Check if the ip is banned for a room
     *
     * @param      string    $ip     The ip to check
     *
     * @return     bool True if ip is banned else false
     */
    public function isIpBanned(string $ip): bool
    {
        return $this->chatRoomBanEntityManager->isIpBanned($ip);
    }
}
