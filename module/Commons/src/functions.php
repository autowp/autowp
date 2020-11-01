<?php

namespace Autowp\Commons;

use ArrayObject;
use Exception;
use Laminas\Db\ResultSet\AbstractResultSet;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;

/**
 * @throws Exception
 * @return null|array|ArrayObject
 */
function currentFromResultSetInterface(ResultSetInterface $resultSet)
{
    if ($resultSet instanceof ResultSet) {
        return $resultSet->current();
    }

    if ($resultSet instanceof AbstractResultSet) {
        return $resultSet->current();
    }

    throw new Exception("AbstractResultSet expected");
}
