<?php
/**
 * Client application to manage clients with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use classes\IniManager as Ini;
use classes\websocket\Client as Client;
use classes\entities\User as User;
use classes\entities\UserRight as UserRight;
use classes\entities\UserChatRight as UserChatRight;
use classes\entitiesCollection\UserChatRightCollection as UserChatRightCollection;
use Icicle\WebSocket\Connection as Connection;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class ClientService
{
    /**
     * @var        string  $serviceName     The chat service name
     */
    private $serviceName;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that loads chat parameters
     *
     * @param      Log   $log    Logger object
     */
    public function __construct()
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $conf               = Ini::getSectionParams('Client service');
        $this->serviceName  = $conf['serviceName'];
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get the service name
     *
     * @return     string  The service name
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * Method to recieves data from the WebSocket server and process it
     *
     * @param      array       $data    JSON decoded client data
     * @param      Client      $client  The client object
     *
     * @return     \Generator
     */
    public function process(array $data, Client $client)
    {
        switch ($data['action']) {
            case 'updateLocation':
                $this->updateLocation($data, $client);

                break;

            case 'updateUser':
                $this->updateUser($data, $client);

                break;

            default:
                yield $client->getConnection()->send(json_encode([
                    'service' => $this->serviceName,
                    'success' => false,
                    'text'    => _('Unknown action `' . $data['action'] . '`')
                ]));
        }
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Update the client location
     *
     * @param      array   $data    JSON decoded client data
     * @param      Client  $client  The client object
     */
    private function updateLocation(array $data, Client $client)
    {
        $client->setLocation($data['location']);
    }

    /**
     * Update the client user
     *
     * @param      array   $data    JSON decoded client data
     * @param      Client  $client  The client object
     */
    private function updateUser(array $data, Client $client)
    {
        $client->setUser(new User($data['user']));
        $client->getUser()->setRight(new UserRight($data['user']['right']));

        if (count($data['user']['chatRight']) > 0) {
            $userChatRightCollection = new UserChatRightCollection();

            foreach ($data['user']['chatRight'] as $userChatRightInfo) {
                $userChatRightCollection->add(new UserChatRight($userChatRightInfo));
            }

            $client->getUser()->setChatRight($userChatRightCollection);
        }
    }

    /*=====  End of Private methods  ======*/
}
