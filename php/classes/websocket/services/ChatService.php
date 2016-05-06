<?php
/**
 * Chat application to manage a chat with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use classes\websocket\Client as Client;
use classes\websocket\ClientCollection as ClientCollection;
use classes\IniManager as Ini;
use classes\entities\User as User;
use classes\entities\UserChatRight as UserChatRight;
use classes\entities\ChatRoom as ChatRoom;
use classes\entities\ChatRoomBan as ChatRoomBan;
use classes\managers\UserManager as UserManager;
use classes\managers\ChatManager as ChatManager;
use classes\ExceptionManager as Exception;
use Icicle\Log\Log as Log;
use Icicle\WebSocket\Connection as Connection;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class ChatService
{
    use \traits\ShortcutsTrait;
    use \traits\PrettyOutputTrait;
    use \traits\FiltersTrait;
    use \traits\DateTrait;

    /**
     * @var        string  $serviceName     The chat service name
     */
    private $serviceName;
    /**
     * @var        string  $esIndex     The Elasticsearch index name
     */
    private $esIndex;
    /**
     * @var        integer  $historicStep   The maximum number of message to retrieve per historic request
     */
    private $historicStep;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that loads chat parameters
     */
    public function __construct()
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->esIndex      = Ini::getParam('ElasticSearch', 'index');
        $conf               = Ini::getSectionParams('Chat service');
        $this->serviceName  = $conf['serviceName'];
        $this->historicStep = $conf['historicStep'];
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Method to recieves data from the WebSocket server and process it
     *
     * @param      array             $data     JSON decoded client data
     * @param      Client            $client   The client object
     * @param      ClientCollection  $clients  The clients collection
     *
     * @return     \Generator
     */
    public function process(array $data, Client $client, ClientCollection $clients)
    {
        switch ($data['action']) {
            case 'sendMessage':
                yield $this->sendMessage($data, $client);

                break;

            case 'getHistoric':
                yield $this->getHistoric($data, $client);

                break;

            case 'kickUser':
                yield $this->kickUser($data, $client);

                break;

            case 'banUser':
                yield $this->banUser($data, $client);

                break;

            default:
                yield $client->getConnection()->send(json_encode([
                    'service' => $this->serviceName,
                    'success' => false,
                    'text'    => _('Unknown action')
                ]));
        }
    }

    /**
     * Disconnet a user from all the chat rooms he was connected to
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

    /*============================================
    =            Direct called method            =
    ============================================*/

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
                'service' => $this->serviceName,
                'action'  => 'sendMessage',
                'success' => $success,
                'text'    => $message
            ]
        )));
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
                        'service'  => $this->serviceName,
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
            'service' => $this->serviceName,
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
                            'service'  => $this->serviceName,
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

                        // Update the users ban list for users who have access to the admin board EG registered users
                        yield $this->updateRoomUsersBanned((int) $room->id);

                        $success = true;
                    } else {
                        $message = _('The room ip ban failed');
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'banUser',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /**
     * Get the next chat conversation historic part of a room
     *
     * @param      array             $data     JSON decoded client data
     * @param      Client            $client   The client object
     * @param      ClientCollection  $clients  The clients collection
     *
     * @return     \Generator
     */
    private function getHistoric(array $data, Client $client)
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

        yield $client->getConnection()->send(json_encode([
            'service'  => $this->serviceName,
            'action'   => 'getHistoric',
            'success'  => $success,
            'text'     => $message,
            'historic' => $historic ?? null,
            'roomId'   => $data['roomId']
        ]));
    }

    /*=====  End of Direct called method  ======*/

    /*======================================
    =            Helper methods            =
    ======================================*/

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
            'service'   => $this->serviceName,
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
     * @param      Client  $client  The client who asked the historic
     * @param      string  $from    The maximum message published date in UNIX microtimestamp (string) DEFAULT null
     *
     * @return     array  The list of messages found
     */
    private function getRoomHistoric(int $roomId, array $client, string $from = null)
    {
        $es     = \Elasticsearch\ClientBuilder::create()->build();
        $from   = $from ?? static::microtimeAsInt();
        $userIp = $client->getConnection()->getRemoteAddress();
        $userId = -1;

        if ($client->getUser() !== null) {
            $userId  = $client->getUser()->id;
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
     * Get all the room users right
     *
     * @param      int    $roomId  The room ID
     *
     * @return     array An array containing all the registered user chat right
     */
    private function getRoomUsersRight(int $roomId): array
    {
        $usersRight = [];

        foreach ($this->rooms[$roomId]['users'] as $userInfo) {
            if ($userInfo['User'] !== null) {
                $chatRight = $userInfo['User']->getChatRight()->getEntityById($roomId);

                if ($chatRight === null) {
                    $chatRight = new UserChatRight([
                        'idUser' => $userInfo['User']->id,
                        'idRoom' => $roomId
                    ]);
                }

                $usersRight[$userInfo['pseudonym']] = $chatRight->__toArray();
            }
        }

        return $usersRight;
    }

    /**
     * Get the room users ban
     *
     * @param      int    $roomId  The room ID
     *
     * @return     array  The chat room ban list as array
     */
    private function getRoomUsersBan(int $roomId): array
    {
        $usersBan    = [];
        $chatManager = new ChatManager();
        $userManager = new UserManager();
        $chatManager->loadChatRoom($roomId);

        foreach ($chatManager->getUsersBanned() as $key => $chatRoomBan) {
            $usersBan[$key]          = $chatRoomBan->__toArray();
            $usersBan[$key]['admin'] = $userManager->getUserPseudonymById((int) $usersBan[$key]['admin']);
        }

        return $usersBan;
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
     * Get a user from his pseudonym in the specified room
     *
     * @param      int     $roomId     The room ID
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     User  The user
     */
    private function getUserByRoomByPseudonym(int $roomId, string $pseudonym): User
    {
        return $this->rooms[$roomId]['users'][$this->getUserHashByPseudonym($roomId, $pseudonym)]['User'];
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
     * @param      int    $roomId  The room ID
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
     * @param      int         $roomId  The room ID
     *
     * @return     \Generator
     */
    private function updateRoomUsers(array $client, int $roomId)
    {
        yield $client['Connection']->send(json_encode([
            'service'    => $this->serviceName,
            'action'     => 'updateRoomUsers',
            'roomId'     => $roomId,
            'pseudonyms' => $this->getRoomPseudonyms($roomId),
            'rights'     => $this->getRoomUsersRight($roomId)
        ]));
    }

    /**
     * Update the room's information for all users in teh room
     *
     * @param      ChatRoom    $room   The room
     *
     * @return     \Generator
     */
    private function updateRoomInformation(ChatRoom $room)
    {
        $roomInfo = $room->__toArray();

        foreach ($this->rooms[$room->id]['users'] as $user) {
            yield $user['Connection']->send(json_encode([
                'service'    => $this->serviceName,
                'action'     => 'updateRoomInformation',
                'roomId'     => $room->id,
                'roomInfo'   => $roomInfo,
                'messageInfo' => [
                    'pseudonym' => 'SERVER',
                    'date'      => static::microtimeAsInt(),
                    'type'      => 'public',
                    'message'   => _('The room information has been updated')
                ]
            ]));
        }
    }

    /**
     * Update the connected users rights list for the registered user with admin dashboard in the room
     *
     * @param      int         $roomId  The room ID
     *
     * @return     \Generator
     */
    private function updateRoomUsersRights(int $roomId)
    {
        $usersRightList = $this->getRoomUsersRight($roomId);

        foreach ($this->rooms[$roomId]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode([
                    'service'     => $this->serviceName,
                    'action'      => 'updateRoomUsersRights',
                    'roomId'      => $roomId,
                    'usersRights' => $usersRightList
                ]));
            }
        }
    }

    /**
     * Update the ip banned list for the registered user with admin dashboard in the room
     *
     * @param      int         $roomId  The room ID
     *
     * @return     \Generator
     */
    private function updateRoomUsersBanned(int $roomId)
    {
        $banList = $this->getRoomUsersBan($roomId);

        foreach ($this->rooms[$roomId]['users'] as $user) {
            if ($user['User'] !== false) {
                yield $user['Connection']->send(json_encode([
                    'service'     => $this->serviceName,
                    'action'      => 'updateRoomUsersBanned',
                    'roomId'      => $roomId,
                    'usersBanned' => $banList
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
            $response['user']      = $userManager->getUser()->__toArray();
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
            $response['usersRights']                                 = $this->getRoomUsersRight($room->id);

            // Send a message to all users in chat and warn them a new user is connected
            $message = sprintf(_("%s joins the room"), $response['pseudonym']);

            foreach ($this->rooms[$room->id]['users'] as $userInfo) {
                yield $this->sendMessageToUser([], $userInfo, $message, $room->id, 'private');
                yield $this->updateRoomUsers($userInfo, $room->id);
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
            'service' => $this->serviceName,
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

    /*=====  End of Helper methods  ======*/

    /*=====  End of Private methods  ======*/
}
