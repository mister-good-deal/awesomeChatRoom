<?php

namespace classes\socket;

use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;

class Server
{
    use \traits\EchoTrait;

    /**
     * @var resource $server The server socket resource
     */
    private $server;
    /**
     * @var string $protocol The server socket protocol
     */
    private $protocol;
    /**
     * @var string $address The server socket address
     */
    private $address;
    /**
     * @var integer $port The server socket port
     */
    private $port;
    /**
     * @var boolean $verbose True to print info in console else false
     */
    private $verbose;
    /**
     * @var integer $errorNum The error code if an error occured
     */
    private $errorNum;
    /**
     * @var string $errorString The error string if an error occured
     */
    private $errorString;
    private $clients = array();

    /**
     * Constructor that load parameters in the ini conf file and run the socket server
     */
    public function __construct()
    {
        cli_set_process_title('PHP socket server');

        $params          = Ini::getSectionParams('Socket');
        $this->protocol  = $params['protocol'];
        $this->address   = $params['address'];
        $this->port      = $params['port'];
        $this->verbose   = $params['verbose'];
        $this->server = stream_socket_server(
            $this->protocol . '://' . $this->address . ':' . $this->port,
            $this->errorNum,
            $this->errorString
        );

        if ($this->server === false) {
            throw new Exception('Error ' . $this->errorNum . '::' . $this->errorString, Exception::$ERROR);
        }

        $this->run();
    }

    /**
     * Run the server
     */
    private function run()
    {
        if ($this->verbose) {
            static::out(
                '[' . date('Y-m-d H:i:s') . ']'
                . ' Server running on ' . stream_socket_get_name($this->server, false) . PHP_EOL
            );
        }

        while (1) {
            $sockets   = $this->clients;
            $sockets[] = $this->server;

            if (@stream_select($sockets, $write = null, $except = null, 3) === false) {
                throw new Exception('Error on stream_select', Exception::$ERROR);
            }

            if (count($sockets) > 0) {
                foreach ($sockets as $socket) {
                    if ($socket === $this->server) {
                        $client     = stream_socket_accept($this->server, 30);
                        $clientName = md5(stream_socket_get_name($client, true));

                        if (!in_array($clientName, $this->clients)) {
                            $this->clients[$clientName] = $client;
                            $this->handshake($client);
                        }
                    } else {
                        $this->treatDataRecieved($socket);
                    }
                }
            }
        }
    }

    private function treatDataRecieved($socket)
    {
        $clientName = md5(stream_socket_get_name($socket, true));
        $data       = $this->unmask(stream_socket_recvfrom($socket, 1500));

        if (trim(strtolower($data)) === 'ping') {
            static::out('PONG' . PHP_EOL);
            stream_socket_sendto($socket, $this->encode('PONG', 'pong'));
        } else {
            $data = json_decode($data, true);

            switch ($data['action']) {
                case 'chat':
                    static::out($clientName . ': ' . PHP_EOL .  $data['message'] . PHP_EOL);
                    break;

                case 'connect':
                    // connect the client
                    break;

                default:
                    stream_socket_sendto($socket, $this->encode('Unknown action'));
            }
        }
    }

    /**
     * Perform an handshake with the remote client by sending a specific HTTP response
     *
     * @param  resource $client The client socket
     */
    private function handshake($client)
    {
        // Retrieves the header and get the WebSocket-Key
        preg_match('/Sec-WebSocket-Key\: (.*)/', stream_socket_recvfrom($client, 1500), $match);

        // Send the accept key built on the base64 encode of the WebSocket-Key, concated with the magic key, sha1 hash
        $upgrade  = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: " . base64_encode(sha1(trim($match[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)) .
        "\r\n\r\n";

        stream_socket_sendto($client, $upgrade);
    }

    /**
     * Encode a text to send to client via ws://
     *
     * @param $text
     * @param $messageType
     */
    private function encode($message, $messageType = 'text')
    {
        switch ($messageType) {
            case 'continuous':
                $b1 = 0;
                break;
            case 'text':
                $b1 = 1;
                break;
            case 'binary':
                $b1 = 2;
                break;
            case 'close':
                $b1 = 8;
                break;
            case 'ping':
                $b1 = 9;
                break;
            case 'pong':
                $b1 = 10;
                break;
        }
        
        $b1 += 128;
        $length = strlen($message);
        $lengthField = "";

        if ($length < 126) {
            $b2 = $length;
        } elseif ($length <= 65536) {
            $b2 = 126;
            $hexLength = dechex($length);

            if (strlen($hexLength)%2 == 1) {
                $hexLength = '0' . $hexLength;
            }

            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i = $i - 2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }

            while (strlen($lengthField) < 2) {
                $lengthField = chr(0) . $lengthField;
            }
        } else {
            $b2 = 127;
            $hexLength = dechex($length);

            if (strlen($hexLength) % 2 == 1) {
                $hexLength = '0' . $hexLength;
            }

            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i = $i - 2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }

            while (strlen($lengthField) < 8) {
                $lengthField = chr(0) . $lengthField;
            }
        }

        return chr($b1) . chr($b2) . $lengthField . $message;
    }

    /**
     * Unmask a received payload
     *
     * @param string $payload The buffer string
     */
    private function unmask($payload)
    {
        $length = ord($payload[1]) & 127;

        if ($length === 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } elseif ($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }
        
        $text = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $text .= $data[$i] ^ $masks[$i%4];
        }

        return $text;
    }
}
