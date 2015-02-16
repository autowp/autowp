<?php

class Project_Validate_Attrs_FloatOrNull extends Zend_Validate_Float
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