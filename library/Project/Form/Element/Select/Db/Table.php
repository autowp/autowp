<?php

class Project_Form_Element_Select_Db_Table extends Project_Form_Element_Select_Db
{
    /**
     * @var Zend_Db_Table
     */
    protected $_table = null;
    protected $_valueField = null;
    protected $_viewField = null;
    protected $_select = null;

    public function setTable(Zend_Db_Table $table)
    {
        $this->_table = $table;
    }

    public function setViewField($field)
    {
        $this->_viewField = $field;
    }

    public function setValueField($field)
    {
        $this->_valueField = $field;
    }

    public function setSelect(array $select)
    {
        $this->_select = $select;
    }

    protected function fillMultioptions()
    {
        if (!$this->_table) {
            throw new Exception('Таблица не задана');
        }

        if (!$this->_required) {
            $this->addMultiOption('', $this->_nonename);
        }

        $select = $this->_table->select();
        if (isset($this->_select['order'])) {
            $select->order($this->_select['order']);
        }

        if (isset($this->_select['where'])) {
            foreach ((array)$this->_select['where'] as $where) {
                $where = (array)$where;
                $operand = null;
                $expr = $where[0];
                if (count($where) > 1) {
                    $operand = $where[1];
                }
                $select->where($expr, $operand);
            }
        }

        foreach ($select->query()->fetchAll() as $row) {
            $this->addMultiOption($row[$this->_valueField], $row[$this->_viewField]);
        }
    }

    public function init()
    {
        parent::init();

        $this->addFilter(new Project_Filter_IntOrNull());

        $this->clearMultiOptions();
        $this->fillMultioptions();
    }
}
