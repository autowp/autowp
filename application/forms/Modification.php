<?php

class Application_Form_Modification extends Project_Form
{
    private $_groupOptions = [];

    public function setGroupOptions(array $options)
    {
        $this->_groupOptions = $options;

        return $this;
    }

    public function init()
    {
        parent::init();

         $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements' => array(
                array('select', 'group_id', array(
                    'label'        => 'Группа',
                    'required'     => false,
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $this->_groupOptions,
                )),
                array('text', 'name', array(
                    'label'       => 'Название',
                    'required'    => true,
                    'filters'     => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper'),
                )),
            )
        ));
    }
}