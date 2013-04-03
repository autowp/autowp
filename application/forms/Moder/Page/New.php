<?php

class Application_Form_Moder_Page_New extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'        => 'post',
            'decorators'    => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript'    => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'        => array(
                array('Select_Db_Table_Tree', 'parent_id', array (
                    'label'       => 'Родитель',
                    'required'    => false,
                    'table'       => new Pages(),
                    'viewField'   => 'name',
                    'valueField'  => 'id',
                    'parentField' => 'parent_id',
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'name', array (
                    'label'       => 'Название',
                    'required'    => true,
                    'filter'      => array('StringTrim', 'SingleSpaces'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'title', array (
                    'label'       => 'Title',
                    'required'    => false,
                    'filter'      => array('StringTrim', 'SingleSpaces'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'url', array (
                    'label'       => 'URL',
                    'required'    => false,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'breadcrumbs', array (
                    'label'       => 'Breadcrumbs',
                    'required'    => false,
                    'filter'      => array('StringTrim', 'SingleSpaces'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'is_group_node', array (
                    'label'       => 'Группообразующий узел?',
                    'required'    => false,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'registered_only', array (
                    'label'       => 'Только для зарегистрированных?',
                    'required'    => false,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'guest_only', array (
                    'label'       => 'Только для гостей?',
                    'required'    => false,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'inherit_blocks', array (
                    'label'       => 'Наследовать блоки?',
                    'required'    => false,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'class', array (
                    'label'       => 'Класс',
                    'required'    => false,
                    'decorators'  => array('ViewHelper')
                )),
                array('submit', 'Создать', array (
                    'required'    => false,
                    'decorators'  => array('ViewHelper'),
                )),
            )
        ));
    }
}