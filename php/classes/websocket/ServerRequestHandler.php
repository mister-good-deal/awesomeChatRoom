<?php
/**
 * WebSocket server to handle multiple clients connections and maintain WebSocket services
 *
 * @package    Class
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use classes\websocket\ServicesDispatcher as Application;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Server\RequestHandler;
use Icicle\Socket\Socket;

/**
 * WebSocket server to handle multiple clients connections and maintain WebSocket services
 */
class ServerRequestHandler implements RequestHandler
{
    /**
     * @var        Application $application The services handler
     */
    private $application;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that load parameters in the ini conf file and run the WebSocket server
     */
    public function __construct()
    {
        $this->application = new Application();
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    public function onRequest(Request $request, Socket $socket)
    {
        return $this->application;
    }

    public function onError(int $code, Socket $socket)
    {
        return new BasicResponse($code);
    }

    /*=====  End of Public methods  ======*/
}

