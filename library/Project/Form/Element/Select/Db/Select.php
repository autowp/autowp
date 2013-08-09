<?php
class Project_Form_Element_Select_Db_Select extends Project_Form_Element_Select_Db
{
    /**
     * @var Zend_Db_Select $_select
     */
    protected $_select = null;

    public function setSelect(Zend_Db_Select $select)
    {
        $this->_select = $select;
    }

    public function init()
    {
        parent::init();

        $this->clearMultiOptions();

        if (!$this->_required)
            $this->addMultiOption('', $this->_nonename);
        foreach ($this->_select->query()->fetchAll() as $row) {
            $row = array_values($row);
            $this->addMultiOption($row[0], isset($row[1]) ? $row[1] : $row[0]);
        }
    }
}
