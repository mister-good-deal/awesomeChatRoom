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
use \classes\entities\User as User;

/**
 * Chat services to manage a chat with a WebSocket server
 *
 * @todo Make rooms management
 * @class ChatService
 */
class ChatService extends Server implements Service
{
    /**
     * @var resources[] $users All the users sockets connected indexed by their pseudonym
     */
    private $users = array();
    /**
     * @var resources[] $usersRegistered All the authenticated users sockets connected indexed by their pseudonym
     */
    private $usersRegistered = array();
    /**
     * @var resources[] $usersGuest All the guest users sockets connected indexed by their pseudonym
     */
    private $usersGuest = array();
    /**
     * @var string $serverAddress The server adress to connect
     */
    private $serverAddress;
    /**
     * @var string $chatService The chat service name
     */
    private $chatService;
    /**
     * @var string[] $rooms Rooms live sessions
     *
     * array(
     *     'room name' => array(
     *         'pseudonyms'   => array('pseudo 1', 'pseudo 2', ...),
     *         'creator'      => User object instance,
     *         'type'         => 'public|private',
     *         'password'     => 'password',
     *         'creationDate' => DateTime object instance,
     *         'maxUsers'     => integer
     *     )
     * )
     */
    private $rooms = array();

    public function __construct($serverAddress)
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->serverAddress = $serverAddress;
        $this->chatService   = Ini::getParam('Socket', 'chatService');

        // Create the default room
        $this->rooms['default'] = array(
            'pseudonyms'   => array('admin'),
            'type'         => 'public',
            'creationDate' => new \DateTime(),
            'maxUsers'     => 200
        );
    }

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param resource $socket The client socket
     * @param array    $data   JSON decoded client data
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

            case 'createRoom':
                $this->createRoom($socket, $data);

                break;

            default:
                $this->send($socket, $this->encode(json_encode(array(
                    'service' => $this->chatService,
                    'success' => false,
                    'text'    => _('Unknown action')
                ))));
        }
    }

    /**
     * Create a chat room by an authenticated user request
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    public function createRoom($socket, $data)
    {
        $success = false;

        if (!isset($data['roomName']) || $data['roomName'] === '') {
            $message = 'The room name is required';
        } elseif (array_key_exists($data['roomName'], $this->rooms)) {
            $message = sprintf(_('The chat room name "%s" already exists'), $data['roomName']);
        } else {
            $userEntityManager = new UserEntityManager();
            $user              = $userEntityManager->authenticateUser($data['login'], $data['password']);

            if ($user === false) {
                $message = _('Authentication failed');
            } else {
                $userEntityManager->setEntity($user);

                $pseudonym                      = $userEntityManager->getPseudonymForChat();
                $this->rooms[$data['roomName']] = array(
                    'pseudonyms'   => array($pseudonym),
                    'creator'      => $user,
                    'type'         => $data['type'],
                    'password'     => $data['roomPassword'],
                    'creationDate' => new \DateTime(),
                    'maxUsers'     => $data['maxUsers']
                );

                $success = true;
                $message = sprintf(_('The chat room name "%s" is successfully created !'), $data['roomName']);
                $this->log(_('[chatService] New room added "' . $data['roomName'] . '" by ' . $pseudonym));
            }
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service' => $this->chatService,
            'success' => $success,
            'text'    => $message
        ))));
    }

    /**
     * Connect a user to one chat room as a registered or a guest user
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     * @todo Refacto => private method to authenticated process
     */
    private function connectUser($socket, $data)
    {
        $userName  = $this->getClientName($socket);
        $pseudonym = '';
        $success   = false;

        // Default room if no room defined
        if (!isset($data['roomName']) || $data['roomName'] === '') {
            $roomName = 'default';
        } else {
            $roomName = $data['roomName'];
        }

        // Check room name
        if (!array_key_exists($roomName, $this->rooms)) {
            $message = sprintf(_('The chat room "%s" does not exist'), $data['roomName']);
        } else {
            $message  = sprintf(_('You\'re connected to the chat room "%s" !'), $roomName);

            if (!in_array($userName, $this->users)) {
                if (isset($data['user'])) {
                    // Authenticated user
                    $userEntityManager = new UserEntityManager();
                    $user              = $userEntityManager->authenticateUser(
                        @$data['user']['email'],
                        @$data['user']['password']
                    );

                    $userEntityManager->setEntity($user);

                    if ($user !== false) {
                        $this->usersRegistered[$userName] = $user;
                        $success                          = true;
                        $pseudonym                        = $userEntityManager->getPseudonymForChat();
                    } else {
                        $message = _('The authentication failed');
                    }
                } elseif (isset($data['pseudonym'])) {
                    // Guest user
                    if ($data['pseudonym'] === '') {
                        $message = _('The pseudonym can\'t be empty');
                    } elseif ($this->isPseudonymUnique($data['pseudonym'], $roomName)) {
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
                    // Add user to the room
                    $this->users[$userName]                 = $socket;
                    $this->rooms[$roomName]['pseudonyms'][] = $pseudonym;
                    $this->log(_(
                        '[chatService] New user added with the pseudonym "' . $pseudonym . '" in the room "'
                        . $roomName . '"'
                    ));
                }
            }
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service' => $this->chatService,
            'action'  => 'connect',
            'success' => $success,
            'text'    => $message
        ))));
    }

    /**
     * Check if a pseudonym is already used in the defined room
     *
     * @param  string  $pseudonym The pseudonym to test
     * @param  string  $roomName  The room name to connect to
     * @return boolean            True is the pseudonym already exists else false
     */
    private function isPseudonymUnique($pseudonym, $roomName)
    {
        return !in_array($pseudonym, $this->rooms[$roomName]['pseudonyms']);
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
