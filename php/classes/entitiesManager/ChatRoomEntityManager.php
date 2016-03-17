<?php
/**
 * Entity manager for the entity ChatRoom
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\ChatRoom as ChatRoom;

/**
 * Performed database action relative to the ChatRoom entity class
 */
class ChatRoomEntityManager extends EntityManager
{
    /**
     * Constructor that can take a ChatRoom entity as first parameter
     *
     * @param      ChatRoom  $entity  A ChatRooms entity object
     */
    public function __construct(ChatRoom $entity)
    {
        parent::__construct($entity);

        if ($entity->getChatRoomBanCollection() !== null) {
            $this->entity->setChatRoomBanCollection($entity->getChatRoomBanCollection());
        }
    }
}
