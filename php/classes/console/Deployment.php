<?php
/**
 * Images manipulation utilities class
 *
 * @category Deployment
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

use \classes\console\Console as Console;
use \classes\fileManager\FtpFileManager as FtpFileManager;

/**
 * Deployment class to deploy the application on a server using several protocol
 */
class Deployment extends Console
{
    use \traits\EchoTrait;

    /**
     * @var string[] $SELF_COMMANDS List of all commands with their description
     */
    private static $SELF_COMMANDS = array(
        'protocol --list' => 'Get all the availables deployment protocol'
    );
    /**
     * @var string[] $PROTOCOLS List of available protocol
     */
    private static $PROTOCOLS = array(
        'FTP'
    );

    /**
     * Call the parent constructor and merge the commands list
     */
    public function __construct()
    {
        parent::__construct();
        parent::$COMMANDS = array_merge(parent::$COMMANDS, static::$SELF_COMMANDS);
    }

    /**
     * @inheritDoc
     */
    protected function processCommand($command, $executed = false)
    {
        $executed = true;

        preg_match('/^[a-zA-Z ]*/', $command, $commandName);

        static::out(PHP_EOL);

        switch (rtrim($commandName[0])) {
            case 'protocol':
                $this->protocolProcess($command);
                break;

            default:
                $executed = false;
                break;
        }

        parent::processCommand($command, $executed);
    }

    /**
     * Process the command called on the protocol
     *
     * @param string $command The command passed with its arguments
     */
    private function protocolProcess($command)
    {
        $args = $this->getArgs($command);

        if (isset($args['list'])) {
            static::out($this->tablePrettyPrint(static::$PROTOCOLS) . PHP_EOL);
        }
    }
}
