<?php

class Project_View_Helper_PictureListHorizontal2 extends Zend_View_Helper_Abstract
{
    public function pictureListHorizontal2($list, array $options = array())
    {
        $defaults = array(
            'colClass' => 'col-md-3',
        );
        $options = array_merge($defaults, $options);

        $html  = array();
        $language = $this->view->language()->get();
        foreach ($list as $picture) {
            if ($picture) {
                $caption = $picture->getCaption(array(
                    'language' => $language
                ));

                $imageHtml = $this->view->img($picture->getFormatRequest(), array(
                    'format'  => 'picture-thumb',
                    'alt'     => $caption,
                    'title'   => $caption,
                    'shuffle' => true
                ));

                $pictureHtml = $this->view->htmlA(array(
                    'href'  => $this->view->pic($picture)->url(),
                    'class' => 'thumbnail'
                ), $imageHtml, false);
            } else {
                $pictureHtml = '&#xa0;';
            }

            $html[] = '<div class="'.$this->view->escape($options['colClass']).'">' .
                          $pictureHtml .
                      '</div>';
        }

        return  '<div class="row thumbnails">'.
                    implode($html).
                '</div>';
    }
}