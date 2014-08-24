<?php

class Application_Form_Account_Settings extends Project_Form
{
    protected $_timezones = array();

    public function setTimezoneList(array $list)
    {
        $this->_timezones = $list;

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
            'elements'   => array(
                array('select', 'timezone', array(
                    'required'     => true,
                    'label'        => 'Часовой пояс',
                    'multioptions' => array_combine($this->_timezones, $this->_timezones),
                    'decorators'   => array('ViewHelper')
                )),
            ),
        ));
    }
}