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
use \classes\IniManager as Ini;

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
        'protocol [-p protocol] [--list|set]'               => 'Get all the available deployment protocols or get/set the protocol',
        'deploy [--website|websocket]'                      => 'Deploy the website or the websocket server or both (DEFAULT)',
        'configuration [-p param -v value] [--print|save]'  => 'Display or set deployment parameter (--save to save it in conf.ini'
    );
    /**
     * @var string[] $PROTOCOLS List of available protocol
     */
    private static $PROTOCOLS = array(
        'FTP'
    );

    /**
     * @var string[] $deploymentConfiguration All the deployment configuration
     */
    private $deploymentConfiguration = array();

    /**
     * Call the parent constructor and merge the commands list
     */
    public function __construct()
    {
        parent::__construct();
        parent::$COMMANDS = array_merge(parent::$COMMANDS, static::$SELF_COMMANDS);

        $this->deploymentConfiguration = Ini::getSectionParams('Deployment');
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

            case 'deploy':
                $this->deployProcess($command);
                break;

            case 'configuration':
                $this->configurationProcess($command);
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
        } elseif (isset($args['set'])) {
            if (in_array($args['p'], static::$PROTOCOLS)) {
                $this->deploymentConfiguration['protocol'] = $args['p'];
                static::out('Protocol is now "' . $args['p'] . '"' . PHP_EOL);
            } else {
                static::out('Protocol "' . $args['p'] . '" is not supported' . PHP_EOL);
            }
        } else {
            static::out('The current protocol is "' . $this->deploymentConfiguration['protocol'] . '"' . PHP_EOL);
        }
    }

    /**
     * Launch the deployement of the website or teh websocket server or both
     *
     * @param string $command The command passed with its arguments
     */
    private function deployProcess($command)
    {
        $args = $this->getArgs($command);

        if (isset($args['website'])) {
            $this->deployWebSite();
        } elseif (isset($args['websocket'])) {
            $this->deployWebsocketServer();
        } else {
            $this->deployWebSite();
            $this->deployWebsocketServer();
        }
    }

    /**
     * Diplay or set deployment configuraton parameters
     *
     * @param string $command The command passed with its arguments
     */
    private function configurationProcess($command)
    {
        $args = $this->getArgs($command);

        if (isset($args['print'])) {
            if (isset($args['p'])) {
                $this->setProtocol($args['p']);
            } else {
                $this->printDeploymentInformation();
            }
        } else {
            if (isset($args['p']) && isset($args['v'])) {
                if (array_key_exists($args['p'], $this->deploymentConfiguration)) {
                    if ($args['p'] === 'protocol') {
                        $this->setProtocol($args['v']);
                    } else {
                        $this->deploymentConfiguration[$args['p']] = $args['v'];
                        static::out($args['p'] . ' = ' . $args['v'] . PHP_EOL);
                    }

                    if (isset($args['save'])) {
                        Ini::setParam('Deployment', $args['p'], $args['v']);
                    }
                } else {
                    static::out('The parameter "' . $args['p'] . '" does not exist' . PHP_EOL);
                }
            } else {
                static::out('You must specify parameters p and v with -p parameter and -v value' . PHP_EOL);
            }
        }
    }

    private function deployWebSite()
    {
        $this->printDeploymentInformation();
    }

    private function deployWebsocketServer()
    {
        $this->printDeploymentInformation();
    }

    /**
     * Output the deployment configuration
     */
    private function printDeploymentInformation()
    {
        static::out($this->tableAssociativPrettyPrint($this->deploymentConfiguration) . PHP_EOL);
    }

    /**
     * Set the protocol
     *
     * @param string $value The protocol to set
     */
    private function setProtocol($value)
    {
        if (in_array($value, static::$PROTOCOLS)) {
            $this->deploymentConfiguration['protocol'] = $value;
            static::out('Protocol is now "' . $value . '"' . PHP_EOL);
        } else {
            static::out(
                'Protocol "' . $value . '" is not supported, type protocol --list to see supported protocols' . PHP_EOL
            );
        }
    }
}
