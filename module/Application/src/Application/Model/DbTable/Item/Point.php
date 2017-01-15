<?php

namespace Application\Model\DbTable\Item;

use Application\Db\Table;

class Point extends Table
{
    protected $_name = 'item_point';
    protected $_primary = ['item_id'];
}
