<?php

class Application_Form_Moder_Category_Edit extends Project_Form
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
                        array('text', 'name', array(
                            'label'      => 'Name',
                            'required'   => true,
                            'filter'     => array('StringTrim'),
                            'maxlength'  => 255,
                            'size'       => 80,
                            'decorators' => array('ViewHelper')
                        )),
                        array('text', 'short_name', array(
                            'label'      => 'Short name',
                            'required'   => true,
                            'filter'     => array('StringTrim'),
                            'maxlength'  => 255,
                            'size'       => 80,
                            'decorators' => array('ViewHelper')
                        )),
                    )
                ),
                $language,
                $i + 6
            );
        }

        $lskip = count($this->_languages) + 6;

        $this->setOptions(array(
            'method'            => 'post',
            'decorators'        => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            //'action'        => $this->_helper->url->url(),
            'subForms'          => $subForms,
            'elements'          => array(
                array('Select_Db_Table_Tree', 'parent_id', array(
                    'label'       => 'Родитель',
                    'required'    => false,
                    'table'       => new Category(),
                    'viewField'   => 'short_name',
                    'valueField'  => 'id',
                    'parentField' => 'parent_id',
                    'order'       => 1,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'name', array(
                    'label'       => 'Name',
                    'required'    => true,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 2,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'short_name', array(
                    'label'       => 'Short name',
                    'required'    => true,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 3,
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'catname', array(
                    'label'       => 'Cat name',
                    'required'    => true,
                    'filter'      => array('StringTrim'),
                    'maxlength'   => 255,
                    'size'        => 80,
                    'order'       => 4,
                    'decorators'  => array('ViewHelper')
                )),
                array('checkbox', 'split_by_brand', array(
                    'label'       => 'Split by brand',
                    'required'    => false,
                    'order'       => 5,
                    'decorators'  => array('ViewHelper')
                )),
                array('button', 'send', array(
                    'type'        => 'submit',
                    'required'    => false,
                    'ignore'      => true,
                    'label'       => 'Сохранить',
                    'class'       => 'btn btn-success',
                    'order'       => $lskip,
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));
    }
}