<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class Zone extends Table
{
    protected $_name = 'attrs_zones';
    protected $_rowClass = \Application\Model\DbTable\Attr\ZoneRow::class;
}
