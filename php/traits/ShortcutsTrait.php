<?php
/**
 * Trait to use utilities methods like a shortcut with many actions in one call
 *
 * @category Trait
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utilities methods to minimize code and process many actions in one call
 *
 * @trait ShortcutsTrait
 */
trait ShortcutsTrait
{
    /**
     * Set a value to a variable if the value is set or set a default value instead if a default value is defined
     *
     * @param mixed $variable The variable to set the value
     * @param mixed $value    The value to set
     * @param mixed $default  OPTIONAL default value to set if the value is not set
     */
    public static function setIfIsSet(&$variable, $value)
    {
        if (isset($value)) {
            $variable = $value;
        } elseif (func_num_args() > 2) {
            $variable = func_get_arg(2);
        }
    }
}
