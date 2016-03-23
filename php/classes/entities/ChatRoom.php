<?php
/**
 * ChatRoom entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;
use \classes\entitiesCollection\ChatRoomBanCollection as ChatRoomBanCollection;

/**
 * ChatRoom entity that extends the Entity abstact class
 *
 * @property   int     $id            The chat room id
 * @property   string  $name          The chat room name
 * @property   int     $creator       The creator id user
 * @property   int     $password      The room password
 * @property   string  $creationDate  The room creation date
 * @property   int     $maxUsers      The room max users number
 *
 * @todo PHP7 defines object return OR null with method(...): ?Class
 * @see https://wiki.php.net/rfc/nullable_types
 * @see https://wiki.php.net/rfc/union_types
 */
class ChatRoom extends Entity
{
    /**
     * @var        ChatRoomBanCollection  $chatRoomBanCollection    Collection of banned users
     */
    private $chatRoomBanCollection = null;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('ChatRoom');
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get the uers banned collection
     *
     * @return     ChatRoomBanCollection  The users banned collection
     */
    public function getChatRoomBanCollection()
    {
        return $this->chatRoomBanCollection;
    }

    /**
     * Set the uers banned collection
     *
     * @param      ChatRoomBanCollection  $chatRoomBanCollection  The users banned collection
     */
    public function setChatRoomBanCollection(ChatRoomBanCollection $chatRoomBanCollection)
    {
        $this->chatRoomBanCollection = $chatRoomBanCollection;
    }

    /*=====  End of Public methods  ======*/
}
