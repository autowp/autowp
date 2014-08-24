<?php

class Project_Form_Element_Select_Db_Table_Tree extends Project_Form_Element_Select_Db_Table
{
    protected $_parentField = null;

    public function setParentField($field)
    {
        $this->_parentField = $field;
    }

    protected function fillMultioptions()
    {
        if (!$this->_table)
            throw new Exception('Таблица не задана');

        if (!$this->_parentField)
            throw new Exception('Не задано поле ссылки на родителя');

        if (!$this->_required)
            $this->addMultiOption('');
        $this->doLoadRows();
    }

    protected function doLoadRows($parent = null, $deep = 0)
    {
        if ($deep > 10)
            throw new Exception('Достигнута максимальная глубина рекурсии');

        if (!$this->_required)
            $this->addMultiOption('', $this->_nonename);

        $select = $this->_table->select();
        if (isset($this->_select['order']))
            $select->order($this->_select['order']);

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

        if (is_null($parent))
            $select->where($this->_parentField . ' is null');
        else
            $select->where($this->_parentField . ' = ?', $parent);


        foreach ($select->query()->fetchAll() as $row)
        {
            $this->addMultiOption($row[$this->_valueField], str_repeat('...', $deep) . ' ' . $row[$this->_viewField]);
            $this->doLoadRows($row[$this->_valueField], $deep+1);
        }
    }
}
