<?php
/**
 * @see Zend_Validate_Abstract
 */
require_once 'Zend/Validate/Abstract.php';

class Project_Validate_File_ImageSizeInArray extends Zend_Validate_Abstract
{
    /**
     * @const string Error constants
     */
    const NOT_IN_ARRAY     = 'fileImageSizeNotInArray';
    const NOT_DETECTED     = 'fileImageSizeNotDetected';
    const NOT_READABLE     = 'fileImageSizeNotReadable';

    /**
     * @var array Error message template
     */
    protected $_messageTemplates = array(
        self::NOT_IN_ARRAY     => "Size of image '%value%' not in list: '%sizesstr%'",
        self::NOT_DETECTED     => "The size of image '%value%' could not be detected",
        self::NOT_READABLE     => "The image '%value%' can not be read"
    );

    /**
     * @var array Error message template variables
     */
    protected $_messageVariables = array(
        'sizesstr'  => '_sizesstr',
        'width'     => '_width',
        'height'    => '_height'
    );

    /**
     * @var array
     */
    protected $_sizes = array();

    /**
     * @var array
     */
    protected $_sizesstr = '';

    /**
     * Detected width
     *
     * @var integer
     */
    protected $_width;

    /**
     * Detected height
     *
     * @var integer
     */
    protected $_height;

    /**
     * Sets validator options
     *
     * Accepts the following option keys:
     * - sizes
     *
     * @param  Zend_Config|array $options
     * @return void
     */
    public function __construct($options)
    {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } elseif (1 < func_num_args()) {
            throw new Exception('Multiple constructor options are deprecated in favor of a single options array');
        } else if (!is_array($options)) {
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Validate_Exception ('Invalid options to validator provided');
        }

        if (isset($options['sizes'])) {
            $this->setSizes($options['sizes']);
        }
    }

    /**
     * @param  array $sizes
     * @throws Zend_Validate_Exception
     * @return Project_Validate_File_ImageSizeInArray Provides a fluent interface
     */
    public function setSizes($sizes)
    {
        $this->_sizes = array();
        foreach ($sizes as $size)
        {
            if (!isset($size['width']))
                throw new Zend_Validate_Exception('Width expected');

            $width = (int)$size['width'];
            if ($width <= 0)
                throw new Zend_Validate_Exception('Width must be positive value');

            if (!isset($size['height']))
                throw new Zend_Validate_Exception('Height expected');

            $height = (int)$size['height'];
            if ($height <= 0)
                throw new Zend_Validate_Exception('Height must be positive value');

            $this->_sizes[] = array(
                'width'     =>  $width,
                'height'    =>  $height
            );
        }

        $a = array();
        foreach ($this->_sizes as $size)
            $a[] = $size['width'] . '×' . $size['height'];

        $this->_sizesstr = implode(', ', $a);

        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * @param  string $value Real file to check for image size
     * @param  array  $file  File data from Zend_File_Transfer
     * @return boolean
     */
    public function isValid($value, $file = null)
    {
        // Is file readable ?
        require_once 'Zend/Loader.php';
        if (!Zend_Loader::isReadable($value)) {
            return $this->_throw($file, self::NOT_READABLE);
        }

        $size = @getimagesize($value);
        $this->_setValue($file);

        if (empty($size) or ($size[0] === 0) or ($size[1] === 0)) {
            return $this->_throw($file, self::NOT_DETECTED);
        }

        $this->_width  = $size[0];
        $this->_height = $size[1];

        $found = false;
        foreach ($this->_sizes as $size)
        {
            if ($size['width'] == $this->_width && $size['height'] == $this->_height)
            {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->_throw($file, self::NOT_IN_ARRAY);
        }

        if (count($this->_messages) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Throws an error of the given type
     *
     * @param  string $file
     * @param  string $errorType
     * @return false
     */
    protected function _throw($file, $errorType)
    {
        if ($file !== null) {
            $this->_value = $file['name'];
        }

        $this->_error($errorType);
        return false;
    }
}
