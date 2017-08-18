<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\Picture;
use Application\PictureNameFormatter;

class Pic extends AbstractHtmlElement
{
    /**
     * @var \Zend_Db_Table_Row_Abstract
     */
    private $picture = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var Picture
     */
    private $pictureModel;

    public function __construct(
        PictureNameFormatter $pictureNameFormatter,
        Picture $picture
    ) {
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->pictureModel = $picture;
    }

    public function __invoke($picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            return $this->view->url('picture/picture', [
                'picture_id' => $this->picture['identity']
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
        if ($pictureRow instanceof \Zend_Db_Table_Row_Abstract) {
            $pictureRow = $pictureRow->toArray();
        } elseif ($pictureRow instanceof \ArrayObject) {
            $pictureRow = (array)$pictureRow;
        }

        $names = $this->pictureModel->getNameData([$pictureRow], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow['id']];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
