<?php

class Application_Form_Moder_Engine extends Zend_Form
{
	public function init()
	{
		parent::init();
		
		$this->setOptions(array(
			'method'		=>	'post',
			'description'	=>	'Двигатель',
			'decorators'	=>	array(
				'PrepareElements',
				array('viewScript', array(
					'viewScript'	=>	'forms/bootstrap-horizontal.phtml'
				)),
				'Form'
			),
			'prefixPath'	=>	array(
				array(
					'prefix'	=>	'Project_Form_Element',
					'path'		=>	'Project/Form/Element',
					'type'		=>	Zend_Form::ELEMENT
				)
			),
			'elementPrefixPath'	=>	array(
				array(
					'prefix'	=>	'Project_Filter',
					'path'		=>	'Project/Filter',
					'type'		=>	Zend_Form_Element::FILTER
				)
			),
			'elements'		=>	array(
				array('Engine_Name', 'caption', array (
					'required'		=>	true,
					'decorators'	=>	array('ViewHelper')
				)),
				array('Brand', 'brand_id', array (
					'required'		=>	false,
					'decorators'	=>	array('ViewHelper')
				)),
			)
		));
	}
}