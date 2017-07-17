<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\DbTable;
use Application\Model\Perspective;
use Application\PictureNameFormatter;

class Pic extends AbstractHtmlElement
{
    /**
     * @var DbTable\Picture\Row
     */
    private $picture = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var Perspective
     */
    private $perspective;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        Perspective $perspective
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->perspective = $perspective;
    }

    public function __invoke(DbTable\Picture\Row $picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            return $this->view->url('picture/picture', [
                'picture_id' => $this->picture->identity
            ]);
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
        $pictureTable = new DbTable\Picture();
        $names = $pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ], $this->perspective);
        $name = $names[$pictureRow->id];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
