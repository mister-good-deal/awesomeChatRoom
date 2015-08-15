<?php
/**
 * Images manipulation utilities class
 *
 * @category Utility
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

use \classes\ExceptionManager as Exception;

/**
 * Images manipulation class with usefull utilities methods
 *
 * @link https://en.wikipedia.org/wiki/Display_resolution
 * @class ImagesManager
 */
class ImagesManager
{
    /**
     * @var integer[] $WIDTHS_16_9 Commons 16/9 ratios width
     */
    public static $WIDTHS_16_9 = array(2560, 2048, 1920, 1600, 1536, 1366, 1360, 1280, 1093);
    /**
     * @var integer[] $HEIGHTS_16_9 Commons 16/9 ratios height
     */
    public static $HEIGHTS_16_9 = array(1440, 1152, 1080, 900, 864, 768, 720, 614);
    /**
     * @var integer[] $MOST_USE_WIDTHS Most use width
     */
    public static $MOST_USE_WIDTHS = array(1920, 1600, 1440, 1366, 1280, 1024, 768, 480);
    /**
     * @var integer[] $MOST_USE_HEIGHTS  Most use height
     */
    public static $MOST_USE_HEIGHTS = array(1080, 1050, 1024, 900, 800, 768);
    /**
     * @var integer $EXTRA_TEXT_BORDER  A padding size to add to text added in an image
     */
    public static $EXTRA_TEXT_PADDING = 10;

    /**
     * @var \Imagick $image \Imagick instance DEFAULT null
     */
    private $image = null;
    /**
     * @var string $imagePath The original image path
     */
    private $imagePath;
     /**
     * @var string $imageSavePath The new image path
     */
    private $imageSavePath;
    /**
     * @var string $imageName The original image name
     */
    private $imageName;
    /**
     * @var string $imageExtension The original image extension
     */
    private $imageExtension;

    /*=====================================
    =            Magic methods            =
    =====================================*/
    
    /**
     * Constructor which can instanciate a new \Imagick object if a path is specified
     *
     * @param string $imagePath OPTIONAL the image path
     */
    public function __construct($imagePath = '')
    {
        if ($imagePath !== '') {
            $this->setImage($imagePath);
        }
    }

