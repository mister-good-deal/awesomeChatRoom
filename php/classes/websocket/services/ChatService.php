<?php
/**
 * Chat application to manage a chat with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use \interfaces\ServiceInterface as Service;
use \classes\websocket\ServicesDispatcher as ServicesDispatcher;
use \classes\IniManager as Ini;
use \classes\entities\User as User;
use \classes\entities\UserChatRight as UserChatRight;
use \classes\entities\ChatRoom as ChatRoom;
use \classes\entities\ChatRoomBan as ChatRoomBan;
use \classes\managers\UserManager as UserManager;
use \classes\managers\ChatManager as ChatManager;
use \classes\ExceptionManager as Exception;
use Icicle\Log\Log as Log;
use Icicle\WebSocket\Connection as Connection;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class ChatService extends ServicesDispatcher implements Service
{
    use \traits\ShortcutsTrait;
    use \traits\PrettyOutputTrait;
    use \traits\FiltersTrait;
    use \traits\DateTrait;

    /**
     * @var        string  $chatService     The chat service name
     */
    private $chatService;
    /**
     * @var        string  $esIndex     The Elasticsearch index name
     */
    private $esIndex;
    /**
     * @var        integer  $historicStep   The maximum number of message to retrieve per historic request
     */
    private $historicStep;
    /**
     * @var array $rooms Rooms live sessions
     *
     * [
     *      'room ID' => [
     *          'users' => [
     *                         userHash1 => [
     *                             'User'       => User,
     *                             'Connection' => Connection,
     *                             'pseudonym'  => 'room user pseudonym',
     *                             'location'   => ['lat' => latitude, 'lon' => longitude]
     *                         ],
     *                         userHash2 => [
     *                             'User'       => User,
     *                             'Connection' => Connection,
     *                             'pseudonym'  => 'room user pseudonym',
     *                             'location'   => ['lat' => latitude, 'lon' => longitude]
     *                         ],
     *                         ...
     *                     ],
     *          'room' => ChatRoom
     *      ]
     * ]
     */
    private $rooms = [];

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that loads chat parameters
     *
     * @param      Log   $log    Logger object
     */
    public function __construct(Log $log)
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->esIndex      = Ini::getParam('ElasticSearch', 'index');
        $conf               = Ini::getSectionParams('Chat service');
        $this->chatService  = $conf['serviceName'];
        $this->historicStep = $conf['historicStep'];
        $this->log          = $log;
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param      array       $data    JSON decoded client data
     * @param      array       $client  The client information [Connection, User] array pair
     *
     * @return     \Generator
     */
    public function process(array $data, array &$client)
    {
        switch ($data['action']) {
            case 'sendMessage':
                yield $this->sendMessage($client, $data);

                break;

            case 'connectRoom':
                yield $this->connectUser($client, $data);

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
                yield $this->getRoomsInfo($client);

                break;

            default:
                yield $client['Connection']->send(json_encode([
                    'service' => $this->chatService,
                    'success' => false,
                    'text'    => _('Unknown action')
                ]));
        }
    }

    /**
     * Disconnet a user from all the chat he was connected to
     *
     * @param      array       $client  The client information [Connection, User] array pair
     *
     * @return     \Generator
     */
    public function disconnectUser($client)
    {
        $userHash = $this->getConnectionHash($client['Connection']);

        foreach ($this->rooms as $roomInfo) {
            if (array_key_exists($userHash, $roomInfo['users'])) {
                yield $this->disconnectUserFromRoomAction($userHash, $roomInfo['room']->id);
            }
        }
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Get the rooms basic information (name, type, usersMax, usersConnected)
     *
     * @param      array       $client  The client information [Connection, User] array pair
     *
     * @return     \Generator
     */
    private function getRoomsInfo(array $client)
    {
        $chatManager = new ChatManager();
        $rooms       = $chatManager->getAllRooms();
        $roomsInfo   = [];

        foreach ($rooms as $room) {
            $roomsInfo[] = [
                'room'           => $room->__toArray(),
                'usersConnected' => isset($this->rooms[$room->id]) ? count($this->rooms[$room->id]['users']) : 0
            ];
        }

        yield $client['Connection']->send(json_encode([
            'service'   => $this->chatService,
            'action'    => 'getRoomsInfo',
            'roomsInfo' => $roomsInfo
        ]));
    }

    /**
     * Create a chat room by an authenticated user request
     *
     * @param      array       $client  The client information [Connection, User] array pair
     * @param      array       $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function createRoom(array &$client, array $data)
    {
        $message = _('An error occured');

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } else {
            $userManager = new UserManager($client['User']);
            $chatManager = new ChatManager();
            $response    = $chatManager->createChatRoom(
                $client['User']->id,
                $data['roomName'],
                $data['maxUsers'],
                $data['roomPassword']
            );

            if ($response['success']) {
                $room                  = $chatManager->getChatRoomEntity();
                $userChatRight         = new UserChatRight();
                $userChatRight->idUser = $client['User']->id;
                $userChatRight->idRoom = $room->id;
                $response['success']   = $userManager->addUserChatRight($userChatRight, true);

                if ($response['success']) {
                    $this->rooms[$room->id] = [
                        'users' => [],
                        'room'  => $room
                    ];

                    yield $this->addUserToTheRoom($client, $room, $userManager->getPseudonymForChat(), $data['location']);

                    $message = sprintf(_('The chat room name "%s" is successfully created !'), $room->name);

                    yield $this->log->log(
                        Log::INFO,
                        _('[chatService] New room added => %s by %s'),
                        $room,
                        $userManager->getPseudonymForChat()
                    );
                }
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            [
                'service' => $this->chatService,
                'action'  => 'connectRoom',
                'room'    => $chatManager->getChatRoomEntity()->__toArray(),
                'success' => false,
                'text'    => $message
            ],
            $response
        )));
    }

    /**
     * Connect a user to one chat room as a registered or a guest user
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function connectUser(array $client, array $data)
    {
        $response    = [];
        $chatManager = new ChatManager();

        if ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            if (!isset($this->rooms[$chatManager->getChatRoomEntity()->id])) {
                $this->rooms[$chatManager->getChatRoomEntity()->id] = [
                    'users' => [],
                    'room'  => $chatManager->getChatRoomEntity()
                ];
            }

            $room = $chatManager->getChatRoomEntity();

            if (count($this->rooms[$room->id]['users']) >= $room->maxUsers) {
                $message = _('The room is full');
            } elseif (!$this->checkPrivateRoomPassword($room, $data['password'] ?? '')) {
                $message = _('Room password is incorrect');
            } elseif ($chatManager->isIpBanned($client['Connection']->getRemoteAddress())) {
                $message = _('You are banned from this room');
            } else {
                $closure = $this->addUserToTheRoom($client, $room, trim($data['pseudonym'] ?? ''), $data['location']);

                foreach ($closure as $value) {
                    yield $value;
                }

                $response = $closure->getReturn();
            }

            if (isset($response['success']) && $response['success']) {
                $message                = sprintf(_('You\'re connected to the chat room "%s" !'), $room->name);
                $response['room']       = $room->__toArray();
                $response['pseudonyms'] = $this->getRoomPseudonyms($room->id);
                $response['historic']   = $this->getRoomHistoric($room->id, $client);
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            [
                'service' => $this->chatService,
                'action'  => 'connectRoom',
                'success' => false,
                'text'    => $message ?? ''
            ],
            $response
        )));
    }

    /**
     * Send a public message to all the users in the room or a private message to one user in the room
     *
     * @param      array       $client  The client information [Connection, User] array pair
     * @param      array       $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function sendMessage(array &$client, array $data)
    {
        $success     = false;
        $response    = [];
        $userHash    = $this->getConnectionHash($client['Connection']);
        $recievers   = $data['recievers'] ?? null;
        $text        = trim($data['message']) ?? '';
        $chatManager = new ChatManager();

        if ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            $room = $chatManager->getChatRoomEntity();

            if ($text === '') {
                $message = _('The message cannot be empty');
            } elseif (!$this->checkPrivateRoomPassword($room, $data['password'] ?? '')) {
                $message = _('Incorrect password');
            } elseif (!array_key_exists($userHash, $this->rooms[$room->id]['users'])) {
                $message = sprintf(_('You are not connected to the room %s'), $room->name);
            } elseif ($recievers === null) {
                $message = _('You must precise a reciever for your message (all or a pseudonym)');
            } elseif ($recievers !== 'all' && !$this->isPseudonymInRoom($recievers, $room->id)) {
                $message = sprintf(_('The user "%" is not connected to the room "%"'), $recievers, $room->name);
            } else {
                if ($recievers === 'all') {
                    // Send the message to all the users in the chat room
                    yield $this->sendMessageToRoom($client, $text, $room->id, 'public');
                } else {
                    // Send the message to one user
                    $recieverHash        = $this->getUserHashByPseudonym($room->id, $recievers);
                    $recieverClient      = $this->rooms[$room->id]['users'][$recieverHash];
                    $response['message'] = $text;
                    $response['type']    = 'private';

                    yield $this->sendMessageToUser($client, $recieverClient, $text, $room->id, 'private');
                    yield $this->sendMessageToUser($client, $client, $text, $room->id, 'private');
                }

                yield $this->log->log(
                    Log::INFO,
                    _('[chatService] Message "%s" sent by "%s" to "%s" in the room "%s"'),
                    $text,
                    $this->getUserPseudonymByRoom($client, $room),
                    $recievers,
                    $room->name
                );

                $message = _('Message successfully sent !');
                $success = true;
            }
        }

        yield $client['Connection']->send(json_encode(array_merge(
            $response,
            [
                'service' => $this->chatService,
                'action'  => 'sendMessage',
                'success' => $success,
                'text'    => $message
            ]
        )));
    }

    /**
     * Get the next chat conversation historic part of a room
     *
     * @param      array       $client  The client information [Connection, User] array pair
     * @param      array       $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function getHistoric(array $client, array $data)
    {
        $success     = false;
        $message     = _('Historic successfully loaded !');
        $chatManager = new ChatManager();

        if ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            $room = $chatManager->getChatRoomEntity();

            if (!$this->checkPrivateRoomPassword($room, $data['password'] ?? '')) {
                $message = _('Room password is incorrect');
            } else {
                $success  = true;
                $historic = $this->getRoomHistoric($room->id, $client, $data['lastMessageDate']);
            }
        }

        yield $client['Connection']->send(json_encode([
            'service'  => $this->chatService,
            'action'   => 'getHistoric',
            'success'  => $success,
            'text'     => $message,
            'historic' => $historic ?? null,
            'roomId'   => $data['roomId']
        ]));
    }

    /**
     * Kick a user from a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function kickUser(array $client, array $data)
    {
        $success     = false;
        $message     = _('User kicked');
        $chatManager = new ChatManager();

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } elseif ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            $room        = $chatManager->getChatRoomEntity();
            $userManager = new UserManager($client['User']);

            if (!$this->checkPrivateRoomPassword($room, $data['password'] ?? '')) {
                $message = _('Incorrect password');
            } elseif (!$userManager->hasChatKickRight((int) $room->id)) {
                $message = _('You do not have the right to kick a user from this room');
            } else {
                try {
                    $userHash = $this->getUserHashByPseudonym($room->id, $data['pseudonym'] ?? '');

                    // Inform the user that he's kicked
                    yield $this->rooms[$room->id]['users'][$userHash]['Connection']->send(json_encode([
                        'service'  => $this->chatService,
                        'action'   => 'getKicked',
                        'roomId'   => $room->id,
                        'roomName' => $room->name,
                        'text'     => sprintf(
                            _('You got kicked from the room by `%s'),
                            $this->getUserPseudonymByRoom($client, $room) . '`'
                            . (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                        )
                    ]));

                    // Kick the user and inform others room users
                    yield $this->disconnectUserFromRoomAction(
                        $userHash,
                        $room->id,
                        'kicked',
                        [
                            'admin'  => $this->getUserPseudonymByRoom($client, $room),
                            'reason' => (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                        ]
                    );

                    $success = true;
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }

        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->chatService,
            'action'  => 'kickUser',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /**
     * Ban a user from a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function banUser(array $client, array $data)
    {
        $success     = false;
        $message     = _('User banned');
        $chatManager = new ChatManager();

        if ($client['User'] === null) {
            $message = _('Authentication failed');
        } elseif ($chatManager->loadChatRoom((int) $data['roomId']) === false) {
            $message = _('This room does not exist');
        } else {
            $room        = $chatManager->getChatRoomEntity();
            $userManager = new UserManager($client['User']);

            if (!$this->checkPrivateRoomPassword($room, $data['password'] ?? '')) {
                $message = _('Incorrect password');
            } elseif (!$userManager->hasChatBanRight((int) $room->id)) {
                $message = _('You do not have the right to ban a user from this room');
            } else {
                try {
                    $userHash = $this->getUserHashByPseudonym($room->id, $data['pseudonym'] ?? '');

                    // Add the ban user to the room data
                    $banInfo = new ChatRoomBan([
                        'idChatRoom' => $room->id,
                        'ip'         => $this->rooms[$room->id]['users'][$userHash]['Connection']->getRemoteAddress(),
                        'pseudonym'  => $data['pseudonym'],
                        'admin'      => $client['User']->id,
                        'reason'     => $data['reason'],
                        'date'       => date('Y-m-d H:i:s')
                    ]);

                    if ($chatManager->banUser($banInfo)) {
                        // Inform the user that he's banned
                        yield $this->rooms[$room->id]['users'][$userHash]['Connection']->send(json_encode([
                            'service'  => $this->chatService,
                            'action'   => 'getBanned',
                            'roomId'   => $room->id,
                            'roomName' => $room->name,
                            'text'     => sprintf(
                                _('You got banned from the room by `%s'),
                                $this->getUserPseudonymByRoom($client, $room) . '`'
                                . (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                            )
                        ]));

                        // Ban the user and inform others room users
                        yield $this->disconnectUserFromRoomAction(
                            $userHash,
                            $room->id,
                            'banned',
                            [
                                'admin'  => $this->getUserPseudonymByRoom($client, $room),
                                'reason' => (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                            ]
                        );

                        $success = true;
                    } else {
                        $message = _('The room ip ban did not succeed');
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }

        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->chatService,
            'action'  => 'banUser',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /**
     * Update a user right for a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to refacto
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
            $usersChatRightsEntityManager->loadEntity(['idUser' => $client['User']->id, 'roomName' => $roomName]);

            if ($client['User']->getUserRights()->chatAdmin || $usersChatRightsEntityManager->getEntity()->grant === 1) {
                $usersChatRightsEntityManager->loadEntity(
                    [
                        'idUser'   => $userEntityManager->getUserIdByPseudonym($pseudonym),
                        'roomName' => $roomName
                    ]
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
                yield $this->sendMessageToRoom([], $text, $roomName, 'public');

                $success = true;
                $message = _('User right successfully updated');
            } else {
                $message = _('You do not have the right to grant a user right on this room');
            }
        }

        yield $client['Connection']->send(json_encode([
            'service'  => $this->chatService,
            'action'   => 'updateRoomUserRight',
            'success'  => $success,
            'text'     => $message,
            'roomName' => $roomName
        ]));
    }

    /**
     * Change a room name / password
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @todo       to refacto
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
            $usersChatRightsEntityManager->loadEntity(['idUser' => $user->id, 'roomName' => $oldRoomName]);

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
     * Send a message to a user
     *
     * @param      array       $clientFrom  The client to send the message from array(Connection, User)
     * @param      array       $clientTo    The client to send the message to array(Connection, User)
     * @param      string      $message     The text message
     * @param      int         $roomId      The room ID
     * @param      string      $type        The message type ('public' || 'private')
     * @param      float       $date        The server timestamp at the moment the message was sent DEFAULT null
     * @param      bool        $indexed     If the messages is already indexed in ES DEFAULT false
     *
     * @return     \Generator
     */
    private function sendMessageToUser(
        array $clientFrom,
        array $clientTo,
        string $message,
        int $roomId,
        string $type,
        float $date = null,
        bool $indexed = false
    ) {
        if (count($clientFrom) === 0) {
            $pseudonym = 'SERVER';
        } else {
            $pseudonym = $this->getUserPseudonymByRoom($clientFrom, $this->rooms[$roomId]['room']);
        }

        $date = ($date !== null ? $date : static::microtimeAsInt());

        yield $clientTo['Connection']->send(json_encode([
            'service'   => $this->chatService,
            'action'    => 'recieveMessage',
            'pseudonym' => $pseudonym,
            'date'      => $date,
            'roomId'    => $roomId,
            'type'      => $type,
            'message'   => $message
        ]));

        if (!$indexed) {
            // Insert elasticSearch record
            yield $this->indexMessage($clientFrom, $clientTo, $message, $roomId, $type, $date);
        }
    }

    /**
     * Send a message to all the room users
     *
     * @param      array       $clientFrom  The client to send the message from array(Connection, User)
     * @param      string      $message     The text message
     * @param      int         $roomId      The room ID
     * @param      string      $type        The message type ('public' || 'private')
     * @param      string      $date        The server micro timestamp at the moment the message was sent DEFAULT null
     *
     * @return     \Generator
     */
    private function sendMessageToRoom(
        array $clientFrom,
        string $message,
        int $roomId,
        string $type,
        string $date = null
    ) {
        $date = ($date !== null ? $date : static::microtimeAsInt());

        foreach ($this->rooms[$roomId]['users'] as $clientTo) {
            yield $this->sendMessageToUser($clientFrom, $clientTo, $message, $roomId, $type, $date, true);
        }

        // Insert elasticSearch record
        yield $this->indexMessage($clientFrom, [], $message, $roomId, $type, $date);
    }

    /**
     * Index a document in ES (a chat message)
     *
     * @param      array       $clientFrom  The client to send the message from array( Connection, User)
     * @param      array       $clientTo    The client to send the message to array( Connection, User) or [] for global
     * @param      string      $message     The text message
     * @param      int         $roomId      The room ID
     * @param      string      $type        The message type ('public' || 'private')
     * @param      string      $date        The server micro timestamp at the moment the message was sent
     *
     * @return     \Generator
     */
    private function indexMessage(
        array $clientFrom,
        array $clientTo,
        string $message,
        int $roomId,
        string $type,
        string $date
    ) {
        if (count($clientFrom) !== 0) {
            $client = \Elasticsearch\ClientBuilder::create()->build();
            $params = [
                'index' => $this->esIndex . '_write',
                'type'  => 'message',
                'body'  => [
                    'pseudonym' => $this->getUserPseudonymByRoom($clientFrom, $this->rooms[$roomId]['room']),
                    'message'   => $message,
                    'type'      => $type,
                    'date'      => $date,
                    'room'      => $roomId,
                    'userFrom'  => [
                        'id'       => isset($clientFrom['User']) ? (int) $clientFrom['User']->id : -1,
                        'ip'       => $clientFrom['Connection']->getRemoteAddress(),
                        'location' => $this->getUserLocationByRoom($clientFrom, $roomId)
                    ],
                    'userTo' => [
                        'id'       => isset($clientTo['User']) ? (int) $clientTo['User']->id : -1,
                        'ip'       => count($clientTo) > 0 ? $clientTo['Connection']->getRemoteAddress() : '0.0.0.0',
                        'location' => count($clientTo) > 0 ? $this->getUserLocationByRoom($clientTo, $roomId) : []
                    ]
                ]
            ];

            try {
                $result = $client->index($params);
            } catch (\Exception $e) {
                yield $this->log->log(
                    Log::ERROR,
                    sprintf('[chatService] Document not indexed in ES %s %s', static::formatVariable($params), $e)
                );
            }
        }
    }

    /**
     * Get a room historic for a specific room ID and with message date lower than the given value
     *
     * @param      int     $roomId  The room ID to search messages in
     * @param      array   $client  The client who asked the historic array(Connection, User)
     * @param      string  $from    The maximum message published date in UNIX microtimestamp (string) DEFAULT null
     *
     * @return     array  The list of messages found
     */
    private function getRoomHistoric(int $roomId, array $client, string $from = null)
    {
        $es     = \Elasticsearch\ClientBuilder::create()->build();
        $from   = $from ?? static::microtimeAsInt();
        $userIp = $client['Connection']->getRemoteAddress();
        $userId = -1;

        if ($client['User'] !== null) {
            $userId  = $client['User']->id;
        }

        return $this->filterEsHitsByArray($es->search([
            'index'  => $this->esIndex . '_read',
            'type'   => 'message',
            'sort'   => 'date:desc',
            'fields' => 'pseudonym,message,type,date',
            'size'   => $this->historicStep,
            'body'   => [
                'query' => [
                    'filtered' => [
                        'filter' => [
                            'bool' => [
                                'must' => [
                                    // Date condition and room ID
                                    [
                                        'range' => [
                                            'date' => [
                                                'lt' => $from
                                            ]
                                        ]
                                    ],
                                    [
                                        'term' => [
                                            'room' => $roomId
                                        ]
                                    ],
                                    // Must be a public message or private one destinating to the user
                                    [
                                        'bool' => [
                                            'should' => [
                                                [
                                                    'term' => [
                                                        'type' => 'public'
                                                    ]
                                                ],
                                                [
                                                    'bool' => [
                                                        'should' => [
                                                            [
                                                                'term' => [
                                                                    'userTo.id' => $userId
                                                                ]
                                                            ],
                                                            [
                                                                'term' => [
                                                                    'userTo.ip' => $userIp
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]));
    }

    /**
     * Get all the users room rights
     *
     * @param      string  $roomName  The room name
     *
     * @return     array   An array containing all the users rights indexed by their ID
     *
     * 'userRight' => ['user ID' => [right]], 'userChatRight' => ['User ID'][chatRight]]
     */
    private function getUsersRightFromRoom(string $roomName): array
    {
        $rights = [
            'userRight'     => [],
            'userChatRight' => []
        ];

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
        $pseudonyms = [];

        foreach ($this->rooms[$roomId]['users'] as $userInfo) {
            $pseudonyms[] = $userInfo['pseudonym'];
        }

        return $pseudonyms;
    }

    /**
     * Get a user hash from his pseudonym
     *
     * @param      int        $roomId     The room ID
     * @param      string     $pseudonym  The user pseudonym
     *
     * @throws     Exception  If the user does not exist in the room
     *
     * @return     string     The user hash
     */
    private function getUserHashByPseudonym(int $roomId, string $pseudonym): string
    {
        $hash = null;

        foreach ($this->rooms[$roomId]['users'] as $userHash => $userInfo) {
            if ($userInfo['pseudonym'] === $pseudonym) {
                $hash = $userHash;
                break;
            }
        }

        if ($hash === null) {
            throw new Exception('This user does not exists in this room', Exception::$WARNING);
        }

        return $hash;
    }

    /**
     * Get a user hash from his pseudonym
     *
     * @param      int     $roomId     The room ID
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     User  The user
     *
     * @todo not used for the moment
     */
    private function getUserByRoomByPseudonym(int $roomId, string $pseudonym): User
    {
        return $this->rooms[$roomId]['users'][$this->getUserHashByPseudonym($roomId, $pseudonym)];
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

    /**
     * Update the connected users list in a room
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      int    $roomId  The room name
     *
     * @return     array  The user location in ['lat' => latitude, 'lon' => longitude] format
     */
    private function getUserLocationByRoom(array $client, int $roomId): array
    {
        return $this->rooms[$roomId]['users'][$this->getConnectionHash($client['Connection'])]['location'];
    }

    /**
     * Update the connected users list in a room
     *
     * @param      array       $client  The client information [Connection, User] array pair
     * @param      int         $roomId  The room name
     *
     * @return     \Generator
     */
    private function updateRoomUsers(array $client, int $roomId)
    {
        yield $client['Connection']->send(json_encode([
            'service'    => $this->chatService,
            'action'     => 'updateRoomUsers',
            'roomId'     => $roomId,
            'pseudonyms' => $this->getRoomPseudonyms($roomId)
        ]));
    }

    /**
     * Update the connected users rights list in a room
     *
     * @param      string      $roomName  The room name
     *
     * @return     \Generator
     */
    private function updateRoomUsersRights(string $roomName)
    {
        foreach ($this->rooms[$roomName]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode([
                    'service'     => $this->chatService,
                    'action'      => 'updateRoomUsersRights',
                    'roomName'    => $roomName,
                    'usersRights' => $this->rooms[$roomName]['usersRights']
                ]));
            }
        }
    }

    /**
     * Update the ip banned list in a room
     *
     * @param      string      $roomName  The room name
     *
     * @return     \Generator
     */
    private function updateRoomUsersBanned(string $roomName)
    {
        foreach ($this->rooms[$roomName]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode([
                    'service'     => $this->chatService,
                    'action'      => 'updateRoomUsersBanned',
                    'roomName'    => $roomName,
                    'usersBanned' => $this->rooms[$roomName]['usersBanned']
                ]));
            }
        }
    }

    /**
     * Tell if a pseudonym is usabled in the room
     *
     * @param      string  $pseudonym  The pseudonym to test
     * @param      int     $roomId     The room ID
     *
     * @return     bool    True if the pseudonym is usabled else false
     *
     * @todo       Check in the user database if the pseudonym is used
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
     * @param      array     $location   The user location ['lat' => latitude, 'lon' => longitude]
     *
     * @return     array  Result as an array of values (success, pseudonym, message)
     */
    private function addUserToTheRoom(array $client, ChatRoom $room, string $pseudonym = '', array $location = [])
    {
        $response = ['success' => false];
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
                $response['text'] = sprintf(_('The pseudonym "%s" is already used'), $pseudonym);
            }
        } else {
            $response['text'] = _('The pseudonym can\'t be empty');
        }

        if ($response['success']) {
            // Add user to the room
            $this->rooms[$room->id]['users'][$userHash]              = $client;
            $this->rooms[$room->id]['users'][$userHash]['pseudonym'] = $response['pseudonym'];
            $this->rooms[$room->id]['users'][$userHash]['location']  = $location;

            // Send a message to all users in chat and warn them a new user is connected
            $message = sprintf(_("%s joins the room"), $response['pseudonym']);
            $rights  = [];

            foreach ($this->rooms[$room->id]['users'] as $userInfo) {
                if ($userInfo['User'] !== null) {
                    $rights[$userInfo['pseudonym']] = ['chatRight' => $userInfo['User']->getChatRight()];
                }

                yield $this->sendMessageToUser([], $userInfo, $message, $room->id, 'private');
                yield $userInfo['Connection']->send(json_encode([
                    'service'    => $this->chatService,
                    'action'     => 'updateRoomUsers',
                    'success'    => true,
                    'roomId'     => $room->id,
                    'pseudonyms' => $this->getRoomPseudonyms($room->id)
                ]));
            }

            if ($client['User'] !== null) {
                $response['chatRights'] = $rights;
            }

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
     * Disconnet a user from a room he was connected to
     *
     * @param      array       $client  The client information [Connection, User] array pair
     * @param      array       $data    JSON decoded client data
     *
     * @return     \Generator
     */
    private function disconnectUserFromRoom(array $client, array $data)
    {
        $success  = false;
        $roomId   = $data['roomId'] ?? null;
        $userHash = $this->getConnectionHash($client['Connection']);

        if ($roomId === null) {
            $message = sprintf(_('This room does not exist'));
        } elseif (!array_key_exists($userHash, $this->rooms[$roomId]['users'])) {
            $message = sprintf(_('You are not connected to the room %s'), $this->rooms[$roomId]['room']->name);
        } else {
            $message = sprintf(_('You are disconnected from the room %s'), $this->rooms[$roomId]['room']->name);
            $success = true;
            yield $this->disconnectUserFromRoomAction($userHash, $roomId, 'leftRoom');
        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->chatService,
            'action'  => 'disconnectFromRoom',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $roomId
        ]));
    }

    /**
     * Disconnect a user from a room
     *
     * @param      string      $userHash  The user hash
     * @param      int         $roomId    The room ID
     * @param      string      $cause     The event that caused the disconnection DEFAULT 'browserClosed'
     * @param      array       $info      Additional information about the disconnection
     *
     * @return     \Generator
     *
     * @todo rename this method with disconnectUserFromRoom
     */
    private function disconnectUserFromRoomAction(
        string $userHash,
        int $roomId,
        string $cause = 'browserClosed',
        array $info = []
    ) {
        $pseudonym = $this->rooms[$roomId]['users'][$userHash]['pseudonym'];

        unset($this->rooms[$roomId]['users'][$userHash]);

        // Close the chat room if noone is in
        if (count($this->rooms[$roomId]['users']) === 0) {
            unset($this->rooms[$roomId]);
        } else {
            foreach ($this->rooms[$roomId]['users'] as $userInfo) {
                yield $this->updateRoomUsers($userInfo, $roomId);

                switch ($cause) {
                    case 'browserClosed':
                        $message = sprintf(_('User `%s` was disconnected from the room'), $pseudonym);
                        break;

                    case 'leftRoom':
                        $message = sprintf(_('User `%s` left the room'), $pseudonym);
                        break;

                    case 'kicked':
                        $message = sprintf(
                            _('User `%s` was kicked from the room by `%s` %s'),
                            $pseudonym,
                            $info['admin'],
                            ($info['reason'] !== '' ? _("\nReason: ") . $info['reason'] : '')
                        );
                        break;

                    case 'banned':
                        $message = sprintf(
                            _('User `%s` was banned from the room by `%s` %s'),
                            $pseudonym,
                            $info['admin'],
                            ($info['reason'] !== '' ? _("\nReason: ") . $info['reason'] : '')
                        );
                        break;
                }

                yield $this->sendMessageToUser([], $userInfo, $message, $this->rooms[$roomId]['room']->id, 'public');
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
     *
     * @todo       chatRoom->password empty value coherence null or ''
     */
    private function checkPrivateRoomPassword(ChatRoom $chatRoom, string $roomPassword)
    {
        return ($chatRoom->password === "" || $chatRoom->password === null || $chatRoom->password === $roomPassword);
    }

    /*=====  End of Private methods  ======*/
}
