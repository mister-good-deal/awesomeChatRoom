<?php
/**
 * Chat services to manage a chat with a WebSocket server
 *
 * @category WebSocket service
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use \classes\websocket\Server as Server;
use \interfaces\ServiceInterface as Service;
use \classes\IniManager as Ini;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;

/**
 * Chat services to manage a chat with a WebSocket server
 *
 * @todo Make rooms management
 * @class ChatService
 */
class ChatService extends Server implements Service
{
    private $users           = array();
    private $usersRegistered = array();
    private $usersGuest      = array();
    private $pseudonyms      = array();
    private $serverAddress;
    private $chatService;

    public function __construct($serverAddress)
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->serverAddress = $serverAddress;
        $this->chatService   = Ini::getParam('Socket', 'chatService');
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
                $this->log(_('[chatService] somone says: "' . $data['message'] . '"'));

                break;

            case 'connect':
                $this->connectUser($socket, $data);

                break;

            case 'disconnect':
                $this->disconnect($socket);
                
                break;

            default:
                $this->send($socket, $this->encode('Unknown action'));
        }
    }

    /**
     * Connect a user to the chat as a registered or a guest user
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    private function connectUser($socket, $data)
    {
        $userName  = $this->getClientName($socket);
        $pseudonym = '';
        $success   = false;
        $message   = _('You\'re connected to the chat !');

        if (!in_array($userName, $this->users)) {
            if (isset($data['user'])) {
                $userEntityManager = new UserEntityManager();
                $user              = $userEntityManager->authenticateUser(
                    @$data['user']['email'],
                    @$data['user']['password']
                );

                if ($user !== false) {
                    $this->usersRegistered[$userName] = $user;
                    $success                          = true;

                    if ($user->pseudonym !== null) {
                        $pseudonym = $user->pseudonym;
                    } else {
                        $pseudonym = $user->firstName . ' ' . $user->lastName;
                    }
                } else {
                    $message = _('The authentication failed');
                }
            } elseif (isset($data['pseudonym'])) {
                if ($data['pseudonym'] === '') {
                    $message = _('The pseudonym can\'t be empty');
                } elseif ($this->isPseudonymUnique($data['pseudonym'])) {
                    $this->usersGuest[$userName] = $data['pseudonym'];
                    $pseudonym                   = $data['pseudonym'];
                    $success                     = true;
                } else {
                    $message = sprintf(_('The pseudonym "%s" is already used'), $data['pseudonym']);
                }
            } else {
                $message = _('You must enter a pseudonym');
            }

            if ($success) {
                $this->users[$userName] = $socket;
                $this->pseudonyms[]     = $pseudonym;
                $this->log(_('[chatService] New user added with the pseudonym "' . $pseudonym . '"'));
            }
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service' => $this->chatService,
            'success' => $success,
            'text'    => $message
       ))));
    }

    /**
     * Check if a pseudonym is already used
     *
     * @param  string  $pseudonym The pseudonym to test
     * @return boolean            True is the pseudonym already exists else false
     */
    private function isPseudonymUnique($pseudonym)
    {
        return !in_array($pseudonym, $this->pseudonyms);
    }

    /**
     * Log a message to teh server if verbose mode is activated
     *
     * @param  string $message The message to output
     */
    private function log($message)
    {
        $serverSocket = stream_socket_client($this->serverAddress);
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->send($serverSocket, Ini::getParam('Socket', 'serviceKey') . $message);
        fclose($serverSocket);
    }
}
