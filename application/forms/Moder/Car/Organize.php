<?php

class Application_Form_Moder_Car_Organize extends Application_Form_Moder_Car_New
{
    protected $_childOptions = array();

    public function setChildOptions(array $options)
    {
        $this->_childOptions = $options;

        return $this;
    }

    public function init()
    {
        parent::init();

        $this->addElements(array(
            array('checkbox', 'is_group', array(
                'required'     => false,
                'label'        => 'Группа',
                'decorators'   => array('ViewHelper'),
                'disabled'     => true,
                'order'        => 10,
            )),
            array('MultiCheckbox', 'childs', array(
                'label'        => 'Автомобили',
                'required'     => true,
                'id'           => 'car_caption',
                'decorators'   => array('ViewHelper'),
                'multioptions' => $this->_childOptions,
                'label_class'  => 'checkbox',
                'separator'    => '',
                'order'        => 11,
            )),
        ));
    }
}