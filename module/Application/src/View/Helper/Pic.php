<?php

namespace Application\View\Helper;

use Application\Model\Picture;
use Application\PictureNameFormatter;
use ArrayObject;
use Exception;
use Laminas\View\Helper\AbstractHtmlElement;
use Laminas\View\Renderer\PhpRenderer;

use function urlencode;

class Pic extends AbstractHtmlElement
{
    /** @var array|ArrayObject */
    private $picture;

    private PictureNameFormatter $pictureNameFormatter;

    private Picture $pictureModel;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        Picture $picture
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->pictureModel         = $picture;
    }

    /**
     * @param null|array|ArrayObject $picture
     */
    public function __invoke($picture = null): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function url(): ?string
    {
        if ($this->picture) {
            return '/picture/' . urlencode($this->picture['identity']);
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function htmlTitle(array $picture): string
    {
        /** @var PhpRenderer $view */
        $view = $this->view;
        /** @var Language $languageHelper */
        $languageHelper = $view->getHelperPluginManager()->get('language');

        return $this->pictureNameFormatter->formatHtml($picture, $languageHelper());
    }

    /**
     * @throws Exception
     */
    public function textTitle(array $picture): string
    {
        /** @var PhpRenderer $view */
        $view = $this->view;
        /** @var Language $languageHelper */
        $languageHelper = $view->getHelperPluginManager()->get('language');

        return $this->pictureNameFormatter->format($picture, $languageHelper());
    }

    /**
     * @param array|ArrayObject $pictureRow
     * @throws Exception
     */
    public function name($pictureRow, string $language): string
    {
        if ($pictureRow instanceof ArrayObject) {
            $pictureRow = (array) $pictureRow;
        }

        $names = $this->pictureModel->getNameData([$pictureRow], [
            'language' => $language,
            'large'    => true,
        ]);
        $name  = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
