<?php

class Application_Form_Moder_Acl_Role_Parent_Add extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Добавить родителя',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('Select_Db_Table', 'role_id', array(
                    'label'      => 'Роль',
                    'required'   => true,
                    'table'      => new Acl_Roles(),
                    'viewField'  => 'name',
                    'valueField' => 'id',
                    'decorators' => array('ViewHelper')
                )),
                array('Select_Db_Table', 'parent_role_id', array(
                    'label'      => 'Родительская роль',
                    'required'   => true,
                    'table'      => new Acl_Roles(),
                    'viewField'  => 'name',
                    'valueField' => 'id',
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}