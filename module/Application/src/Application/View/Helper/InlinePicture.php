<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pictures_Row;

class InlinePicture extends AbstractHelper
{
    private $_blockNumber = 0;

    public function __invoke(Pictures_Row $picture)
    {
        $this->_blockNumber++;

        $view = $this->view;

        $url = $view->pic($picture)->url();

        $caption = $picture->getCaption();

        $imageHtml = $this->view->img($picture->getFormatRequest(), [
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