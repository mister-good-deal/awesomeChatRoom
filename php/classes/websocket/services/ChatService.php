<?php
/**
 * Chat application to manage a chat with a WebSocket server
 *
 * @category WebSocket service
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use \interfaces\ServiceInterface as Service;
use classes\websocket\ServicesDispatcher as ServicesDispatcher;
use Icicle\Log\Log as Log;
use Icicle\Stream\DuplexStream;
use Icicle\Stream\MemoryStream as MemoryStream;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use \classes\IniManager as Ini;
use \classes\entities\User as User;
use \classes\entities\ChatRoom as ChatRoom;
use \classes\managers\UserManager as UserManager;
use \classes\managers\ChatManager as ChatManager;
use Icicle\WebSocket\Connection as Connection;

/**
 * Chat services to manage a chat with a WebSocket server
 *
 * @todo       Refacto all this class with Icicle lib [in progress]
 *             Get all rooms name in $this->roomsName
 */
class ChatService extends ServicesDispatcher implements Service
{
    use \traits\ShortcutsTrait;
    use \traits\PrettyOutputTrait;

    /**
     * @var        string  $chatService     The chat service name
     */
    private $chatService;
    /**
     * @var        array  $server   The server info
     */
    private $server;
    /**
     * @var        string  $savingDir   The absolute path from the lib path where conversations will be stored
     */
    private $savingDir;
    /**
     * @var        integer  $maxMessagesPerFile     The maximum number of messages per file saved
     */
    private $maxMessagesPerFile;
    /**
     * @var        string[]  $roomsName     Array containing all the rooms name that exists
     */
    private $roomsName;
    /**
     * @var        string  $roomsNamePath   The path of the file storing the list of rooms name
     */
    private $roomsNamePath;
    /**
     * @var        string[]  $usersRooms    All rooms where users are connected to indexed by their socketHash
     */
    private $usersRooms = array();
    /**
     * @var array $rooms Rooms live sessions
     *
     * array(
     *     'room name' => array(
     *         'users'        => array(userHash1 => user, userHash2 => user, ...),
     *         'pseudonyms'   => array(userHash1 => pseudonym1, userHash2 => pseudonym2, ...)
     *         'usersRights'  => array(pseudonym1 => UsersChatRights, pseudonym2 => UsersChatRights, ...)
     *         'room'         => ChatRoom,
     *         'historic'     => array(
     *             'part'          => the part number,
     *             'conversations' => array(
     *                 'text' => the text message,
     *                 'time' => the message sent time,
     *                 'from' => the pseudonym of the message owner,
     *                 'to'   => the pseudonym of the message reciever or 'all'
     *             )
     *         )
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
     * @param      MemoryStream  $stream  A stream with the server
     * @param      Log           $log     Logger object
     */
    public function __construct(MemoryStream $stream, Log $log)
    {
        $this->log    = $log;
        $this->stream = $stream;
        $this->server = array('Connection' => 'SERVER');

        $generator = function (DuplexStream $stream) {
            while ($stream->isReadable()) {
                $data = (yield from $stream->read());
                $this->treatDataFromServer(json_decode($data, true));
            }
        };

        $coroutine = new Coroutine($generator($this->stream));

        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $params                   = Ini::getSectionParams('Chat service');
        $this->serverKey          = Ini::getParam('Socket', 'serverKey');
        $this->chatService        = $params['serviceName'];
        $this->savingDir          = $params['savingDir'];
        $this->maxMessagesPerFile = $params['maxMessagesPerFile'];
        $this->roomsNamePath      = $this->savingDir . DIRECTORY_SEPARATOR . 'rooms_name';
        $this->roomsName          = $this->getRoomsName();

        // Create the default room
        $this->rooms['default'] = array(
            'users'        => array(),
            'pseudonyms'   => array(),
            'usersRights'  => array(),
            'room'         => new ChatRoom()
            'historic'     => array('part' => 0, 'conversations' => array())
        );

        // $this->loadHistoric('default', $this->getLastPartNumber('default'));
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param      array  $data    JSON decoded client data
     * @param      array  $client  The client information [Connection, User] array pair
     */
    public function process(array $data, array $client)
    {
        switch ($data['action']) {
            case $this->serverKey . 'disconnect':
                // Action called by the server
                yield $this->disconnectUser($data['clientSocket']);

                break;

            case 'sendMessage':
                yield $this->sendMessage($client, $data);

                break;

            case 'connectRoom':
                yield $this->connectUser($client, $data);

                break;

            case 'disconnect':
                yield $this->disconnectUser($client);

                break;

            case 'disconnectFromRoom':
                yield $this->disconnectUserFromRoom($client, $data);

                break;

            case 'createRoom':
                yield $this->createRoom($client, $data);

                break;

            case 'getHistoric':
                yield $this->getHistoric($client, $data);

                break;

            case 'kickUser':
                yield $this->kickUser($client, $data);

                break;

            case 'banUser':
                yield $this->banUser($client, $data);

                break;

            case 'updateRoomUserRight':
                yield $this->updateRoomUserRight($client, $data);

                break;

            case 'setRoomInfo':
                yield $this->setRoomInfo($client, $data);

                break;

            case 'getRoomsInfo':
                yield $this->getRoomsInfo($client, $data);

                break;

            default:
                yield $client['Connection']->send(
                    json_encode(
                        array(
                        'service' => $this->chatService,
                        'success' => false,
                        'text'    => _('Unknown action')
                        )
                    )
                );
        }
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Treat the data sent from the server (not from the clients)
     *
     * @param      array  $data   JSON decoded server data
     */
    private function treatDataFromServer(array $data)
    {
        yield $this->log->log(Log::INFO, 'Data recieved from server: %s', $this->formatVariable($data));
    }

    /**
     * Connect a user to one chat room as a registered or a guest user
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function connectUser(array $client, array $data)
    {
        $success         = false;
        $response        = array();

        @$this->setIfIsSet($roomPassword, $data['password'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);

        if ($roomName === null) {
            $message = _('The chat room name cannot be empty');
        } elseif (!in_array($roomName, $this->roomsName)) {
            $message = sprintf(_('The chat room "%s" does not exist'), $roomName);
        } else {
            if (!isset($this->rooms[$roomName])) {
                // Load the room if it is not in cache
                $this->loadRoom($roomName);
            }

            $chatManager = new ChatManager($this->rooms[$roomName]);

            if (count($this->rooms[$roomName]['users']) >= $this->rooms[$roomName]->maxUsers) {
                $message = _('The room is full');
            } elseif (!$this->checkPrivateRoomPassword($roomName, $roomPassword)) {
                $message = _('You cannot access to this room or the password is incorrect');
            } elseif ($chatManager->isIpBanned($client['Connection']->getRemoteAddress()) {
                $message = _('You are banned from this room');
            } elseif ($client['User'] !== null) {
                // Authenticated user
                $userManager = new UserManager($client['User']);
                $pseudonym   = $userManager->getPseudonymForChat();
                $success     = true;
            } elseif ($pseudonym !== null && $pseudonym !== '') {
                // Guest user
                if ($this->isPseudonymUsabled($pseudonym, $roomName)) {
                    $success = true;
                } else {
                    $message = sprintf(_('The pseudonym "%s" is already used'), $pseudonym);
                }
            } else {
                $message = _('The pseudonym can\'t be empty');
            }

            if ($success) {
                // Add user to the room
                $userHash                                        = $this->getConnectionHash($client['Connection']);
                $this->rooms[$roomName]['users'][$userHash]      = $client;
                $this->rooms[$roomName]['pseudonyms'][$userHash] = $pseudonym;

                yield $this->addUserRoom($userHash, $roomName);
                yield $this->log->log(
                    Log::INFO,
                    _('[chatService] New user "%s" added in the room "%s"'),
                    $pseudonym,
                    $roomName
                );

                $message                = sprintf(_('You\'re connected to the chat room "%s" !'), $roomName);
                $response['room']       = $this->rooms[$roomName]->__toArray();
                $response['pseudonym']  = $pseudonym;
                $response['pseudonyms'] = array_values($this->rooms[$roomName]['pseudonyms']);
                $response['historic']   = $this->filterConversations(
                    $this->rooms[$roomName]['historic']['conversations'],
                    $pseudonym
                );


                // @todo check this
                if ($client['User'] !== null) {
                    $response['usersRights'] = $this->rooms[$roomName]['usersRights'];
                    $response['usersBanned'] = $this->rooms[$roomName]['usersBanned'];
                }
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            $response,
            array(
                'service' => $this->chatService,
                'action'  => 'connectRoom',
                'success' => $success,
                'text'    => $message
            )
        )));
    }

    /**
     * Create a chat room by an authenticated user request
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function createRoom(array $client, array $data)
    {
        $success = false;
        @$this->setIfIsSet($roomPassword, $data['roomPassword'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($type, $data['type'], null);
        @$this->setIfIsSetAndTrim($maxUsers, $data['maxUsers'], null);

        if ($roomName === null || $roomName === '') {
            $message = _('The room name is required');
        } elseif (in_array($roomName, $this->roomsName)) {
            $message = sprintf(_('The chat room name "%s" already exists'), $roomName);
        } elseif ($type !== 'public' && $type !== 'private') {
            $message = _('The room type must be "public" or "private"');
        } elseif ($type === 'private' && ($roomPassword === null || strlen($roomPassword) === 0)) {
            $message = _('The password is required and must not be empty');
        } elseif (!is_numeric($maxUsers) || $maxUsers < 2) {
            $message = _('The max number of users must be a number and must no be less than 2');
        } else {
            if ($client['User'] === null) {
                $message = _('Authentication failed');
            } else {
                $usersChatRights              = new UsersChatRights();
                $usersChatRightsEntityManager = new UsersChatRightsEntityManager($usersChatRights);
                $userEntityManager            = new UserEntityManager($client['User']);
                $usersChatRights->idUser      = $client['User']->id;
                $usersChatRights->roomName    = $roomName;
                $usersChatRightsEntityManager->addRoomName($roomName);
                $usersChatRightsEntityManager->grantAll();

                $userHash               = $this->getConnectionHash($user['Connection']);
                $pseudonym              = $userEntityManager->getPseudonymForChat();
                $this->roomsName[]      = $roomName;
                $this->rooms[$roomName] = array(
                    'users'        => array($userHash => $client),
                    'pseudonyms'   => array($userHash => $pseudonym),
                    'usersRights'  => array(),
                    'creator'      => $client['User']->email,
                    'type'         => $type,
                    'password'     => $roomPassword,
                    'creationDate' => new \DateTime(),
                    'maxUsers'     => $maxUsers,
                    'usersBanned'  => array(),
                    'historic'     => array('part' => 0, 'conversations' => array())
                );

                mkdir(stream_resolve_include_path($this->savingDir) . DIRECTORY_SEPARATOR . $roomName);
                $this->addUserRoom($userHash, $roomName);
                $this->setRoomsName();
                $this->setLastPartNumber($roomName, 0);
                $this->saveRoom($roomName);

                $success = true;
                $message = sprintf(_('The chat room name "%s" is successfully created !'), $roomName);

                yield $this->log->log(
                    Log::INFO,
                    _('[chatService] New room added "%s" (%s) maxUsers = %s and password = "%s" by %s'),
                    $roomName,
                    $type,
                    $maxUsers,
                    $roomPassword,
                    $pseudonym
                );
            }
        }

        yield $client['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'createRoom',
            'success'  => $success,
            'roomName' => $roomName,
            'type'     => $type,
            'maxUsers' => $maxUsers,
            'password' => $roomPassword,
            'text'     => $message
        )));
    }

    /**
     * Send a public message to all the users in the room or a private message to one user in the room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function sendMessage(array $client, array $data)
    {
        $success    = false;
        $message    = _('Message successfully sent !');
        $userHash   = $this->getConnectionHash($client['Connection']);
        $response   = array();
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
        } elseif (!array_key_exists($userHash, $this->rooms[$roomName]['users'])) {
            $message = sprintf(_('You are not connected to the room %s'), $roomName);
        } elseif ($recievers === null) {
            $message = _('You must precise a reciever for your message (all or a pseudonym)');
        } elseif ($recievers !== 'all' && !$this->pseudonymIsInRoom($recievers, $roomName)) {
            $message = sprintf(_('The user "%" is not connected to the room "%"'), $recievers, $roomName);
        } else {
            $now       = date('Y-m-d H:i:s');
            $pseudonym = $this->rooms[$roomName]['pseudonyms'][$userHash];

            if ($recievers === 'all') {
                // Send the message to all the users in the chat room
                yield $this->sendMessageToRoom($client, $text, $roomName, 'public', $now);
            } else {
                // Send the message to one user
                $recieverHash        = $this->getUserHashByPseudonym($roomName, $recievers);
                $recieverClient      = $this->rooms[$roomName]['users'][$recieverHash];
                $response['message'] = $text;
                $response['type']    = 'private';

                yield $this->sendMessageToUser($client, $recieverClient, $text, $roomName, 'private', $now);
                yield $this->sendMessageToUser($client, $client, $text, $roomName, 'private', $now);
            }

            yield $this->log->log(
                Log::INFO,
                _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                $text,
                $pseudonym,
                $recievers,
                $roomName
            );

            $this->updateHistoric($roomName, $now, $text, $pseudonym, $recievers);
            $success = true;
        }

        yield $client['Connection']->send(json_encode(array_merge(
            $response,
            array(
                'service' => $this->chatService,
                'action'  => 'sendMessage',
                'success' => $success,
                'text'    => $message
            )
        )));
    }

    /**
     * Get the next chat conversation historic part of a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function getHistoric(array $client, array $data)
    {
        $success  = false;
        $message  = _('Historic successfully loaded !');
        $historic = array();
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], '');
        @$this->setIfIsSetAndTrim($password, $data['roomPassword'], '');
        @$this->setIfIsSetAndTrim($part, $data['historicLoaded'], '');

        if ($roomName === '') {
            $message = _('The room name is required');
        } elseif (!in_array($roomName, $this->roomsName)) {
            $message = sprintf(_('The chat room name "%s" does not exists'), $roomName);
        } elseif ($this->rooms[$roomName]['type'] === 'private' && $this->rooms[$roomName]['password'] !== $password) {
            $message = _('You cannot access to this room or the password is incorrect');
        } elseif (!is_numeric($part)) {
            $message = _('The part must be numeric');
        } else {
            $success  = true;
            $lastPart = $this->getLastPartNumber($roomName);

            if ($lastPart < $part) {
                $message = _('There is no more conversation historic for this chat');
            } else {
                $historic = $this->filterConversations(
                    $this->getHistoricPart($roomName, $lastPart - $part)['conversations'],
                    $this->rooms[$roomName]['pseudonyms'][$this->getConnectionHash($client['Connection'])]
                );
            }
        }

        yield $client['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'getHistoric',
            'success'  => $success,
            'text'     => $message,
            'historic' => $historic,
            'roomName' => $roomName
        )));
    }

    /**
     * Kick a user from a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function kickUser(array $client, array $data)
    {
        $success = false;
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);
        @$this->setIfIsSetAndTrim($reason, $data['reason'], '');

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } else {
            $usersChatRightsEntityManager = new UsersChatRightsEntityManager();
            $usersChatRightsEntityManager->loadEntity(array('idUser' => $client['User']->id, 'roomName' => $roomName));

            if ($client['User']->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->kick === 1) {
                $userHash  = $this->getUserHashByPseudonym($roomName, $pseudonym);
                $adminHash = $this->getConnectionHash($client['Connection']);

                if ($userHash !== false) {
                    if ($reason !== '') {
                        $reason = sprintf(_(' because %s'), $reason);
                    }

                    $success        = true;
                    $message        = sprintf(_('You kicked "%s" from the room "%s"'), $pseudonym, $roomName) . $reason;
                    $adminPseudonym = $this->rooms[$roomName]['pseudonyms'][$adminHash];

                    $this->rooms[$roomName]['users'][$userHash]->send(json_encode(array(
                        'service'  => $this->chatService,
                        'action'   => 'getKicked',
                        'text'     => sprintf(_('You got kicked from the room by "%s"'), $adminPseudonym) . $reason,
                        'roomName' => $roomName
                    )));

                    yield $this->disconnectUser($this->rooms[$roomName]['users'][$userHash]);

                    $text = sprintf(_('The user "%s" got kicked by "%s"'), $pseudonym, $adminPseudonym) . $reason;

                    yield $this->sendMessageToRoom($this->server, $text, $roomName, 'public', date('Y-m-d H:i:s'));
                } else {
                    $message = sprintf(_('The user "%s" cannot be found in the room "%s"'), $pseudonym, $roomName);
                }
            } else {
                $message = _('You do not have the right to kick a user on this room');
            }
        }

        yield $client['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'kickUser',
            'success'  => $success,
            'text'     => $message,
            'roomName' => $roomName
        )));
    }

    /**
     * Ban a user from a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function banUser(array $client, array $data)
    {
        $success = false;
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);
        @$this->setIfIsSetAndTrim($reasonInput, $data['reason'], '');

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } else {
            $usersChatRightsEntityManager = new UsersChatRightsEntityManager();
            $usersChatRightsEntityManager->loadEntity(array('idUser' => $client['User']->id, 'roomName' => $roomName));

            if ($client['User']->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->ban === 1) {
                $userHash  = $this->getUserHashByPseudonym($roomName, $pseudonym);
                $adminHash = $this->getConnectionHash($client['Connection']);

                if ($userHash !== false) {
                    if ($reasonInput !== '') {
                        $reason = sprintf(_(' for the reason %s'), $reasonInput);
                    } else {
                        $reason = '';
                    }

                    $success        = true;
                    $message        = sprintf(_('You banned "%s" from the room "%s"'), $pseudonym, $roomName) . $reason;
                    $adminPseudonym = $this->rooms[$roomName]['pseudonyms'][$adminHash];
                    $userInfo       = $this->rooms[$roomName]['users'][$userHash];
                    $banInfo        = array(
                        'ip'        => $userInfo['Connection']->getRemoteAddress(),
                        'pseudonym' => $pseudonym,
                        'admin'     => $adminPseudonym,
                        'reason'    => $reasonInput,
                        'date'      => date('Y-m-d H:i:s')
                    );

                    yield $userInfo['Connection']->send(json_encode(array(
                        'service'  => $this->chatService,
                        'action'   => 'getBanned',
                        'text'     => sprintf(_('You got banned from the room by "%s"'), $adminPseudonym) . $reason,
                        'roomName' => $roomName
                    )));

                    $this->rooms[$roomName]['usersBanned'][] = $banInfo;
                    $text = sprintf(_('The user "%s" got banned by "%s"'), $pseudonym, $adminPseudonym) . $reason;
                    yield $this->disconnectUser($userInfo);
                    yield $this->updateRoomUsersBanned($roomName);
                    yield $this->sendMessageToRoom($this->server, $text, $roomName, 'public', date('Y-m-d H:i:s'));
                } else {
                    $message = sprintf(_('The user "%s" cannot be found in the room "%s"'), $pseudonym, $roomName);
                }
            } else {
                $message = _('You do not have the right to ban a user on this room');
            }
        }

        yield $user['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'banUser',
            'success'  => $success,
            'text'     => $message,
            'roomName' => $roomName
        )));
    }

    /**
     * Update a user right for a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function updateRoomUserRight(array $client, array $data)
    {
        $success = false;
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], '');
        @$this->setIfIsSetAndTrim($rightName, $data['rightName'], '');
        @$this->setIfIsSetAndTrim($rightValue, $data['rightValue'], '');

        if ($roomName === '') {
            $message = _('The room name is required');
        } elseif (!in_array($roomName, $this->roomsName)) {
            $message = sprintf(_('The chat room name "%s" does not exists'), $roomName);
        } elseif ($client['User'] === null) {
            $message = _('Authentication failed');
        } else {
            $usersChatRightsEntityManager = new UsersChatRightsEntityManager();
            $usersChatRightsEntityManager->loadEntity(array('idUser' => $client['User']->id, 'roomName' => $roomName));

            if ($client['User']->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->grant === 1) {
                $usersChatRightsEntityManager->loadEntity(
                    array(
                        'idUser'   => $userEntityManager->getUserIdByPseudonym($pseudonym),
                        'roomName' => $roomName
                    )
                );

                $usersChatRightsEntityManager->getEntity()->{$rightName} = (int) $rightValue;
                $usersChatRightsEntityManager->saveEntity();
                $text = sprintf(
                    _('The user %s has now %s the right to %s in the room %s'),
                    $pseudonym,
                    ($rightValue ? '' : _('not')),
                    $rightName,
                    $roomName
                );

                // Update all others admin users panel
                yield $this->updateRoomUsersRights($roomName);
                yield $this->sendMessageToRoom($this->server, $text, $roomName, 'public', date('Y-m-d H:i:s'));

                $success = true;
                $message = _('User right successfully updated');
            } else {
                $message = _('You do not have the right to grant a user right on this room');
            }
        }

        yield $client['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'updateRoomUserRight',
            'success'  => $success,
            'text'     => $message,
            'roomName' => $roomName
        )));
    }

    /**
     * Change a room name / password
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function setRoomInfo(array $client, array $data)
    {
        $success        = false;
        $messageToUsers = array();
        $user           = $client['User'];
        @$this->setIfIsSetAndTrim($oldRoomName, $data['oldRoomName'], '');
        @$this->setIfIsSetAndTrim($newRoomName, $data['newRoomName'], '');
        @$this->setIfIsSetAndTrim($oldRoomPassword, $data['oldRoomPassword'], '');
        @$this->setIfIsSetAndTrim($newRoomPassword, $data['newRoomPassword'], '');

        if ($oldRoomName === '') {
            $message[] = _('The room name is required');
        } elseif ($newRoomName === '') {
            $message[] = _('The new room name is required');
        } elseif (!in_array($oldRoomName, $this->roomsName)) {
            $message[] = sprintf(_('The chat room name "%s" does not exists'), $oldRoomName);
        } elseif ($oldRoomName !== $newRoomName && in_array($newRoomName, $this->roomsName)) {
            $message[] = sprintf(_('The chat room name "%s" already exists'), $newRoomName);
        } elseif ($user === null) {
            $message[] = _('Authentication failed');
        } else {
            $usersChatRightsEntityManager = new UsersChatRightsEntityManager();
            $usersChatRightsEntityManager->loadEntity(array('idUser' => $user->id, 'roomName' => $oldRoomName));

            if ($oldRoomPassword !== $newRoomPassword) {
                if ($user->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->password === 1) {
                    $success                               = true;
                    $this->rooms[$oldRoomName]['password'] = $newRoomPassword;
                    $message[]                             = _('The room password has been successfully updated');
                    $messageToUsers[]                      = sprintf(
                        _('The room password has been updated from "%s" to "%s"'),
                        $oldRoomPassword,
                        $newRoomPassword
                    );

                    if ($newRoomPassword === '') {
                        $this->rooms[$oldRoomName]['type'] = 'public';
                    } else {
                        $this->rooms[$oldRoomName]['type'] = 'private';
                    }
                } else {
                    $message[] = _('You do not have the right to change the room password');
                }
            }

            if ($oldRoomName !== $newRoomName) {
                if ($user->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->rename === 1) {
                    $success                   = true;
                    $this->rooms[$newRoomName] = $this->rooms[$oldRoomName];
                    $message[]                 = _('The room name has been successfully updated');
                    unset($this->rooms[$oldRoomName]);
                    $this->roomsName[array_search($oldRoomName, $this->roomsName)] = $newRoomName;

                    foreach ($this->usersRooms as &$roomName) {
                        if ($roomName === $oldRoomName) {
                            $roomName = $newRoomName;
                        }
                    }

                    rename(
                        stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $oldRoomName),
                        stream_resolve_include_path($this->savingDir) . DIRECTORY_SEPARATOR . $newRoomName
                    );

                    $usersChatRightsEntityManager->changeRoomName($oldRoomName, $newRoomName);

                    $this->setRoomsName();

                    $messageToUsers[] = sprintf(
                        _('The room name has been updated from "%s" to "%s"'),
                        $oldRoomName,
                        $newRoomName
                    );
                } else {
                    $message[] = _('You do not have the right to change the room name');
                }
            }

            $this->saveRoom($newRoomName);

            $date = date('Y-m-d H:i:s');

            foreach ($this->rooms[$newRoomName]['users'] as $userInfo) {
                yield $userInfo['Connection']->send(json_encode(array(
                    'service'         => $this->chatService,
                    'action'          => 'changeRoomInfo',
                    'oldRoomName'     => $oldRoomName,
                    'newRoomName'     => $newRoomName,
                    'oldRoomPassword' => $oldRoomPassword,
                    'newRoomPassword' => $newRoomPassword,
                    'pseudonym'       => 'SERVER',
                    'time'            => $date,
                    'roomName'        => $newRoomName,
                    'type'            => 'public',
                    'text'            => $messageToUsers
                )));
            }
        }

        yield $client['Connection']->send(json_encode(array(
            'service'         => $this->chatService,
            'action'          => 'setRoomInfo',
            'success'         => $success,
            'text'            => implode('. ', $message),
            'oldRoomName'     => $oldRoomName,
            'newRoomName'     => $newRoomName,
            'oldRoomPassword' => $oldRoomPassword,
            'newRoomPassword' => $newRoomPassword
        )));
    }

    /**
     * Disconnet a user from a room he was connected to
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to test
     */
    private function disconnectUserFromRoom(array $client, array $data)
    {
        @$this->setIfIsSetAndTrim($roomName, $data['roomName'], null);
        $userHash = $this->getConnectionHash($client['Connection']);
        $success    = false;

        if (!isset($this->usersRooms[$userHash])) {
            $message = _('An error occured');
        } elseif (!in_array($roomName, $this->usersRooms[$userHash])) {
            $message = sprintf(_('You are not connected to the room %s'), $roomName);
        } else {
            yield $this->disconnectUserFromRoomAction($userHash, $roomName);
            $message = sprintf(_('You are disconnected from the room %s'), $roomName);
            $success = true;
        }

        yield $client['Connection']->send(json_encode(array(
            'service'  => $this->chatService,
            'action'   => 'disconnectFromRoom',
            'success'  => $success,
            'text'     => $message,
            'roomName' => $roomName
        )));
    }

    /**
     * Get the rooms basic information (name, type, usersMax, usersConnected)
     *
     * @param      array  $client  The client information [Connection, User] array pair
     */
    private function getRoomsInfo(array $client)
    {
        $roomsInfo = array();

        foreach ($this->roomsName as $roomName) {
            if (isset($this->rooms[$roomName])) {
                $roomInfo       = $this->rooms[$roomName];
                $usersConnected = count($this->rooms[$roomName]['users']);
            } else {
                $roomInfo       = $this->getRoomInfo($roomName);
                $usersConnected = 0;
            }

            $roomsInfo[] = array(
                'name'           => $roomName,
                'type'           => $roomInfo['type'],
                'maxUsers'       => $roomInfo['maxUsers'],
                'usersConnected' => $usersConnected
            );
        }

        yield $client['Connection']->send(json_encode(array(
            'service'   => $this->chatService,
            'action'    => 'getRoomsInfo',
            'roomsInfo' => $roomsInfo
        )));
    }

    /**
     * Update the connected users list in a room
     *
     * @param      array   $client    The client information [Connection, User] array pair
     * @param      string  $roomName  The room name
     */
    private function updateRoomUsers(array $client, string $roomName)
    {
        yield $client['Connection']->send(json_encode(array(
            'service'    => $this->chatService,
            'action'     => 'updateRoomUsers',
            'roomName'   => $roomName,
            'pseudonyms' => array_values($this->rooms[$roomName]['pseudonyms'])
        )));
    }

    /**
     * Update the connected users rights list in a room
     *
     * @param      string  $roomName  The room name
     */
    private function updateRoomUsersRights(string $roomName)
    {
        foreach ($this->rooms[$roomName]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode(array(
                    'service'     => $this->chatService,
                    'action'      => 'updateRoomUsersRights',
                    'roomName'    => $roomName,
                    'usersRights' => $this->rooms[$roomName]['usersRights']
                )));
            }
        }
    }

