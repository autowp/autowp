<?php

namespace Application\Validator\Attrs;

use Application\Service\SpecificationsService;
use Laminas\I18n\Validator\IsFloat;

class IsFloatOrNull extends IsFloat
{
    public function isValid($value)
    {
        if ($value === SpecificationsService::NULL_VALUE_STR) {
            return true;
        } else {
            return parent::isValid($value);
        }
    }
}
