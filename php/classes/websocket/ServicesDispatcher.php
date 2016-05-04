<?php
/**
 * WebsSocket services dispatcher
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use Icicle\Http\Message\Request as Request;
use Icicle\Http\Message\Response as Response;
use Icicle\Log\Log as Log;
use Icicle\Socket\Socket as Socket;
use Icicle\WebSocket\Application as Application;
use Icicle\WebSocket\Connection as Connection;
use Icicle\Concurrent\Threading\Parcel as Parcel;
use Icicle\Concurrent\Threading\Thread as Thread;
use classes\entities\User as User;
use classes\websocket\services\ChatService as ChatService;
use classes\websocket\services\RoomService as RoomService;
use function Icicle\Log\log;

/**
 * Services dispatcher class to handle client requests and root them to the right websocket service handler
 */
class ServicesDispatcher implements Application
{
    use \traits\PrettyOutputTrait;

    /**
     * @var        $services    array     The differents services
     */
    private $services;
    /**
     * @var        $clientsShared       Parcel  The clients pool shared resource between threads
     */
    private $clientsShared;
    /**
     * @var        $log  \Icicle\Log\Log
     */
    protected $log = null;

    /**
     * Constructor the initialize the log function, the chat service handler and the chat service pipe
     *
     * @param      \Icicle\Log\Log|null  $log
     */
    public function __construct(Log $log = null)
    {
        $this->clientsShared           = new Parcel([]);
        $this->log                     = $log ?: log();
        // $this->services['chatService'] = new ChatService($this->log);
        $this->services['roomService'] = new RoomService($this->log);
    }

    /**
     * This method is called before responding to a handshake request when the request has been verified to be a valid
     * WebSocket request. This method can simply resolve with the response object given to it if no headers need to be
     * set or no other validation is needed. This method can also reject the request by resolving with another response
     * object entirely.
     *
     * @param      \Icicle\Http\Message\Response                                         $response
     * @param      \Icicle\Http\Message\Request                                          $request
     * @param      \Icicle\Socket\Socket                                                 $socket
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|\Icicle\Http\Message\Response
     */
    public function onHandshake(Response $response, Request $request, Socket $socket)
    {
        // Cookies may be set and returned on a new Response object, e.g.: return $response->withCookie(...);
        return $response;
    }

    /**
     * This method is called when a WebSocket connection is established to the WebSocket server. This method should not
     * resolve until the connection should be closed.
     *
     * @coroutine
     *
     * @param      \Icicle\WebSocket\Connection                 $connection
     * @param      \Icicle\Http\Message\Response                $response
     * @param      \Icicle\Http\Message\Request                 $request
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onConnection(Connection $connection, Response $response, Request $request)
    {
        yield $this->log->log(
            Log::INFO,
            'WebSocket connection from %s:%d opened',
            $connection->getRemoteAddress(),
            $connection->getRemotePort()
        );

        yield $this->connectionSessionHandle($connection, $response, $request);
    }

    /**
     * Create a client array of a Connection and a User object and handle the client session with until the connection
     * is closed
     *
     * @coroutine
     *
     * @param      \Icicle\WebSocket\Connection   $connection
     * @param      \Icicle\Http\Message\Response  $response
     * @param      \Icicle\Http\Message\Request   $request
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    private function connectionSessionHandle(Connection $connection, Response $response, Request $request)
    {
        $clientsShared = $this->clientsShared;

        // Add a thread to handle the client session
        Thread::spawn(function (Parcel $clientsShared, Connection $connection, Response $response, Request $request) {

            // Add a client in the clientsShared Parcel
            yield $clientsShared->synchronized(function (Parcel $clientsShared) {
                $clients                                        = $clientsShared->unwrap();
                $clients[$this->getConnectionHash($connection)] = array('Connection' => $connection, 'User' => null);
                $clientsShared->wrap($clients);
            });

            $iterator = $connection->read()->getIterator();

            while (yield $iterator->isValid()) {
                $clients = $clientsShared->unwrap();

                yield $this->serviceSelector(
                    json_decode($iterator->getCurrent()->getData(), true),
                    $clients[$this->getConnectionHash($connection)],
                    $clients
                );
            }

            yield $this->onDisconnection($connection, $response, $request, $clientsShared);
        }, $clientsShared, $connection, $response, $request);
    }

    /**
     * This method is called when a WebSocket connection is closed from the WebSocket server.
     *
     * This method close the connection properly and alert services that a client is disconnected
     *
     * @coroutine
     *
     * @param      \Icicle\WebSocket\Connection                 $connection
     * @param      \Icicle\Http\Message\Response                $response
     * @param      \Icicle\Http\Message\Request                 $request
     * @param      Parcel                                       $clientsShared  The clients pool shared between threads
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onDisconnection(Connection $connection, Response $response, Request $request, Parcel $clientsShared)
    {
        $connectionHash = $this->getConnectionHash($connection);

        // Remove a client from the clientsShared Parcel
        yield $clientsShared->synchronized(function (Parcel $clientsShared, string $connectionHash) {
            $clients = $clientsShared->unwrap();

            yield $this->services['chatService']->disconnectUser($clients[$connectionHash]);
            unset($clients[$connectionHash]);
            $clientsShared->wrap($clients);
        });

        yield $this->log->log(
            Log::INFO,
            'WebSocket connection from %s:%d closed',
            $connection->getRemoteAddress(),
            $connection->getRemotePort()
        );
    }

    /**
     * Get the connection hash like a Connecton ID
     *
     * @param      Connection  $connection  The connection to get the hash from
     *
     * @return     string The connection hash
     */
    protected function getConnectionHash(Connection $connection): string
    {
        return md5($connection->getRemoteAddress() + $connection->getRemotePort());
    }

    /**
     * Service dispatcher to call the class which can treat the client request
     *
     * @param      array                                   $data     JSON decoded client data
     * @param      array                                   $client   The client information [Connection, User]
     * @param      Parcel                                  $clients  The clients pool parcel
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable
     */
    private function serviceSelector(array $data, array $client, Parcel $clients)
    {
        // yield $this->log->log(Log::DEBUG, 'Data: %s', $this->formatVariable($data));

        foreach ($data['service'] as $service) {
            switch ($service) {
                case 'server':
                    yield $this->serverAction($data, $client, $clients);
                    break;

                case 'chatService':
                    break;

                default:
                    yield $this->services[$service]->process($data, $client, $clients);
            }
        }
    }

    /**
     * Action called by the client to be executed in the websocket server
     *
     * @param      array                                   $data           JSON decoded client data
     * @param      array                                   $client         The client information [Connection, User]
     * @param      Parcel                                  $clientsShared  The clients shared pool parcel
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable
     */
    private function serverAction(array $data, array $client, Parcel $clientsShared)
    {
        switch ($data['action']) {
            // Register a client in the clients pool
            case 'register':
                yield $clientsShared->synchronized(function (Parcel $clientsShared, array $client) {
                    $clients = $clientsShared->unwrap();

                    $clients[$this->getConnectionHash($client['Connection'])]['User'] = new User($data['user']);

                    $clientsShared->wrap($clients);
                });

                break;
        }
    }
}
