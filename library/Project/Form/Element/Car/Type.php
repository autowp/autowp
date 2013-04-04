<?php

class Project_Form_Element_Car_Type extends Project_Form_Element_Select_Db_Table_Tree
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Тип кузова';

    public function __construct($spec, $options = null)
    {
        $this->_table = new Car_Types();
        $this->_valueField = 'id';
        $this->_viewField = 'name';
        $this->_parentField = 'parent_id';
        $this->_select = array(
            'order' =>  'position'
        );
        $this->_nonename = '--';

        parent::__construct($spec, $options);
    }
}