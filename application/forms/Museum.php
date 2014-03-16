<?php

class Application_Form_Museum extends Project_Form
{
    protected $_maxFileSize = 4194304; //1024*1024*4;

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
            'elements' => array(
                array('text', 'name', array(
                    'label'       => 'Название',
                    'required'    => true,
                    'filters'     => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper'),
                    'class'       => 'span6'
                )),
                array('text', 'url', array(
                    'label'       => 'URL',
                    'required'    => false,
                    'filters'     => array('StringTrim'),
                    'validators'  => array('Url'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper'),
                    'class'       => 'span6'
                )),
                array('text', 'address', array(
                    'required'    => true,
                    'label'       => 'Адрес',
                    'maxlength'   => 255,
                    'size'        => 80,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper'),
                    'class'       => 'span6'
                )),
                array('text', 'lat', array(
                    'required'    => false,
                    'label'       => 'Latitude',
                    'maxlength'   => 20,
                    'size'        => 20,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'lng', array(
                    'required'    => false,
                    'label'       => 'Longtitude',
                    'maxlength'   => 20,
                    'size'        => 20,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper')
                )),
                array('textarea', 'description', array(
                    'required'    => false,
                    'label'       => 'Описание',
                    'cols'        => 80,
                    'rows'        => 8,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper'),
                    'class'       => 'span6'

                )),
                array('file', 'photo', array(
                    'required'    => false,
                    'label'       => 'Фотография',
                    'validators'  => array(
                        array('Count',      true, 1),
                        array('Size',       true, $this->_maxFileSize),
                        array('IsImage',    true),
                        array('Extension',  true, 'jpg,jpeg,jpe,png,gif,bmp'),
                    ),
                    'MaxFileSize' => $this->_maxFileSize,
                    'decorators'  => array('File'),
                )),
            )
        ));
    }
}