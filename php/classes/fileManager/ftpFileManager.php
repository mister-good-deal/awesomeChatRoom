<?php
/**
 * FTP protocol file manager implementation
 *
 * @category Utility
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\fileManager;

use \interfaces\FileManagerInterface as FileManagerInterface;
use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;

/**
 * FtpFileManager class to manipulate files with FTP protocol
 */
class FtpFileManager implements FileManagerInterface
{
    use \traits\EchoTrait;

    /**
     * @var array $params Connection parameters
     */
    private $params;
    /**
     * @var ressource $ressource The connection ressource
     */
    private $ressource;
    /**
     * @var boolean $output True if the output should be printed else false
     */
    private $verbose;

    /**
     * Constructor that loads connection paramaters
     *
     * @param string[] $parameters OPTIONAL connection paramaters
     * @param boolean  $verbose OPTIONAL true if output should be print, false if not and null will load the ini value
     */
    public function __construct($parameters = null, $verbose = null)
    {
        $this->params    = ($parameters !== null ? $parameters : Ini::getSectionParams('Deployment'));
        $this->verbose   =  Ini::getParam('Deployment', 'verbose');
        $this->ressource = null;
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $this->ressource = ftp_connect($this->params['url'], $this->params['port']);

        if ($this->ressource === false) {
            throw new Exception(
                'Cannot connect to FTP "' . $this->params['url'] . ':' . $this->params['port'] . '"',
                Exception::$ERROR
            );
        }

        if ($this->verbose) {
            static::out('Successfuly connected to ' . $this->params['url'] . ':' . $this->params['port']) . PHP_EOL;
        }
    }

    /**
     * @inheritDoc
     */
    public function login()
    {
        if (ftp_login($this->ressource, $this->params['login'], $this->params['password']) === false) {
            throw new Exception('Login or password incorrect', Exception::$ERROR);
        }

        if ($this->verbose) {
            static::out('Successfuly login with the username ' . $this->params['login'] . PHP_EOL);
        }
    }

    /**
     * @inheritDoc
     */
    public function changeDir($path)
    {
        if (ftp_chdir($this->ressource, $path) === false) {
            throw new Exception('PATH incorrect, impossible to access to "' . $path . '"', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('Current directory is now "' . $path . '"' . PHP_EOL);
        }
    }

    /**
     * @inheritDoc
     */
    public function makeDir($dirName)
    {
        if (ftp_mkdir($this->ressource, $dirName) === false) {
            throw new Exception('Fail to create directory <' . $dirName . '>', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('New directory <' . $dirName . '> successfuly created' . PHP_EOL);
        }
    }

    /**
     * @inheritDoc
     */
    public function makeDirIfNotExists($dirName)
    {
        if (!in_array($dirName, $fileManager->listFiles())) {
            $fileManager->makeDir($dirName);
        }
    }

    /**
     * @inheritDoc
     */
    public function listFiles()
    {
        return ftp_nlist($this->ressource, ".");
    }

    /**
     * @inheritDoc
     */
    public function upload($remoteFilePath, $localFilePath)
    {
        ftp_pasv($this->ressource, true);

        $fileName = basename($remoteFilePath);
        $pathFile = dirname($remoteFilePath);

        if ($pathFile !== '') {
            $this->changeDir($pathFile);
        }

        if (is_resource($localFilePath)) {
            if (!ftp_fput($this->ressource, $fileName, $localFilePath, FTP_ASCII)) {
                throw new Exception('Fail to upload "' . $fileName . '" to "' . $pathFile . '"', Exception::$WARNING);
            }
        } else {
            if (!ftp_put($this->ressource, $fileName, $localFilePath, FTP_BINARY)) {
                throw new Exception('Fail to upload "' . $fileName . '" to "' . $pathFile . '"', Exception::$WARNING);
            }
        }

        if ($this->verbose) {
            static::out('File "' . $localFilePath . '" successfuly uploaded to "' . $remoteFilePath . '"' . PHP_EOL);
        }
    }

    /**
     * @inheritDoc
     */
    public function chmod($value, $path)
    {
        if (ftp_chmod($this->ressource, $value, $path) === false) {
            throw new Exception('Fail to change rights on directory / file "'. $path . '"', Exception::$WARNING);
        }

        if ($this->verbose) {
            static::out('File "' . $path . '" has now the permission ' . $value . PHP_EOL);
        }
    }

    /**
     * @inheritDoc
     */
    public function lastModified($path)
    {
        return ftp_mdtm($this->ressource, $path);
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        $success = ftp_close($this->ressource);

        if ($this->verbose) {
            if ($success) {
                static::out('Connection closed successfuly' . PHP_EOL);
            } else {
                static::out('Fail to close the connection' . PHP_EOL);
            }
        }

        return $success;
    }
}
