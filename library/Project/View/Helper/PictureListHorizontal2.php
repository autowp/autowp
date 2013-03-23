<?php

class Project_View_Helper_PictureListHorizontal2 extends Zend_View_Helper_Abstract
{
    public function pictureListHorizontal2($list)
    {
        $html  = array();
        $language = $this->view->language()->get();
        foreach ($list as $picture) {
        	if ($picture) {
        		$caption = $picture->getCaption(array(
					'language'	=>	$language
				));
	        	$html[] =   '<li class="span2">'.
	            				$this->view->htmlA(
		            				array(
		            					'href'	=>	$this->view->pic($picture)->url(),
		            					'class'	=>	'thumbnail'
		            				), 
		            				$this->view->image($picture, 'file_name', array(
							            'format' => 6,
							            'alt'    => $caption,
							            'title'  => $caption,
							        )), 
		            				false
		            			) . 
	                        '</li>';
        	} else {
        		$html[] =   '<li class="span2">&#xa0;</li>';
        	}
        }

        return  '<ul class="thumbnails">'.
                    implode($html).
                '</ul>';
    }
}