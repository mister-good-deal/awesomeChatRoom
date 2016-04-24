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
use \classes\entitiesCollection\ChatRoomCollection as ChatRoomCollection;
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
     * @param      ChatRoom               $entity      A user entity object DEFAULT null
     * @param      ChatRoomBanCollection  $collection  A ChatRoomBanCollection object DEFAULT null
     */
    public function __construct(ChatRoom $entity = null, ChatRoomBanCollection $collection = null)
    {
        parent::__construct();

        $this->chatRoomEntity           = $entity;
        $this->chatRoomEntityManager    = new ChatRoomEntityManager($entity, $collection);
        $this->chatRoomBanEntityManager = new ChatRoomBanEntityManager($entity, $collection);
    }

    /*=====  End of Magic Methods  ======*/

    /**
     * Get the current chat room entity
     *
     * @return     ChatRoom  The current chat room entity
     */
    public function getChatRoomEntity(): ChatRoom
    {
        return $this->chatRoomEntity;
    }

    /**
     * Get all the rooms in the database
     *
     * @return     ChatRoomCollection  All the rooms in the database
     */
    public function getAllRooms(): ChatRoomCollection
    {
        return $this->chatRoomEntityManager->getAllRooms();
    }

    /**
     * Create a new chat room
     *
     * @param      int     $idUser    The user creator id
     * @param      string  $roomName  The room name
     * @param      int     $maxUsers  The max room users
     * @param      string  $password  The room password DEFAULT ''
     *
     * @return     array   An array with the success and the errors if it failed
     */
    public function createChatRoom(int $idUser, string $roomName, int $maxUsers, string $password = '')
    {
        $infos = $this->chatRoomEntityManager->createChatRoom($idUser, $roomName, $maxUsers, $password);

        if ($infos['success']) {
            $this->chatRoomEntity = $this->chatRoomEntityManager->getEntity();
            $this->chatRoomBanEntityManager->setEntity($this->chatRoomEntity);
            $this->chatRoomBanEntityManager->setEntityCollection($this->chatRoomEntity->getChatRoomBanCollection());
        }

        return $infos;
    }

    /**
     * Load a chat room
     *
     * @param      int   $id     The chat room ID
     *
     * @return     bool  True if the chat was successfully loaded else false
     */
    public function loadChatRoom(int $id): bool
    {
        $success = $this->chatRoomEntityManager->loadEntity($id);

        if ($success) {
            $this->chatRoomEntity = $this->chatRoomEntityManager->getEntity();
            $this->chatRoomBanEntityManager->setEntity($this->chatRoomEntity);
            $this->chatRoomBanEntityManager->setEntityCollection($this->chatRoomEntity->getChatRoomBanCollection());
            $this->chatRoomBanEntityManager->loadBannedUsers();
        }

        return $success;
    }

    /**
     * Ban a user from a room
     *
     * @param      ChatRoomBan  $chatRoomBan  Ban info
     *
     * @return     bool         True if ip is banned else false
     */
    public function banUser(ChatRoomBan $chatRoomBan): bool
    {
        $this->chatRoomEntity->getChatRoomBanCollection()->add($chatRoomBan);

        return $this->chatRoomBanEntityManager->saveCollection();
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

    /**
     * Save a chat room collection
     *
     * @param      ChatRoomCollection  $collection  The chat room collection to save
     *
     * @return     bool                True if the chat room collection has been saved else false
     */
    public function saveChatRoomCollection(ChatRoomCollection $collection): bool
    {
        $success = $this->chatRoomEntityManager->saveCollection($collection);

        foreach ($this->chatRoomEntityManager->getEntityCollection() as $room) {
            if ($success) {
                $success = $this->chatRoomBanEntityManager()->saveCollection($room->getChatRoomBanCollection());
            }
        }

        return $success;
    }
}
