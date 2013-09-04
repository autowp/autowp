<?php

/**
 * @author Dmitry
 * @desc for future use, when sprites capture the web :(
 */
class Project_LogoSprite
{
    /**
     * @var Autowp_Service_ImageStorage
     */
    protected $_imageStorage = null;

    /**
     * @var string
     */
    protected $_format = null;

    /**
     * @var int
     */
    protected $_cols = 1;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return Project_LogoSprite
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param Autowp_Service_ImageStorage $imageStorage
     * @return Project_LogoSprite
     */
    public function setImageStorage(Autowp_Service_ImageStorage $imageStorage)
    {
        $this->_imageStorage = $imageStorage;

        return $this;
    }

    /**
     * @param string $format
     * @return Project_LogoSprite
     */
    public function setFormat($format)
    {
        $this->_format = $format;

        return $this;
    }

    /**
     * @param int $format
     * @return Project_LogoSprite
     */
    public function setCols($cols)
    {
        $cols = (int)$cols;
        if ($cols <= 0) {
            return $this->_raise("Cols count must be > 0");
        }
        $this->_cols = $cols;

        return $this;
    }

    /**
     * @param array $images
     * @param string $imageFilepath
     * @param string $cssFilepath
     */
    public function generate(array $images, $imageFilepath, $cssFilepath)
    {
        if (!$this->_imageStorage) {
            return $this->_raise("Image storage not initialized");
        }
        $imageStorage = $this->_imageStorage;

        $sprite = new Imagick();


        foreach ($images as $image) {
            $imageBlob = $imageStorage->getFormatedImageBlob($image['id'], $this->_format);
            $imagick = new Imagick();
            $imagick->readImageBlob($imageBlob);
            $sprite->addImage($imagick);
        }

        $sprite->resetIterator();
        $sprite = $sprite->appendImages(false);

        $sprite->writeImages($imageFilepath, true);
        $sprite->clear();
    }

    /**
     * @param string $message
     * @throws Exception
     */
    protected function _raise($message)
    {
        throw new Exception($message);
    }
}