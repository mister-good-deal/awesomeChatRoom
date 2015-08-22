<?php

namespace classes\websocket\services;

use \classes\websocket\Server as Server;
use \interfaces\ServiceInterface as Service;

class ChatService extends Server implements Service
{
    public function __construct()
    {
    }

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param  resource $socket The client socket
     * @param  array    $data   JSON decoded client data
     */
    public function service($socket, $data)
    {
        switch ($data['action']) {
            case 'chat':
                static::out($this->getClientName($socket) . ': ' . $data['message'] . PHP_EOL);
                break;

            case 'connect':
                // connect the client
                break;

            case 'disconnect':
                $this->disconnect($socket);
                break;

            default:
                stream_socket_sendto($socket, $this->encode('Unknown action'));
        }
    }
}
