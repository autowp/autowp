<?php

namespace Application\View\Helper;

use Application\Model\Picture;
use Application\PictureNameFormatter;
use ArrayObject;
use Laminas\View\Helper\AbstractHtmlElement;

use function urlencode;

class Pic extends AbstractHtmlElement
{
    /** @var array|ArrayObject */
    private $picture;

    /** @var PictureNameFormatter */
    private $pictureNameFormatter;

    /** @var Picture */
    private $pictureModel;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        Picture $picture
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->pictureModel         = $picture;
    }

    public function __invoke($picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            return '/picture/' . urlencode($this->picture['identity']);
        }
        return false;
    }

    public function htmlTitle(array $picture)
    {
        return $this->pictureNameFormatter->formatHtml($picture, $this->view->language());
    }

    public function textTitle(array $picture)
    {
        return $this->pictureNameFormatter->format($picture, $this->view->language());
    }

    public function name($pictureRow, $language)
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
