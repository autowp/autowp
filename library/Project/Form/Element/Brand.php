<?php

class Project_Form_Element_Brand extends Project_Form_Element_Select_Db_Table
{
	public function init()
	{
		$this->setOptions(array(
			'table'			=>	new Brands(),
			'valueField'	=>	'id',
			'viewField'		=>	'caption',
			'select'		=>	array (
				'order' => array('position', 'caption')
			),
			'nonename'		=>	'--',
			'label'			=>	'Производитель'
		));
		
		parent::init();
	}
}