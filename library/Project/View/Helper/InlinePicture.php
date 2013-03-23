<?php

class Project_View_Helper_InlinePicture extends Zend_View_Helper_Abstract
{
    private $_blockNumber = 0;

    public function inlinePicture(Pictures_Row $picture)
    {
        $this->_blockNumber++;
        
        $view = $this->view;
        
        $url = $view->pic($picture)->url();
        
        return
        	$view->htmlA(array(
				'href'	=>	$url,
				'class'	=>	'inline-picture-preview thumbnail thumbnail-inline'
			), $view->picture($picture), false) .
			'<div class="inline-picture-details" style="display:none;">'.
				'<h5>' . $view->htmlA($url, $picture->getCaption()) . '</h5>' .
				$view->getHelper('pictures2')->behaviour($picture) . 
			'</div>';
        
    }
}