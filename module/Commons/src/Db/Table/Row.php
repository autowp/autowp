<?php

namespace Autowp\Commons\Db\Table;

use DateTime;
use DateTimeZone;
use Exception;

class Row
{
    public static function getDateTimeByColumnType($type, $value)
    {
        switch ($type) {
            case 'date':
                $format = 'Y-m-d H:i:s';
                $value .= '00:00:00';
                break;

            case 'datetime':
            case 'timestamp':
                $format = 'Y-m-d H:i:s';
                break;

            default:
                throw new Exception('Column type not a date type');
        }

        if (! $value) {
            return null;
        }

        //TODO: extract constant
        $timezone = new DateTimeZone(MYSQL_TIMEZONE);

        return DateTime::createFromFormat($format, $value, $timezone);
    }
}
