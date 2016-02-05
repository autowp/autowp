<?php

class Application_Form_Account_Profile extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'enctype'    => 'multipart/form-data',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('text', 'name', array(
                    'required'   => true,
                    'label'      => 'login/name',
                    'maxlength'  => 30,
                    'size'       => 30,
                    'decorators' => array('ViewHelper')
                )),
                /*array('text', 'icq', array(
                    'required'   => false,
                    'label'      => 'ICQ',
                    'filters'    => array('Digits'),
                    'maxlength'  => 30,
                    'size'       => 30,
                    'decorators' => array('ViewHelper')
                )),*/
                /*array('text', 'url', array(
                    'required'   => false,
                    'label'      => 'Web-сайт',
                    'filters'    => array('StringTrim'),
                    'maxlength'  => 50,
                    'size'       => 50,
                    'decorators' => array('ViewHelper')
                )),
                array('text', 'own_car', array(
                    'required'   => false,
                    'label'      => 'Автомобиль (имеющийся)',
                    'filters'    => array('StringTrim'),
                    'maxlength'  => 100,
                    'size'       => 80,
                    'decorators' => array('ViewHelper')

                )),
                array('text', 'dream_car', array(
                    'required'   => false,
                    'label'      => 'Автомобиль (желаемый)',
                    'filters'    => array('StringTrim'),
                    'maxlength'  => 100,
                    'size'       => 80,
                    'decorators' => array('ViewHelper')
                )),*/
            ),
        ));
    }
}