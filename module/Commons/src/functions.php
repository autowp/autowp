<?php

namespace Autowp\Commons;

use ArrayObject;
use Exception;
use Laminas\Db\ResultSet\AbstractResultSet;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Location\Coordinate;

use function unpack;

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

function parsePointWkb(string $str): ?Coordinate
{
    if (! $str) {
        return null;
    }
    $coordinates = unpack('x/x/x/x/corder/Ltype/dlng/dlat', $str);
    return new Coordinate($coordinates['lat'], $coordinates['lng']);
}
