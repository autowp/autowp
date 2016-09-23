<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Autowp\Image\Storage;

class Img extends AbstractHtmlElement
{
    private $attribs;

    /**
     * @param int $imageId
     * @param array $attribs
     * @return Img
     */
    public function __invoke($imageId, array $attribs = [])
    {
        $this->attribs = [];
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
            $this->attribs = $attribs;
        }

        return $this;
    }

    public function src()
    {
        return isset($this->attribs['src']) ? $this->attribs['src'] : '';
    }

    public function __toString()
    {
        try {

            if (isset($this->attribs['src'])) {
                return $this->view->htmlImg($this->attribs);
            }

        } catch (\Exception $e) {

            print $e->getMessage();

        }

        return '';
    }

    public function exists()
    {
        return isset($this->attribs['src']) && $this->attribs['src'];
    }
}

