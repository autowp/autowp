<?php

namespace Application\Validator\Attrs;

use Zend\I18n\Validator\IsFloat;

use Application_Service_Specifications;

class IsFloatOrNull extends IsFloat
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