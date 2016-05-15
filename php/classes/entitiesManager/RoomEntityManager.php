<?php
/**
 * Entity manager for the entity Room
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use abstracts\EntityManager as EntityManager;
use classes\entities\Room as Room;
use classes\entitiesCollection\RoomCollection as RoomCollection;
use classes\ExceptionManager as Exception;
use classes\logger\LogLevel as LogLevel;
use classes\DataBase as DB;

/**
 * Performed database action relative to the chat room entity class
 *
 * @property   Room     $entity     The Room entity
 *
 * @method Room getEntity() {
 *      Get the room entity
 *
 *      @return Room The room entity
 * }
 */
class RoomEntityManager extends EntityManager
{
    /**
     * Constructor that can take a Room entity as first parameter
     *
     * @param      Room            $room            A Room entity DEFAULT null
     * @param      RoomCollection  $roomCollection  A Room collection DEFAULT null
     */
    public function __construct(Room $room = null, RoomCollection $roomCollection = null)
    {
        parent::__construct($room, $roomCollection);
    }

    /**
     * Get all the rooms from the database in the $entityCollection attribute
     *
     * @return     RoomCollection  All the rooms from the database
     */
    public function getAllRooms()
    {
        $rooms    = new RoomCollection();
        $sqlMarks = 'SELECT * FROM %s';
        $sql      = static::sqlFormat($sqlMarks, (new Room)->getTableName());

        foreach (DB::query($sql)->fetchAll() as $roomAttributes) {
            $rooms->add(new Room($roomAttributes));
        }

        return $rooms;
    }

    /**
     * Create a new room
     *
     * @param      int     $idUser    The user creator id
     * @param      string  $roomName  The room name
     * @param      int     $maxUsers  The max room users
     * @param      string  $password  The room password DEFAULT ''
     *
     * @throws     Exception  If the room name is empty
     * @throws     Exception  If the room name already exists
     * @throws     Exception  If the max number of users is lower than 2
     *
     * @return     bool    True if the room was successfully created, false otherwise
     */
    public function createRoom(int $idUser, string $roomName, int $maxUsers, string $password = ''): bool
    {
        $roomName = trim($roomName);

        // Checking error

        if ($roomName === '') {
            throw new Exception(_('The room name cannot be empty'), LogLevel::PARAMETER);
        }

        if ($maxUsers < 2) {
            throw new Exception(_('The max number of users must be greater than 1'), LogLevel::PARAMETER);
        }

        $sqlMarks = 'SELECT COUNT(id) FROM %s WHERE name = %s';
        $sql      = static::sqlFormat($sqlMarks, (new Room)->getTableName(), DB::quote($roomName));

        if ((int) DB::query($sql)->fetchColumn() > 0) {
            throw new Exception(_('This room name already exists'), LogLevel::PARAMETER);
        }

        // Creation

        $query = 'SELECT MAX(id) FROM ' . $this->entity->getTableName();
        $room  = new Room([
            'id'           => (int) DB::query($query)->fetchColumn() + 1,
            'name'         => $roomName,
            'creator'      => $idUser,
            'password'     => $password,
            'creationDate' => new \DateTime(),
            'maxUsers'     => $maxUsers
        ]);

        return $this->saveEntity($room);
    }
}
