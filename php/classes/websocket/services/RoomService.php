<?php
/**
 * Room application to manage rooms with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use classes\IniManager as Ini;
use classes\websocket\Client as Client;
use classes\websocket\RoomCollection as RoomCollection;
use classes\managers\ChatManager as ChatManager;
use Icicle\WebSocket\Connection as Connection;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class RoomService
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
        $conf               = Ini::getSectionParams('Room service');
        $this->serviceName  = $conf['serviceName'];
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
            case 'connectRoom':
                yield $this->connectUser($client, $data);

                break;

            case 'disconnectFromRoom':
                yield $this->disconnectUserFromRoom($client, $data);

                break;

            case 'createRoom':
                yield $this->createRoom($client, $data);

                break;

            case 'updateRoomUserRight':
                yield $this->updateRoomUserRight($client, $data);

                break;

            case 'setRoomInfo':
                yield $this->setRoomInfo($client, $data);

                break;

            case 'getAllRooms':
                yield $this->getAllRooms($client, $rooms);

                break;

            default:
                yield $client->getConnection()->send(json_encode([
                    'service' => $this->serviceName,
                    'success' => false,
                    'text'    => _('Unknown action')
                ]));
        }
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

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
                $response['success']   = $userManager->addUserGlobalChatRight($userChatRight, true);

                if ($response['success']) {
                    $this->rooms[$room->id] = [
                        'users' => [],
                        'room'  => $room
                    ];

                    yield $this->addUserToTheRoom(
                        $client,
                        $room,
                        $userManager->getPseudonymForChat(),
                        $data['location']
                    );

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
                'service' => $this->serviceName,
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
                'service'     => $this->serviceName,
                'action'      => 'connectRoom',
                'success'     => false,
                'text'        => $message ?? ''
            ],
            $response
        )));
    }

    /**
     * Get the rooms basic information (name, type, usersMax, usersConnected)
     *
     * @param      Client          $client  The client
     * @param      RoomCollection  $rooms   The rooms
     *
     * @return     \Generator
     */
    private function getAllRooms(Client $client, RoomCollection $rooms)
    {
        $chatManager = new ChatManager();
        $roomsInfo   = [];

        foreach ($chatManager->getAllRooms() as $room) {
            $roomsInfo[] = [
                'room'           => $room->__toArray(),
                'usersConnected' => $rooms->getObjectById($room->id) !== null ?
                    count($rooms->getObjectById($room->id)->getClients()) :
                    0
            ];
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'getAllRooms',
            'rooms'   => $roomsInfo
        ]));
    }

    /**
     * Change a room name / password
     *
     * @param      array  $client  The client information [Connection, User] array pair
     * @param      array  $data    JSON decoded client data
     *
     * @return     \Generator
     * @todo       to test
     */
    private function setRoomInfo(array $client, array $data)
    {
        $success     = false;
        $message     = _('Room information updated');
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
            } elseif (!$userManager->hasChatEditRight((int) $room->id)) {
                $message = _('You do not have the right to edit the room\'s information');
            } else {
                try {
                    foreach ($data['roomInfo'] as $attribute => $value) {
                        $room->{$attribute} = $value;
                    }

                    $success = $chatManager->saveChatRoom();

                    // Update the room's information for all users in the room
                    if ($success) {
                        yield $this->updateRoomInformation($room);
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'changeRoomInfo',
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
     * @return     \Generator
     * @todo       to test
     */
    private function updateRoomUserRight(array $client, array $data)
    {
        $success     = false;
        $message     = _('User right updated');
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
            } elseif (!$userManager->hasChatGrantRight((int) $room->id)) {
                $message = _('You do not have the right to grant a user right in this room');
            } else {
                try {
                    $user      = $this->getUserByRoomByPseudonym((int) $room->id, $data['pseudonym']);
                    $chatRight = $user->getChatRight()->getEntityById($room->id);
                    $userManager->setUser($user);

                    if ($chatRight === null) {
                        $chatRight = new UserChatRight([
                            'idUser' => $user->id,
                            'idRoom' => $room->id
                        ]);
                    }

                    $chatRight->{$data['rightName']} = (bool) $data['rightValue'];
                    $success                         = $userManager->setUserChatRight($chatRight);

                    // Update the users right for users who have access to the admin board EG registered users
                    if ($success) {
                        yield $this->updateRoomUsersRights((int) $room->id);
                    } else {
                        $message = _('The right update failed');
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client['Connection']->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'updateRoomUserRight',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /*=====  End of Private methods  ======*/
}
