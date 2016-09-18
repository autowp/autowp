<?php

namespace Application\Validator\Attrs;

use Zend\I18n\Validator\IsInt;

use Application_Service_Specifications;

class IsIntOrNull extends IsInt
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