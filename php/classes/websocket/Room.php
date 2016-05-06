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
    /**
     * @var        $clients     ClientCollection The clients connected to the room
     */
    private $clients;
    /**
     * @var        $room    ChatRoom A ChatRoom entity representing the room instance
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

    /*=====  End of Getters / setters  ======*/
}
