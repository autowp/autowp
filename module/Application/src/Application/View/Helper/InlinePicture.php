<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\Picture;

class InlinePicture extends AbstractHelper
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
    }

    public function __invoke($picture)
    {
        $view = $this->view;

        $url = $view->pic($picture)->url();

        $name = $view->pic()->name($picture, $this->view->language());

        $imageHtml = $view->img($this->picture->getFormatRequest($picture), [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true
        ]);

        return
            $view->htmlA([
                'href'  => $url,
                'class' => 'inline-picture-preview thumbnail thumbnail-inline'
            ], $imageHtml, false) .
            '<div class="inline-picture-details" style="display:none;">'.
                '<h5>' . $view->htmlA($url, $name) . '</h5>' .
                $view->pictures()->behaviour($picture) .
            '</div>';
    }
}
