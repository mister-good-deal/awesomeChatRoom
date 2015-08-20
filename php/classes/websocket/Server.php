<?php

namespace classes\websocket;

use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;

class Server
{
    use \traits\EchoTrait;

    /**
     * @var resource $server The server socket resource
     */
    protected $server;
    /**
     * @var string $protocol The server socket protocol
     */
    protected $protocol;
    /**
     * @var string $address The server socket address
     */
    protected $address;
    /**
     * @var integer $port The server socket port
     */
    protected $port;
    /**
     * @var boolean $verbose True to print info in console else false
     */
    protected $verbose;
    /**
     * @var integer $errorNum The error code if an error occured
     */
    protected $errorNum;
    /**
     * @var string $errorString The error string if an error occured
     */
    protected $errorString;
    /**
     * @var resource[] $clients The clients socket stream
     */
    protected $clients = array();
    /**
     * @var array[] $services The services functions / methods implemented
     */
    protected $services = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/
    
    /**
     * Constructor that load parameters in the ini conf file
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
    }
    
    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/
    
    /**
     * Add a service to the server
     * The service function must take 2 arguments: the first is the client socket, the second is the data sent
     *
     * @param  object    $serviceInstance The service instancied object
     * @param  string    $serviceMethod   The service method name
     * @param  string    $serviceName     The service name
     * @throws Exception                  If the service name already exist
     * @throws Exception                  If the service method does not exist
     */
    public function addService($serviceInstance, $serviceMethod, $serviceName)
    {
        if (array_key_exists($serviceName, $this->services)) {
            throw new Exception('This service name is already set', Exception::$ERROR);
        }

        if (!method_exists($serviceInstance, $serviceMethod)) {
            throw new Exception('This service does not exist in the object sent', Exception::$ERROR);
        }

        $this->services[$serviceName] = array($serviceInstance, $serviceMethod);
    }

    /**
     * Remove a service from the server
     *
     * @param  string    $serviceName The service name
     * @throws Exception              If the service name doeas not exist
     */
    public function removeService($serviceName)
    {
        if (!array_key_exists($serviceName, $this->services)) {
            throw new Exception('This service name does not exist', Exception::$ERROR);
        }
        
        unset($this->services[$serviceName]);
    }

    /**
     * List all the service name implemented
     *
     * @return string[] The service name list
     */
    public function listServices()
    {
        return array_keys($this->services);
    }

    /**
     * Run the server, accept connections and handle them
     */
    public function run()
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

            if (@stream_select($sockets, $write = null, $except = null, null) === false) {
                throw new Exception('Error on stream_select', Exception::$ERROR);
            }

            if (count($sockets) > 0) {
                foreach ($sockets as $socket) {
                    if ($socket === $this->server) {
                        $client     = stream_socket_accept($this->server, 30);
                        $clientName = $this->getClientName($client);

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
    
    /*=====  End of Public methods  ======*/

    /*=========================================
    =            Protected methods            =
    =========================================*/
    
    /**
     * Get the client name from his socket stream
     *
     * @param resource $socket The client socket
     * @return string          The client name
     */
    protected function getClientName($socket)
    {
        return md5(stream_socket_get_name($socket, true));
    }

    /**
     * Send data to a client via stream socket
     *
     * @param resource $socket The client stream socket
     * @param string   $data   The data to send
     */
    protected function send($socket, $data)
    {
        stream_socket_sendto($socket, $data);
    }

    /**
     * Disconnect a client
     *
     * @param resource $socket     The client socket
     * @param string   $clientName OPTIONAL the client name
     */
    protected function disconnect($socket, $clientName = null)
    {
        if ($clientName === null) {
            $clientName = $this->getClientName($socket);
        }

        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        unset($this->clients[$clientName]);

        if ($this->verbose) {
            static::out(
                '[' . date('Y-m-d H:i:s') . '] Client disconnected : ' . $clientName . PHP_EOL
            );
        }
    }
    
    /*=====  End of Protected methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Get data from a client via stream socket
     *
     * @param  resource $socket The client stream socket
     * @return string           The client data
     */
    private function get($socket)
    {
        return stream_socket_recvfrom($socket, 1500);
    }

    /**
     * Treat recieved data from a client socket and perform actions depending on data recieved and services implemented
     * The ping / pong protocol is handled
     *
     * @param resource $socket The client socket
     */
    private function treatDataRecieved($socket)
    {
        $clientName = $this->getClientName($socket);
        $data       = $this->get($socket);

        if (strlen($data) < 2) {
            $this->disconnect($socket, $clientName);
        } else {
            $data = $this->unmask($data);

            if (trim(strtolower($data)) === 'ping') {
                static::out('PONG' . PHP_EOL);
                $this->send($socket, $this->encode('PONG', 'pong'));
            } else {
                foreach ($this->services as $service) {
                    call_user_func_array($service, array($socket, json_decode($data, true)));
                }
            }
        }
    }

    /**
     * Perform an handshake with the remote client by sending a specific HTTP response
     *
     * @param resource $client The client socket
     */
    private function handshake($client)
    {
        // Retrieves the header and get the WebSocket-Key
        preg_match('/Sec-WebSocket-Key\: (.*)/', $this->get($client), $match);

        // Send the accept key built on the base64 encode of the WebSocket-Key, concated with the magic key, sha1 hash
        $upgrade  = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: " . base64_encode(sha1(trim($match[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)) .
        "\r\n\r\n";

        $this->send($client, $upgrade);

        if ($this->verbose) {
            static::out(
                '[' . date('Y-m-d H:i:s') . '] New client added : ' . $this->getClientName($client) . PHP_EOL
            );
        }
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

    /*=====  End of Private methods  ======*/
}
