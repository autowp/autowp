<?php

class Project_Validate_Attrs_IntOrNull extends Zend_Validate_Int
{
    public function isValid($value)
    {
        if ($value == Application_Service_Specifications::NULL_VALUE_STR) {
            return true;
        } else {
            return parent::isValid($value);
        }
    }
}