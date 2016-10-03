<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable\Picture\Row as PictureRow;

class InlinePicture extends AbstractHelper
{
    public function __invoke(PictureRow $picture)
    {
        $view = $this->view;

        $url = $view->pic($picture)->url();

        $caption = $view->pic()->name($picture, $this->view->language());

        $imageHtml = $view->img($picture->getFormatRequest(), [
            'format'  => 'picture-thumb',
            'alt'     => $caption,
            'title'   => $caption,
            'shuffle' => true
        ]);

        return
            $view->htmlA([
                'href'  => $url,
                'class' => 'inline-picture-preview thumbnail thumbnail-inline'
            ], $imageHtml, false) .
            '<div class="inline-picture-details" style="display:none;">'.
                '<h5>' . $view->htmlA($url, $caption) . '</h5>' .
                $view->pictures()->behaviour($picture) .
            '</div>';

    }
}