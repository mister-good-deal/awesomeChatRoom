<?php
/**
 * Room application to manage rooms with a WebSocket server
 *
 * @category WebSocket
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket\services;

use \interfaces\ServiceInterface as Service;
use \classes\websocket\ServicesDispatcher as ServicesDispatcher;
use \classes\IniManager as Ini;
use Icicle\WebSocket\Connection as Connection;
use Icicle\Concurrent\Threading\Parcel as Parcel;
use Icicle\Log\Log as Log;

/**
 * Chat services to manage a chat with a WebSocket server
 */
class RoomService extends ServicesDispatcher implements Service
{
    /**
     * @var        string  $serviceName     The chat service name
     */
    private $serviceName;
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
        $conf               = Ini::getSectionParams('Room service');
        $this->serviceName  = $conf['serviceName'];
        $this->log          = $log;
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param      array   $data     JSON decoded client data
     * @param      array   $client   The client information [Connection, User]
     * @param      Parcel  $clients  The clients pool parcel shared between threads
     *
     * @return     \Generator
     */
    public function process(array $data, array $client, Parcel $clients)
    {

    }

    /*=====  End of Public methods  ======*/
}
