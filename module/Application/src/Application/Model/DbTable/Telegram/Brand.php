<?php

namespace Application\Model\DbTable\Telegram;

use Zend_Db_Table;

class Brand extends Zend_Db_Table
{
    protected $_name = 'telegram_brand';
    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ],
    ];
}