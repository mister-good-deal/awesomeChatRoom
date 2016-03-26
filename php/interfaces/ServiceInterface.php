<?php
/**
 * Service interface to normalize WebSocket protocole services
 *
 * @package    Interface
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */
namespace interfaces;

use Icicle\WebSocket\Connection as Connection;

/**
 * Service interface to normalize WebSocket protocole services
 */
interface ServiceInterface
{
    /**
     * Method to recieves data from the WebSocket server
     *
     * @param      array  $data    JSON decoded client data
     * @param      array  $client  The client information [Connection, User] array pair
     */
    public function process(array $data, array &$client);
}