    /**
     * destructor, free the image ressource memory
     */
    public function __destruct()
    {
        if ($this->image !== null) {
            $this->image->destroy();
        }
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Setters / getters            =
    =========================================*/
    
    /**
     * Instantiate a new \Imagick object and destroyed the last if exists
     *
     * @param  string    $imagePath The image path
     * @throws Exception            If there is no image at the specified path
     * @throws Exception            If there is an error on image creation
     */
    public function setImage($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new Exception('There is no image at this path : "' . $imagePath . '"', Exception::$PARAMETER);
        }

        $this->__destruct();

        try {
            $this->image          = new \Imagick($imagePath);
            $this->imagePath      = $imagePath;
            $this->imageSavePath  = pathinfo($imagePath, PATHINFO_DIRNAME);
            $this->imageName      = pathinfo($imagePath, PATHINFO_FILENAME);
            $this->imageExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::$ERROR);
        }
    }

    /**
     * Set the image save path and create the repositories if the new path doesn't exist (must be an absolute path)
     *
     * @param string $path The new save path
     */
    public function setImageSavePath($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $this->imageSavePath = $path;
    }
    
    /*-----  End of Setters / getters  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Generate and save scales images with specified widths
     *
     * @param integer[] $widths The widths to resize the image with
     *                          DEFAULT [1920, 1600, 1440, 1366, 1280, 1024, 768, 480]
     */
    public function generateResizedImagesByWidth($widths = array(1920, 1600, 1440, 1366, 1280, 1024, 768, 480))
    {
        foreach ($widths as $width) {
            $this->generateResizedImages($width, 0);
        }
    }

    /**
     * Generate and save scales images with specified heights
     *
     * @param integer[] $heights The heights to resize the image with
     *                           DEFAULT [1080, 1050, 1024, 900, 800, 768]
     */
    public function generateResizedImagesByHeight($heights = array(1080, 1050, 1024, 900, 800, 768))
    {
        foreach ($heights as $height) {
            $this->generateResizedImages(0, $height);
        }
    }

    /**
     * Generate and save the image with a copyright text at the bottom right corner
     *
     * @param string  $text     The copyright text
     * @param integer $fontSize OPTIONAL the copyright font size DEFAULT 22
     * @param string  $font     OPTIONAL the copyright font DEFAULT "Verdana"
     * @param string  $position OPTIONAL the position to put copyright text DEFAULT "bottom-right"
     *                          Possibles Values ("bottom-right", "bottom-left", "top-right", "top-left")
     */
    public function copyrightImage($text, $fontSize = 22, $font = 'Verdana', $position = 'bottom-right')
    {
        $draw  = new \ImagickDraw();

        $draw->setFontSize($fontSize);
        $draw->setFont($font);
        $draw->setFillColor('#ffffff');
        $draw->setTextUnderColor('#00000088');
        
        $textMetrics     = $this->image->queryFontMetrics($draw, $text);
        $textWidth       = $textMetrics['textWidth'] + 2 * $textMetrics['boundingBox']['x1'];
        $extraTextHeight = $textMetrics['descender'];
        $textHeight      = $textMetrics['textHeight'] + $extraTextHeight;

        switch ($position) {
            case 'bottom-right':
                $width  = $this->image->getImageWidth() - $textWidth;
                $height = $this->image->getImageHeight() + $extraTextHeight;
                $width  -= static::$EXTRA_TEXT_PADDING;
                $height -= static::$EXTRA_TEXT_PADDING;
                break;

            case 'bottom-left':
                $width  = 0;
                $height = $this->image->getImageHeight() + $extraTextHeight;
                $width  += static::$EXTRA_TEXT_PADDING;
                $height -= static::$EXTRA_TEXT_PADDING;
                break;

            case 'top-right':
                $width  = $this->image->getImageWidth() - $textWidth;
                $height = $textHeight;
                $width  -= static::$EXTRA_TEXT_PADDING;
                $height += static::$EXTRA_TEXT_PADDING;
                break;

            case 'top-left':
                $width  = 0;
                $height = $textHeight;
                $width  += static::$EXTRA_TEXT_PADDING;
                $height += static::$EXTRA_TEXT_PADDING;
                break;
            
            default:
                $width  = $this->image->getImageWidth() - $textWidth;
                $height = $this->image->getImageHeight() + $extraTextHeight;
                $width  -= static::$EXTRA_TEXT_PADDING;
                $height -= static::$EXTRA_TEXT_PADDING;
                break;
        }

        $this->image->annotateImage($draw, $width, $height, 0, $text);
        $this->image->writeImage(
            $this->imageSavePath . DIRECTORY_SEPARATOR . $this->imageName . '_copyright' . '.' . $this->imageExtension
        );

    }
    
    /*-----  End of Public methods  ------*/
    
    /*=======================================
    =            Private methods            =
    =======================================*/
    
    /**
     * Generate and save scales images with specified width and height
     *
     * @param integer $width  The width to resize the image with
     * @param integer $height The height to resize the image with
     */
    private function generateResizedImages($width, $height)
    {
        $this->image->scaleImage($width, $height);

        if ($width === 0) {
            $width = $this->image->getImageWidth();
        }

        if ($height === 0) {
            $height = $this->image->getImageHeight();
        }

        $this->image->writeImage(
            $this->imageSavePath . DIRECTORY_SEPARATOR . $this->imageName
            . '_' . $width . 'x' . $height . '.' . $this->imageExtension
        );
    }
    
    /*-----  End of Private methods  ------*/
}
