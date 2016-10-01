<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\PictureNameFormatter;

use Picture;
use Picture_Row;

class Pic extends AbstractHtmlElement
{
    /**
     * @var Picture_Row
     */
    private $picture = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    public function __construct(PictureNameFormatter $pictureNameFormatter)
    {
        $this->pictureNameFormatter = $pictureNameFormatter;
    }

    public function __invoke(Picture_Row $picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            $identity = $this->picture->identity ? $this->picture->identity : $this->picture->id;

            return $this->view->url('picture/picture', [
                'picture_id' => $identity
            ]);
        }
        return false;
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function htmlTitle(array $picture)
    {
        $view = $this->view;

        if (isset($picture['name']) && $picture['name']) {
            return $view->escapeHtml($picture['name']);
        }

        switch ($picture['type']) {
            case Picture::VEHICLE_TYPE_ID:
                if ($picture['car']) {
                    return
                        ($picture['perspective'] ? $view->escapeHtml(self::mbUcfirst($view->translate($picture['perspective']))) . ' ' : '') .
                        $view->car()->htmlTitle($picture['car']);
                } else {
                    return 'Unsorted car';
                }
                break;

            case Picture::ENGINE_TYPE_ID:
            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
            case Picture::FACTORY_TYPE_ID:
                return $view->escapeHtml($this->textTitle($picture));
                break;
        }

        return 'Picture';
    }

    public function textTitle(array $picture)
    {
        return $this->pictureNameFormatter->format($picture, $this->view->language());
    }

    public function name($pictureRow, $language)
    {
        $pictureTable = new Picture();
        $names = $pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow->id];

        return $this->pictureNameFormatter->format($name, $language);
    }
}