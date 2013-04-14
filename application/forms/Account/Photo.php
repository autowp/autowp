<?php

class Application_Form_Account_Photo extends Project_Form
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
            'elements'   => array(
                array('file', 'photo', array (
                    'label'       => 'Фотография',
                    'required'    => true,
                    'validators'  => array(
                        array('Count', true, 1),
                        array('Size', true, $this->_maxFileSize),
                        array('IsImage', true),
                        array('Extension', true, 'jpg,jpeg,jpe,png,gif,bmp')
                    ),
                    'MaxFileSize' => $this->_maxFileSize,
                    'decorators'  => array('File'),
                )),
            ),
        ));
    }
}