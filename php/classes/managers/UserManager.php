<?php
/**
 * Manager for the entities User, UserRight and RoomRight
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\managers;

use abstracts\Manager as Manager;
use classes\entities\User as User;
use classes\entities\Room as Room;
use classes\entitiesManager\UserEntityManager as UserEntityManager;
use classes\entitiesManager\UserRightEntityManager as UserRightEntityManager;
use classes\entitiesManager\RoomRightEntityManager as RoomRightEntityManager;
use classes\entitiesCollection\UserCollection as UserCollection;
use classes\LoggerManager as Logger;

/**
 * Perform action relative to the User, UserRight and RoomRight entities classes
 *
 * @todo refactoring like RoomManager...
 */
class UserManager extends Manager
{
    /**
     * @var        User  $user    A user entity to work with
     */
    private $user;
    /**
     * @var        UserCollection  $userCollection  A user collection to work with
     */
    private $userCollection;

    /*=====================================
    =            Magic Methods            =
    =====================================*/

    /**
     * Constructor that can take a User entity as first parameter and a Collection as second parameter
     *
     * @param      User            $user            A user entity object DEFAULT null
     * @param      UserCollection  $userCollection  A users collection object DEFAULT null
     */
    public function __construct(User $user = null, UserCollection $userCollection = null)
    {
        parent::__construct();

        $this->user           = $user;
        $this->userCollection = $userCollection;
    }

    /*=====  End of Magic Methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get the current user
     *
     * @return     User  The current user
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Get a user ID by his pseudonym
     *
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     int     The user id
     */
    public function getUserIdByPseudonym(string $pseudonym): int
    {
        $userEntityManager = new UserEntityManager();

        return $userEntityManager->getUserIdByPseudonym($pseudonym);
    }

    /**
     * Get a user pseudonym by his ID
     *
     * @param      int     $id     The user ID
     *
     * @return     string  The user pseudonym
     */
    public function getUserPseudonymById(int $id): string
    {
        $userEntityManager = new UserEntityManager();

        return $userEntityManager->getUserPseudonymById($id);
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        $userEntityManager = new UserEntityManager();

        return $userEntityManager->getPseudonymForChat();
    }

    /**
     * Register a user and return errors if errors occurred
     *
     * @param      array  $inputs  The user inputs in an array($columnName => $value) pairs to set the object
     *
     * @return     array  The occurred errors or success in a array
     */
    public function register(array $inputs): array
    {
        $userEntityManager = new UserEntityManager();

        return $userEntityManager->register($inputs);
    }

    /**
     * Connect a user with his login / password combination
     *
     * @param      string[]  $inputs  Inputs array containing array('login' => 'login', 'password' => 'password')
     *
     * @return     array  The occurred errors or success in a array
     */
    public function connect(array $inputs): array
    {
        $userEntityManager = new UserEntityManager();
        $response          = $userEntityManager->connect($inputs);

        if ($response['success']) {
            $this->user       = $userEntityManager->getEntity();
            $response['user'] = $this->user->__toArray();
            $_SESSION['user'] = serialize($this->user);
        }

        return $response;
    }

    /**
     * Send an email to the user
     *
     * @param      string      $subject  The email subject
     * @param      string      $content  The email content in HTML
     */
    public function sendEmail(string $subject, string $content)
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        try {
            $userEntityManager->sendEmail($subject, $content);
        } catch (\Exception $e) {
            (new Logger())->log($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Check if a user have the admin access to the WebSocket server
     *
     * @return     bool  True if the User has the right else false
     */
    public function hasWebSocketServerRight(): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() &&
            $this->user->getRight() !== null &&
            $this->user->getRight()->webSocket
        );
    }

    /**
     * Check if a user have the admin access to the WebSocket server
     *
     * @return     bool  True if the User has the right else false
     */
    public function hasKibanaRight(): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() &&
            $this->user->getRight() !== null &&
            $this->user->getRight()->kibana
        );
    }

    /**
     * Determine if the user has room kick right
     *
     * @param      Room  $room   The room to check in
     *
     * @return     bool  True if the user has room kick right, false otherwise.
     */
    public function hasRoomKickRight(Room $room): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() && (
                $this->user->getRight()->chatAdmin || (
                    $this->user->getRoomRight()->getEntityById($room->id) !== null &&
                    $this->user->getRoomRight()->getEntityById($room->id)->kick
                )
            )
        );
    }

    /**
     * Determine if the user has room ban right
     *
     * @param      Room  $room   The room to check in
     *
     * @return     bool  True if the user has room ban right, false otherwise
     */
    public function hasRoomBanRight(Room $room): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() && (
                $this->user->getRight()->chatAdmin || (
                    $this->user->getRoomRight()->getEntityById($room->id) !== null &&
                    $this->user->getRoomRight()->getEntityById($room->id)->ban
                )
            )
        );
    }

    /**
     * Determine if the user has room grant right
     *
     * @param      Room  $room   The room to check in
     *
     * @return     bool  True if the user has room grant right, false otherwise
     */
    public function hasRoomGrantRight(Room $room): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() && (
                $this->user->getRight()->chatAdmin || (
                    $this->user->getRoomRight()->getEntityById($room->id) !== null &&
                    $this->user->getRoomRight()->getEntityById($room->id)->grant
                )
            )
        );
    }

    /**
     * Determine if the user has room edit right
     *
     * @param      Room  $room   The room to check in
     *
     * @return     bool  True if the user has room edit right, false otherwise
     */
    public function hasRoomEditRight(Room $room): bool
    {
        $userEntityManager = new UserEntityManager($this->user, $this->userCollection);

        return (
            $userEntityManager->checkSecurityToken() && (
                $this->user->getRight()->chatAdmin || (
                    $this->user->getRoomRight()->getEntityById($room->id) !== null &&
                    $this->user->getRoomRight()->getEntityById($room->id)->edit
                )
            )
        );
    }

    /**
     * Save the current user collection
     *
     * @return     bool  True if the user collection has been saved, false otherwise
     */
    public function saveUserCollection(): bool
    {
        $userEntityManager      = new UserEntityManager();
        $userRightEntityManager = new UserRightEntityManager();
        $roomRightEntityManager = new RoomRightEntityManager();
        $success                = $userEntityManager->saveCollection($this->userCollection);

        if ($success) {
            foreach ($this->userCollection as $user) {
                if ($success && $user->getRight() !== null) {
                    $success = $userRightEntityManager->saveEntity($user->getRight());
                }

                if ($success && $user->getRoomRight() !== null) {
                    $success = $roomRightEntityManager->saveCollection($user->getRoomRight());
                }
            }
        }

        return $success;
    }

    /*=====  End of Public methods  ======*/
}
