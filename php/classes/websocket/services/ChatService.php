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
use \classes\entities\User as User;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;

/**
 * Chat services to manage a chat with a WebSocket server
 *
 * @todo Sonar this class
 * @class ChatService
 */
class ChatService extends Server implements Service
{
    use \traits\ShortcutsTrait;

    /**
     * @var string[] $users All the user rooms where he is connected to indexed by his socketHash
     */
    private $usersRooms = array();
    /**
     * @var User[] $usersRegistered All the authenticated User object connected indexed by their socketHash
     */
    private $usersRegistered = array();
    /**
     * @var string[] $usersGuest All the guest users pseudonyms connected indexed by their socketHash
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
     * @var array $rooms Rooms live sessions
     *
     * array(
     *     'room name' => array(
     *         'sockets'      => array(socketHash1 => socket, socketHash2 => socket, ...),
     *         'pseudonyms'   => array(socketHash1 => pseudonym1, socketHash2 => pseudonym2, ...)
     *         'creator'      => User object instance,
     *         'type'         => 'public' || 'private',
     *         'password'     => 'password',
     *         'creationDate' => DateTime object instance,
     *         'maxUsers'     => integer
     *     )
     * )
     */
    private $rooms = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/
    
    /**
     * Constructor that sets the WebSocket server adress and create en empty default room
     *
     * @param string $serverAddress The WebSocket server adress
     */
    public function __construct($serverAddress)
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $params              = Ini::getSectionParams('Chat service');
        $this->serverAddress = $serverAddress;
        $this->chatService   = $params['serviceName'];

