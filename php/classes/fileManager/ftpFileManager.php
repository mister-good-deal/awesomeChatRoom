<?php
/**
 * FTP protocol file manager implementation
 *
 * @package    Utility
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\fileManager;

use \interfaces\FileManagerInterface as FileManagerInterface;
use \classes\IniManager as Ini;

/**
 * FtpFileManager class to manipulate files with FTP protocol
 */
class FtpFileManager implements FileManagerInterface
{
    use \traits\EchoTrait;

    /**
     * @var        array  $params   Connection parameters
     */
    private $params;
    /**
     * @var        ressource  $ressource    The connection ressource
     */
    private $ressource;
    /**
     * @var        bool  $output    True if the output should be printed else false
     */
    private $verbose;

    /**
     * Constructor that loads connection paramaters
     *
     * @param      string[]  $parameters  OPTIONAL connection paramaters
     * @param      bool      $verbose     OPTIONAL true if output should be print, false if not and null will load the
     *                                    ini value
     */
    public function __construct(array $parameters = null, bool $verbose = null)
    {
        $this->params    = ($parameters !== null ? $parameters : Ini::getSectionParams('Deployment'));
        $this->verbose   =  (int) Ini::getParam('Deployment', 'verbose');
        $this->ressource = null;
    }

    /**
     * Destructor that calls close() method if a connection is established
     */
    public function __destruct()
    {
        if (is_resource($this->ressource)) {
            $this->close();
        }
    }

    /**
     * Connect to the server
     *
     * @throws     \Exception  If the connection fails
     */
    public function connect()
    {
        $this->ressource = @ftp_connect($this->params['url'], (int) $this->params['port']);

        if ($this->ressource === false) {
            throw new \Exception('Cannot connect to FTP ' . $this->params['url'] . ':' . $this->params['port']);
        }

        if ($this->verbose > 0) {
            static::ok('Successfuly connected to ' . $this->params['url'] . ':' . $this->params['port'] . PHP_EOL);
        }
    }

    /**
     * Login to the server
     *
     * @throws     \Exception  If the login fails
     */
    public function login()
    {
        if (@ftp_login($this->ressource, $this->params['login'], $this->params['password']) === false) {
            throw new \Exception('Login or password incorrect');
        }

        if ($this->verbose > 0) {
            static::ok('Successfuly login with the username ' . $this->params['login'] . PHP_EOL);
        }
    }

    /**
     * Change the current directory to the one passed in parameter
     *
     * @param      string     $path   The new working directory path
     *
     * @throws     \Exception  If the change directory fails
     */
    public function changeDir(string $path)
    {
        if (@ftp_chdir($this->ressource, $path) === false) {
            throw new \Exception('wrong path, cannot access to "' . ftp_pwd($this->ressource) . '/' . $path . '"');
        }

        if ($this->verbose > 1) {
            static::out('Current directory is now "' . ftp_pwd($this->ressource) . '"' . PHP_EOL);
        }
    }

    /**
     * Create a new directory in the current working directory
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     \Exception  If the creation of the new directory fails
     */
    public function makeDir(string $dirName)
    {
        if (@ftp_mkdir($this->ressource, $dirName) === false) {
            throw new \Exception('Fail to create directory <' . $dirName . '> in "' . ftp_pwd($this->ressource) . '"');
        }

        if ($this->verbose > 0) {
            static::ok(
                'New directory <' . $dirName . '> successfuly created in "' . ftp_pwd($this->ressource) . '"' . PHP_EOL
            );
        }
    }

    /**
     * Create a new directory in the current working directory if this directory does not exists
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     \Exception  If the creation of the new directory fails
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
        ftp_pasv($this->ressource, true);

        $list = @ftp_nlist($this->ressource, ".");

        if ($list === false) {
            throw new \Exception('Cannot list files from "' . ftp_pwd($this->ressource) . '"');
        }

        return $list;
    }

    /**
     * Upload a file on the server
     *
     * @param      string     $remoteFilePath  The remote file path on the server
     * @param      string     $localFilePath   The local file path
     *
     * @throws     \Exception  If the upload fails
     */
    public function upload(string $remoteFilePath, string $localFilePath)
    {
        ftp_pasv($this->ressource, true);

        $fileName = basename($remoteFilePath);
        $pathFile = dirname($remoteFilePath);

        if ($pathFile !== '') {
            $this->changeDir($pathFile);
        }

        if (is_resource($localFilePath)) {
            if (!@ftp_fput($this->ressource, $fileName, $localFilePath, FTP_ASCII)) {
                throw new \Exception('Fail to upload "' . $fileName);
            }
        } else {
            if (!@ftp_put($this->ressource, $fileName, $localFilePath, FTP_BINARY)) {
                throw new \Exception('Fail to upload "' . $fileName);
            }
        }

        if ($this->verbose > 0) {
            static::ok('File "' . $localFilePath . '" successfuly uploaded'. PHP_EOL);
        }
    }

    /**
     * Set a new permission on a directory / file
     *
     * @param      int        $value  The octal permission value (ex: 0644)
     * @param      string     $path   The path to the directory / file
     *
     * @throws     \Exception  If the permission changed fails
     */
    public function chmod(int $value, string $path)
    {
        if (@ftp_chmod($this->ressource, $value, $path) === false) {
            throw new \Exception('Fail to change rights on directory / file "'. $path . '"');
        }

        if ($this->verbose > 0) {
            static::ok('File "' . $path . '" has now the permission ' . $value . PHP_EOL);
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
        return ftp_mdtm($this->ressource, $path);
    }

    /**
     * Close the remote connection
     *
     * @return     bool  True on success else false
     */
    public function close(): bool
    {
        $success         = @ftp_close($this->ressource);
        $this->ressource = null;

        if ($this->verbose > 0) {
            if ($success) {
                static::ok('Connection closed successfuly' . PHP_EOL);
            } else {
                static::fail('Fail to close the connection' . PHP_EOL);
            }
        }

        return $success;
    }
}
