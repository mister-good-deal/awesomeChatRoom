<?php
/**
 * Trait to set beautiful indent on multiple array values
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utility methods to smart align values
 */
trait PrettyOutputTrait
{
    /**
     * @var        array  $beautifulIndentMaxSize  Array containing the max size of each array
     */
    public static $beautifulIndentMaxSize = array();

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Return the value with the exact number of right extra spaces to keep all the values align
     *
     * @param      string            $value      The value to print
     * @param      string[]|array[]  $arrays     The array of values to align the value with (can be an array of array)
     * @param      int               $extraSize  An extra size to add to the max value size of the category
     * @param      int               $position   The position to align as str_pad constant DEFAULT STR_PAD_RIGHT
     *
     * @return     string            The formatted value with extra spaces
     *
     * @todo refactoring this shit
     */
    public static function smartAlign(string $value, array $arrays, int $extraSize = 0, int $position = STR_PAD_RIGHT): string
    {
        // if The array passed is a simple strings array we transform it into an array of one strings array
        if (!is_array($arrays[0])) {
            $tempArray = $arrays;
            $arrays = array($tempArray);
        }

        $max = 0;

        foreach ($arrays as $array) {
            $arrayHash = static::md5Array($array);

            if (!isset(static::$beautifulIndentMaxSize[$arrayHash])) {
                static::setMaxSize($array, 0, $arrayHash);
            }

            $max += static::$beautifulIndentMaxSize[$arrayHash];
        }

        return str_pad($value, $max + $extraSize, ' ', $position);
    }

    /**
     * Format an array in a pretty indented output string
     *
     * @param      array   $array  The array to format
     * @param      int     $depth  OPTIONAL the array values depth DEFAULT 1
     *
     * @return     string  The array as a pretty string
     */
    public static function prettyArray(array $array, int $depth = 1): string
    {
        $arrayFormatted = array();
        $arrayIndent    = implode(array_fill(0, $depth - 1, "\t"));
        $valuesIndent   = implode(array_fill(0, $depth, "\t"));
        $keys           = array_keys($array);

        foreach ($array as $key => $value) {
            $alignKey         = $valuesIndent . static::smartAlign($key, $keys) . ' => ';
            $arrayFormatted[] = $alignKey . static::formatVariable($value, $depth + 1);
        }

        return '[' . PHP_EOL . implode(',' . PHP_EOL, $arrayFormatted) . PHP_EOL . $arrayIndent . ']';
    }

    /**
     * Format a two dimensional array in a pretty indented output string
     *
     * @param      array   $arrays  The 2D array to format
     *
     * @return     string  The array as a pretty string
     */
    public static function prettyTwoDimensionalArray(array $arrays): string
    {
        $maxLength      = static::getMaxSize($arrays);
        $separationLine = '+' . str_pad('', 2 * ($maxLength + 2) + 1, '-', STR_PAD_BOTH) . '+';
        $headers        = array_shift($arrays);
        $arrayFormatted = [$separationLine];

        $header = '| ';

        foreach ($headers as $value) {
            $header .= str_pad($value, $maxLength, ' ', STR_PAD_BOTH) . ' | ';
        }

        $arrayFormatted[] = $header;
        $arrayFormatted[] = $separationLine;

        foreach ($arrays as $array) {
            $line = '| ';

            foreach ($array as $value) {
                $line .= str_pad($value, $maxLength, ' ') . ' | ';
            }

            $arrayFormatted[] = $line;
        }

        $arrayFormatted[] = $separationLine;

        return implode(PHP_EOL, $arrayFormatted);
    }

    /**
     * Return the variable in a formatted string with type and value
     *
     * @param      mixed   $variable  The variable (can be any type)
     * @param      int     $depth     OPTIONAL the array values depth for the prettyArray method DEFAULT 1
     *
     * @return     string  The variable in a formatted string
     */
    public static function formatVariable($variable, int $depth = 1): string
    {
        switch (gettype($variable)) {
            case 'array':
                $argumentFormatted = static::prettyArray($variable, $depth);
                break;

            case 'object':
                $argumentFormatted = 'object(' . get_class($variable) . ')::' . PHP_EOL . $variable;
                break;

            case 'resource':
                $argumentFormatted = 'resource::' . get_resource_type($variable);
                break;

            case 'boolean':
                $argumentFormatted = $variable ? 'true' : 'false';
                break;

            case 'integer':
                $argumentFormatted = (int) $variable;
                break;

            case 'string':
                $argumentFormatted = '"' . $variable . '"';
                break;

            case 'NULL':
                $argumentFormatted = 'null';
                break;

            default:
                $argumentFormatted = $variable;
                break;
        }

        return $argumentFormatted;
    }

    /**
     * Get the max size of a array
     *
     * @param      array  $array  The array
     *
     * @return     int    The max size
     */
    public static function getMaxSize(array $array): int
    {
        $arrayHash = static::md5Array($array);

        if (!isset(static::$beautifulIndentMaxSize[$arrayHash])) {
            static::setMaxSize($array, 0, $arrayHash);
        }

        return static::$beautifulIndentMaxSize[$arrayHash];
    }

    /**
     * Get the md5 hash of an array
     *
     * @param      array   $array  The array to hash
     * @param      bool    $sort   If the array should be sorted before hashing DEFAULT true
     *
     * @return     string  The md5 hash
     */
    public static function md5Array(array $array, bool $sort = true): string
    {
        if ($sort) {
            array_multisort($array);
        }

        return md5(json_encode($array));
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Process the max value size of an array
     *
     * If the array is processed and the array did not change, the array is not reprocessed
     *
     * @param      array    $array      The array to calculate max size of
     * @param      integer  $minSize    OPTIONAL The minimum size DEFAULT 0
     * @param      string   $arrayHash  OPTIONAL The already calculated array hash DEFAULT null
     */
    private static function setMaxSize(array $array, int $minSize = 0, string $arrayHash = null)
    {
        if ($arrayHash === null) {
            $arrayHash = static::md5Array($array);
        }

        $max = 0;

        foreach ($array as $value) {
            if (is_array($value)) {
                $size = static::getMaxSize($value);
            } else {
                $size = strlen((string) $value);
            }

            if ($size > $max) {
                $max = $size;
            }
        }

        static::$beautifulIndentMaxSize[$arrayHash] = max($max, $minSize);
    }

    /*-----  End of Private methods  ------*/
}
