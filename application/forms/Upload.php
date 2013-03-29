<?php

class Application_Form_Upload extends Zend_Form
{
	protected $_maxFileSize = 4194304; //1024*1024*4;
	
	public function init()
	{
		parent::init();
		
		$this->setOptions(array(
			'method'		=>	'post',
			'enctype'		=>	'multipart/form-data',
			'decorators'	=>	array(
				'PrepareElements',
				array('viewScript', array(
					'viewScript'	=>	'forms/bootstrap-horizontal.phtml'
				)),
				'Form'
			),
			'elementPrefixPath'	=>	array(
				array(
					'prefix'	=>	'Project_Validate_File',
					'path'		=>	'Project/Validate/File',
					'type'		=>	Zend_Form_Element::VALIDATE
				)
			),
			'elements'		=>	array(
				array ('file', 'picture', array (
					'label'			=>	'Файл картинки',
					'required'		=>	true,
					'validators'	=>	array(
						array('Count', true, 1),
						array('Size',  true, $this->_maxFileSize),
						//array('IsImage',	true),
						array('Extension',  true, 'jpg,jpeg,jpe'),
						array('ImageSizeInArray',  true, array(
							'sizes'	=>	Pictures::getResolutions()
						)),
					),
					'MaxFileSize'	=>	$this->_maxFileSize,
					'decorators'	=>	array('File'),
				)),
				array ('text', 'note', array (
					'required'		=>	false,
					'label'			=>	'Примечание к картинке',
					'size'			=>	50,
					'maxlength'		=>	255,
					'filters'		=>	array('StringTrim'),
					'validators'	=>	array(
						array('StringLength', true, array(3, 255))
					),
					'decorators'	=>	array('ViewHelper')
				)),
			)
		));
	}
}