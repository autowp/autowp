<?php

class Project_Form_Element_Car_Spec extends Project_Form_Element_Select_Db_Table_Tree
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Spec';

    public function __construct($spec, $options = null)
    {
        $this->_table = new Spec();
        $this->_valueField = 'id';
        $this->_viewField = 'short_name';
        $this->_parentField = 'parent_id';
        $this->_select = array(
            'order' =>  'short_name'
        );
        $this->_nonename = '--';

        parent::__construct($spec, $options);
    }
}