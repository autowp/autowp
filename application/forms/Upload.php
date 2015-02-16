<?php

class Application_Form_Upload extends Project_Form
{
    protected $_maxFileSize = 20485760; //1024*1024*4;

    protected $_miltipleFiles = false;

    public function init()
    {
        parent::init();

        $pictureOptions = array (
            'label'       => 'Файл картинки',
            'required'    => true,
            'validators'  => array(
                array('Size', true, $this->_maxFileSize),
                array('IsImage', true),
                array('Extension', true, 'jpg,jpeg,jpe,png'),
                array('ImageSize', true, array(
                    'minwidth'  => 640,
                    'minheight' => 360,
                    'maxwidth'  => 4096,
                    'maxheight' => 4096
                )),
                /*array('ImageSizeInArray', true, array(
                    'sizes' => Picture::getResolutions()
                )),*/
            ),
            'MaxFileSize' => $this->_maxFileSize,
            'decorators'  => array('File'),
        );

        if ($this->_miltipleFiles) {
            $pictureOptions['multiple'] = 'multiple';
            $pictureOptions['isArray'] = true;
            array_unshift(
                $pictureOptions['validators'],
                array('Count', true, 1)
            );
        }

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
                array ('file', 'picture', $pictureOptions),
                array ('textarea', 'note', array (
                    'required'    => false,
                    'label'       => 'Примечание к изображению',
                    'filters'     => array('StringTrim'),
                    'rows'        => 3,
                    'validators'  => array(
                        array('StringLength', true, array(3, 4096))
                    ),
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));
    }

    /**
     * @param boolean $value
     * @return Application_Form_Upload
     */
    public function setMultipleFiles($value)
    {
        $this->_miltipleFiles = (bool)$value;

        return $this;
    }
}