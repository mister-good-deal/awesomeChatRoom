<?php
/**
 * WebsSocket services dispatcher
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Log\Log;
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application as Application;
use Icicle\WebSocket\Connection;
use Icicle\Stream\DuplexStream;
use Icicle\Stream\MemoryStream;
use classes\entities\User as User;
use classes\websocket\services\ChatService as ChatService;
use function Icicle\Log\log;

/**
 * Services dispatcher class to handle client requests and root them to the right websocket service handler
 */
class ServicesDispatcher implements Application
{
    use \traits\PrettyOutputTrait;

    /**
     * @var        $services  array     The differents services
     */
    private $services;
    /**
     * @var        $clients  array  The clients pool
     */
    private $clients = array();
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
        $this->log                     = $log ?: log();
        $this->services['chatService'] = new ChatService($this->log);
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
     * This method is called when a WebSocket connection is closed from the WebSocket server.
     *
     * This method close the connection properly and alert services that a client is disconnected
     *
     * @coroutine
     *
     * @param      \Icicle\WebSocket\Connection                 $connection
     * @param      \Icicle\Http\Message\Response                $response
     * @param      \Icicle\Http\Message\Request                 $request
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    public function onDisconnection(Connection $connection, Response $response, Request $request)
    {
        $connectionHash = $this->getConnectionHash($connection);

        yield $this->services['chatService']->disconnectUser($this->clients[$connectionHash]);

        unset($this->clients[$connectionHash]);

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
     * Create a client array of a Connection and a User object and handle the client session with until the connection
     * is closed
     *
     * @coroutine
     *
     * @param      \Icicle\WebSocket\Connection                 $connection
     * @param      \Icicle\Http\Message\Response                $response
     * @param      \Icicle\Http\Message\Request                 $request
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable|null
     */
    private function connectionSessionHandle(Connection $connection, Response $response, Request $request)
    {
        $this->clients[$this->getConnectionHash($connection)] = array('Connection' => $connection, 'User' => null);
        $iterator                                             = $connection->read()->getIterator();

        while (yield $iterator->isValid()) {
            yield $this->serviceSelector(
                json_decode($iterator->getCurrent()->getData(), true),
                $this->clients[$this->getConnectionHash($connection)]
            );
        }

        yield $this->onDisconnection($connection, $response, $request);
    }

    /**
     * Service dispatcher to call the class which can treat the client request
     *
     * @param      array                                   $data    JSON decoded client data
     * @param      array                                   $client  The client information [Connection, User] array pair
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable
     */
    private function serviceSelector(array $data, array $client)
    {
        yield $this->log->log(Log::DEBUG, 'Data: %s', $this->formatVariable($data));

        foreach ($data['service'] as $service) {
            switch ($service) {
                case 'server':
                    yield $this->serverAction($data, $client);
                    break;

                case 'chatService':
                    yield $this->services['chatService']->process($data, $client);
                    break;
            }
        }
    }

    /**
     * Action called by the client to be executed in the websocket server
     *
     * @param      array                                   $data    JSON decoded client data
     * @param      array                                   $client  The client information [Connection, User] array pair
     *
     * @return     \Generator|\Icicle\Awaitable\Awaitable
     */
    private function serverAction(array $data, array $client)
    {
        switch ($data['action']) {
            case 'register':
                $this->clients[$this->getConnectionHash($client['Connection'])]['User'] = new User($data['user']);
                yield $this->log->log(Log::DEBUG, 'serverAction => register: %s', $this->formatVariable($this->clients));
                break;
        }
    }
}
