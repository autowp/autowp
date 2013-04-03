<?php

class Application_Form_Moder_Brand_Edit extends Project_Form
{
    protected $_languages = array();

    public function setLanguages(array $languages)
    {
        $this->_languages = $languages;
    }

    public function init()
    {
        parent::init();

        $elements = array(
            array('Brand_Name', 'caption', array(
                'required'   => true,
                'size'       => 60,
                'decorators' => array('ViewHelper'),
                'readonly'   => 'readonly'
            )),
        );

        foreach ($this->_languages as $language) {
            $elements[] = array('Brand_Name', 'name'.$language, array (
                'label'      => 'Name ('.$language.')',
                'required'   => false,
                'decorators' => array('ViewHelper'),
            ));
        }

        $elements = array_merge($elements, array(
            array ('Brand_FullName', 'full_caption', array (
                'required'   => false,
                'decorators' => array('ViewHelper'),
            )),
            array ('Brand_Description', 'description', array (
                'required'   => false,
                'decorators' => array('ViewHelper'),
            )),
        ));

        $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'  => $elements
        ));
    }
}