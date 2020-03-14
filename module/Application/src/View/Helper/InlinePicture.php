<?php

namespace Application\View\Helper;

use Application\Model\Picture;
use ArrayAccess;
use Laminas\View\Helper\AbstractHelper;

class InlinePicture extends AbstractHelper
{
    private Picture $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
    }

    /**
     * @param array|ArrayAccess $picture
     */
    public function __invoke($picture): string
    {
        $view = $this->view;

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $url = $view->pic($picture)->url();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $name = $view->pic()->name($picture, $this->view->language());

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $imageHtml = $view->img($picture['image_id'], [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true,
            'class'   => 'rounded border border-light',
        ]);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $view->htmlA([
            'href'  => $url,
            'class' => 'd-inline-block rounded',
        ], $imageHtml, false);
    }
}
