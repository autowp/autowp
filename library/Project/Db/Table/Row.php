<?php

class Project_Db_Table_Row extends Zend_Db_Table_Row
{
    public function getDate($col)
    {
        $metadata = $this->getTable()->info(Zend_Db_Table_Abstract::METADATA);
        if (!isset($metadata[$col])) {
            throw new Exception('Column '.$col.' not found');
        }

        $result = null;
        switch ($metadata[$col]['DATA_TYPE']) {
            case 'date':
                $format = 'yyyy-MM-dd';
                break;

            case 'datetime':
            case 'timestamp':
                $format = 'yyyy-MM-dd HH:mm:ss';
                break;

            default:
                throw new Exception('Column type not a date type');
        }

        if (!$this[$col]) {
            return null;
        }

        return new Zend_Date($this[$col], $format);
    }

    public function getDateTime($col)
    {
        $metadata = $this->getTable()->info(Zend_Db_Table_Abstract::METADATA);
        if (!isset($metadata[$col])) {
            throw new Exception('Column '.$col.' not found');
        }

        $result = null;
        switch ($metadata[$col]['DATA_TYPE']) {
            case 'date':
                $format = 'Y-m-d';
                break;

            case 'datetime':
            case 'timestamp':
                $format = 'Y-m-d H:i:s';
                break;

            default:
                throw new Exception('Column type not a date type');
        }

        if (!$this[$col]) {
            return null;
        }

        $tz = new DateTimeZone(MYSQL_TIMEZONE);

        return DateTime::createFromFormat($format, $this[$col], $tz);
    }
}