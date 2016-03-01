<?php

namespace classes\websocket;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Log\{Log, function log};
use Icicle\Socket\Socket;
use Icicle\WebSocket\Application as Application;
use Icicle\WebSocket\Connection;
use classes\entities\User as User;

class ServicesApplication implements Application
{
    use \traits\PrettyOutputTrait;

    /**
     * @var \Icicle\Log\Log
     */
    private $log;
    /**
     * @var $clients array The clients pool
     */
    private $clients;
    /**
     * @param \Icicle\Log\Log|null $log
     */
    public function __construct(Log $log = null)
    {
        $this->log = $log ?: log();
        $this->clients = array();
    }
    /**
     * {@inheritdoc}
     */
    public function onHandshake(Response $response, Request $request, Socket $socket)
    {
        // This method provides an opportunity to inspect the Request and Response before a connection is accepted.
        // Cookies may be set and returned on a new Response object, e.g.: return $response->withCookie(...);
        return $response; // No modification needed to the response, so the passed Response object is simply returned.
    }
    /**
     * {@inheritdoc}
     */
    public function onConnection(Connection $connection, Response $response, Request $request)
    {
        $message = array(
            'service' => 'notificationService',
            'text'    => 'Connected to echo WebSocket server powered by Icicle'
        );

        yield $connection->send(json_encode($message));
        yield $this->log->log(
            Log::INFO,
            'WebSocket connection from %s:%d opened',
            $connection->getRemoteAddress(),
            $connection->getRemotePort()
        );

        $this->clients[$this->getConnectionHash($connection)] = array('connection' => $connection, 'user' => false);
        // Messages are read through an Observable that represents an asynchronous set. There are a variety of ways
        // to use this asynchronous set, including an asynchronous iterator as shown in the example below.
        $iterator = $connection->read()->getIterator();
        while (yield $iterator->isValid()) {
            /** @var \Icicle\WebSocket\Message $message */
            $message = $iterator->getCurrent();
            yield $this->treatData(json_decode($message->getData(), true), $connection);
        }
        /** @var \Icicle\WebSocket\Close $close */
        $close = $iterator->getReturn(); // Only needs to be called if the close reason is needed.
        yield $this->log->log(
            Log::INFO,
            'WebSocket connection from %s:%d closed; Code %d; Data: %s',
            $connection->getRemoteAddress(),
            $connection->getRemotePort(),
            $close->getCode(),
            $close->getData()
        );
    }

    private function treatData(array $data, Connection $connection)
    {
        yield $this->log->log(Log::INFO, 'Data: %s', $this->formatVariable($data));

        if ($data['action'] === 'register') {
            $this->clients[$this->getConnectionHash($connection)]['user'] = new User($data['user']);
            yield $this->log->log(Log::INFO, 'Data: %s', $this->formatVariable($this->clients));
        }
    }

    /**
     * Get the connection hash like a Connecton ID
     *
     * @param      Connection  $connection  The connection to get the hash from
     *
     * @return     string The connection hash
     */
    private function getConnectionHash(Connection $connection): string
    {
        return md5($connection->getRemoteAddress() + $connection->getRemotePort());
    }
}
