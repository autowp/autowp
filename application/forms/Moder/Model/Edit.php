<?php

class Application_Form_Moder_Model_Edit extends Project_Form
{
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
            'elements'  => array(
                array('Model_Name', 'name', array(
                    'required'     => true,
                    'decorators'   => array('ViewHelper')
                )),
                array('Model_BeginYear', 'begin_year', array(
                    'required'     => false,
                    'decorators'   => array('ViewHelper')
                )),
                array('Model_EndYear', 'end_year', array(
                    'required'     => false,
                    'decorators'   => array('ViewHelper')
                )),
                array('MultiCheckbox', 'layout', array(
                    'required'     => false,
                    'label'        => 'Раскладывать по',
                    'multioptions' => array(
                        'concepts' => 'концепты отдельно',
                        'body'     => 'по номерам кузова',
                        'shape'    => 'по типу кузова',
                        'year'     => 'по годам',
                        'tenyears' => 'по десяткам лет'
                    ),
                    'decorators'   => array('ViewHelper'),
                    'separator'    => '',
                    'label_class'  => 'checkbox'
                )),
                array('submit', 'send', array(
                    'required'     => false,
                    'ignore'       => true,
                    'label'        => 'Сохранить',
                    'decorators'   => array('ViewHelper')
                ))
            ),
        ));
    }
}