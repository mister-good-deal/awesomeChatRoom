<?php
/**
 * Client class to handle websocket client session
 *
 * @package    websocket
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use classes\entities\User as User;
use Icicle\WebSocket\Connection as Connection;

/**
 * Client class to handle websocket client session
 */
class Client
{
    use \traits\PrettyOutputTrait;

    /**
     * @var        $connection      Connection An Icicle Connection
     */
    private $connection;
    /**
     * @var        $user    User A user entity representing the client if he's registered
     */
    private $user = null;
    /**
     * @var        $location    array An array ['lat'= > lat, 'lon' => lon] containing the client geo location
     */
    private $location = [];
    /**
     * @var        $id      string A client ID auto generated from his Icicle Connection
     */
    private $id;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that create a new Client based on the Icicle Connection
     *
     * @param      Connection  $connection  An Icicle Connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->id         = $this->generateClientHash();
    }

    /**
     * Client string representation
     *
     * @return     string  The Client string representation
     */
    public function __toString(): string
    {
        return 'Client ::' . PHP_EOL
            . "\t id         = " . $this->id . PHP_EOL
            . "\t connection = " . $this->connection->getRemoteAddress() . ':' . $this->connection->getRemotePort() . PHP_EOL
            . "\t location   = " . static::formatVariable($this->location) . PHP_EOL
            . "\t user       = " . $this->user . PHP_EOL;
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Getters / setters            =
    =========================================*/

    /**
     * Get the user
     *
     * @return     User  A User entity
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set the user
     *
     * @param      User  $user   A user entity
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the connection
     *
     * @return     Connection  An Icicle Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Set the connection
     *
     * @param      Connection  $connection  An Icicle Connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the location
     *
     * @return     array  Location as array ['lat'= > lat, 'lon' => lon]
     */
    public function getLocation(): array
    {
        return $this->location;
    }

    /**
     * Set the location
     *
     * @param      array  $location  Location as array ['lat'= > lat, 'lon' => lon]
     */
    public function setLocation(array $location)
    {
        $this->location = $location;
    }

    /**
     * Get the client id
     *
     * @return     string  The client ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /*=====  End of Getters / setters  ======*/

    /*======================================
    =            Private method            =
    ======================================*/

    /**
     * Generate a client hash like a client ID
     *
     * @return     string  The client hash
     */
    protected function generateClientHash(): string
    {
        return md5($this->connection->getRemoteAddress() + $this->connection->getRemotePort());
    }

    /*=====  End of Private method  ======*/
}
