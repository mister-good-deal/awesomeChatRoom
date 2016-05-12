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
     *
     * @todo       to test
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
                } catch (Exception $e) {
                    $message = $e->getMessage();
                }
            }
        }

        yield $client->getConnection()->send(json_encode([
            'service' => $this->serviceName,
            'action'  => 'updateUserRight',
            'success' => $success,
            'text'    => $message,
            'roomId'  => $data['roomId']
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
                'roomId'          => $room->getId(),
                'roomInformation' => $room->getRoom()->__toArray()
            ]));
        }
    }

    /*=====  End of Utilities methods  ======*/

    /*=====  End of Private methods  ======*/
}
