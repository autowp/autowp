<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class BrandLink extends Table
{
    protected $_name = 'links';
    protected $_primary = 'id';

    protected $_referenceMap = [
        'Item' => [
            'columns'       => ['item_id'],
            'refTableClass' => Vehicle::class,
            'refColumns'    => ['id']
        ]
    ];
}
