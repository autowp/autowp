<?php

class Project_Form_Element_Engine_Name extends Zend_Form_Element_Text
{
	public function init()
	{
		parent::init();

		$this->setOptions(array(
			'size'		=>	60,
			'maxlength'	=>	255,
			'label'		=>	'Название',
			'filters'	=>	array(
				'StringTrim',
				'SingleSpaces'
			)
		));
	}
}