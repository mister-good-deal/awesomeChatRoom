<?php
/**
 * Entity manager for the entity RoomRight
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\RoomRight as RoomRight;
use \classes\entitiesCollection\RoomRightCollection as RoomRightCollection;
use \classes\DataBase as DB;

/**
 * Performed database action relative to the room right entity class
 *
 * @property   RoomRight            $entity             The RoomRight entity
 * @property   RoomRightCollection  $entityCollection   The RoomRightCollection collection
 *
 * @method RoomRight getEntity() {
 *      Get the room right entity
 *
 *      @return RoomRight The room right entity
 * }
 *
 * @method RoomRightCollection getEntityCollection() {
 *      Get the room right collection
 *
 *      @return RoomRightCollection The room right collection
 * }
 */
class RoomRightEntityManager extends EntityManager
{
    /**
     * Constructor that can take a RoomRight entity as first parameter and a Collection as second parameter
     *
     * @param      RoomRight            $roomRight            A RoomRight entity object DEFAULT null
     * @param      RoomRightCollection  $roomRightCollection  A RoomRightCollection DEFAULT null
     */
    public function __construct(RoomRight $roomRight = null, RoomRightCollection $roomRightCollection = null)
    {
        parent::__construct($roomRight, $roomRightCollection);
    }

    /**
     * Grant all the rights to the user in the current room
     *
     * @return     bool  True if the room right was updated, false otherwise
     */
    public function grantAll(): bool
    {
        $this->entity->kick  = true;
        $this->entity->ban   = true;
        $this->entity->grant = true;
        $this->entity->edit  = true;

        return $this->saveEntity();
    }

    /**
     * Update a room right
     *
     * @param      string  $roomRightName   The room right name
     * @param      bool    $value           The new room right value
     *
     * @return     bool    True if the room right has been updated, false otherwise
     */
    public function update(string $roomRightName, bool $value): bool
    {
        $this->{$roomRightName} = $value;

        return $this->saveEntity();
    }

    /**
     * Load all the user rooms right in the $entityCollection attribute
     *
     * @param      int   $userId  The user ID
     */
    public function loadRoomsRight(int $userId)
    {
        $sqlMarks   = 'SELECT * FROM %s WHERE idUser = %d';
        $sql        = static::sqlFormater($sqlMarks, $this->entity->getTableName(), $userId);
        $chatRights = DB::query($sql)->fetchAll();

        foreach ($roomsRight as $roomRightInfo) {
            $this->entityCollection->add(new RoomRight($roomRightInfo));
        }
    }
}
