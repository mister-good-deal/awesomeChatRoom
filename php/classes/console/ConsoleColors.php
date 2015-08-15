<?php
/**
 * Console colors to output colors in console
 *
 * @category Console
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

/**
 * Class to get different color to output in a console
 *
 * @class ConsoleColors
 */
class ConsoleColors
{
    const BLACK          = 'black';
    const DARK_GRAY_F    = 'dark_gray';
    const BLUE           = 'blue';
    const LIGHT_BLUE_F   = 'light_blue';
    const GREEN          = 'green';
    const LIGHT_GREEN_F  = 'light_green';
    const CYAN           = 'cyan';
    const LIGHT_CYAN_F   = 'light_cyan';
    const RED            = 'red';
    const LIGHT_RED_F    = 'light_red';
    const PURPLE_F       = 'purple';
    const LIGHT_PURPLE_F = 'light_purple';
    const BROWN_F        = 'brown';
    const YELLOW         = 'yellow';
    const LIGHT_GRAY     = 'light_gray';
    const WHITE_F        = 'white';
    const MAGENTA_B      = 'magenta';

    /**
     * @var string[] $foregroundColors Foreground colors
     */
    private static $foregroundColors = array(
        self::BLACK          => '0;30',
        self::DARK_GRAY_F    => '1;30',
        self::BLUE           => '0;34',
        self::LIGHT_BLUE_F   => '1;34',
        self::GREEN          => '0;32',
        self::LIGHT_GREEN_F  => '1;32',
        self::CYAN           => '0;36',
        self::LIGHT_CYAN_F   => '1;36',
        self::RED            => '0;31',
        self::LIGHT_RED_F    => '1;31',
        self::PURPLE_F       => '0;35',
        self::LIGHT_PURPLE_F => '1;35',
        self::BROWN_F        => '0;33',
        self::YELLOW         => '1;33',
        self::LIGHT_GRAY     => '0;37',
        self::WHITE_F        => '1;37'
    );
    /**
     * @var string[] $backgroundColors Background colors
     */
    private static $backgroundColors = array(
        self::BLACK      => '40',
        self::RED        => '41',
        self::GREEN      => '42',
        self::YELLOW     => '43',
        self::BLUE       => '44',
        self::MAGENTA_B  => '45',
        self::CYAN       => '46',
        self::LIGHT_GRAY => '47'
    );

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Return the string colored
     *
     * @param  string $string          The string to color
     * @param  string $foregroundColor The foreground color
     * @param  string $backgroundColor The background color
     * @return string                  The colored string
     */
    public function getColoredString($string, $foregroundColor = null, $backgroundColor = null)
    {
        $coloredString = '';

        // Check if given foreground color found
        if (isset(static::$foregroundColors[$foregroundColor])) {
            $coloredString .= "\033[" . static::$foregroundColors[$foregroundColor] . 'm';
        }
        // Check if given background color found
        if (isset(static::$backgroundColors[$backgroundColor])) {
            $coloredString .= "\033[" . static::$backgroundColors[$backgroundColor] . 'm';
        }

        // Add string and end coloring
        return $coloredString . $string . "\033[0m";
    }

    /**
     * Returns all foreground color names
     *
     * @return string[] Foreground color names
     */
    public function getForegroundColors()
    {
        return array_keys(static::$foregroundColors);
    }

    /**
     * Returns all background color names
     *
     * @return string[] Background color names
     */
    public function getBackgroundColors()
    {
        return array_keys(static::$backgroundColors);
    }

    /*-----  End of Public methods  ------*/
}
