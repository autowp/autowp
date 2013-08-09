<?php

class Application_Form_Moder_Attrs_List_Option extends Project_Form
{
    protected $_attribute;

    public function setAttribute($attribute)
    {
        $this->_attribute = $attribute;

        return $this;
    }

    public function init()
    {
        parent::init();

        $this->setMethod('post');

        $listOptions = new Attrs_List_Options();

        $this->addElements(array(
            array('Select_Db_Table_Tree', 'parent_id', array(
                'required'    => false,
                'label'       => 'Родитель',
                'table'       => $listOptions,
                'parentField' => 'parent_id',
                'valueField'  => 'id',
                'viewField'   => 'name',
                'select'      => array(
                    'order' => 'position',
                    'where' => array(
                        array('attribute_id = ?', $this->_attribute->id)
                    )
                ),
                'class'       => 'form-control'
            )),
            array('text', 'name', array(
                'required'    => true,
                'label'       => 'Название',
                'class'       => 'form-control'
            )),
            array('submit', 'send', array(
                'required'    => false,
                'ignore'      => true,
                'label'       => 'сохранить',
                'class'       => 'btn btn-primary'
            ))
        ));
    }
}