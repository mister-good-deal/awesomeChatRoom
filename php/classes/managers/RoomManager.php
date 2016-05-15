<?php
/**
 * Manager for the entities Room, RoomBan
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\managers;

use abstracts\Manager as Manager;
use classes\entities\Room as Room;
use classes\entities\RoomBan;
use classes\entities\RoomRight as RoomRight;
use classes\entitiesCollection\RoomCollection as RoomCollection;
use classes\entitiesManager\RoomEntityManager as RoomEntityManager;
use classes\entitiesManager\RoomRightEntityManager as RoomRightEntityManager;
use classes\entitiesManager\RoomBanEntityManager as RoomBanEntityManager;
use classes\websocket\Client as Client;
use classes\ExceptionManager as Exception;

/**
 * Perform action relative to the Room and RoomBan entities classes
 */
class RoomManager extends Manager
{
    use \traits\ShortcutsTrait;

    /**
     * @var        Room  $room  A Room entity to work with
     */
    private $room;
    /**
     * @var        RoomCollection  $roomCollection  A RoomCollection to work with
     */
    private $roomCollection;

    /*=====================================
    =            Magic Methods            =
    =====================================*/

    /**
     * Constructor that can take a Room entity as first parameter and a RoomCollection as second parameter
     *
     * @param      Room            $room            A user entity object DEFAULT null
     * @param      RoomCollection  $roomCollection  A RoomCollection DEFAULT null
     */
    public function __construct(Room $room = null, RoomCollection $roomCollection = null)
    {
        parent::__construct();

        $this->room           = $room;
        $this->roomCollection = $roomCollection;
    }

    /*=====  End of Magic Methods  ======*/

    /**
     * Get the current room
     *
     * @return     Room  The current Room
     */
    public function getRoom(): Room
    {
        return $this->room;
    }

    /**
     * Add a client to the room
     *
     * @param      Client  $client     The client to add
     * @param      string  $pseudonym  The client pseudonym for this room
     *
     * @throws     Exception  If the Client is already in the room
     */
    public function addClient(Client $client, string $pseudonym)
    {
        $this->room->getClients()->add($client);
        $this->room->addPseudonym($client->getId(), $pseudonym);
    }

    /**
     * Ban a client from a room
     *
     * @param   Client  $client The client to ban
     * @param   Client  $admin  The admin who banned the client
     * @param   string  $reason The reason why the client got banned DEFAULT ''
     *
     * @return  bool    True if the client got banned, false otherwise
     */
    public function banClient(Client $client, Client $admin, string $reason = '')
    {
        $roomBan = new RoomBan([
            'idRoom'    => $this->room->id,
            'ip'        => $client->getConnection()->getRemoteAddress(),
            'pseudonym' => $this->room->getClientPseudonym($client),
            'admin'     => $admin->getUser()->id,
            'reason'    => $reason,
            'date'      => new \DateTime()
        ]);

        $roomBanEntityManager = new RoomBanEntityManager($roomBan);

        $success = $roomBanEntityManager->saveEntity();

        if ($success) {
            $this->room->getClients()->remove($client);
        }
        
        return $success;
    }

    /**
     * Load a room from the given room collection
     *
     * @param      int   $roomId  The id room to load
     */
    public function loadRoomFromCollection(int $roomId)
    {
        $this->room = $this->roomCollection->getEntityById($roomId);
    }

    /**
     * Get all the rooms from the database in the $entityCollection attribute
     *
     * @return     RoomCollection  All the rooms from the database
     */
    public function getAllRooms()
    {
        $roomEntityManager = new RoomEntityManager($this->room, $this->roomCollection);

        return $roomEntityManager->getAllRooms();
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
        $roomEntityManager = new RoomEntityManager();

        $success = $roomEntityManager->createRoom($idUser, $roomName, $maxUsers, $password);

        if ($success && $this->roomCollection !== null) {
            $this->roomCollection->add($roomEntityManager->getEntity());
        }

        return $success;
    }

    /**
     * Get all the room rights for a client
     *
     * @param      Client  $client  The client to grant the room rights
     *
     * @return     bool    True if the room right was added / updated, false otherwise
     */
    public function grantAllRoomRights(Client $client)
    {
        $roomRight = $client->getUser()->getRoomRight()->getEntityById($this->room->id);

        // Create the room right if it does not exist and add it to the user room right collection
        if ($roomRight === null) {
            $roomRight         = new RoomRight();
            $roomRight->idRoom = $this->room->id;
            $roomRight->idUser = $client->getUser()->id;
            $client->getUser()->getRoomRight()->add($roomRight);
        }

        $roomRightEntityManager = new RoomRightEntityManager($roomRight);

        return $roomRightEntityManager->grantAll();
    }

