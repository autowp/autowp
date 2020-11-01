<?php

namespace Application\View\Helper;

use ArrayAccess;
use Autowp\ZFComponents\View\Helper\HtmlA;
use Exception;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Renderer\PhpRenderer;

class InlinePicture extends AbstractHelper
{
    /**
     * @param array|ArrayAccess $picture
     * @throws Exception
     */
    public function __invoke($picture): string
    {
        /** @var PhpRenderer $view */
        $view = $this->view;
        /** @var Pic $picHelper */
        $picHelper = $view->getHelperPluginManager()->get('pic');
        /** @var Language $languageHelper */
        $languageHelper = $view->getHelperPluginManager()->get('language');
        /** @var HtmlA $htmlAhelper */
        $htmlAhelper = $view->getHelperPluginManager()->get('htmlA');
        /** @var Img $imgHelper */
        $imgHelper = $view->getHelperPluginManager()->get('img');

        $url = $picHelper($picture)->url();

        $name = $picHelper()->name($picture, $languageHelper());

        $imageHtml = $imgHelper($picture['image_id'], [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true,
            'class'   => 'rounded border border-light',
        ]);

        return $htmlAhelper([
            'href'  => $url,
            'class' => 'd-inline-block rounded',
        ], $imageHtml, false);
    }
}
