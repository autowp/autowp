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
				$img = $this->view->image($picture, 'file_name', array(
					'format'	=>	6,
					'alt'		=>	$caption,
					'title'		=>	$caption,
				));
				
				$pictureHtml = $this->view->htmlA(array(
					'href'	=>	$this->view->pic($picture)->url(),
					'class'	=>	'thumbnail'
				), $img, false);
			} else {
				$pictureHtml = '&#xa0;';
			}
			
			$html[] = '<li class="span2">' . $pictureHtml . '</li>';
		}

		return  '<ul class="thumbnails">'.
					implode($html).
				'</ul>';
	}
}