    /**
     * Update a room right
     *
     * @param      Client  $client          The client to update the room right to
     * @param      string  $roomRightName   The room right name
     * @param      bool    $value           The new room right value
     *
     * @return     bool    True if the room right has been updated, false otherwise
     */
    public function updateRoomRight(Client $client, string $roomRightName, bool $value): bool
    {
        $roomRight = $client->getUser()->getRoomRight()->getEntityById($this->room->id);

        // Create the room right if it does not exist and add it to the user room right collection
        if ($roomRight === null) {
            $roomRight         = new RoomRight();
            $roomRight->idRoom = $this->room->id;
            $roomRight->idUser = $client->getUser()->id;
            $client->getUser()->getRoomRight()->add($roomRight);
        }

        $roomRightEntityManager = new RoomRightEntityManager($roomRight);

        return $roomRightEntityManager->update($roomRightName, $value);
    }

    /**
     * Save the room
     *
     * @return     bool  True if the room has been saved, false otherwise
     */
    public function saveRoom(): bool
    {
        $roomEntityManager = new RoomEntityManager($this->room, $this->roomCollection);

        return $roomEntityManager->saveEntity();
    }

    /**
     * Save the room collection
     *
     * @return     bool  True if the collection has been saved, false otherwise
     */
    public function saveRoomCollection()
    {
        $roomEntityManager = new RoomEntityManager($this->room, $this->roomCollection);

        return $roomEntityManager->saveCollection();
    }

    /**
     * Determine if a client has room edit right
     *
     * @param      Client  $client  The client to check the right on
     *
     * @return     bool    True if the client has edit right, False otherwise
     */
    public function hasEditRight(Client $client): bool
    {
        $userManager = new UserManager($client->getUser());

        return $userManager->hasRoomEditRight($this->room);
    }

    /**
     * Determine if the client has room grant right
     *
     * @param      Client  $client  The client to check the right on
     *
     * @return     bool    True if the client has chat grant right, false otherwise
     */
    public function hasGrantRight(Client $client): bool
    {
        $userManager = new UserManager($client->getUser());

        return $userManager->hasRoomGrantRight($this->room);
    }

    /**
     * Determine if the client has room kick right
     *
     * @param      Client  $client  The client to check the right on
     *
     * @return     bool    True if the client has room kick right, false otherwise
     */
    public function hasKickRight(Client $client): bool
    {
        $userManager = new UserManager($client->getUser());

        return $userManager->hasRoomKickRight($this->room);
    }

    /**
     * Determine if the client has room ban right
     *
     * @param      Client  $client  The client to check the right on
     *
     * @return     bool    True if the client has room ban right, false otherwise
     */
    public function hasBanRight(Client $client): bool
    {
        $userManager = new UserManager($client->getUser());

        return $userManager->hasRoomBanRight($this->room);
    }

    /**
     * Determine if a room exist in the current collection
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if room exist, false otherwise.
     */
    public function isRoomExist(int $roomId): bool
    {
        return $this->roomCollection->isRoomExist($roomId);
    }

    /**
     * Determine if the room is full
     *
     * @return     bool  True if the room is full, false otherwise.
     */
    public function isFull(): bool
    {
        return count($this->room->getClients()) >= $this->room->maxUsers;
    }

    /**
     * Determine if a room is public
     *
     * @return     bool  True if the room is public, false otherwise.
     */
    public function isPublic(): bool
    {
        return $this->room->password === null || strlen($this->room->password) === 0;
    }

    /**
     * Determine if the room password is correct
     *
     * @param      string  $password  The room password to check
     *
     * @return     bool    True if room password is correct, false otherwise.
     */
    public function isPasswordCorrect(string $password): bool
    {
        return $this->isPublic() || $this->room->password === $password;
    }

    /**
     * Determine if a client is banned
     *
     * @param      Client  $client  The client to test
     *
     * @return     bool    True if the client is banned, false otherwise
     */
    public function isClientBanned(Client $client): bool
    {
        return static::inSubArray(
            $client->getConnection()->getRemoteAddress(),
            $this->room->getRoomBanCollection(),
            'ip'
        );
    }

    /**
     * Determine if a pseudonym is already used in the room
     *
     * @param      string  $pseudonym  The pseudonym to check
     *
     * @return     bool    True if the pseudonym is already used in the room, false otherwise
     */
    public function isPseudonymAlreadyUsed(string $pseudonym): bool
    {
        return in_array($pseudonym, $this->room->getPseudonyms());
    }
}
