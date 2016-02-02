<?php
/**
 * File manager interface
 *
 * @package    Interface
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */
namespace interfaces;

use \classes\ExceptionManager as Exception;

/**
 * FileManagerInterface defines a way to interact with files manipulation with basic functions to let several protocol
 * implented this interface
 */
interface FileManagerInterface
{
    /**
     * Connect to the server
     *
     * @throws     Exception  if the connection fails
     */
    public function connect();

    /**
     * Login to the server
     *
     * @throws     Exception  if the login fails
     */
    public function login();

    /**
     * Change the current directory to the one passed in parameter
     *
     * @param      string     $path   The new working directory path
     * @throws     Exception  if the change directory fails
     */
    public function changeDir($path);

    /**
     * Create a new directory in the current working directory
     *
     * @param      string     $dirName  The new directory name
     * @throws     Exception  if the creation of the new directory fails
     */
    public function makeDir($dirName);

    /**
     * Create a new directory in the current working directory if this directory does not exists
     *
     * @param      string     $dirName  The new directory name
     * @throws     Exception  if the creation of the new directory fails
     */
    public function makeDirIfNotExists($dirName);

    /**
     * Get the directories / files list on the current working directory
     *
     * @return     string[]  The list of directories / files conatained in the current working directory
     */
    public function listFiles();

    /**
     * Upload a file on the server
     *
     * @param      string     $remoteFilePath  The remote file path on the server
     * @param      string     $localFilePath   The local file path
     * @throws     Exception  if the upload fails
     */
    public function upload($remoteFilePath, $localFilePath);

    /**
     * Set a new permission on a directory / file
     *
     * @param      integer    $value  The octal permission value (ex: 0644)
     * @param      string     $path   The path to the directory / file
     * @throws     Exception  if the permission changed fails
     */
    public function chmod($value, $path);

    /**
     * Return the last modified file time as an UNIX timestamp
     *
     * @param      string   $path   The path to the directory / file
     * @return     integer  The last modified time as an UNIX timestamp
     */
    public function lastModified($path);

    /**
     * Close the remote connection
     *
     * @return     boolean  True on success else false
     */
    public function close();
}
