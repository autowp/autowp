<?php

class Application_Form_Moder_Car_Organize extends Application_Form_Moder_Car_New
{
    protected $childOptions = array();

    public function setChildOptions(array $options)
    {
        $this->childOptions = $options;

        return $this;
    }

    public function init()
    {
        parent::init();

        $this->addElements(array(
            array('hidden', 'is_group', array(
                'required'     => false,
                'label'        => 'Группа',
                'decorators'   => array('ViewHelper'),
                'readonly'     => true,
                'order'        => 11,
            )),
            array('MultiCheckbox', 'childs', array(
                'label'        => 'Автомобили',
                'required'     => true,
                'id'           => 'car_caption',
                'decorators'   => array('ViewHelper'),
                'multioptions' => $this->childOptions,
                'label_class'  => 'checkbox',
                'separator'    => '',
                'order'        => 12,
            )),
        ));
    }
}