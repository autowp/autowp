<?php

namespace Application\View\Helper;

use Autowp\Image\Storage;
use Exception;
use ImagickException;
use Laminas\View\Helper\AbstractHtmlElement;

use function array_key_exists;

class Img extends AbstractHtmlElement
{
    private array $attribs;

    /**
     * @throws Storage\Exception
     * @throws ImagickException
     */
    public function __invoke(int $imageId, array $attribs = []): self
    {
        $this->attribs = [];
        $format        = null;
        if (array_key_exists('format', $attribs)) {
            $format = $attribs['format'];
            unset($attribs['format']);
        }

        if (! $imageId) {
            return $this;
        }

        /** @var Storage $storage */
        $storage = $this->view->imageStorage();

        if ($format) {
            $imageInfo = $storage->getFormatedImage($imageId, $format);
        } else {
            $imageInfo = $storage->getImage($imageId);
        }

        if ($imageInfo) {
            $attribs['src'] = $imageInfo->getSrc();
            $this->attribs  = $attribs;
        }

        return $this;
    }

    public function src(): string
    {
        return $this->attribs['src'] ?? '';
    }

    public function __toString(): string
    {
        try {
            if (isset($this->attribs['src'])) {
                return $this->view->htmlImg($this->attribs);
            }
        } catch (Exception $e) {
            print $e->getMessage();
        }

        return '';
    }

    public function exists(): bool
    {
        return isset($this->attribs['src']) && $this->attribs['src'];
    }
}
