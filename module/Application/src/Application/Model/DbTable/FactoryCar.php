<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class FactoryCar extends Table
{
    protected $_name = 'factory_item';
    protected $_primary = ['factory_id', 'item_id'];

    protected $_referenceMap = [
        'Factory' => [
            'columns'       => ['factory_id'],
            'refTableClass' => Factory::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => Item::class,
            'refColumns'    => ['id']
        ],
    ];
}
