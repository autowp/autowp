<?php

class Application_Form_Account_AddAccount extends Project_Form
{
    protected $_typeMultioptions = array();

    public function setTypeMultioptions(array $options)
    {
        $this->_typeMultioptions = $options;
    }

    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/account/add-account.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('select', 'type', array(
                    'label'        => 'Добавить учётную запись',
                    'required'     => true,
                    'multioptions' => $this->_typeMultioptions,
                    'decorators'   => array('ViewHelper')
                ))
            ),
        ));
    }
}