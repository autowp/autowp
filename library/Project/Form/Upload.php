<?php

class Project_Form_Upload extends My_Form
{
	protected $_usingCaptcha = false;

	/**
	 * Set form state from options array
	 *
	 * @param  array $options
	 * @return Zend_Form
	 */
	public function setOptions(array $options)
	{
		if (isset($options['captcha'])) {
			$this->_usingCaptcha = (bool)$options['captcha'];
			unset($options['captcha']);
		}

		parent::setOptions($options);
	}

	public function init()
	{
		$this->setMethod('post');

		$this->setAttrib('enctype', 'multipart/form-data');
		
		$maxFileSize = 1024*1024*4;
		
		$this->setDecorators(array(
			'PrepareElements',
			array('viewScript', array('viewScript' => 'forms/upload.phtml')),
			'Form'
		));

		$this->addElements(array(
			array ('file', 'picture', array (
				'label'			=>	'Файл картинки',
				'required'		=>	true,
				'validators'	=>	array(
					array('Count', true, 1),
					array('Size',  true, $maxFileSize),
					//array('IsImage',	true),
					array('Extension',  true, 'jpg,jpeg,jpe'),
					array('ImageSizeInArray',  true, array(
						'sizes'	=>	Pictures::getResolutions()
					)),
				),
				'MaxFileSize'	=>	$maxFileSize,
				'decorators'	=>	array('File')
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
		));

		if ($this->_usingCaptcha) {
			$this->addElements(array(
				array ('captcha', 'captcha', array(
					'required'		=>	true,
					'label'			=>	'Введите код защиты',
					'decorators'	=>	array('ViewHelper')
				)),
			));
		}
	}
}