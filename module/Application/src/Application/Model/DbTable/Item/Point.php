<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;

class Point extends Table
{
    protected $_name = 'item_point';
    protected $_primary = ['item_id'];
}
