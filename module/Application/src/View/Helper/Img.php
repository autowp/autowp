<?php

namespace Application\View\Helper;

use Autowp\Image\Storage;
use Autowp\ZFComponents\View\Helper\HtmlImg;
use Exception;
use ImagickException;
use Laminas\View\Helper\AbstractHtmlElement;
use Laminas\View\Renderer\PhpRenderer;

use function array_key_exists;

class Img extends AbstractHtmlElement
{
    private array $attribs;

    private Storage $imageStorage;

    public function __construct(Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

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

        if ($format) {
            $imageInfo = $this->imageStorage->getFormatedImage($imageId, $format);
        } else {
            $imageInfo = $this->imageStorage->getImage($imageId);
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
        /** @var PhpRenderer $view */
        $view = $this->view;
        /** @var HtmlImg $htmlImgHelper */
        $htmlImgHelper = $view->getHelperPluginManager()->get('htmlImg');
        try {
            if (isset($this->attribs['src'])) {
                return $htmlImgHelper($this->attribs);
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
