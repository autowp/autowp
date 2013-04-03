<?php

class Application_Form_Moder_Acl_Rule extends Project_Form
{
    public function init()
    {
        parent::init();

        $multioptions = array();
        $resources = new Acl_Resources();
        foreach ($resources->fetchAll() as $resource) {
            foreach ($resource->findAcl_Resources_Privileges() as $privilege) {
                $multioptions[$privilege->id] = $resource->name . ' / ' . $privilege->name;
            }
        }

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Добавить правило',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('Select_Db_Table', 'role_id', array (
                    'label'        => 'Роль',
                    'required'     => true,
                    'table'        => new Acl_Roles(),
                    'viewField'    => 'name',
                    'valueField'   => 'id',
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'privilege_id', array (
                    'label'        => 'Привелегия',
                    'required'     => true,
                    'multioptions' => $multioptions,
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'what', array (
                    'label'        => 'Действие',
                    'required'     => true,
                    'multioptions' => array (
                        '0' => 'запретить',
                        '1' => 'разрешить'
                    ),
                    'decorators'   => array('ViewHelper')
                )),
            )
        ));
    }
}