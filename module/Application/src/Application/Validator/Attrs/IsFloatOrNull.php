<?php

namespace Application\Validator\Attrs;

use Zend\I18n\Validator\IsFloat;
use Application\Service\SpecificationsService;

class IsFloatOrNull extends IsFloat
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
