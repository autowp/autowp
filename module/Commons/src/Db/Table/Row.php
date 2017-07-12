<?php

namespace Autowp\Commons\Db\Table;

use Zend_Db_Table_Abstract;
use Zend_Db_Table_Row;

use DateTime;
use DateTimeZone;
use Exception;

class Row extends Zend_Db_Table_Row
{
    public function getDateTime($col)
    {
        $metadata = $this->getTable()->info(Zend_Db_Table_Abstract::METADATA);
        if (! isset($metadata[$col])) {
            throw new Exception('Column '.$col.' not found');
        }

        return self::getDateTimeByColumnType($metadata[$col]['DATA_TYPE'], $this[$col]);
    }

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
