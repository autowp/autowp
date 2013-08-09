<?php

class Application_Form_Attrs_ChangeLogFilter extends Project_Form
{
    public function init()
    {
        $this->setMethod('post');

        $options = array(
            ''  => 'все'
        );
        $table = new Users();
        $select = $table->select(true)
            ->join('attrs_user_values', 'users.id=attrs_user_values.user_id', null)
            ->group('users.id')
            ->order('users.id');
        foreach ($table->fetchAll($select) as $row) {
            $options[$row->id] = $row->getCompoundName();
        }

        $this->addElements(array(
            array('select', 'user_id', array(
                'required'     => false,
                'label'        => 'Пользователь',
                'multioptions' => $options,
                'class'        => 'form-control'
            )),
            array('submit', 'do-filter', array(
                'required'     => false,
                'label'        => 'Фильтровать',
                'ignore'       => true,
                'class'        => 'btn btn-primary'
            ))
        ));
    }
}