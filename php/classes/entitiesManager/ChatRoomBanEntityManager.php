<?php
/**
 * Entity manager for the entity ChatRoomBan
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\ChatRoom as ChatRoom;
use \classes\entities\ChatRoomBan as ChatRoomBan;
use \classes\entitiesCollection\ChatRoomBanCollection as ChatRoomBanCollection;
use \classes\DataBase as DB;

/**
 * Performed database action relative to the chat room ban entity class
 *
 * @property   ChatRoom               $entity             The ChatRoom entity
 * @property   ChatRoomBanCollection  $entityCollection   The ChatRoomBanCollection collection
 *
 * @method ChatRoom getEntity() {
 *      Get the chat room entity
 *
 *      @return ChatRoom The chat room entity
 * }
 *
 * @method ChatRoomBanCollection getEntityCollection() {
 *      Get the chat room ban collection
 *
 *      @return ChatRoomBanCollection The chat room ban collection
 * }
 */
class ChatRoomBanEntityManager extends EntityManager
{
    use \traits\ShortcutsTrait;

    /**
     * Constructor that can take a ChatRoom entity as first parameter and a ChatRoomBanCollection as second parameter
     *
     * @param      ChatRoom  $entity  A ChatRooms entity object DEFAULT null
     */
    public function __construct(ChatRoom $entity = null)
    {
        parent::__construct($entity);

        if ($entity === null) {
            $this->entity = new ChatRoom();
        }

        $this->loadBannedUsers();
    }

    /**
     * Tell if an ip is banned or not
     *
     * @param      string  $ip     The ip to check
     *
     * @return     bool    True if ip is banned else false
     */
    public function isIpBanned(string $ip): bool
    {
        return $this->inSubArray($ip, $this->entity->getChatRoomBanCollection()->getCollection(), 'ip');
    }

    /**
     * Load the banned users for the current chat room
     */
    public function loadBannedUsers()
    {
        $chatRoomBanCollection = new ChatRoomBanCollection();
        $sqlMarks              = 'SELECT * FROM %s WHERE `idChatRoom` = %d';
        $sql                   = static::sqlFormater(
            $sqlMarks,
            (new ChatRoomBan())->getTableName(),
            $this->entity->id
        );

        foreach (DB::query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $chatRoomBanCollection->add((new ChatRoomBan($row)));
        }

        $this->entity->setChatRoomBanCollection($chatRoomBanCollection);
        $this->entityCollection = $chatRoomBanCollection;
    }
}
