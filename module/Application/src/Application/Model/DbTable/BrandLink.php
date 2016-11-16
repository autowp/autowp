<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class BrandLink extends Table
{
    protected $_name = 'links';
    protected $_primary = 'id';

    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brandId'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ]
    ];
}
