<?php
/**
 * Trait to use utilities methods like a shortcut with many actions in one call
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utilities methods to minimize code and process many actions in one call
 */
trait ShortcutsTrait
{
    /**
     * Set a value to a variable if the value is set or set a default value instead if a default value is defined
     *
     * @param      mixed  $variable  The variable to set the value
     * @param      mixed  $value     The value to set
     * @param      mixed  $default   OPTIONAL default value to set if the value is not set
     */
    public static function setIfIsSet(&$variable, $value)
    {
        if (isset($value)) {
            $variable = $value;
        } elseif (func_num_args() > 2) {
            $variable = func_get_arg(2);
        }
    }

    /**
     * Set a trimed value to a variable if the value is set or set a default value instead if a default value is defined
     *
     * @param      mixed  $variable  The variable to set the value
     * @param      mixed  $value     The value to set
     * @param      mixed  $default   OPTIONAL default value to set if the value is not set
     */
    public function setIfIsSetAndTrim(&$variable, $value)
    {
        $this->setIfIsSet($variable, $value);
        $variable = trim($variable);
    }

    /**
     * Perform an in_array research for each specified sub-array
     *
     * @param      mixed    $needle    The value to research
     * @param      array    $haystack  The array to perform the research
     * @param      string   $key       The sub-array key
     * @return     boolean  True if the value is found else false
     */
    public function inSubArray($needle, $haystack, $key)
    {
        $found = false;

        foreach ($haystack as $subarray) {
            if ($subarray[$key] === $needle) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}
