<?php

namespace Application\Model\DbTable\Item;

use Application\Db\Table;

class Type extends Table
{
    protected $_name = 'item_type';
    protected $_primary = 'id';

    const VEHICLE  = 1,
          ENGINE   = 2,
          CATEGORY = 3,
          TWINS    = 4,
          BRAND    = 5,
          FACTORY  = 6,
          MUSEUM   = 7;
}
