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
 * implemented this interface
 */
interface FileManagerInterface
{
    /**
     * Connect to the server
     *
     * @throws     Exception  If the connection fails
     */
    public function connect();

    /**
     * Login to the server
     *
     * @throws     Exception  If the login fails
     */
    public function login();

    /**
     * Change the current directory to the one passed in parameter
     *
     * @param      string     $path   The new working directory path
     *
     * @throws     Exception  If the change directory fails
     */
    public function changeDir(string $path);

    /**
     * Create a new directory in the current working directory
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     Exception  If the creation of the new directory fails
     */
    public function makeDir(string $dirName);

    /**
     * Create a new directory in the current working directory if this directory does not exists
     *
     * @param      string     $dirName  The new directory name
     *
     * @throws     Exception  if the creation of the new directory fails
     */
    public function makeDirIfNotExists(string $dirName);

    /**
     * Get the directories / files list on the current working directory
     *
     * @return     string[]  The list of directories / files contained in the current working directory
     */
    public function listFiles(): array;

    /**
     * Upload a file on the server
     *
     * @param      string     $remoteFilePath  The remote file path on the server
     * @param      string     $localFilePath   The local file path
     *
     * @throws     Exception  if the upload fails
     */
    public function upload(string $remoteFilePath, string $localFilePath);

    /**
     * Set a new permission on a directory / file
     *
     * @param      int        $value  The octal permission value (ex: 0644)
     * @param      string     $path   The path to the directory / file
     *
     * @throws     Exception  if the permission changed fails
     */
    public function chmod(int $value, string $path);

    /**
     * Return the last modified file time as an UNIX timestamp
     *
     * @param      string  $path   The path to the directory / file
     *
     * @return     int     The last modified time as an UNIX timestamp
     */
    public function lastModified(string $path): int;

    /**
     * Close the remote connection
     *
     * @return     bool  True on success else false
     */
    public function close(): bool;
}
