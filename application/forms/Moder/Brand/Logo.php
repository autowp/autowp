<?php

class Application_Form_Moder_Brand_Logo extends Project_Form
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
                    'viewScript' => 'forms/bootstrap-vertical.phtml'
                )),
                'Form'
            ),
            'elements'  => array(
                array('file', 'logo', array(
                    'label'       => 'Логотип',
                    'required'    => true,
                    'validators'  => array(
                        array('Count',     true, 1),
                        array('Size',      true, $this->_maxFileSize),
                        //array('IsImage',   true),
                        array('Extension', false, 'png'),
                        array('ImageSize', false, array(
                            'minheight' => 50,
                            'minwidth'  => 50
                        )),
                    ),
                    'MaxFileSize' => $this->_maxFileSize,
                    'decorators'  => array('File')
                )),
            )
        ));
    }
}