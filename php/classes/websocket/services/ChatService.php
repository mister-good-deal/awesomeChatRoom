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
    use \traits\ShortcutsTrait;

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
     *         'users'        => array(array('pseudo 1' => socket), array('pseudo 2' => socket), ...),
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
            'users'        => array(),
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
            case 'sendMessage':
                $this->sendMessage($socket, $data);

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
        @$this->setIfIsSet($roomName, $data['roomName'], null);
        @$this->setIfIsSet($login, $data['login'], null);
        @$this->setIfIsSet($password, $data['password'], null);
        @$this->setIfIsSet($type, $data['type'], null);
        @$this->setIfIsSet($roomPassword, $data['roomPassword'], null);
        @$this->setIfIsSet($maxUsers, $data['maxUsers'], null);

        if ($roomName === null || $roomName === '') {
            $message = _('The room name is required');
        } elseif (array_key_exists($roomName, $this->rooms)) {
            $message = sprintf(_('The chat room name "%s" already exists'), $roomName);
        } elseif ($type !== 'public' && $type !== 'private') {
            $message = _('The room type must be "public" or "private"');
        } elseif ($type === 'private' && ($password === null || strlen($password) === 0)) {
            $message = _('The password is required and must not be empty');
        } elseif (!is_numeric($maxUsers) || $maxUsers < 2) {
            $message = _('The max number of users must be a number and must no be less than 2');
        } else {
            $userEntityManager = new UserEntityManager();
            $user              = $userEntityManager->authenticateUser($login, $password);

            if ($user === false) {
                $message = _('Authentication failed');
            } else {
                $userEntityManager->setEntity($user);

                $pseudonym                      = $userEntityManager->getPseudonymForChat();
                $this->rooms[$roomName] = array(
                    'users'        => array($pseudonym => $socket),
                    'creator'      => $user,
                    'type'         => $type,
                    'password'     => $roomPassword,
                    'creationDate' => new \DateTime(),
                    'maxUsers'     => $maxUsers
                );

                $success = true;
                $message = sprintf(_('The chat room name "%s" is successfully created !'), $roomName);
                $this->log(_('[chatService] New room added "' . $roomName . '" by ' . $pseudonym));
            }
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service'      => $this->chatService,
            'action'       => 'createRoom',
            'success'      => $success,
            'roomName'     => $roomName,
            'type'         => $type,
            'maxUsers'     => $maxUsers,
            'roomPassword' => $roomPassword,
            'text'         => $message
        ))));
    }

    /**
     * Connect a user to one chat room as a registered or a guest user
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     * @todo Refacto => getClientName VS getPseudonymForChat
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
                    } elseif ($this->pseudonymIsInRoom($data['pseudonym'], $roomName)) {
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
                    $this->users[$userName]                      = $socket;
                    $this->rooms[$roomName]['users'][$pseudonym] = $socket;
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
     * Send a public message to all the users in the room or a private message to one user in the room
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    public function sendMessage($socket, $data)
    {
        $success = false;
        $message = _('Message successfully sent !');
        @$this->setIfIsSet($roomName, $data['roomName'], null);
        @$this->setIfIsSet($pseudonym, $data['pseudonym'], null);
        @$this->setIfIsSet($password, $data['password'], null);
        @$this->setIfIsSet($recievers, $data['recievers'], null);
        @$this->setIfIsSet($text, $data['message'], null);

        if ($text === null || $text === '') {
            $message = _('The message cannot be empty');
        } elseif ($roomName === null) {
            $message = _('The chat room name cannot be empty');
        } elseif ($pseudonym === null) {
            $message = _('Your pseudonym cannot be empty');
        } elseif ($this->rooms[$roomName]['type'] === 'private') {
            if ($password === null) {
                $message = sprintf(_('The chat room "%s" requires a password'), $roomName);
            } elseif ($password !== $this->rooms[$roomName]['password']) {
                $message = _('Incorrect password');
            }
        } elseif ($recievers === null) {
            $message = _('You must precise a reciever for your message (all or a pseudonym');
        } elseif ($recievers === 'all') {
            // Send the message to all the users in the chat room
            foreach ($this->rooms[$roomName]['users'] as $userSocket) {
                $this->sendMessageToUser($userSocket, $pseudonym, $text, $roomName, 'public');
            }

            $this->log(sprintf(
                _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                $text,
                $pseudonym,
                $recievers,
                $roomName
            ));
            $success = true;
        } elseif (!$this->pseudonymIsInRoom($recievers, $roomName)) {
            $message = sprintf(_('The user "%" is not connected to the room "%"'), $recievers, $roomName);
        } else {
            // Send the message to one user
            $this->sendMessageToUser(
                $this->rooms[$roomName]['users'][$recievers],
                $pseudonym,
                $text,
                $roomName,
                'private'
            );

            $this->log(sprintf(
                _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                $text,
                $pseudonym,
                $recievers,
                $roomName
            ));
            $success = true;
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service' => $this->chatService,
            'action'  => 'sendMessage',
            'success' => $success,
            'text'    => $message
        ))));
    }

    /**
     * Check if a pseudonym is already used in the defined room
     *
     * @param  string  $pseudonym The pseudonym
     * @param  string  $roomName  The room name to connect to
     * @return boolean            True if the pseudonym exists in the room else false
     */
    private function pseudonymIsInRoom($pseudonym, $roomName)
    {
        return !in_array($pseudonym, $this->rooms[$roomName]['pseudonyms']);
    }

    /**
     * Send a message to a user
     *
     * @param  resource $socket     The user socket
     * @param  string   $pseudonym  The user message owner pseudonym
     * @param  string   $message    The text message
     * @param  string   $roomName   The room name
     * @param  string   $type       The message type ('public' || 'private')
     */
    private function sendMessageToUser($socket, $pseudonym, $message, $roomName, $type)
    {
        $this->send($socket, $this->encode(json_encode(array(
            'service'   => $this->chatService,
            'action'    => 'recieveMessage',
            'pseudonym' => $pseudonym,
            'time'      => date('Y-m-d H:i:s'),
            'roomName'  => $roomName,
            'type'      => $type,
            'text'      => $message
        ))));
    }

    /**
     * Log a message to the server if verbose mode is activated
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
