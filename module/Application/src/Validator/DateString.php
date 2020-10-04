<?php

namespace Application\Validator;

use DateTime;
use DateTimeImmutable;
use Laminas\Validator\Date;

use function gettype;

class DateString extends Date
{
    /**
     * Attempts to convert an string to a DateTime object
     *
     * @param  string|int|array $param
     * @param  bool             $addErrors
     * @return bool|DateTime|DateTimeImmutable
     */
    protected function convertToDateTime($param, $addErrors = true)
    {
        if ($param instanceof DateTime || $param instanceof DateTimeImmutable) {
            return $param;
        }

        $type = gettype($param);
        if ($type !== 'string') {
            if ($addErrors) {
                $this->error(self::INVALID);
            }
            return false;
        }

        return $this->convertString($param, $addErrors);
    }
}
