<?php
/**
 * Chat application to manage a chat with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use classes\IniManager as Ini;
use classes\websocket\Client as Client;
use classes\websocket\ClientCollection as ClientCollection;
use classes\entities\User as User;
use classes\entities\Room as Room;
use classes\entities\ChatRoomBan as ChatRoomBan;
use classes\managers\RoomManager as RoomManager;
use classes\managers\UserManager as UserManager;
use classes\managers\ChatManager as ChatManager;
use classes\ExceptionManager as Exception;
use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;
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
     * @var        int   $historicStep  The maximum number of message to retrieve per historic request
     */
    private $historicStep;
    /**
     * @var        Logger  $logger  A LoggerManager instance
     */
    private $logger;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that loads chat parameters
     */
    public function __construct()
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $this->logger       = new Logger([Logger::CONSOLE]);
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
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client object
     * @param      RoomCollection  $rooms   The rooms collection
     *
     * @return     \Generator
     */
    public function process(array $data, Client $client, RoomCollection $rooms)
    {
        switch ($data['action']) {
            case 'sendMessage':
                yield $this->sendMessage($data, $client, $rooms);

                break;

            case 'getHistoric':
                yield $this->getHistoric($data, $client, $rooms);

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
     *
     * @todo       to refacto
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
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       to test
     */
    private function sendMessage(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $message     = _('An error occured');
        $text        = trim($data['message']) ?? '';
        $roomManager = new RoomManager(null, $rooms);
        $recievers   = new ClientCollection();
        $room        = null;

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int) $data['roomId']);
            $room = $roomManager->getRoom();

            if ($text === '') {
                $message = _('The message cannot be empty');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } else {
                if ($data['recievers'] === 'all') {
                    $recievers = $room->getClients();
                    $type      = 'public';
                } else {
                    foreach (($data['recievers'] ?? []) as $clientId) {
                        $reciever = $room->getClients()->getObjectById($clientId);
                        $type     = 'private';

                        if ($reciever !== null) {
                            $recievers->add($reciever);
                        }
                    }

                    // Self send the message
                    $recievers->add($client);
                }

                yield $this->sendMessageToGroup($client, $recievers, $room, $text, $type);

                $message = _('Message successfully sent !');
                $success = true;
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'sendMessage',
            'success' => $success,
            'text'    => $message
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
     * Send a message to a client
     *
     * @param      Client      $clientFrom  The client to send the message from
     * @param      Client      $clientTo    The client to send the message to
     * @param      Room        $room        The room
     * @param      string      $message     The text message
     * @param      string      $type        The message type ('public' || 'private')
     * @param      float       $date        The server timestamp at the moment the message was sent DEFAULT null
     * @param      bool        $indexed     If the messages is already indexed in ES DEFAULT false
     *
     * @return     \Generator
     */
    private function sendMessageToClient(
        Client $clientFrom,
        Client $clientTo,
        Room $room,
        string $message,
        string $type,
        float $date = null,
        bool $indexed = false
    ) {
        if ($clientFrom->getConnection()->getRemoteAddress() === '127.0.0.1') {
            $pseudonym = 'SERVER';
        } else {
            $pseudonym = $room->getClientPseudonym($clientFrom);
        }

        $date = ($date !== null ? $date : static::microtimeAsInt());

        yield $clientTo->getConnection()->send(json_encode([
            'service'   => $this->serviceName,
            'action'    => 'recieveMessage',
            'pseudonym' => $pseudonym,
            'date'      => $date,
            'roomId'    => $room->id,
            'type'      => $type,
            'message'   => $message
        ]));

        if (!$indexed) {
            // Insert elasticSearch record
            $clientsTo = new ClientCollection();
            $clientsTo->add($clientTo);

            yield $this->indexMessage($clientFrom, $clientsTo, $room, $message, $type, $date);
        }
    }

    /**
     * Send a message to a group of clients
     *
     * @param      Client            $clientFrom  The client to send the message from
     * @param      ClientCollection  $clientsTo   The clients to send the message to
     * @param      Room              $room        The room
     * @param      string            $message     The text message
     * @param      string            $type        The message type ('public' || 'private')
     * @param      string            $date        The server micro timestamp when the message was sent DEFAULT null
     *
     * @return     \Generator
     */
    private function sendMessageToGroup(
        Client $clientFrom,
        ClientCollection $clientsTo,
        Room $room,
        string $message,
        string $type,
        string $date = null
    ) {
        $date = ($date !== null ? $date : static::microtimeAsInt());

        foreach ($clientsTo as $clientTo) {
            yield $this->sendMessageToUser($clientFrom, $clientTo, $room, $message, $type, $date, true);
        }

        // Insert elasticSearch record
        yield $this->indexMessage($clientFrom, $clientsTo, $room, $message, $type, $date);
    }

    /**
     * Index a document in ES (a chat message)
     *
     * @param      Client            $clientFrom  The client to send the message from
     * @param      ClientCollection  $clientsTo   The client(s) to send the message
     * @param      Room              $room        The room
     * @param      string            $message     The text message
     * @param      string            $type        The message type ('public' || 'private')
     * @param      string            $date        The server micro timestamp at the moment the message was sent
     *
     * @return     \Generator
     */
    private function indexMessage(
        Client $clientFrom,
        ClientCollection $clientsTo,
        Room $room,
        string $message,
        string $type,
        string $date
    ) {
        if ($clientFrom->getConnection()->getRemoteAddress() !== '127.0.0.1') {
            foreach ($clientsTo as $clientTo) {
                $es = \Elasticsearch\ClientBuilder::create()->build();
                $params = [
                    'index' => $this->esIndex . '_write',
                    'type'  => 'message',
                    'body'  => [
                        'message'   => $message,
                        'type'      => $type,
                        'date'      => $date,
                        'room'      => $roomId,
                        'userFrom'  => [
                            'id'        => $clientFrom->isRegistered() ? $clientFrom->getUser()->id : -1,
                            'ip'        => $clientFrom->getConnection()->getRemoteAddress(),
                            'location'  => $clientFrom->getLocation(),
                            'pseudonym' => $room->getClientPseudonym($clientFrom)
                        ],
                        'userTo' => [
                            'id'        => $clientTo->isRegistered() ? $clientTo->getUser()->id : -1,
                            'ip'        => $clientTo->getConnection()->getRemoteAddress(),
                            'location'  => $clientTo->getLocation(),
                            'pseudonym' => $room->getClientPseudonym($clientTo)
                        ]
                    ]
                ];

                try {
                    $result = $es->index($params);
                } catch (\Exception $e) {
                    $this->logger->log(
                        LogLevel::ERROR,
                        sprintf('[chatService] Document not indexed in ES `%s` %s', $e, static::formatVariable($params))
                    );
                }
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

            $this->logger->log(
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

    /*=====  End of Helper methods  ======*/

    /*=====  End of Private methods  ======*/
}
