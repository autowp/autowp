<?php

class Project_Form_Feedback extends My_Form
{
	public function init()
	{
		$this->setMethod('post');
		$this->setDescription('Обратная связь');
		$this->setAttrib('class', 'form-horizontal');
		
		$this->setDecorators(array(
			'PrepareElements',
			array('viewScript', array('viewScript'	=>	'forms/feedback.phtml')),
			'Form'
		));
		
		$this->addElements(array(
			array ('text', 'name', array (
				'label'			=>	'Ваше имя',
				'required'		=>	true,
				'filters'		=>	array(
					'StringTrim'
				),	
				'maxlength'		=>	255,
				'size'			=>	80,
				'decorators'	=>	array(
					'ViewHelper'
				),
				'class'			=>	'span5'
			)),
			array ('text', 'email', array (
				'label'			=>	'E-mail',
				'required'		=>	false,
				'filters'		=>	array(
					'StringTrim'
				),	
				'validators'	=>	array(
					'EmailAddress'
				),
				'maxlength'		=>	255,
				'size'			=>	80,
				'decorators'	=>	array(
					'ViewHelper'
				),
				'class'			=>	'span5'
			)),
			array ('textarea', 'message', array (
				'required'		=>	true,
				'label'			=>	'Сообщение',
				'cols'			=>	80,
				'rows'			=>	8,
				'filters'		=>	array(
					'StringTrim'
				),
				'decorators'	=>	array(
					'ViewHelper'
				),
				'class'			=>	'span5'
			)),
			array ('captcha', 'captcha', array(
				'required'		=>	true,
				'label'			=>	'Введите код защиты',
				'captcha'		=>	array(
					'captcha'	=>	'Image',
					'wordLen'	=>	4,
					'timeout'	=>	300,
					'font'		=>	APPLICATION_DIR . '/resources/fonts/arial.ttf',
					'imgDir'	=>	PUBLIC_DIR . '/img/captcha/',
					'imgUrl'	=>	'/img/captcha/'
				),
				'class'			=>	'span5',
				'decorators'	=>	array(
					'captcha'
				),
			)),
		));
	}
}