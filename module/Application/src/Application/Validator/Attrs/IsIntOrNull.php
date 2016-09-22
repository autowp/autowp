<?php

namespace Application\Validator\Attrs;

use Zend\I18n\Validator\IsInt;

use Application\Service\SpecificationsService;

class IsIntOrNull extends IsInt
{
    public function isValid($value)
    {
        if ($value == SpecificationsService::NULL_VALUE_STR) {
            return true;
        } else {
            return parent::isValid($value);
        }
    }
}