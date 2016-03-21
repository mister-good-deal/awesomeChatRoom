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
use \classes\entities\UserChatRight as UserChatRight;
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
        $this->chatService = Ini::getParam('Chat service', 'serviceName');

        // Create the default room (temporary)
        $chatManager = new ChatManager();
        $chatManager->loadChatRoom(1);
        $this->rooms[1] = array(
            'users'       => array(),
            'room'        => $chatManager->getChatRoomEntity()
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
    public function process(array $data, array &$client)
    {
        switch ($data['action']) {
            // case $this->serverKey . 'disconnect':
            //     // Action called by the server
            //     yield $this->disconnectUser($data['clientSocket']);

            //     break;

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
     * @todo       To test
     */
    private function connectUser(array &$client, array $data)
    {
        $response = array();

        @$this->setIfIsSet($password, $data['password'], '');
        @$this->setIfIsSetAndTrim($pseudonym, $data['pseudonym'], null);

        $chatManager = new ChatManager();

        if ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            if (!isset($this->rooms[$chatManager->getChatRoomEntity()->id])) {
                $this->rooms[$chatManager->getChatRoomEntity()->id] = array(
                    'users' => array(),
                    'room'  => $chatManager->getChatRoomEntity()
                );
            }

            $chatRoom = $chatManager->getChatRoomEntity();

            if (count($this->rooms[$chatRoom->id]['users']) >= $chatRoom->maxUsers) {
                $message = _('The room is full');
            } elseif (!$this->checkPrivateRoomPassword($chatRoom, $password)) {
                $message = _('Room password is incorrect');
            } elseif ($chatManager->isIpBanned($client['Connection']->getRemoteAddress())) {
                $message = _('You are banned from this room');
            } else {
                $closure = $this->addUserToTheRoom($client, $chatRoom, $pseudonym);

                foreach ($closure as $value) {
                    yield $value;
                }

                $response = $closure->getReturn();
            }

            if (isset($response['success']) && $response['success']) {
                $message                = sprintf(_('You\'re connected to the chat room "%s" !'), $chatRoom->name);
                $response['room']       = $chatRoom->__toArray();
                $response['pseudonyms'] = $this->getRoomPseudonyms($chatRoom->id);
                // @todo ElasticSearch room historic
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            array(
                'service' => $this->chatService,
                'action'  => 'connectRoom',
                'success' => false,
                'text'    => $message
            ),
            $response
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
    private function createRoom(array &$client, array $data)
    {
        $message = _('An error occured');

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } else {
            $userManager = new UserManager($client['User']);
            $chatManager = new ChatManager();
            $response = $chatManager->createChatRoom(
                $client['User']->id,
                $data['roomName'],
                $data['maxUsers'],
                $data['roomPassword']
            );

            if ($response['success']) {
                $chatRoom                   = $chatManager->getChatRoomEntity();
                $userChatRight              = new UserChatRight();
                $userChatRight->idUser      = $client['User']->id;
                $userChatRight->idRoom      = $chatRoom->id;
                $response['success']        = $userManager->addUserChatRight($userChatRight, true);

                if ($response['success']) {
                    $this->rooms[$chatRoom->id] = array(
                        'users' => array(),
                        'room'  => $chatRoom
                    );

                    yield $this->addUserToTheRoom($client, $chatRoom, $userManager->getPseudonymForChat());

                    $message = sprintf(_('The chat room name "%s" is successfully created !'), $chatRoom->name);

                    yield $this->log->log(
                        Log::INFO,
                        _('[chatService] New room added => %s by %s'),
                        $chatRoom->__toString(),
                        $userManager->getPseudonymForChat()
                    );
                }
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            array(
                'service' => $this->chatService,
                'action'  => 'connectRoom',
                'room'    => $chatManager->getChatRoomEntity()->__toArray(),
                'success' => false,
                'text'    => $message
            ),
            $response
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
    private function sendMessage(array &$client, array $data)
    {
        $success  = false;
        $response = array();
        $userHash = $this->getConnectionHash($client['Connection']);
        @$this->setIfIsSet($password, $data['password'], '');
        @$this->setIfIsSetAndTrim($recievers, $data['recievers'], null);
        @$this->setIfIsSetAndTrim($text, $data['message'], null);


        $chatManager = new ChatManager();

        if ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            $chatRoom = $chatManager->getChatRoomEntity();

            if ($text === null || $text === '') {
                $message = _('The message cannot be empty');
            } elseif (!$this->checkPrivateRoomPassword($chatRoom, $password)) {
                $message = _('Incorrect password');
            } elseif (!array_key_exists($userHash, $this->rooms[$chatRoom->id]['users'])) {
                $message = sprintf(_('You are not connected to the room %s'), $chatRoom->name);
            } elseif ($recievers === null) {
                $message = _('You must precise a reciever for your message (all or a pseudonym)');
            } elseif ($recievers !== 'all' && !$this->isPseudonymInRoom($recievers, $chatRoom->id)) {
                $message = sprintf(_('The user "%" is not connected to the room "%"'), $recievers, $chatRoom->name);
            } else {
                if ($recievers === 'all') {
                    // Send the message to all the users in the chat room
                    yield $this->sendMessageToRoom($client, $text, $chatRoom->id, 'public');
                } else {
                    // Send the message to one user
                    $recieverHash        = $this->getUserHashByPseudonym($chatRoom->id, $recievers);
                    $recieverClient      = $this->rooms[$chatRoom->id]['users'][$recieverHash];
                    $response['message'] = $text;
                    $response['type']    = 'private';

                    yield $this->sendMessageToUser($client, $recieverClient, $text, $chatRoom->id, 'private');
                    yield $this->sendMessageToUser($client, $client, $text, $chatRoom->id, 'private');
                }

                yield $this->log->log(
                    Log::INFO,
                    _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                    $text,
                    $this->getUserPseudonymByRoom($client, $chatRoom),
                    $recievers,
                    $chatRoom->name
                );

                // @todo historic
                $message = _('Message successfully sent !');
                $success = true;
            }
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

        foreach ($this->rooms as $roomInfo) {
            $roomsInfo[] = array(
                'room'           => $roomInfo['room']->__toArray(),
                'usersConnected' => count($roomInfo['users'])
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
     * Check if a user has the right to enter a private room
     *
     * @param      ChatRoom  $chatRoom      The chat room to connect to
     * @param      string    $roomPassword  The room password that the user sent
     *
     * @return     bool      True if the user has the right to enter the room else false
     */
    private function checkPrivateRoomPassword(ChatRoom $chatRoom, string $roomPassword)
    {
        yield $this->log->log(Log::DEBUG, 'checkPrivateRoomPassword $chatRoom = %s', $this->formatVariable($chatRoom));
        return ($chatRoom->password === "" || $chatRoom->password === $roomPassword);
    }

    /**
     * Send a message to a user
     *
     * @param      array   $clientFrom  The client to send the message from array(Connection, User)
     * @param      array   $clientTo    The client to send the message to array(Connection, User)
     * @param      string  $message     The text message
     * @param      int     $roomId      The room ID
     * @param      string  $type        The message type ('public' || 'private')
     * @param      string  $date        The server date at the moment the message was sent (Y-m-d H:i:s) DEFAULT null
     */
    private function sendMessageToUser(
        array $clientFrom,
        array $clientTo,
        string $message,
        int $roomId,
        string $type,
        string $date = null
    ) {
        if ($clientFrom['Connection'] === 'SERVER') {
            $pseudonym = 'SERVER';
        } else {
            $pseudonym = $this->getUserPseudonymByRoom($clientFrom, $this->rooms[$roomId]['room']);
        }

        yield $clientTo['Connection']->send(json_encode(array(
            'service'   => $this->chatService,
            'action'    => 'recieveMessage',
            'pseudonym' => $pseudonym,
            'time'      => $date !== null ? $date : date('Y-m-d H:i:s'),
            'roomId'    => $roomId,
            'type'      => $type,
            'text'      => $message
        )));
    }

    /**
     * Send a message to all the room users
     *
     * @param      array   $clientFrom  The client to send the message from array(Connection, User)
     * @param      string  $message     The text message
     * @param      int     $roomId      The room ID
     * @param      string  $type        The message type ('public' || 'private')
     * @param      string  $date        The server date at the moment the message was sent (Y-m-d H:i:s) DEFAULT null
     */
    private function sendMessageToRoom(
        array $clientFrom,
        string $message,
        int $roomId,
        string $type,
        string $date = null
    ) {
        $date = ($date !== null ? $date : date('Y-m-d H:i:s'));

        foreach ($this->rooms[$roomId]['users'] as $clientTo) {
            yield $this->sendMessageToUser($clientFrom, $clientTo, $message, $roomId, $type, $date);
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
     * Get all the users room rights
     *
     * @param      string  $roomName  The room name
     *
     * @return     array   An array containing all the users rights with array('userRight' => array(), 'userChatRight'
     *                     => array) indexed by their user ID
     */
    private function getUsersRightFromRoom(string $roomName): array
    {
        $rights = array(
            'userRight'     => array(),
            'userChatRight' => array()
        );

        foreach ($this->rooms[$roomName]['users'] as $userInfo) {
            if ($userInfo['User'] !== null) {
                $rights['userRight'][$userInfo['User']->id]     = $userInfo['User']->getRight()->__toArray();
                $rights['userChatRight'][$userInfo['User']->id] = $userInfo['User']->getChatRight()->__toArray();
            }
        }

        return $rights;
    }

    /**
     * Get all the room used pseudonyms
     *
     * @param      int    $roomId  The room ID
     *
     * @return     array  An array of all used pseudonyms
     */
    private function getRoomPseudonyms(int $roomId): array
    {
        $pseudonyms  = array();

        foreach ($this->rooms[$roomId]['users'] as $userInfo) {
            $pseudonyms[] = $userInfo['pseudonym'];
        }

        return $pseudonyms;
    }

    /**
     * Get a user hash from his pseudonym
     *
     * @param      int     $roomId     The room ID
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     string  The user hash
     */
    private function getUserHashByPseudonym(int $roomId, string $pseudonym): string
    {
        foreach ($this->rooms[$roomId]['users'] as $userHash => $userInfo) {
            if ($userInfo['pseudonym'] === $pseudonym) {
                break;
            }
        }

        return $userHash;
    }

    /**
     * Tell if a pseudonym is usabled in the room
     *
     * @param      string  $pseudonym  The pseudonym to test
     * @param      int     $roomId     The room ID
     *
     * @return     bool    True if the pseudonym is usabled else false
     *
     * @todo Check in the user database if the pseudonym is used
     */
    private function isPseudonymUsabled(string $pseudonym, int $roomId): bool
    {
        return !in_array($pseudonym, $this->getRoomPseudonyms($roomId));
    }

    /**
     * Tell if a pseudonym is in the room
     *
     * @param      string  $pseudonym  The pseudonym to test
     * @param      int     $roomId     The room ID
     *
     * @return     bool    True if the pseudonym is in the room else false
     */
    private function isPseudonymInRoom(string $pseudonym, int $roomId): bool
    {
        return in_array($pseudonym, $this->getRoomPseudonyms($roomId));
    }

    /**
     * Add a user to a room
     *
     * @param      array     $client     The client information [Connection, User] array pair
     * @param      ChatRoom  $room       The chat room
     * @param      string    $pseudonym  The user pseudonym DEFAULT ''
     *
     * @return     array     Result as an array of values (success, pseudonym, message)
     */
    private function addUserToTheRoom(array $client, ChatRoom $room, string $pseudonym = '')
    {
        $response = array('success' => false);
        $userHash = $this->getConnectionHash($client['Connection']);

        if ($client['User'] !== null) {
            // Authenticated user
            $userManager           = new UserManager($client['User']);
            $response['success']   = true;
            $response['pseudonym'] = $userManager->getPseudonymForChat();
        } elseif ($pseudonym !== '') {
            // Guest user
            if ($this->isPseudonymUsabled($pseudonym, $room->id)) {
                $response['pseudonym'] = $pseudonym;
                $response['success']   = true;

            } else {
                $response['message'] = sprintf(_('The pseudonym "%s" is already used'), $pseudonym);
            }
        } else {
            $response['message'] = _('The pseudonym can\'t be empty');
        }

        if ($response['success']) {
            // Send a message to all users in chat
            $message = sprintf(_("%s joins the room"), $response['pseudonym']);
            yield $this->sendMessageToRoom($this->server, $message, $room->id, 'public');
            // Add user to the room
            $this->rooms[$room->id]['users'][$userHash]              = $client;
            $this->rooms[$room->id]['users'][$userHash]['pseudonym'] = $response['pseudonym'];

            yield $this->log->log(
                Log::INFO,
                _('[chatService] New user "%s" added in the room "%s" (%d)'),
                $response['pseudonym'],
                $room->name,
                $room->id
            );
        }

        return $response;
    }

    /**
     * Get a user pseudonym for a room with his client connection
     *
     * @param      array     $client  The client information [Connection, User] array pair
     * @param      ChatRoom  $room    The chat room
     *
     * @return     string    The user pseudonym for this room
     */
    private function getUserPseudonymByRoom(array $client, ChatRoom $room): string
    {
        return $this->rooms[$room->id]['users'][$this->getConnectionHash($client['Connection'])]['pseudonym'];
    }

    /*=====  End of Private methods  ======*/
}
