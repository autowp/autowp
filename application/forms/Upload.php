<?php

class Application_Form_Upload extends Project_Form
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
                array ('file', 'picture', array (
                    'label'       => 'Файл картинки',
                    'required'    => true,
                    'validators'  => array(
                        array('Count', true, 1),
                        array('Size',  true, $this->_maxFileSize),
                        //array('IsImage',    true),
                        array('Extension',  true, 'jpg,jpeg,jpe'),
                        array('ImageSizeInArray',  true, array(
                            'sizes' => Picture::getResolutions()
                        )),
                    ),
                    'MaxFileSize' => $this->_maxFileSize,
                    'decorators'  => array('File'),
                )),
                array ('text', 'note', array (
                    'required'    => false,
                    'label'       => 'Примечание к картинке',
                    'size'        => 50,
                    'maxlength'   => 255,
                    'filters'     => array('StringTrim'),
                    'validators'  => array(
                        array('StringLength', true, array(3, 255))
                    ),
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));
    }
}