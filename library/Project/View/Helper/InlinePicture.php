<?php

class Project_View_Helper_InlinePicture extends Zend_View_Helper_Abstract
{
    private $_blockNumber = 0;

    public function inlinePicture(Pictures_Row $picture)
    {
        $this->_blockNumber++;

        $view = $this->view;

        $url = $view->pic($picture)->url();

        $caption = $picture->getCaption();

        if ($picture->image_id) {
            $imageHtml = $this->view->img($picture->getFormatRequest(), array(
                'format'  => 'picture-thumb',
                'alt'     => $caption,
                'title'   => $caption,
                'shuffle' => true
            ));
        } else {
            $image = $this->view->image($picture, 'file_name', array(
                'format'  => 6,
                'alt'     => $caption,
                'title'   => $caption,
                'shuffle' => true
            ));
            $imageHtml = $image->exists() ? $image : '';
        }

        return
            $view->htmlA(array(
                'href'  => $url,
                'class' => 'inline-picture-preview thumbnail thumbnail-inline'
            ), $imageHtml, false) .
            '<div class="inline-picture-details" style="display:none;">'.
                '<h5>' . $view->htmlA($url, $picture->getCaption()) . '</h5>' .
                $view->getHelper('pictures')->behaviour($picture) .
            '</div>';

    }
}