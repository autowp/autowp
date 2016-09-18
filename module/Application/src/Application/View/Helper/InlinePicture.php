<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pictures_Row;

use Exception;

class InlinePicture extends AbstractHelper
{
    private $translator;

    public function __construct($translator)
    {
        $this->translator = $translator;
    }

    public function __invoke(Pictures_Row $picture)
    {
        if (!$this->translator) {
            throw new Exception('`translator` expected');
        }

        $view = $this->view;

        $url = $view->pic($picture)->url();

        $caption = $picture->getCaption([
            'language'   => $this->view->language(),
            'translator' => $this->translator
        ]);

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