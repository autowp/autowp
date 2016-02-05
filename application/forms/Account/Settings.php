<?php

class Application_Form_Account_Settings extends Project_Form
{
    private $_timezones = [];
    
    private $_languages = [];

    public function setTimezoneList(array $list)
    {
        $this->_timezones = $list;

        return $this;
    }
    
    public function setLanguages(array $languages)
    {
        $this->_languages = $languages;
        
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
                array('select', 'language', array(
                    'required'     => true,
                    'label'        => 'account/profile/language',
                    'multioptions' => $this->_languages,
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'timezone', array(
                    'required'     => true,
                    'label'        => 'account/profile/timezone',
                    'multioptions' => array_combine($this->_timezones, $this->_timezones),
                    'decorators'   => array('ViewHelper')
                )),
                
            ),
        ));
    }
}