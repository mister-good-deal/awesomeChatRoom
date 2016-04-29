<?php
/**
 * Entity manager for he entity User
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\User as User;
use \classes\entitiesCollection\UserCollection as UserCollection;
use \classes\DataBase as DB;
use \classes\IniManager as Ini;
use \classes\LoggerManager as Logger;
use \classes\logger\LogLevel as LogLevel;
use \classes\WebContentInclude as WebContentInclude;

/**
 * Performed database action relative to the User entity class
 *
 * @property   User             $entity             The user entity
 * @property   UserCollection   $entityCollection   The UserCollection collection
 *
 * @method User getEntity() {
 *      Get the user entity
 *
 *      @return User The user entity
 * }
 *
 * @method UserCollection getEntityCollection() {
 *      Get the user collection
 *
 *      @return UserCollection The user collection
 * }
 */
class UserEntityManager extends EntityManager
{
    use \traits\FiltersTrait;

    /**
     * @var        array  $params   An array containing User params in conf.ini
     */
    private $params;
    /**
     * @var        array  $errors   An array containing the occured errors when fields are set
     */
    private $errors = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that can take a User entity as first parameter and a UserCollection as second parameter
     *
     * @param      User            $entity            A user entity object DEFAULT null
     * @param      UserCollection  $entityCollection  A users collection oject DEFAULT null
     */
    public function __construct(User $entity = null, UserCollection $entityCollection = null)
    {
        parent::__construct($entity, $entityCollection);

        if ($entity === null) {
            $this->entity = new User();
        }

        Ini::setIniFileName('conf.ini');
        $this->params = Ini::getSectionParams('User');
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get a user id by his pseudonym
     *
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     int     The user id
     */
    public function getUserIdByPseudonym(string $pseudonym): int
    {
        $sqlMarks = 'SELECT id FROM %s WHERE pseudonym = %s';
        $sql      = static::sqlFormater($sqlMarks, $this->entity->getTableName(), DB::quote($pseudonym));

        return (int) DB::query($sql)->fetchColumn();
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
        $sqlMarks = 'SELECT pseudonym, firstName, lastName FROM %s WHERE id = %d';
        $sql      = static::sqlFormater($sqlMarks, $this->entity->getTableName(), $id);
        $result   = DB::query($sql)->fetch();

        return (
            $result['pseudonym'] !== null ? $result['pseudonym'] : $result['firstName'] . ' ' . $result['lastName']
        );
    }

    /**
     * Register a user and return errors if errors occured
     *
     * @param      array  $inputs  The user inputs in an array($columnName => $value) pairs to set the object
     *
     * @return     array  The occured errors or success in a array
     */
    public function register(array $inputs): array
    {
        $success = false;
        $errors  = $this->checkMustDefinedField(array_keys($inputs));

        try {
            if (count($errors['SERVER']) === 0) {
                $query            = 'SELECT MAX(id) FROM ' . $this->entity->getTableName();
                $this->entity->id = DB::query($query)->fetchColumn() + 1;

                $this->bindInputs($inputs);
                $errors = $this->errors;

                if (count($errors) === 0) {
                    $this->sendEmail(_('[awesomeChatRoom] Account created'), WebContentInclude::formatTemplate(
                        WebContentInclude::getEmailTemplate('register'),
                        [
                            'firstName' => $this->entity->firstName,
                            'lastName'  => $this->entity->lastName,
                            'password'  => $inputs['password']
                        ]
                    ));

                    $success = $this->saveEntity();
                }
            }
        } catch (\Exception $e) {
            $errors['SERVER'] = _('Confirmation email failed to be sent by the server');
            (new Logger())->log($e->getCode(), $e->getMessage());
        } finally {
            return array('success' => $success, 'errors' => $errors, 'user' => $this->entity->__toArray());
        }
    }

    /**
     * Connect a user with his login / password combinaison
     *
     * @param      string[]  $inputs  Inputs array containing array('login' => 'login', 'password' => 'password')
     *
     * @return     array  The occured errors or success in a array
     * @todo       refacto make it shorter...
     */
    public function connect(array $inputs): array
    {
        $errors   = array();
        $success  = false;
        $login    = trim($inputs['login'] ?? '');
        $password = $inputs['password'] ?? '';

        if ($login === '') {
            $errors['login'] = _('Login can\'t be empty');
        } else {
            $login = DB::quote($login);
        }

        if ($password === '') {
            $errors['password'] = _('Password can\'t be empty');
        }

        if (count($errors) === 0) {
            $sqlMarks   = 'SELECT * FROM %s WHERE email = %s OR pseudonym = %s';
            $sql        = static::sqlFormater($sqlMarks, $this->entity->getTableName(), $login, $login);
            $userParams = DB::query($sql)->fetch();
            $now        = new \DateTime();
            $continue   = true;

            if ($userParams !== false) {
                $this->entity->setAttributes($userParams);

                if ((int) $this->entity->connectionAttempt === -1) {
                    $intervalInSec         = $this->dateIntervalToSec($now->diff($this->entity->lastConnectionAttempt));
                    $minInterval           = (int) $this->params['minTimeAttempt'];

                    if ($intervalInSec < $minInterval) {
                        $continue         = false;
                        $errors['SERVER'] = _(
                            'You have to wait ' . ($minInterval - $intervalInSec) . ' sec before trying to reconnect'
                        );
                    } else {
                        $this->entity->connectionAttempt = 1;
                    }
                } else {
                    $this->entity->connectionAttempt++;
                    $this->entity->ipAttempt             = $_SERVER['REMOTE_ADDR'];
                    $this->entity->lastConnectionAttempt = $now;
                }

                if ($this->entity->ipAttempt === $_SERVER['REMOTE_ADDR']) {
                    if ($this->entity->connectionAttempt === (int) $this->params['maxFailConnectAttempt']) {
                        $this->entity->connectionAttempt = -1;
                    }
                } else {
                    $this->entity->connectionAttempt = 1;
                    $this->entity->ipAttempt         = $_SERVER['REMOTE_ADDR'];
                }

                if ($continue) {
                    // Connection success
                    if (hash_equals($userParams['password'], crypt($password, $userParams['password']))) {
                        $success                            = true;
                        $this->entity->lastConnection       = $now;
                        $this->entity->connectionAttempt    = 0;
                        $this->entity->ip                   = $_SERVER['REMOTE_ADDR'];
                        $this->entity->securityToken        = bin2hex(random_bytes($this->params['securityTokenLength']));
                        $this->entity->securityTokenExpires = $now->add(
                            new \DateInterval('PT' . $this->params['securityTokenDuration'] . 'S')
                        );
                    } else {
                        $errors['password'] = _('Incorrect password');
                    }
                }

                $this->saveEntity();
            } else {
                $errors['login'] = _('This login does not exist');
            }
        }

        return array('success' => $success, 'errors' => $errors);
    }

    /**
     * Send an email to the user
     *
     * @param      string      $subject  The email subject
     * @param      string      $content  The email content in HTML
     *
     * @throws     \Exception  If the email failed to be sent
     */
    public function sendEmail(string $subject, string $content)
    {
        $mailParams = Ini::getSectionParams('Email');
        $mail       = new \PHPMailer();

        $mail->isSMTP();
        $mail->SMTPDebug  = $mailParams['debugMode'];
        $mail->Host       = $mailParams['smtpHost'];
        $mail->SMTPAuth   = (bool) $mailParams['smtpAuth'];
        $mail->Username   = $mailParams['smtpUserName'];
        $mail->Password   = $mailParams['smtpPassword'];
        $mail->SMTPSecure = $mailParams['smtpSecure'];
        $mail->Port       = $mailParams['port'];
        $mail->Subject    = $subject;

        $mail->setFrom($mailParams['fromEmail'], $mailParams['fromAlias']);
        $mail->addAddress($this->entity->email, $this->entity->firstName . ' ' . $this->entity->lastName);
        $mail->addReplyTo($mailParams['replyToEmail'], $mailParams['replyToAlias']);
        $mail->isHTML((bool) $mailParams['isHtml']);
        $mail->msgHTML($content);

        if (!$mail->send()) {
            throw new \Exception($mail->ErrorInfo, LogLevel::ERROR);
        }
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        if ($this->entity->pseudonym !== '' && $this->entity->pseudonym !== null) {
            $pseudonym = $this->entity->pseudonym;
        } else {
            $pseudonym = $this->entity->firstName . ' ' . $this->entity->lastName;
        }

        return $pseudonym;
    }

    /**
     * Check the user security token
     *
     * @return     bool  True if the check is ok else false
     */
    public function checkSecurityToken(): bool
    {
        $sqlMarks = 'SELECT securityToken, securityTokenExpires FROM %s WHERE id = %d';
        $sql      = static::sqlFormater($sqlMarks, $this->entity->getTableName(), $this->entity->id);
        $results  = DB::query($sql)->fetch();

        return (
            $this->entity->securityToken === $results['securityToken'] &&
            new \DateTime() <= new \DateTime($results['securityTokenExpires'])
        );
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

     /**
     * Bind user inputs to set User class attributes with inputs check
     *
     * @param      array  $inputs  The user inputs
     */
    private function bindInputs(array $inputs)
    {
        foreach ($inputs as $inputName => &$inputValue) {
            $inputValue = $this->validateField($inputName, $inputValue);
        }

        $this->entity->setAttributes($inputs);
    }

    /**
     * Check and sanitize the input field before setting the value and keep errors trace
     *
     * @param      string     $columnName  The column name
     * @param      string     $value       The new column value
     *
     * @return     string  The sanitized value
     *
     * @todo Global validateField in EntityManager with generic checks (size) and custom to define in entity ?
     */
    private function validateField(string $columnName, string $value): string
    {
        if ($columnName !== 'password') {
            $value = trim($value);
        }

        $this->errors[$columnName] = array();
        $length                    = strlen($value);
        $maxLength                 = $this->entity->getColumnMaxSize($columnName);
        $name                      = _(strtolower(preg_replace('/([A-Z])/', ' $0', $columnName)));

        if (in_array($columnName, $this->entity::$mustDefinedFields) && $length === 0) {
            $this->errors[$columnName][] = _('The ' . $name . ' can\'t be empty');
        } elseif ($length > $maxLength) {
            $this->errors[$columnName][] = _('The ' . $name . ' size can\'t exceed ' . $maxLength . ' characters');
        }

        if ($this->entity->checkUniqueField($columnName, $value)) {
            $this->errors[$columnName][] = _('This ' . $name . ' is already used');
        }

        switch ($columnName) {
            case 'lastName':
                $value = ucwords(strtolower($value));
                $value = preg_replace('/ ( )*/', ' ', $value);

                break;

            case 'firstName':
                $value = ucfirst(strtolower($value));
                $value = preg_replace('/ ( )*(.)?/', '-' . strtoupper('$2'), $value);

                break;

            case 'pseudonym':
                if (in_array(strtolower($value), $this->entity::$pseudoBlackList)) {
                    $this->errors[$columnName][] = _('The pseudonym "' . $value . '" is not accepted');
                }

                foreach ($this->entity::$forbiddenPseudoCharacters as $forbiddenPseudoCharacter) {
                    if (strpos($value, $forbiddenPseudoCharacter) !== false) {
                        $this->errors[$columnName][] = _(
                            'The character "' . $forbiddenPseudoCharacter . '" is not accepted in pseudonyms'
                        );
                    }
                }

                if ($value === '') {
                    $value = null;
                }

                break;

            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->errors[$columnName][] = _('This is not a valid email address');
                }

                break;

            case 'password':
                Ini::setIniFileName(Ini::INI_CONF_FILE);
                $minPasswordLength = Ini::getParam('User', 'minPasswordLength');

                if ($length < $minPasswordLength) {
                    $this->errors[$columnName][] = _('The password length must be at least ' . $minPasswordLength);
                }

                $value = crypt($value, Ini::getParam('User', 'passwordCryptSalt'));

                break;
        }

        if (count($this->errors[$columnName]) === 0) {
            unset($this->errors[$columnName]);
        }

        return $value;
    }

    /**
     * Check if a pseudonym exists in the database
     *
     * @param      string  $pseudonym  The pseudonym
     *
     * @return     bool    True if the pseudonym exists else false
     */
    private function isPseudonymExist(string $pseudonym): bool
    {
        $sqlMarks = 'SELECT count(*) FROM %s WHERE pseudonym = %s';
        $sql      = static::sqlFormater($sqlMarks, $this->entity->getTableName(), DB::quote($pseudonym));

        return (int) DB::query($sql)->fetchColumn() > 0;
    }

    /**
     * Check all the must defined fields and fill an errors array if not
     *
     * @param      array  $fields  The fields to check
     *
     * @return     array  The errors array with any missing must defined fields
     */
    private function checkMustDefinedField(array $fields): array
    {
        $errors           = array();
        $errors['SERVER'] = array();

        foreach ($this->entity::$mustDefinedFields as $field) {
            if (!in_array($field, $fields)) {
                $errors['SERVER'][] = _($field . ' must be defined');
            }
        }

        return $errors;
    }

    /*=====  End of Private methods  ======*/
}
