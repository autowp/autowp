<?php

class Application_Form_Moder_Car_OrganizePictures extends Application_Form_Moder_Car_New
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
            array('hidden', 'is_group', array(
                'required'     => false,
                'label'        => 'Группа',
                'decorators'   => array('ViewHelper'),
                'readonly'     => true,
                'order'        => 11,
            )),
            array('MultiCheckbox', 'childs', array(
                'label'        => 'Изображения',
                'required'     => true,
                'decorators'   => array('ViewHelper'),
                'multioptions' => $this->_childOptions,
                'label_class'  => 'checkbox',
                'separator'    => '',
                'order'        => 12,
                'decorators'   => array(
                    array('ViewScript', array(
                        'viewScript' => 'element/multicheckbox-pictures.phtml'
                    )),
                )
            )),
        ));
    }
}