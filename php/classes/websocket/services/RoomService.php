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
use classes\websocket\Room as Room;
use classes\websocket\RoomCollection as RoomCollection;
use classes\managers\ChatManager as ChatManager;
use classes\ExceptionManager as Exception;
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
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The rooms collection
     *
     * @return     \Generator
     */
    public function process(array $data, Client $client, RoomCollection $rooms)
    {
        switch ($data['action']) {
            case 'create':
                yield $this->create($client, $data, $rooms);

                break;

            case 'connect':
                yield $this->connect($client, $data);

                break;

            case 'disconnectFromRoom':
                yield $this->disconnectUserFromRoom($client, $data);

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
     * Create a room by an authenticated user request
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       to test
     */
    private function create(array $data, Client $client, RoomCollection $rooms)
    {
        $success = false;
        $message = _('An error occured');

        if (!$client->isRegistered()) {
            $message = _('Authentication failed');
        } else {
            $userManager = new UserManager($client->getUser());
            $chatManager = new ChatManager();
            // @todo createChatRoom() must throw error with message on failure
            $response    = $chatManager->createChatRoom(
                $client->getUser()->id,
                $data['roomName'] ?? '',
                $data['maxUsers'] ?? 0,
                $data['roomPassword'] ?? ''
            );

            if ($response['success']) {
                $room                  = new Room($chatManager->getChatRoomEntity());
                $userChatRight         = new UserChatRight();
                $userChatRight->idUser = $client->getUser()->id;
                $userChatRight->idRoom = $room->getRoom()->id;
                $response['success']   = $userManager->addUserGlobalChatRight($userChatRight, true);

                if ($response['success']) {
                    $room->addClient($client);
                    $rooms->add($room);
                    $success = true;
                    $message = sprintf(_('The chat room name "%s" is successfully created !'), $room->name);

                    yield $this->log->log(
                        Log::INFO,
                        _('[chatService] New room added by %s ' . PHP_EOL . '%s'),
                        $client,
                        $room
                    );
                }
            }
        }

        yield $client->getConnection()->send(json_encode(array_merge(
            [
                'service' => $this->serviceName,
                'action'  => 'create',
                'success' => $success,
                'text'    => $message,
                'room'    => $success ? $room->__toArray() : []
            ],
            $response
        )));
    }

    /**
     * Connect a user to one room as a registered or a guest user
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       To test
     */
    private function connect(array $data, Client $client, RoomCollection $rooms)
    {
        $success = false;

        if (!$rooms->isRoomExist((int) ($data['roomId'] ?? -1))) {
            $message = _('This room does not exist');
        } else {
            $room = $rooms->getObjectById((int) $data['roomId']);

            if ($room->isFull()) {
                $message = _('The room is full');
            } elseif (!$room->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif ($room->isClientBanned($client)) {
                // @todo add ban info ?
                $message = _('You are banned from this room');
            } else {
                // Insert the client in the room
                try {
                    $room->addClient($client);
                    $message = sprintf(_('You are connected to the room `%s`'), $room->getRoom()->name);
                    $success = true;
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client->getConnection()->send(json_encode(
            [
                'service' => $this->serviceName,
                'action'  => 'connect',
                'success' => $success,
                'text'    => $message,
                'room'    => $success ? $room->__toArray() : []
            ]
        ));
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
     * @todo To refacto
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
     * @todo To refacto
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