    /**
     * Update the ip banned list in a room
     *
     * @param      string  $roomName  The room name
     */
    private function updateRoomUsersBanned(string $roomName)
    {
        foreach ($this->rooms[$roomName]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode(array(
                    'service'     => $this->chatService,
                    'action'      => 'updateRoomUsersBanned',
                    'roomName'    => $roomName,
                    'usersBanned' => $this->rooms[$roomName]['usersBanned']
                )));
            }
        }
    }

    /**
     * Disconnect a user from a room
     *
     * @param      string  $userHash  The user hash
     * @param      string  $roomName  The room name
     */
    private function disconnectUserFromRoomAction(string $userHash, string $roomName)
    {
        $pseudonym = $this->rooms[$roomName]['pseudonyms'][$userHash];

        unset($this->rooms[$roomName]['users'][$userHash]);
        unset($this->rooms[$roomName]['pseudonyms'][$userHash]);

        // Save and close the chat room if noone is in
        if (count($this->rooms[$roomName]['users']) === 0) {
            $this->saveHistoric($roomName);
            unset($this->rooms[$roomName]);
        } else {
            foreach ($this->rooms[$roomName]['users'] as $user) {
                if ($this->isRegistered($roomName, $pseudonym)) {
                    unset($this->rooms[$roomName]['usersRights'][$pseudonym]);
                    yield $this->updateRoomUsersRights($roomName);
                }

                yield $this->updateRoomUsers($user, $roomName);

                yield $this->sendMessageToUser(
                    $this->server,
                    $user,
                    sprintf(_('User "%s" disconnected'), $pseudonym),
                    $roomName,
                    'public',
                    date('Y-m-d H:i:s')
                );
            }
        }
    }

    /**
     * Disconnet a user from all the chat he was connected to
     *
     * @param      array  $client  The client information [Connection, User] array pair
     */
    private function disconnectUser($client)
    {
        $userHash = $this->getConnectionHash($client['Connection']);

        if (isset($this->usersRooms[$userHash])) {
            foreach ($this->usersRooms[$userHash] as $roomName) {
                yield $this->disconnectUserFromRoomAction($userHash, $roomName);
            }
        }
    }

    /**
     * Add a room to the user when he is connected to this room
     *
     * @param      string  $userHash  The user socket hash
     * @param      string  $roomName  The room name
     */
    private function addUserRoom(string $userHash, string $roomName)
    {
        if (!isset($this->usersRooms[$userHash])) {
            $this->usersRooms[$userHash] = array();
        }

        $this->usersRooms[$userHash][] = $roomName;
        $pseudonym                     = $this->rooms[$roomName]['pseudonyms'][$userHash];
        $date                          = date('Y-m-d H:i:s');

        yield $this->updateRoomUsersRights($roomName);

        foreach ($this->rooms[$roomName]['users'] as $user) {

            yield $this->updateRoomUsers($user, $roomName);
            yield $this->sendMessageToUser(
                $this->server,
                $user,
                sprintf(_('User "%s" connected'), $pseudonym),
                $roomName,
                'public',
                $date
            );
        }
    }

    /**
     * Check if a user has the right to enter a private room
     *
     * @param      string  $roomName      The room name
     * @param      string  $roomPassword  The room password the user sent
     *
     * @return     bool    True if the user have the right to enter the room else false
     */
    private function checkPrivateRoomPassword(string $roomName, string $roomPassword): bool
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
     * @param      string  $pseudonym  The pseudonym
     * @param      string  $roomName   The room name to connect to
     *
     * @return     bool    True if the pseudonym exists in the room else false
     */
    private function pseudonymIsInRoom(string $pseudonym, string $roomName): bool
    {
        return in_array($pseudonym, $this->rooms[$roomName]['pseudonyms']);
    }

    /**
     * Check if a pseudonym is usabled to be used in a room (not already in the room and not used by a registered user)
     *
     * @param      string  $pseudonym  The pseudonym
     * @param      string  $roomName   The room name to connect to
     *
     * @return     bool    True if the pseudonym is usabled else false
     */
    private function isPseudonymUsabled(string $pseudonym, string $roomName): bool
    {
        $isUsabled = !$this->pseudonymIsInRoom($pseudonym, $roomName);

        if ($isUsabled) {
            $userManager = new UserManager();
            $isUsabled   = !$userManager->isPseudonymExist($pseudonym);
        }

        return $isUsabled;
    }

    /**
     * Get the user hash by his pseudonym and the room name where he's connected
     *
     * @param      string       $roomName   The room name
     * @param      string       $pseudonym  The user pseudonym
     *
     * @return     string|bool  The user hash or false if an the user cannot be found
     */
    private function getUserHashByPseudonym(string $roomName, string $pseudonym)
    {
        return array_search($pseudonym, $this->rooms[$roomName]['pseudonyms']);
    }

    /**
     * Tell if a user is registered or not
     *
     * @param      string  $roomName   The room name
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     bool    True if the user is registered else false
     */
    private function isRegistered(string $roomName, string $pseudonym): bool
    {
        return array_key_exists($pseudonym, $this->rooms[$roomName]['usersRights']);
    }

    /**
     * Send a message to a user
     *
     * @param      array   $clientFrom  The client to send the message from array(Connection, User)
     * @param      array   $clientTo    The client to send the message to array(Connection, User)
     * @param      string  $message     The text message
     * @param      string  $roomName    The room name
     * @param      string  $type        The message type ('public' || 'private')
     * @param      string  $date        The server date at the moment the message was processed (Y-m-d H:i:s)
     */
    private function sendMessageToUser(
        array $clientFrom,
        array $clientTo,
        string $message,
        string $roomName,
        string $type,
        string $date
    ) {
        if ($clientFrom['Connection'] === 'SERVER') {
            $pseudonym = 'SERVER';
        } else {
            $pseudonym = $this->rooms[$roomName]['pseudonyms'][$this->getConnectionHash($clientFrom['Connection'])];
        }

        yield $clientTo['Connection']->send(json_encode(array(
            'service'   => $this->chatService,
            'action'    => 'recieveMessage',
            'pseudonym' => $pseudonym,
            'time'      => $date,
            'roomName'  => $roomName,
            'type'      => $type,
            'text'      => $message
        )));
    }

    /**
     * Send a message to all the room users
     *
     * @param      array   $clientFrom  The client to send the message from array(Connection, User)
     * @param      string  $message     The text message
     * @param      string  $roomName    The room name
     * @param      string  $type        The message type ('public' || 'private')
     * @param      string  $date        The server date at the moment the message was processed (Y-m-d H:i:s)
     */
    private function sendMessageToRoom(
        array $clientFrom,
        string $message,
        string $roomName,
        string $type,
        string $date
    ) {
        foreach ($this->rooms[$roomName]['users'] as $clientTo) {
            yield $this->sendMessageToUser($clientFrom, $clientTo, $message, $roomName, $type, $date);
        }
    }

    /**
     * Filter conversations to delete private message which must not be viewed by the user and parse the content
     *
     * @param      array   $conversations  The conversations
     * @param      string  $pseudonym      The user pseudonym
     *
     * @return     array   The filtered conversations
     */
    private function filterConversations(array $conversations, string $pseudonym): array
    {
        $filteredConversations = array();

        if (count($conversations) > 0) {
            foreach ($conversations as $conversation) {
                $filteredConversation['pseudonym'] = $conversation['from'];
                $filteredConversation['time']      = $conversation['time'];
                $filteredConversation['text']      = $conversation['text'];

                if ($conversation['to'] !== 'all') {
                    if ($conversation['from'] === $pseudonym) {
                        $filteredConversation['type'] = 'private';
                        $filteredConversations[]      = $filteredConversation;
                    }
                } else {
                    $filteredConversation['type'] = 'public';
                    $filteredConversations[]      = $filteredConversation;
                }
            }
        }

        return $filteredConversations;
    }

    /**
     * Store a room in a file to recover it later
     *
     * @param      string  $roomName  The room name
     */
    private function saveRoom(string $roomName)
    {
        $tmpUsers                             = $this->rooms[$roomName]['users'];
        $tmpPseudonyms                        = $this->rooms[$roomName]['pseudonyms'];
        $tmpHistoric                          = $this->rooms[$roomName]['historic'];
        $this->rooms[$roomName]['users']      = array();
        $this->rooms[$roomName]['pseudonyms'] = array();
        $this->rooms[$roomName]['historic']   = array();

        file_put_contents(
            stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $roomName) .
            DIRECTORY_SEPARATOR . 'room.json',
            json_encode($this->rooms[$roomName])
        );

        $this->rooms[$roomName]['users']      = $tmpUsers;
        $this->rooms[$roomName]['pseudonyms'] = $tmpPseudonyms;
        $this->rooms[$roomName]['historic']   = $tmpHistoric;
    }

    /**
     * Get the room information stored in a JSON file
     *
     * @param      string  $roomName  The room name
     *
     * @return     array   The JSON decoded room information as associative array
     */
    private function getRoomInfo(string $roomName): array
    {
        return json_decode(
            file_get_contents(
                stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR .$roomName) .
                DIRECTORY_SEPARATOR . 'room.json'
            ),
            true
        );
    }

    /**
     * Load a room that was stored in a file
     *
     * @param      string  $roomName  The room name
     */
    private function loadRoom(string $roomName)
    {
        $this->rooms[$roomName] = $this->getRoomInfo($roomName);

        $this->loadHistoric($roomName, $this->getLastPartNumber($roomName));
    }

    /**
     * Update a conversation historic with a new message
     *
     * @param      string  $roomName  The room name
     * @param      string  $time      The server message sent time
     * @param      string  $message   The text message
     * @param      string  $from      The pseudonym of the user message owner
     * @param      string  $to        The pseudonym of the user message reviever or 'all'
     */
    private function updateHistoric(string $roomName, string $time, string $message, string $from, string $to)
    {
        if (count($this->rooms[$roomName]['historic']['conversations']) >= $this->maxMessagesPerFile) {
            $this->saveHistoric($roomName);
            $this->rooms[$roomName]['historic']['conversations'] = array();
            $this->setLastPartNumber($roomName, ++$this->rooms[$roomName]['historic']['part']);
        }

        $this->rooms[$roomName]['historic']['conversations'][] = array(
            'text' => $message,
            'time' => $time,
            'from' => $from,
            'to'   => $to
        );
    }

    /**
     * Store the conversation historic into a JSON text file
     *
     * @param      string  $roomName  The room name
     */
    private function saveHistoric(string $roomName)
    {
        $part = $this->rooms[$roomName]['historic']['part'];

        file_put_contents(
            stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $roomName) .
            DIRECTORY_SEPARATOR . 'historic-part-' . $part . '.json',
            json_encode($this->rooms[$roomName]['historic'])
        );
    }

    /**
     * Load an historic
     *
     * @param      string  $roomName  The room name
     * @param      int     $part      The historic part
     */
    private function loadHistoric(string $roomName, int $part)
    {
        $historic = $this->getHistoricPart($roomName, $part);

        if ($historic === false || $historic === null) {
            $historic = array('part' => 0, 'conversations' => array());
        }

        $this->rooms[$roomName]['historic'] = $historic;
    }

    /**
     * Get a conversation historic part
     *
     * @param      string       $roomName  The room name
     * @param      int          $part      The conversation part
     *
     * @return     array|false  The conversation historic as an array or false if an error occured
     */
    private function getHistoricPart(string $roomName, int $part)
    {
        return json_decode(
            @file_get_contents(
                stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $roomName) .
                DIRECTORY_SEPARATOR . 'historic-part-' . $part . '.json'
            ),
            true
        );
    }

    /**
     * Get the last part number of room historic
     *
     * @param      string  $roomName  The room name
     *
     * @return     int     The last part number
     */
    private function getLastPartNumber(string $roomName): int
    {
        return (int) file_get_contents(
            stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $roomName) .
            DIRECTORY_SEPARATOR . 'historic-last-part'
        );
    }

    /**
     * Set the last part number of room historic
     *
     * @param      string  $roomName  The room name
     * @param      int     $part      The last part number
     */
    private function setLastPartNumber(string $roomName, int $part)
    {
        file_put_contents(
            stream_resolve_include_path($this->savingDir . DIRECTORY_SEPARATOR . $roomName) .
            DIRECTORY_SEPARATOR . 'historic-last-part',
            $part
        );
    }

    /**
     * Get the rooms name
     *
     * @return     string[]  The rooms name
     */
    private function getRoomsName(): array
    {
        return json_decode(file_get_contents($this->roomsNamePath, FILE_USE_INCLUDE_PATH), true);
    }

    /**
     * Update the rooms name
     */
    private function setRoomsName()
    {
        file_put_contents($this->roomsNamePath, json_encode($this->roomsName), FILE_USE_INCLUDE_PATH);
    }

    /*=====  End of Private methods  ======*/
}
