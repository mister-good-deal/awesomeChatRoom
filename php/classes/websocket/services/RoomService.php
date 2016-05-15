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
use classes\ExceptionManager as Exception;
use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;

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
     * Method to receive data from the WebSocket server and process it
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

            case 'kick':
                yield $this->kick($data, $client, $rooms);

                break;

            case 'ban':
                yield $this->ban($data, $client, $rooms);

                break;

            case 'updateUserRight':
                yield $this->updateUserRight($data, $client, $rooms);

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

    /*============================================
    =            Direct called method            =
    ============================================*/

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
        $success     = false;
        $message     = _('An error occurred');
        $roomManager = new RoomManager(null, $rooms);

        if (!$client->isRegistered()) {
            $message = _('You must be registered to create a room');
        } else {
            try {
                $success = $roomManager->createRoom(
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

                    $this->logger->log(
                        LogLevel::INFO,
                        sprintf(
                            _('[chatService] New room added by %s ' . PHP_EOL . '%s'),
                            $client,
                            $roomManager->getRoom()
                        )
                    );
                }
            } else {
                $message = _('An error occurred during the room creation');
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'create',
            'success' => $success,
            'text'    => $message,
            'room'    => $success ? $roomManager->getRoom()->__toArray() : []
        ]));
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
     * @todo       To test
     */
    private function update(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $message     = _('Room information updated');
        $roomManager = new RoomManager(null, $rooms);
        $room        = null;

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int) $data['roomId']);
            $room = $roomManager->getRoom();

            if (!$client->isRegistered()) {
                $message = _('You are not registered so you cannot update the room information');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif (!$roomManager->hasEditRight($client)) {
                    $message = _('You do not have the right to edit the room\'s information');
            } else {
                try {
                    foreach ($data['roomInfo'] as $attribute => $value) {
                        $room->{$attribute} = $value;
                    }

                    $success = $roomManager->saveRoom();

                    // Update the room's information for all users in the room
                    if ($success) {
                        yield $this->updateRoom($room);
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
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
     */
    private function connect(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $pseudonym   = trim(($data['pseudonym'] ?? ''));
        $roomManager = new RoomManager(null, $rooms);
        $room        = null;

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int) $data['roomId']);
            $room = $roomManager->getRoom();

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
                    // Inform room's clients to add the new one
                    yield $this->addClientInRoom($room, $client);
                    $message = sprintf(_('You are connected to the room `%s`'), $room->name);
                    $success = true;
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service'    => $this->serviceName,
            'action'     => 'connect',
            'success'    => $success,
            'text'       => $message,
            'roomId'     => $data['roomId'],
            'clients'    => $room !== null ? $room->getClients()->__toArray() : [],
            'pseudonyms' => $room !== null ? $room->getPseudonyms() : []
        ]));
    }

    /**
     * Kick a user from a room
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       to test
     */
    private function kick(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $message     = _('User kicked');
        $roomManager = new RoomManager(null, $rooms);

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int)$data['roomId']);
            $room = $roomManager->getRoom();

            if (!$client->isRegistered()) {
                $message = _('You are not registered so you cannot kick a user from the room');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif (!$roomManager->hasKickRight($client)) {
                $message = _('You do not have the right to kick a user from this room');
            } else {
                $kickedClient = $room->getClients()->getObjectById($data['userId']);
                // Inform the user that he's kicked
                yield $kickedClient->getConnection()->send(json_encode([
                    'service'  => $this->serviceName,
                    'action'   => 'getKicked',
                    'roomId'   => $room->id,
                    'text'     => sprintf(
                        _('You got kicked from the room by `%s'),
                        $room->getClientPseudonym($client) . '`'
                        . (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                    )
                ]));
                // Kick the user
                $room->getClients()->remove($kickedClient);
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'kick',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /**
     * Ban a user from a room
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       to test
     */
    private function ban(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $message     = _('An error occurred');
        $roomManager = new RoomManager(null, $rooms);

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int)$data['roomId']);
            $room = $roomManager->getRoom();

            if (!$client->isRegistered()) {
                $message = _('You are not registered so you cannot kick a user from the room');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif (!$roomManager->hasBanRight($client)) {
                $message = _('You do not have the right to ban a user from this room');
            } else {
                $kickedClient = $room->getClients()->getObjectById($data['userId']);
                // Inform the user that he's banned
                yield $kickedClient->getConnection()->send(json_encode([
                    'service'  => $this->serviceName,
                    'action'   => 'getBanned',
                    'roomId'   => $room->id,
                    'text'     => sprintf(
                        _('You got banned from the room by `%s'),
                        $room->getClientPseudonym($client) . '`'
                        . (isset($data['reason']) ? _("\nReason: ") . $data['reason'] : '')
                    )
                ]));
                // Ban the user
                $success = $roomManager->banClient($kickedClient, $client, $data['reason'] ?? '');

                if ($success) {
                    $message = _('User banned');
                }
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'ban',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
        ]));
    }

    /**
     * Update a user right in a room
     *
     * @param      array           $data    JSON decoded client data
     * @param      Client          $client  The client calling the request
     * @param      RoomCollection  $rooms   The actives rooms
     *
     * @return     \Generator
     *
     * @todo       to test
     */
    private function updateUserRight(array $data, Client $client, RoomCollection $rooms)
    {
        $success     = false;
        $message     = _('User right updated');
        $roomManager = new RoomManager(null, $rooms);
        $room        = null;

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $roomManager->loadRoomFromCollection((int) $data['roomId']);
            $room = $roomManager->getRoom();

            if (!$client->isRegistered()) {
                $message = _('You are not registered so you cannot update the room information');
            } elseif (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
                $message = _('Room password is incorrect');
            } elseif (!$roomManager->hasGrantRight($client)) {
                $message = _('You do not have the right to grant a user right in this room');
            } elseif ($room->getClients()->getObjectById(($data['clientId'] ?? -1)) === null) {
                $message = _('This client does not exists in this room');
            } else {
                try {
                    $success = $roomManager->updateRoomRight(
                        $room->getClients()->getObjectById($data['clientId']),
                        $data['rightName'] ?? '',
                        (bool) $data['rightValue'] ?? false
                    );

                    if ($success) {
                        yield $this->changeUserRight($room, $room->getClients()->getObjectById($data['clientId']));
                    }
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'updateUserRight',
            'success' => $success,
            'text'    => $message
        ]));
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
                'room'             => $room->getRoomBasicAttributes(),
                'connectedClients' => count($room->getClients())
            ];
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'getAll',
            'rooms'   => $roomsInfo
        ]));
    }

    /*=====  End of Direct called method  ======*/

    /*=========================================
    =            Utilities methods            =
    =========================================*/

    /**
     * Inform room's clients to add the new one
     *
     * @param      Room        $room    The room to update the clients from
     * @param      Client      $client  The new client ot add
     *
     * @return     \Generator
     */
    private function addClientInRoom(Room $room, Client $client)
    {
        foreach ($room->getClients() as $roomClient) {
            if ($roomClient !== $client) {
                yield $roomClient->getConnection()->send(json_encode([
                    'service'   => $this->serviceName,
                    'action'    => 'addClientInRoom',
                    'roomId'    => $room->id,
                    'client'    => $client->__toArray(),
                    'pseudonym' => $room->getClientPseudonym($client)
                ]));
            }
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
                'roomId'          => $room->id,
                'roomInformation' => $room->__toArray()
            ]));
        }
    }

    /**
     * Change a user right in a room to display it in the admin panel
     *
     * @param      Room        $room    The room where the right has been updated
     * @param      Client      $client  The client who got a right changed
     *
     * @return     \Generator
     */
    public function changeUserRight(Room $room, Client $client)
    {
        $clientRight = $client->getUser()->getRoomRight()->getEntityById($room->id)->__toArray();

        foreach ($room->getClients() as $roomClient) {
            if ($roomClient->isRegistered()) {
                yield $roomClient->getConnection()->send(json_encode([
                    'service'      => $this->serviceName,
                    'action'       => 'updateRoom',
                    'roomId'       => $room->id,
                    'changedRight' => $clientRight
                ]));
            }
        }
    }

    /*=====  End of Utilities methods  ======*/

    /*=====  End of Private methods  ======*/
}
