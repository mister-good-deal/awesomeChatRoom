<?php

namespace classes\socket;

use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;

class Server
{
    use \traits\EchoTrait;

    /**
     * @var resource $resource The server socket resource
     */
    private $resource;
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
        $this->resource = stream_socket_server(
            $this->protocol . '://' . $this->address . ':' . $this->port,
            $this->errorNum,
            $this->errorString
        );

        if ($this->resource === false) {
            throw new Exception('Error ' . $this->errorNum . '::' . $this->errorString, Exception::$ERROR);
        }

        $this->run();
    }

    /**
     * Run the server
     */
    private function run()
    {
        while (1) {
            if ($this->verbose) {
                $this->runningInfo();
            }

            sleep(60);
        }
    }

    private function runningInfo()
    {
        static::out(
            '[' . date('Y-m-d H:i:s') . ']'
            . ' Server running on ' . stream_socket_get_name($this->resource, false) . PHP_EOL
        );
    }
}