        // Create the default room
        $this->rooms['default'] = array(
            'sockets'      => array(),
            'pseudonyms'   => array(),
            'type'         => 'public',
            'password'     => '',
            'creationDate' => new \DateTime(),
            'maxUsers'     => $params['maxUsers']
        );
    }
    
    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/
    
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
                $this->disconnectUser($socket);
                
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
    
    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/
    
    /**
     * Create a chat room by an authenticated user request
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    private function createRoom($socket, $data)
    {
        $success = false;
        @$this->setIfIsSet($password, $data['password'], null);
        @$this->setIfIsSet($roomPassword, $data['roomPassword'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($login, $data['login'], null);
        @$this->setIfIsSetAndTrim($type, $data['type'], null);
        @$this->setIfIsSetAndTrim($maxUsers, $data['maxUsers'], null);

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

                $socketHash             = $this->getClientName($socket);
                $pseudonym              = $userEntityManager->getPseudonymForChat();
                $this->rooms[$roomName] = array(
                    'sockets'      => array($socketHash => $socket),
                    'pseudonyms'   => array($socketHash => $pseudonym),
                    'creator'      => $user,
                    'type'         => $type,
                    'password'     => $roomPassword,
                    'creationDate' => new \DateTime(),
                    'maxUsers'     => $maxUsers
                );

                $success = true;
                $message = sprintf(_('The chat room name "%s" is successfully created !'), $roomName);
                $this->log(sprintf(
                    _('[chatService] New room added "%s" (%s) maxUsers = %s and password = "%s" by %s'),
                    $roomName,
                    $type,
                    $maxUsers,
                    $roomPassword,
                    $pseudonym
                ));
            }
        }

        $this->send($socket, $this->encode(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'createRoom',
            'success'  => $success,
            'roomName' => $roomName,
            'type'     => $type,
            'maxUsers' => $maxUsers,
            'password' => $roomPassword,
            'text'     => $message
        ))));
    }

    /**
     * Connect a user to one chat room as a registered or a guest user
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    private function connectUser($socket, $data)
    {
        $success   = false;
        $response  = array();
        @$this->setIfIsSet($password, $data['user']['password'], null);
        @$this->setIfIsSet($roomPassword, $data['password'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($email, $data['user']['email'], null);
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);

        // Default room if no room defined
        if ($roomName === null || $roomName === '') {
            $roomName = 'default';
        }

        if (!array_key_exists($roomName, $this->rooms)) {
            $message = sprintf(_('The chat room "%s" does not exist'), $roomName);
        } elseif (count($this->rooms[$roomName]['sockets']) >= $this->rooms[$roomName]['maxUsers']) {
            $message = _('The room is full');
        } else {
            $message    = sprintf(_('You\'re connected to the chat room "%s" !'), $roomName);
            $socketHash = $this->getClientName($socket);

            if ($email !== null && $password !== null) {
                // Authenticated user
                $userEntityManager = new UserEntityManager();
                $user              = $userEntityManager->authenticateUser($email, $password);

                $userEntityManager->setEntity($user);

                if ($user !== false) {
                    // check if room is private
                    if (!$this->checkPrivateRoomPassword($roomName, $roomPassword)) {
                        $message = _('You cannot access to this room or the password is incorrect');
                    } else {
                        $pseudonym                          = $userEntityManager->getPseudonymForChat();
                        $this->usersRegistered[$socketHash] = $user;
                        $success                            = true;
                    }
                } else {
                    $message = _('The authentication failed');
                }
            } elseif ($pseudonym !== null) {
                // Guest user
                if ($pseudonym === '') {
                    $message = _('The pseudonym can\'t be empty');
                } elseif (!$this->pseudonymIsInRoom($pseudonym, $roomName)) {
                     // check if room is private
                    if (!$this->checkPrivateRoomPassword($roomName, $roomPassword)) {
                        $message = _('You cannot access to this room or the password is incorrect');
                    } else {
                        $this->usersGuest[$socketHash] = $pseudonym;
                        $success                       = true;
                    }
                } else {
                    $message = sprintf(_('The pseudonym "%s" is already used'), $pseudonym);
                }
            } else {
                $message = _('You must enter a pseudonym');
            }

            if ($success) {
                // Add user to the room
                $socketHash                                        = $this->getClientName($socket);
                $this->rooms[$roomName]['sockets'][$socketHash]    = $socket;
                $this->rooms[$roomName]['pseudonyms'][$socketHash] = $pseudonym;

                if (!isset($this->usersRooms[$socketHash])) {
                    $this->usersRooms[$socketHash] = array();
                }

                $this->usersRooms[$socketHash][] = $roomName;

                $this->log(_(
                    '[chatService] New user added with the pseudonym "' . $pseudonym . '" in the room "'
                    . $roomName . '"'
                ));

                $response['roomName'] = $roomName;
                $response['type']     = $this->rooms[$roomName]['type'];
                $response['maxUsers'] = $this->rooms[$roomName]['maxUsers'];
                $response['password'] = $this->rooms[$roomName]['password'];
            }
        }

        $response = array_merge($response, array(
                'service' => $this->chatService,
                'action'  => 'connect',
                'success' => $success,
                'text'    => $message
        ));

        $this->send($socket, $this->encode(json_encode($response)));
    }

    /**
     * Send a public message to all the users in the room or a private message to one user in the room
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    private function sendMessage($socket, $data)
    {
        $success    = false;
        $message    = _('Message successfully sent !');
        $socketHash = $this->getClientName($socket);
        @$this->setIfIsSet($password, $data['password'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($recievers, $data['recievers'], null);
        @$this->setIfIsSetAndTrim($text, $data['message'], null);

        if ($text === null || $text === '') {
            $message = _('The message cannot be empty');
        } elseif ($roomName === null) {
            $message = _('The chat room name cannot be empty');
        } elseif ($this->rooms[$roomName]['type'] === 'private' && $password !== $this->rooms[$roomName]['password']) {
            $message = _('Incorrect password');
        } elseif (!array_key_exists($socketHash, $this->rooms[$roomName]['sockets'])) {
            $message = sprintf(_('You are not connected to the room %s'), $roomName);
        } elseif ($recievers === null) {
            $message = _('You must precise a reciever for your message (all or a pseudonym)');
        } elseif ($recievers === 'all') {
            // Send the message to all the users in the chat room
            foreach ($this->rooms[$roomName]['sockets'] as $userSocket) {
                $this->sendMessageToUser($socket, $userSocket, $text, $roomName, 'public');
            }

            $this->log(sprintf(
                _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                $text,
                $this->rooms[$roomName]['pseudonyms'][$socketHash],
                $recievers,
                $roomName
            ));
            $success = true;
        } elseif (!$this->pseudonymIsInRoom($recievers, $roomName)) {
            $message = sprintf(_('The user "%" is not connected to the room "%"'), $recievers, $roomName);
        } else {
            // Send the message to one user
            $recieverHash   = array_search($recievers, $this->rooms[$roomName]['pseudonyms']);
            $recieverSocket = $this->rooms[$roomName]['sockets'][$recieverHash];

            $this->sendMessageToUser($socket, $recieverSocket, $text, $roomName, 'private');

            $this->log(sprintf(
                _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                $text,
                $this->rooms[$roomName]['pseudonyms'][$socketHash],
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
     * Disconnet a user from all the chat he was connected to
     *
     * @param resource $socket The user socket
     */
    private function disconnectUser($socket)
    {
        $socketHash = $this->getClientName($socket);

        foreach ($this->usersRooms[$socketHash] as $roomName) {
            unset($this->rooms[$roomName]['sockets'][$socketHash]);
            unset($this->rooms[$roomName]['pseudonyms'][$socketHash]);
        }

        $this->disconnect($socket);
    }

    /**
     * Check if a user has the right to enter a private room
     *
     * @param  string  $roomName     The room name
     * @param  string  $roomPassword The room password the user sent
     * @return boolean               True if the user have the right to enter the room else false
     */
    private function checkPrivateRoomPassword($roomName, $roomPassword)
    {
        if ($this->rooms[$roomName]['type'] === 'private' && $this->rooms[$roomName]['password'] !== $roomPassword) {
            $authorized = false;
        } else {
            $authorized = true;
        }

        return $authorized;
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
        return in_array($pseudonym, $this->rooms[$roomName]['pseudonyms']);
    }

    /**
     * Send a message to a user
     *
     * @param resource $socketFrom The user socket to send the message from
     * @param resource $socketTo   The user socket to send the message to
     * @param string   $message    The text message
     * @param string   $roomName   The room name
     * @param string   $type       The message type ('public' || 'private')
     */
    private function sendMessageToUser($socketFrom, $socketTo, $message, $roomName, $type)
    {
        $this->send($socketTo, $this->encode(json_encode(array(
            'service'   => $this->chatService,
            'action'    => 'recieveMessage',
            'pseudonym' => $this->rooms[$roomName]['pseudonyms'][$this->getClientName($socketFrom)],
            'time'      => date('Y-m-d H:i:s'),
            'roomName'  => $roomName,
            'type'      => $type,
            'text'      => $message
        ))));
    }

    /**
     * Log a message to the server if verbose mode is activated
     *
     * @param string $message The message to output
     */
    private function log($message)
    {
        $serverSocket = stream_socket_client($this->serverAddress);
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->send($serverSocket, Ini::getParam('Socket', 'serviceKey') . $message);
        fclose($serverSocket);
    }
    
    /*=====  End of Private methods  ======*/
}
