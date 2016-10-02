<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;

class Language extends Table
{
    protected $_name = 'car_language';
    protected $_primary = ['car_id', 'language'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ],
    ];
}