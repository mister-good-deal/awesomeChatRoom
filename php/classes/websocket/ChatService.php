<?php

namespace classes\websocket;

use \classes\websocket\Server as Server;

class ChatService extends Server
{
    public function __construct()
    {
    }

    public function chatService($socket, $data)
    {
        switch ($data['action']) {
            case 'chat':
                echo $this->getClientName($socket) . ': ' . $data['message'] . PHP_EOL;
                // static::out($this->getClientName($socket) . ': ' . $data['message'] . PHP_EOL);
                break;

            case 'connect':
                // connect the client
                break;

            default:
                stream_socket_sendto($socket, $this->encode('Unknown action'));
        }
    }
}
