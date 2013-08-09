<?php

class Application_Form_Moder_Page_Edit extends Project_Form
{
    protected $_languages = array();

    public function setLanguages(array $languages)
    {
        $this->_languages = $languages;
    }

    public function init()
    {
        parent::init();

        $subForms = array();
        foreach ($this->_languages as $i => $language) {
            $subForms[] = array(
                array(
                    'description' => 'Fields for ' . $language,
                    'legend'      => $language,
                    'decorators'  => array(
                        'PrepareElements',
                        array('viewScript', array(
                            'viewScript' => 'forms/bootstrap-horizontal.phtml'
                        )),
                    ),
                    'elements'    => array(
                        array('text', 'name', array (
                            'label'      => 'Название',
                            'required'   => false,
                            'filter'     => array('StringTrim'),
                            'maxlength'  => 255,
                            'decorators' => array('ViewHelper')
                        )),
                        array('text', 'title', array (
                            'label'      => 'Title',
                            'required'   => false,
                            'filter'     => array('StringTrim'),
                            'maxlength'  => 255,
                            'decorators' => array('ViewHelper')
                        )),
                        array('text', 'breadcrumbs', array (
                            'label'      => 'Breadcrumbs',
                            'required'   => false,
                            'filter'     => array('StringTrim'),
                            'maxlength'  => 100,
                            'decorators' => array('ViewHelper')
                        )),
                    )
                ),
                $language,
                $i + 5
            );
        }

        $lskip = count($this->_languages) + 5;

        $this->setOptions(array(
            'method'            => 'post',
            'decorators'        => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'subForms'          => $subForms,
            'elements'          => array(
                array('Select_Db_Table_Tree', 'parent_id', array (
                    'label'       => 'Родитель',
                    'required'    => false,
                    'table'       => new Pages(),
                    'viewField'   => 'name',
                    'valueField'  => 'id',
                    'parentField' => 'parent_id',
                    'order'       => 1,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'name', array (
                    'label'       => 'Название',
                    'required'    => true,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 2,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'title', array (
                    'label'       => 'Title',
                    'required'    => false,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 3,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'breadcrumbs', array (
                    'label'       => 'Breadcrumbs',
                    'required'    => false,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 4,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'url', array (
                    'label'       => 'URL',
                    'required'    => false,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => $lskip + 1,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'is_group_node', array (
                    'label'       => 'Группообразующий узел?',
                    'required'    => false,
                    'order'       => $lskip + 2,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'registered_only', array (
                    'label'       => 'Только для зарегистрированных?',
                    'required'    => false,
                    'order'       => $lskip + 3,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'guest_only', array (
                    'label'       => 'Только для гостей?',
                    'required'    => false,
                    'order'       => $lskip + 4,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'inherit_blocks', array (
                    'label'       => 'Наследовать блоки?',
                    'required'    => false,
                    'order'       => $lskip + 5,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'class', array (
                    'label'       => 'Класс',
                    'required'    => false,
                    'order'       => $lskip + 6,
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));

        $this->addSubForms($subForms);
    }
}