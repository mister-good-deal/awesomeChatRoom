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
    /**
     * @var array $params Connection parameters
     */
    private $params;
    /**
     * @var ressource $ressource The connection ressource
     */
    private $ressource;

    /**
     * Constructor that loads connection paramaters
     */
    public function __construct()
    {
        $this->params    = Ini::getSectionParams('Deployment');
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
    }

    /**
     * @inheritDoc
     */
    public function login()
    {
        if (ftp_login($this->ressource, $this->params['login'], $this->params['password']) === false) {
            throw new Exception('Login or password incorrect', Exception::$ERROR);
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
    }

    /**
     * @inheritDoc
     */
    public function makeDir($dirName)
    {
        if (ftp_mkdir($this->ressource, $dirName) === false) {
            throw new Exception('Fail to create directory <' . $dirName . '>', Exception::$WARNING);
        }
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
    }

    /**
     * @inheritDoc
     */
    public function chmod($value, $path)
    {
        if (ftp_chmod($this->ressource, $value, $path) === false) {
            throw new Exception('Fail to change rights on directory / file "'. $path . '"', Exception::$WARNING);
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
        return ftp_close($this->ressource);
    }
}
