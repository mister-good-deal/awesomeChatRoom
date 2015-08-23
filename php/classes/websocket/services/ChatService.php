<?php

namespace classes\websocket\services;

use \classes\websocket\Server as Server;
use \interfaces\ServiceInterface as Service;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;

class ChatService extends Server implements Service
{
    private $users           = array();
    private $usersRegistered = array();
    private $usersGuest      = array();
    private $pseudonyms      = array();

    public function __construct()
    {
    }

    /**
     * Method to recieves data from the WebSocket server
     *
     * @param  resource $socket The client socket
     * @param  array    $data   JSON decoded client data
     */
    public function service($socket, $data)
    {
        switch ($data['action']) {
            case 'chat':
                $this->log(_('[chatService] somone says: "' . $data['message'] . '"'));

                break;

            case 'connect':
                $this->connectUser($socket, $data);

                break;

            case 'disconnect':
                $this->disconnect($socket);
                
                break;

            default:
                $this->send($socket, $this->encode('Unknown action'));
        }
    }

    /**
     * Connect a user to the chat as a registered or a guest user
     *
     * @param resource $socket The user socket
     * @param array    $data   JSON decoded client data
     */
    private function connectUser($socket, $data)
    {
        $userName  = $this->getClientName($socket);
        $pseudonym = '';
        $success   = false;
        $errors    = array();

        if (!in_array($userName, $this->users)) {
            if (isset($data['user'])) {
                $userEntityManager = new UserEntityManager();
                $user              = $userEntityManager->authenticateUser(
                    $data['user']['email'],
                    $data['user']['password']
                );

                if ($user !== false) {
                    $this->usersRegistered[$userName] = $user;
                    $success                          = true;

                    if ($user->pseudonym !== null) {
                        $pseudonym = $user->pseudonym;
                    } else {
                        $pseudonym = $user->firstName . ' ' . $user->lastName;
                    }
                } else {
                    $errors[] = _('Authentication failed');
                }
            } elseif (isset($data['pseudonym'])) {
                if ($this->isPseudonymUnique($data['pseudonym'])) {
                    $this->usersGuest[$userName] = $data['pseudonym'];
                    $pseudonym                   = $data['pseudonym'];
                    $success                     = true;
                } else {
                    $errors[] = _('The pseudonym "' . $data['pseudonym'] . '" already exists');
                }
            } else {
                $errors[] = _('Error');
            }

            if ($success) {
                $this->users[$userName] = $socket;
                $this->pseudonyms[]     = $pseudonym;
                $this->log(_('[chatService] New user added with the pseudonym "' . $pseudonym . '"'));
            }
        }

        $this->send($socket, $this->encode(json_encode(array('success' => $success, 'errors' => $errors))));
    }

    /**
     * Check if a pseudonym is already used
     *
     * @param  string  $pseudonym The pseudonym to test
     * @return boolean            True is the pseudonym already exists else false
     */
    public function isPseudonymUnique($pseudonym)
    {
        return !in_array($pseudonym, $this->pseudonyms);
    }
}
