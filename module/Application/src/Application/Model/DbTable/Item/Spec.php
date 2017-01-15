<?php

namespace Application\Model\DbTable\Item;

use Application\Db\Table;

class Spec extends Table
{
    protected $_name = 'vehicle_spec';
    protected $_primary = ['vehicle_id', 'spec_id'];
}
