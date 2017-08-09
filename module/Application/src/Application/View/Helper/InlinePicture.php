<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\DbTable;

class InlinePicture extends AbstractHelper
{
    public function __construct(DbTable\Picture $pictureTable)
    {
        $this->pictureTable = $pictureTable;
    }

    public function __invoke(\Zend_Db_Table_Row_Abstract $picture)
    {
        $view = $this->view;

        $url = $view->pic($picture)->url();

        $name = $view->pic()->name($picture, $this->view->language());

        $imageHtml = $view->img($this->pictureTable->getFormatRequest($picture), [
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
