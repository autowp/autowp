<?php

namespace Application\Validator\Attrs;

use Application\Service\SpecificationsService;
use Laminas\I18n\Validator\IsFloat;

class IsFloatOrNull extends IsFloat
{
    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        if ($value === SpecificationsService::NULL_VALUE_STR) {
            return true;
        } else {
            return parent::isValid($value);
        }
    }
}
