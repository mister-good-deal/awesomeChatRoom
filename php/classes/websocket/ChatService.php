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
        static::out('Flag 2' . PHP_EOL);
        switch ($data['action']) {
            case 'chat':
                static::out(': ' . PHP_EOL .  $data['message'] . PHP_EOL);
                break;

            case 'connect':
                // connect the client
                break;

            default:
                stream_socket_sendto($socket, $this->encode('Unknown action'));
        }
    }
}
