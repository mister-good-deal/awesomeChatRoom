<?php
/**
 * Utility class to include web "static" files in the main index.php for the single page pattern
 *
 * @package    Web
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

use \classes\IniManager as Ini;

/**
 * Utility class to include web "static" files in the main index.php for the single page pattern
 */
class WebContentInclude
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor empty
     */
    public function __construct()
    {
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Include all the files in the passed directory
     *
     * @param      string  $directoryPath  The directory path
     */
    public static function includeDirectoryFiles(string $directoryPath)
    {
        foreach ((new \DirectoryIterator($directoryPath)) as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isFile()) {
                include $fileInfo->getPathname();
            }
        }
    }

    /**
     * Replace all the %vars% in a template by the values given in parameter
     *
     * @param      string  $template  The HTML template
     * @param      array   $vars      An array with ['varName' => 'varValue', ...]
     *
     * @return     string  The formated template
     * @todo       htmlentities fails to encode multiple backslashes \\
     */
    public static function formatTemplate(string $template, array $vars): string
    {
        foreach ($vars as $var => $value) {
            $template = preg_replace('/\%' . $var . '\%/m', htmlentities($value, ENT_QUOTES), $template);
        }

        // Epic lines of code XD
        ob_start();
        eval(' ?>' . $template . '<?php ');

        return ob_get_clean();
    }

    /**
     * Get the email template by its name
     *
     * @param      string      $templateName  The email template name
     *
     * @throws     \Exception  if the template is not found
     *
     * @return     string      The email template
     */
    public static function getEmailTemplate(string $templateName): string
    {
        $templatePath = dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . Ini::getParam('Web', 'emailsPath')
        . DIRECTORY_SEPARATOR . $templateName . '.php';

        if (stream_resolve_include_path($templatePath) === false) {
            throw new \Exception('"' . $templatePath . '" not find in email templates path');
        }

        return file_get_contents($templatePath);
    }

    /*=====  End of Public methods  ======*/
}
