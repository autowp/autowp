<?php
require_once 'Zend/Validate/Interface.php';

class Project_Validate_Tablekey extends Zend_Validate_Abstract
{
    const NOT_FOUND = 'notFound';

    /**
     * @var Zend_Db_Table
     */
    protected $table = null;

    public function __construct(Zend_Db_Table $table)
    {
        $this->table = $table;
    }

    public function isValid($value)
    {
        $this->_messages = array();

        $row = $this->table->find($value)->current();

        if (!$row) {
            $this->_messages[] = "Запись в справочнике не найдена";
            return false;
        }

        return true;
    }
}