<?php

class Application_Form_Moder_Attrs_Attribute extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setMethod('post');

        $types = new Attrs_Types();
        $units = new Attrs_Units();

        $this->addElements(array(
            array('text', 'name', array(
                'required' => true,
                'label'    => 'Название',
                'class'    => 'form-control'
            )),
            array('Select_Db_Select', 'type_id', array(
                'required' => false,
                'select'   => $types->select()->from($types, array('id', 'name')),
                'label'    => 'Тип',
                'class'    => 'form-control'
            )),
            array('uint', 'precision', array(
                'required' => false,
                'label'    => 'Точность (для float аттрибута)',
                'class'    => 'form-control'
            )),
            array('Select_Db_Select', 'unit_id', array(
                'required' => false,
                'select'   => $units->select()->from($units, array('id', 'name')),
                'label'    => 'Единица измерения',
                'class'    => 'form-control'
            )),
            array('textarea', 'description', array(
                'required' => false,
                'label'    => 'Описание',
                'rows'     => 3,
                'cols'     => 30,
                'class'    => 'form-control'
            )),
            array('submit', 'send', array(
                'required' => false,
                'ignore'   => true,
                'label'    => 'сохранить',
                'class'    => 'btn btn-primary'
            ))
        ));
    }
}