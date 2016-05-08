<?php
/**
 * Room class to handle websocket room
 *
 * @package    websocket
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use classes\websocket\ClientCollection as ClientCollection;
use classes\entities\ChatRoom as ChatRoom;
use Icicle\WebSocket\Connection as Connection;

/**
 * Room class to handle websocket room
 */
class Room
{
    use traits\ShortcutsTrait;

    /**
     * @var        ClientCollection  $clients   The clients connected to the room
     */
    private $clients;
    /**
     * @var        ChatRoom  $room  A ChatRoom entity representing the room instance
     */
    private $room;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that create a new Room based on the ChatRoom instance
     *
     * @param      ChatRoom  $room   A ChatRoom instance
     */
    public function __construct(ChatRoom $room)
    {
        $this->room = $room;
    }

    /**
     * Room string representation
     *
     * @return     string  The Room string representation
     */
    public function __toString(): string
    {
        return 'Room ::' . PHP_EOL
            . 'room    = ' . $this->room . PHP_EOL
            . 'clients = ' . $this->clients . PHP_EOL;
    }

    /**
     * Room object as an array
     *
     * @return     array  Room object as an array
     */
    public function __toArray(): array
    {
        return [
            'room'    => $this->room->__toArray(),
            'clients' => $this->clients->__toArray()
        ];
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Getters / setters            =
    =========================================*/

    /**
     * Get the clients
     *
     * @return     ClientCollection  The connected Clients
     */
    public function getClients(): ClientCollection
    {
        return $this->clients;
    }

    /**
     * Set the clients
     *
     * @param      ClientCollection  $clients  The connected Clients
     */
    public function setClients(ClientCollection $clients)
    {
        $this->clients = $clients;
    }

    /**
     * Get the room
     *
     * @return     ChatRoom  The ChatRoom entity
     */
    public function getRoom(): ChatRoom
    {
        return $this->room;
    }

    /**
     * Set the room
     *
     * @param      ChatRoom  $room   The ChatRoom entity
     */
    public function setRoom(ChatRoom $room)
    {
        $this->room = $room;
    }

    /**
     * Get the room ID
     *
     * @return     int   The room ID
     */
    public function getId(): int
    {
        return $this->room->id;
    }

    /*=====  End of Getters / setters  ======*/

    /*=========================================
    =            Utilities methods            =
    =========================================*/

    /**
     * Add a client to the room
     *
     * @param      Client  $client  The client to add
     */
    public function addClient(Client $client)
    {
        $this->clients->add($client);
    }

    /**
     * Determine if the room is full
     *
     * @return     bool  True if the room is full, false otherwise.
     */
    public function isFull(): bool
    {
        return count($this->clients) >= $this->room->maxUsers;
    }

    /**
     * Determine if a room is public
     *
     * @return     bool  True if the room is public, false otherwise.
     */
    public function isPublic(): bool
    {
        return $this->room->password === null || count($this->room->password) === 0;
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
            $this->room->getChatRoomBanCollection(),
            'ip'
        );
    }

    /*=====  End of Utilities methods  ======*/
}
