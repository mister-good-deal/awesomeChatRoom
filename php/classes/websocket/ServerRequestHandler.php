<?php
/**
 * WebSocket server to handle multiple clients connections and maintain WebSocket services
 *
 * @package    Class
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\websocket\ServicesApplication as Application;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Server\RequestHandler;
use Icicle\Socket\Socket;

/**
 * WebSocket server to handle multiple clients connections and maintain WebSocket services
 */
class ServerRequestHandler implements RequestHandler
{
    use \traits\EchoTrait;

    /**
     * @var        Application  $application    The services handler
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

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Add a service to the server
     *
     * @param      string    $serviceName  The service name
     *
     * @return     string[]  Array containing error or success message
     */
    private function addService(string $serviceName): array
    {
        $message = sprintf(_('The service "%s" is now running'), $serviceName);
        $success = false;

        if (array_key_exists($serviceName, $this->services)) {
            $message = sprintf(_('The service "%s" is already running'), $serviceName);
        } else {
            $servicePath = Ini::getParam('Socket', 'servicesPath') . DIRECTORY_SEPARATOR . $serviceName;

            if (stream_resolve_include_path($servicePath . '.php') === false) {
                $message = sprintf(_('The service "%s" does not exist'), $serviceName);
            } else {
                $service                      = new $servicePath($this->serverAddress);
                $this->services[$serviceName] = array($service, 'service');
                $success                      = true;
                $this->log('[SERVER] Service "' . $serviceName . '" is now running');
            }
        }

        return array(
            'service'     => $this->notificationService,
            'success'     => $success,
            'text'        => $message
        );
    }

    /**
     * Remove a service from the server
     *
     * @param      string    $serviceName  The service name
     *
     * @return     string[]  Array containing errors or empty array if success
     */
    private function removeService(string $serviceName): array
    {
        $message  = sprintf(_('The service "%s" is now stopped'), $serviceName);
        $success = false;

        if (!array_key_exists($serviceName, $this->services)) {
            $message = sprintf(_('The service "%s" is not running'), $serviceName);
        } else {
            unset($this->services[$serviceName]);
            $success = true;
            $this->log('[SERVER] Service "' . $serviceName . '" is now stopped');
        }

        return array(
            'service' => $this->notificationService,
            'success' => $success,
            'text'    => $message
        );
    }

    /**
     * List all the service name which are currently running
     *
     * @return     string[]  The services name list
     */
    private function listServices(): array
    {
        return array('service' => $this->websocketService, 'services' => array_keys($this->services));
    }

    /**
     * Check the authentication to perform administration action on the WebSocket server
     *
     * @param      array  $data   JSON decoded client data
     *
     * @return     bool   True if the authentication succeed else false
     */
    private function checkAuthentication(array $data): bool
    {
        $userEntityManager = new UserEntityManager();
        $user              = $userEntityManager->authenticateUser($data['login'], $data['password']);

        if ($user === false) {
            $check = false;
        } else {
            $check = (int) $user->getUserRights()->webSocket === 1;
        }

        return $check;
    }

    /**
     * Log a message to the server if verbose mode is activated
     *
     * @param      string  $message  The message to output
     */
    private function log(string $message)
    {
        if ($this->verbose) {
            static::out('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
        }
    }

    /*=====  End of Private methods  ======*/
}
