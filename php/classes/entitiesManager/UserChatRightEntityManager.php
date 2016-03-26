<?php
/**
 * Entity manager for the entity UserChatRight
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\UserChatRight as UserChatRight;
use \classes\entitiesCollection\UserChatRightCollection as UserChatRightCollection;
use \classes\DataBase as DB;

/**
 * Performed database action relative to the user chat right entity class
 *
 * @property   UserChatRight            $entity             The UserChatRight entity
 * @property   UserChatRightCollection  $entityCollection   The UserChatRight collection
 *
 * @method UserChatRight getEntity() {
 *      Get the user chat right entity
 *
 *      @return UserChatRight The user chat right entity
 * }
 *
 * @method UserChatRightCollection getEntityCollection() {
 *      Get the user chat right collection
 *
 *      @return UserChatRightCollection The user chat right collection
 * }
 *
 * @todo       Move changeRoomName(), addRoomName() and removeRoomName() in a chatRoom entityManager class
 */
class UserChatRightEntityManager extends EntityManager
{
    /**
     * Constructor that can take a UserChatRight entity as first parameter and a Collection as second parameter
     *
     * @param      UserChatRight            $entity      A UserChatRight entity object DEFAULT null
     * @param      UserChatRightCollection  $collection  A colection oject DEFAULT null
     */
    public function __construct(UserChatRight $entity = null, UserChatRightCollection $collection = null)
    {
        parent::__construct($entity, $collection);

        if ($entity === null) {
            $this->entity = new UserChatRight();
        }

        if ($collection === null) {
            $this->entityCollection = new UserChatRightCollection();
        }
    }

    /**
     * Grant all the rights to the user in the current chat room
     */
    public function grantAll()
    {
        $this->entity->kick     = true;
        $this->entity->ban      = true;
        $this->entity->grant    = true;
        $this->entity->password = true;
        $this->entity->rename   = true;
    }

    /**
     * Load all the user rooms chat right
     *
     * @param      int   $userId  The user ID
     */
    public function loadUserChatRight(int $userId)
    {
        $sqlMarks   = 'SELECT * FROM %s WHERE idUser = %d';
        $sql        = static::sqlFormater($sqlMarks, $this->entity->getTableName(), $userId);
        $chatRights = DB::query($sql)->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($chatRights as $chatRightInfo) {
            $chatRight = (new UserChatRight())->setAttributes($chatRightInfo);
            $this->entityCollection->add($chatRight, $chatRight->idRoom);
        }
    }

    /**
     * Change a room name in the chat rights table
     *
     * @param      string  $oldRoomName  The old room name
     * @param      string  $newRoomName  The new room name
     *
     * @return     int     The number of rows updated
     */
    public function changeRoomName(string $oldRoomName, string $newRoomName): int
    {
        $sqlMarks = 'UPDATE %s SET `roomName` = %s WHERE `roomName` = %s';
        $sql      = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            DB::quote($newRoomName),
            DB::quote($oldRoomName)
        );

        return (int) DB::exec($sql);
    }

    /**
     * Change a room name in the chat rights table
     *
     * @param      string  $roomName  The old room name
     *
     * @return     int     The number of rows deleted
     */
    public function removeRoomName(string $roomName): int
    {
        $sqlMarks = 'DELETE FROM %s WHERE `roomName` = %s';
        $sql      = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            DB::quote($roomName)
        );

        return (int) DB::exec($sql);
    }
}
