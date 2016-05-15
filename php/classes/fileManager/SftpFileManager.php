<?php
/**
 * SFTP protocol file manager implementation
 *
 * @package    Utility
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\fileManager;

use interfaces\FileManagerInterface as FileManagerInterface;
use classes\ExceptionManager as Exception;
use classes\IniManager as Ini;

/**
 * SftpFileManager class to manipulate files with SFTP protocol
 */
class SftpFileManager implements FileManagerInterface
{
    use \traits\EchoTrait;

    /**
     * @var        array  $params   Connection parameters
     */
    private $params;
    /**
     * @var        resource  $resource    The connection resource
     */
    private $resource;
    /**
     * @var        resource  $sftp  An SSH connection link identifier
     */
    private $sftp;
    /**
     * @var        bool  $output    True if the output should be printed else false
     */
    private $verbose;

    /**
     * Constructor that loads connection parameters
     *
     * @param      string[]  $parameters  OPTIONAL connection parameters
     * @param      bool      $verbose     OPTIONAL true if output should be print, false if not and null will load the ini value
     */
    public function __construct(array $parameters = null, bool $verbose = null)
    {
        $this->params    = ($parameters !== null ? $parameters : Ini::getSectionParams('Deployment'));
        $this->verbose   =  Ini::getParam('Deployment', 'verbose');
        $this->resource = null;
    }

    /**
     * Destructor that calls close() method if a connection is established
     */
    public function __destruct()
    {
        if (is_resource($this->resource) || is_resource($this->sftp)) {
            $this->close();
        }
    }

    /**
     * Connect to the server
     *
     * @throws     Exception  If the connection fails
     */
    public function connect()
    {
        $this->resource = ssh2_connect($this->params['url'], (int) $this->params['port']);

        if ($this->resource === false) {
            throw new Exception(
                'Cannot connect to SFTP "' . $this->params['url'] . ':' . $this->params['port'] . '"',
                Exception::$ERROR
            );
        }

        // ssh2_fingerprint($this->resource, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);

        if ($this->verbose) {
            static::out('Successfully connected to ' . $this->params['url'] . ':' . $this->params['port'] . PHP_EOL);
        }
    }

    /**
     * Login to the server
     *
     * @throws     Exception  If the login fails
     */
    public function login()
    {
        if (ssh2_auth_password($this->resource, $this->params['login'], $this->params['password']) === false) {
            throw new Exception('Login or password incorrect', Exception::$ERROR);
        }

        if ($this->verbose) {
            static::out('Successfully login with the username ' . $this->params['login'] . PHP_EOL);
        }

        $this->sftp = ssh2_sftp($this->resource);

        if ($this->sftp === false) {
            throw new Exception('Unable to create SFTP connection');
        }
    }

    /**
     * Change the current directory to the one passed in parameter
     *
     * @param      string     $path   The new working directory path
     *
     * @throws     Exception  If the change directory fails
     */
    public function changeDir(string $path)
    {
        if (ssh2_exec($this->resource, 'cd ' . $path) === false) {
            throw new Exception('PATH incorrect, impossible to access to "' . $path . '"', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('Current directory is now "' . $path . '"' . PHP_EOL);
        }
    }

    /**
     * Create a new directory in the current working directory
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     Exception  If the creation of the new directory fails
     */
    public function makeDir(string $dirName)
    {
        if (ssh2_exec($this->resource, 'mkdir ' . $dirName) === false) {
            throw new Exception('Fail to create directory <' . $dirName . '>', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('New directory <' . $dirName . '> successfully created' . PHP_EOL);
        }
    }

    /**
     * Create a new directory in the current working directory if this directory does not exists
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     Exception  If the creation of the new directory fails
     */
    public function makeDirIfNotExists(string $dirName)
    {
        if (!in_array($dirName, $this->listFiles())) {
            $this->makeDir($dirName);
        }
    }

    /**
     * Get the directories / files list on the current working directory
     *
     * @return     string[]  The list of directories / files contained in the current working directory
     */
    public function listFiles(): array
    {
        return scandir(
            'ssh2.sftp://' . $this->sftp . $this->params['remoteProjectRootDirectory'] . '/'
            . $this->params['remoteProjectRootDirectoryName']
        );
    }

    /**
     * Upload a file on the server
     *
     * @param      string     $remoteFilePath  The remote file path on the server
     * @param      string     $localFilePath   The local file path
     *
     * @throws     Exception  If the upload fails
     */
    public function upload(string $remoteFilePath, string $localFilePath)
    {
        ftp_pasv($this->resource, true);

        $fileName = basename($remoteFilePath);
        $pathFile = dirname($remoteFilePath);

        if ($pathFile !== '') {
            $this->changeDir($pathFile);
        }

        if (!ssh2_scp_send($this->sftp, $localFilePath, $fileName)) {
            throw new Exception('Fail to upload "' . $fileName . '" to "' . $pathFile . '"', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('File "' . $localFilePath . '" successfully uploaded to "' . $remoteFilePath . '"' . PHP_EOL);
        }
    }

    /**
     * Set a new permission on a directory / file
     *
     * @param      int        $value  The octal permission value (ex: 0644)
     * @param      string     $path   The path to the directory / file
     *
     * @throws     Exception  If the permission changed fails
     */
    public function chmod(int $value, string $path)
    {
        if (ssh2_exec($this->sftp, 'chmod ' . $value . ' ' . $path ) === false) {
            throw new Exception('Fail to change rights on directory / file "'. $path . '"', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('File "' . $path . '" has now the permission ' . $value . PHP_EOL);
        }
    }

    /**
     * Return the last modified file time as an UNIX timestamp
     *
     * @param      string  $path   The path to the directory / file
     *
     * @return     int     The last modified time as an UNIX timestamp
     */
    public function lastModified(string $path): int
    {
        return ssh2_sftp_stat($this->sftp, $path)['mtime'];
    }

    /**
     * Close the remote connection
     *
     * @return     bool  True on success else false
     */
    public function close(): bool
    {
        $success         = ssh2_exec($this->sftp, 'exit');
        $this->sftp      = null;
        $this->resource = null;

        if ($this->verbose) {
            if ($success) {
                static::out('Connection closed successfully' . PHP_EOL);
            } else {
                static::out('Fail to close the connection' . PHP_EOL);
            }
        }

        return $success;
    }
}
