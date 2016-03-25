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
use \classes\DataBase as DB;

/**
 * Performed database action relative to the ChatRoom entity class
 *
 * @property   ChatRoom  $entity  The chat room entity
 */
class ChatRoomEntityManager extends EntityManager
{
    /**
     * Constructor that can take a ChatRoom entity as first parameter
     *
     * @param      ChatRoom  $entity  A ChatRooms entity object DEFAULT null
     */
    public function __construct(ChatRoom $entity = null)
    {
        parent::__construct($entity);

        if ($entity === null) {
            $this->entity = new ChatRoom();
        } elseif ($entity->getChatRoomBanCollection() !== null) {
            $this->entity->setChatRoomBanCollection($entity->getChatRoomBanCollection());
        }
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
        $success  = false;
        $errors   = array();
        $roomName = trim($roomsName);

        if ($roomName === '') {
            $errors[] = _('The room name cannot be empty');
        }

        if (!is_numeric($maxUsers) || $maxUsers < 2) {
            $errors[] = _('The max number of users must be a number and must no be less than 2');
        }

        $sqlMarks = 'SELECT COUNT(id) FROM %s WHERE name = %s';
        $sql      = static::sqlFormater($sqlMarks, $this->entity->getTableName(), DB::quote($roomName));

        if ((int) DB::query($query)->fetchColumn() > 0) {
            $errors[] = _('This room name already exists');
        }

        if (count($errors) === 0) {
            // Creation
            $query = 'SELECT MAX(id) FROM ' . $this->entity->getTableName();
            $this->entity->id           = (int) DB::query($query)->fetchColumn() + 1;
            $this->entity->creator      = $idUser;
            $this->entity->name         = $roomName;
            $this->entity->maxUsers     = $maxUsers;
            $this->entity->password     = $password;
            $this->entity->creationDate = date('Y-m-d H:i:s');
            $success = $this->saveEntity();
        }

        return array(
            'success'  => $success,
            'errors'   => $errors
        );
    }
}
