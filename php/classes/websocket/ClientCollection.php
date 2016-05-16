<?php
/**
 * Client Collection
 *
 * @package    Collection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\websocket;

use abstracts\Collection as Collection;
use classes\ExceptionManager as Exception;

/**
 * A collection of Client that extends the Collection pattern
 *
 * @method Client current() {
 *      Returns the current client
 *
 *      @return Client The current client
 *}
 *
 * @method Client getObjectById($id) {
 *      Get a client by his ID
 *
 *      @return Client The client
 * }
 *
 * @method Client getObjectByIndex($index) {
 *      Get a client by its index
 *
 *      @return Client The client
 * }
 */
class ClientCollection extends Collection
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * constructor
     */
    public function __construct()
    {
    }

    /**
     * Client collection as an array
     *
     * @return     array  Client collection as an array
     */
    public function __toArray(): array
    {
        $clients = [];

        foreach ($this->collection as $client) {
            $clients[$client->getId()] = $client->__toArray();
        }

        return $clients;
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Add a client to the collection
     *
     * @param      Client  $client  The client
     * @param      null    $key     Not used parameter but need to be there because it is in the parent class
     *
     * @throws     Exception  If the Client is already in the collection
     */
    public function add($client, $key = null)
    {
        $id = $client->getId();

        if (array_key_exists($id, $this->indexId)) {
            throw new Exception(_('You are already in this room'), Exception::$WARNING);
        }

        $this->collection[] = $client;
        $this->indexId[$id] = $this->count() - 1;
    }

    /**
     * Remove a client from the collection
     *
     * @param      Client  $client  The client
     *
     * @throws     Exception  If the Client is not already in the collection
     */
    public function remove(Client $client)
    {
        $id = $client->getId();

        if (!array_key_exists($id, $this->indexId)) {
            throw new Exception(
                'This client' . $client . ' is not already in the collection ' . $this,
                Exception::$WARNING
            );
        }

        $index = $this->indexId[$id];
        unset($this->indexId[$id]);
        unset($this->collection[$index]);
    }

    /**
     * Determine if a client exists in the collection
     *
     * @param      Client  $client  The client to test
     *
     * @return     bool    True if the client exists in the collection, false otherwise
     */
    public function isClientExist(Client $client): bool
    {
        return array_key_exists($client->getId(), $this->indexId);
    }

    /*=====  End of Public methods  ======*/
}
