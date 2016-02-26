<?php

class Application_Form_Moder_Category_Organize extends Project_Form
{
    private $childOptions = [];

    public function setChildOptions(array $options)
    {
        $this->childOptions = $options;

        return $this;
    }

    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'      => 'post',
            'decorators'  => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'legend'      => 'Группировка',
            'elements'    => array(
                array('MultiCheckbox', 'childs', array(
                    'label'      => 'Автомобили',
                    'required'   => true,
                    'id'         => 'car_caption',
                    'decorators' => array('ViewHelper'),
                    'multioptions' => $this->childOptions,
                    'label_class'  => 'checkbox',
                    'separator'    => ''
                )),
                array('Car_Caption', 'caption', array(
                    'required'   => true,
                    'id'         => 'car_caption',
                    'validators' => array('Car_NameNotExists'),
                    'decorators' => array('ViewHelper')
                )),
                array('Car_Body', 'body', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
                array('year', 'begin_year', array(
                    'required'   => false,
                    'label'      => 'Выпускалась с',
                    'decorators' => array('ViewHelper')
                )),
                array('year', 'end_year', array(
                    'required'   => false,
                    'label'      => 'Выпускалась по',
                    'decorators' => array('ViewHelper')
                )),
                array('select', 'today', array(
                    'required'     => false,
                    'label'        => 'наше время',
                    'multioptions' => array(
                        '0' => '--',
                        '1' => 'выпуск закончен',
                        '2' => 'производится в н.в.'
                    ),
                    'decorators'   => array('ViewHelper'),
                )),
                array('Car_Type', 'car_type_id', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
                array('hidden', 'is_group', array(
                    'required'     => true,
                    'label'        => 'Группа',
                    'decorators'   => array('ViewHelper')
                )),
            )
        ));
    }
}