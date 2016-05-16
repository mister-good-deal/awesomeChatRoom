<?php
/**
 * Entity manager for the entity RoomBan
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use abstracts\EntityManager as EntityManager;
use classes\entities\Room as Room;
use classes\entities\RoomBan as RoomBan;
use classes\entitiesCollection\RoomBanCollection as RoomBanCollection;
use classes\DataBase as DB;
use traits\ShortcutsTrait as ShortcutsTrait;

/**
 * Performed database action relative to the chat room ban entity class
 *
 * @property   RoomBan            $entity             The Room entity
 * @property   RoomBanCollection  $entityCollection   The RoomBanCollection collection
 *
 * @method Room getEntity() {
 *      Get the room entity
 *
 *      @return Room The chat room entity
 * }
 *
 * @method RoomBanCollection getEntityCollection() {
 *      Get the room ban collection
 *
 *      @return RoomBanCollection The room ban collection
 * }
 */
class RoomBanEntityManager extends EntityManager
{
    use ShortcutsTrait;

    /**
     * Constructor that can take a RoomBan entity as first parameter and a RoomBanCollection as second parameter
     *
     * @param      RoomBan            $roomBan            A room ban entity object DEFAULT null
     * @param      RoomBanCollection  $roomBanCollection  A room ban collection DEFAULT null
     */
    public function __construct(RoomBan $roomBan = null, RoomBanCollection $roomBanCollection = null)
    {
        parent::__construct($roomBan, $roomBanCollection);
    }

    /**
     * Determine if an ip is banned.
     *
     * @param      string  $ip     The ip to check
     *
     * @return     bool    True if the ip is banned, false otherwise
     */
    public function isIpBanned(string $ip): bool
    {
        return static::inSubArray($ip, $this->entityCollection, 'ip');
    }

    /**
     * Load the banned users for the current room
     */
    public function loadBannedUsers()
    {
        $roomBanCollection = new RoomBanCollection();
        $sqlMarks          = 'SELECT * FROM %s WHERE `idRoom` = %d';
        $sql               = static::sqlFormat(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->entity->idRoom
        );

        foreach (DB::query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $roomBanCollection->add((new RoomBan($row)));
        }

        $this->entityCollection = $roomBanCollection;
    }
}
