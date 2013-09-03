<?php

class Project_Validate_Brand_NameNotExists extends Zend_Validate_Abstract
{
    const EXISTS = 'brandNameAlreadyExists';

    public function isValid($value, $context = null)
    {
        $this->_messages = array();

        $brands = new Brands();
        $row = $brands->fetchRowByCaption($value);
        if ($row) {
            $this->_messages[self::EXISTS] = sprintf("Бренд с названием '%s' уже существует", $row->caption);
            return false;
        }
        return true;
    }
}