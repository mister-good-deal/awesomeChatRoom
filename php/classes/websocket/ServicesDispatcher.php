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
use Icicle\Socket\Socket as Socket;
use Icicle\WebSocket\Application as Application;
use Icicle\WebSocket\Connection as Connection;
use classes\websocket\Client as Client;
use classes\websocket\ClientCollection as ClientCollection;
use classes\websocket\RoomCollection as RoomCollection;
use classes\websocket\services\ChatService as ChatService;
use classes\websocket\services\RoomService as RoomService;
use classes\websocket\services\ClientService as ClientService;
use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;

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
     * @var        $logger      Logger A LoggerManager instance
     */
    private $logger;
    /**
     * @var        ClientCollection  $clients   Clients live session as ClientCollection
     */
    private $clients;
    /**
     * @var        RoomCollection  $rooms   Rooms live sessions as RoomCollection
     */
    private $rooms;

    /**
     * Constructor the initialize the log function, the chat service handler and the chat service pipe
     */
    public function __construct()
    {
        $this->rooms                     = new RoomCollection();
        $this->clients                   = new ClientCollection();
        $this->logger                    = new Logger([Logger::CONSOLE]);
        $this->services['chatService']   = new ChatService();
        $this->services['roomService']   = new RoomService();
        $this->services['clientService'] = new ClientService();
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
        $this->logger->log(
            LogLevel::INFO,
            sprintf(
                '[WebSocket] :: connection from %s:%d opened',
                $connection->getRemoteAddress(),
                $connection->getRemotePort()
            )
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
        $client   = new Client($connection);
        $iterator = $connection->read()->getIterator();

        $this->clients->add($client);

        yield $connection->send(json_encode([
            'service'    => 'clientService',
            'action'     => 'connect',
            'id'         => $client->getId(),
            'connection' => [
                'remoteAddress' => $connection->getRemoteAddress(),
                'remotePort'    => $connection->getRemotePort()
            ]
        ]));

        while (yield $iterator->isValid()) {
            yield $this->serviceSelector(
                json_decode($iterator->getCurrent()->getData(), true),
                $this->clients->getObjectById($client->getId())
            );

            $this->logger->log(LogLevel::DEBUG, $this->clients);
        }

        yield $this->onDisconnection($client, $response, $request);
    }

    /**
     * This method is called when a WebSocket connection is closed from the WebSocket server.
     *
     * This method close the connection properly and alert services that a client is disconnected
     *
     * @coroutine
     *
     * @param      Client                                       $client
     * @param      \Icicle\Http\Message\Response                $response
     * @param      \Icicle\Http\Message\Request                 $request
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onDisconnection(Client $client, Response $response, Request $request)
    {
        // yield $this->services['chatService']->disconnectUser($client);
        $this->clients->remove($client);

        $this->logger->log(
            LogLevel::INFO,
            '[WebSocket] :: Client disconnected => ' . $client
        );
    }

    /**
     * Service dispatcher to call the class which can treat the client request
     *
     * @param      array                                   $data    JSON decoded client data
     * @param      Client                                  $client  The client object
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable
     */
    private function serviceSelector(array $data, Client $client)
    {
        $this->logger->log(LogLevel::DEBUG, 'Data: ' . $this->formatVariable($data));

        foreach ($data['service'] as $service) {
            switch ($service) {
                case $this->services['clientService']->getServiceName():
                    yield $this->services['clientService']->process($data, $client);
                    break;

                case 'chatService':
                    break;

                default:
                    yield $this->services[$service]->process($data, $client, $this->rooms);
            }
        }
    }
}
