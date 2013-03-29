<?php

class Application_Form_Feedback extends Zend_Form
{
	public function init()
	{
		parent::init();
		
		$this->setOptions(array(
			'method'		=>	'post',
			'description'	=>	'Обратная связь',
			'decorators'	=>	array(
				'PrepareElements',
				array('viewScript', array(
					'viewScript'	=>	'forms/bootstrap-horizontal.phtml'
				)),
				'Form'
			),
			'elements'		=>	array(
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
						'font'		=>	APPLICATION_PATH . '/resources/fonts/arial.ttf',
						'imgDir'	=>	PUBLIC_DIR . '/img/captcha/',
						'imgUrl'	=>	'/img/captcha/'
					),
					'class'			=>	'span5',
					'decorators'	=>	array(
						'captcha'
					),
				)),
			)
		));
	}
}