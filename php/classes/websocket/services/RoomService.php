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
use classes\entities\Room as Room;
use classes\entitiesCollection\RoomCollection as RoomCollection;
use classes\managers\RoomManager as RoomManager;
use classes\managers\ChatManager as ChatManager;
use classes\managers\UserManager as UserManager;
use classes\ExceptionManager as Exception;
use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;
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
        $this->logger       = new Logger([Logger::CONSOLE]);
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
                yield $this->create($data, $client, $rooms);

                break;

            case 'update':
                yield $this->update($data, $client, $rooms);

                break;

            case 'connect':
                yield $this->connect($data, $client, $rooms);

                break;

            case 'disconnectFromRoom':
                yield $this->disconnectUserFromRoom($data, $client, $rooms);

                break;

            case 'updateRoomUserRight':
                yield $this->updateRoomUserRight($data, $client, $rooms);

                break;

            case 'getAll':
                yield $this->getAll($client, $rooms);

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
            $message = _('You must be registered to create a room');
        } else {
            $roomManager = new RoomManager(null, $rooms);

            try {
                $success = $roomManager->createChatRoom(
                    $client->getUser()->id,
                    $data['roomName'] ?? '',
                    $data['maxUsers'] ?? 0,
                    $data['roomPassword'] ?? ''
                );
            } catch (Exception $e) {
                $message = $e->getMessage();
            }

            if ($success) {
                $success = $roomManager->grantAllRoomRights($client);

                if ($success) {
                    $roomManager->addClient($client, $client->getUser()->pseudonym);
                    $success = true;
                    $message = sprintf(_('The room `%s` is successfully created !'), $roomManager->getRoom()->name);

                    yield $this->log->log(
                        Log::INFO,
                        _('[chatService] New room added by %s ' . PHP_EOL . '%s'),
                        $client,
                        $roomManager->getRoom()
                    );
                }
            } else {
                $message = _('An error occured during the room creation');
            }
        }

        yield $client->getConnection()->send(json_encode(
            [
                'service' => $this->serviceName,
                'action'  => 'create',
                'success' => $success,
                'text'    => $message,
                'room'    => $success ? $roomManager->getRoom()->__toArray() : []
            ]
        ));
    }

     /**
     * Update a room name / password
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       To refacto
     */
    private function update(array $data, Client $client, RoomCollection $rooms)
    {
        $success = false;
        $message = _('Room information updated');

        if (is_numeric($data['roomId']) && !$rooms->isRoomExist((int) ($data['roomId'] ?? -1))) {
            $message = _('This room does not exist');
        } else {
            $room = $rooms->getEntityById((int) $data['roomId']);

            if (!$client->isRegistered()) {
                $message = _('You are not registered so you cannot update the room information');
            } elseif (!$room->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } else {
                $userManager = new UserManager($client->getUser());

                if (!$userManager->hasChatEditRight($room->getId())) {
                    $message = _('You do not have the right to edit the room\'s information');
                } else {
                    try {
                        foreach ($data['roomInfo'] as $attribute => $value) {
                            $room->getRoom()->{$attribute} = $value;
                        }

                        $chatManager = new ChatManager($room->getRoom());
                        $success     = $chatManager->saveChatRoom();

                        // Update the room's information for all users in the room
                        if ($success) {
                            yield $this->updateRoom($room);
                        }
                    } catch (Exception $e) {
                        $message = $e->getMessage();
                    }
                }
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'changeRoomInfo',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
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
     * @todo       to test
     */
    private function connect(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $pseudonym   = trim(($data['pseudonym'] ?? ''));
        $roomManager = new RoomManager(null, $rooms);

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int) $data['roomId']);

            if ($roomManager->isFull()) {
                $message = _('The room is full');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif ($roomManager->isClientBanned($client)) {
                $message = _('You are banned from this room');
            } elseif ($pseudonym === '') {
                $message = _('Pseudonym must not be empty');
            } elseif ($roomManager->isPseudonymAlreadyUsed($pseudonym)) {
                $message = _('This pseudonym is already used in this room');
            } else {
                // Insert the client in the room
                try {
                    $roomManager->addClient($client, $pseudonym);
                    // Inform others clients
                    yield $this->updateClientsInRoom($roomManager->getRoom());
                    $message = sprintf(_('You are connected to the room `%s`'), $roomManager->getRoom()->name);
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
                'text'    => $message
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
    private function getAll(Client $client, RoomCollection $rooms)
    {
        $roomsInfo = [];

        foreach ($rooms as $room) {
            $roomsInfo[] = [
                'room'           => $room->getRoomBasicAttributes(),
                'usersConnected' => count($room->getClients())
            ];
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'getAll',
            'rooms'   => $roomsInfo
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

    /**
     * Update the clients list for all the client in the room
     *
     * @param      Room        $room   The room to update the clients from
     *
     * @return     \Generator
     */
    private function updateClientsInRoom(Room $room)
    {
        foreach ($room->getClients() as $client) {
            yield $client->getConnection()->send(json_encode([
                'service'    => $this->serviceName,
                'action'     => 'updateClients',
                'roomId'     => $room->id,
                'clients'    => $room->getClients()->__toArray(),
                'pseudonyms' => $room->getPseudonyms()
            ]));
        }
    }

    /**
     * Update the room information for all the client in the room
     *
     * @param      Room        $room   The room to update the clients from
     *
     * @return     \Generator
     */
    private function updateRoom(Room $room)
    {
        foreach ($room->getClients() as $client) {
            yield $client->getConnection()->send(json_encode([
                'service'         => $this->serviceName,
                'action'          => 'updateRoom',
                'roomId'          => $room->getId(),
                'roomInformation' => $room->getRoom()->__toArray()
            ]));
        }
    }

    /*=====  End of Private methods  ======*/
}
