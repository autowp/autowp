<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;

class Type extends Table
{
    protected $_name = 'car_types';

    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ],
    ];
}
