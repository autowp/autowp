<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Autowp\Image\Storage;

class Img extends AbstractHtmlElement
{
    private $_attribs;

    /**
     * @param int $imageId
     * @param array $attribs
     * @return Img
     */
    public function __invoke($imageId, array $attribs = [])
    {
        $this->_attribs = array();
        $format = null;
        if (array_key_exists('format', $attribs)) {
            $format = $attribs['format'];
            unset($attribs['format']);
        }

        if (!$imageId) {
            return $this;
        }

        $storage = $this->view->imageStorage();

        if ($format) {
            $imageInfo = $storage->getFormatedImage($imageId, $format);
        } else {
            $imageInfo = $storage->getImage($imageId);
        }

        if ($imageInfo) {
            $attribs['src'] = $imageInfo->getSrc();
            $this->_attribs = $attribs;
        }

        return $this;
    }

    public function src()
    {
        return isset($this->_attribs['src']) ? $this->_attribs['src'] : '';
    }

    public function __toString()
    {
        try {

            if (isset($this->_attribs['src'])) {
                return $this->view->htmlImg($this->_attribs);
            }

        } catch (\Exception $e) {

            print $e->getMessage();

        }

        return '';
    }

    public function exists()
    {
        return isset($this->_attribs['src']) && $this->_attribs['src'];
    }
}

