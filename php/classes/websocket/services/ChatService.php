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
use classes\entitiesCollection\RoomCollection as RoomCollection;
use classes\entities\Room as Room;
use classes\managers\RoomManager as RoomManager;
use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;
use Elasticsearch\ClientBuilder as EsClientBuilder;
use traits\PrettyOutputTrait as PrettyOutputTrait;
use traits\FiltersTrait as FiltersTrait;
use traits\DateTrait as DateTrait;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class ChatService
{
    use PrettyOutputTrait;
    use FiltersTrait;
    use DateTrait;

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
     * Method to receives data from the WebSocket server and process it
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
                yield $this->getHistoric($data, $client);

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
        $text        = trim($data['message']) ?? '';
        $roomManager = new RoomManager(null, $rooms);
        $receivers   = new ClientCollection();
        $type        = 'public';
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
                if ($data['receivers'] === 'all') {
                    $receivers = $room->getClients();
                } else {
                    foreach (($data['receivers'] ?? []) as $clientId) {
                        $receiver = $room->getClients()->getObjectById($clientId);
                        $type     = 'private';

                        if ($receiver !== null) {
                            $receivers->add($receiver);
                        }
                    }

                    // Self send the message
                    $receivers->add($client);
                }

                yield $this->sendMessageToGroup($client, $receivers, $room, $text, $type);

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
     *
     * @return     \Generator
     *
     * @todo To test
     */
    private function getHistoric(array $data, Client $client)
    {
        $success     = false;
        $message     = _('Historic successfully loaded !');
        $roomManager = new RoomManager();

        if (!is_numeric(($data['roomId'] ?? null)) && !$roomManager->isRoomExist((int) $data['roomId'])) {
            $message = _('This room does not exist');
        } else {
            $room = $roomManager->getRoom();

            if (!$roomManager->isPasswordCorrect(($data['password'] ?? ''))) {
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
            'historic' => $historic ?? [],
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
            'action'    => 'receiveMessage',
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

            $this->indexMessage($clientFrom, $clientsTo, $room, $message, $type, $date);
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
            yield $this->sendMessageToClient($clientFrom, $clientTo, $room, $message, $type, $date, true);
        }

        // Insert elasticSearch record
        $this->indexMessage($clientFrom, $clientsTo, $room, $message, $type, $date);
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
                $es = EsClientBuilder::create()->build();
                $params = [
                    'index' => $this->esIndex . '_write',
                    'type'  => 'message',
                    'body'  => [
                        'message'   => $message,
                        'type'      => $type,
                        'date'      => $date,
                        'room'      => $room->id,
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
                    $es->index($params);
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
     * @param      string  $from    The maximum message published date in UNIX micro timestamp (string) DEFAULT null
     *
     * @return     array  The list of messages found
     */
    private function getRoomHistoric(int $roomId, Client $client, string $from = null)
    {
        $es     = EsClientBuilder::create()->build();
        $from   = $from ?? static::microtimeAsInt();
        $userIp = $client->getConnection()->getRemoteAddress();
        $userId = -1;

        if ($client->getUser() !== null) {
            $userId  = $client->getUser()->id;
        }

        return static::filterEsHitsByArray($es->search([
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
                                    // Must be a public message or private one to the user identifies by his ID or IP
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

    /*=====  End of Helper methods  ======*/

    /*=====  End of Private methods  ======*/
}
