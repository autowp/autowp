<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pictures_Row;

class InlinePicture extends AbstractHelper
{
    public function __invoke(Pictures_Row $picture)
    {
        $view = $this->view;

        $url = $view->pic($picture)->url();

        $caption = $picture->getCaption();

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
                '<h5>' . $view->htmlA($url, $picture->getCaption()) . '</h5>' .
                $view->pictures()->behaviour($picture) .
            '</div>';

    }
}