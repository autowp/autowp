<?php

namespace Application\Model\DbTable\Brand;

use Zend_Db_Table;

class VehicleLanguage extends Zend_Db_Table
{
    protected $_name = 'brand_vehicle_language';
    protected $_primary = ['brand_id', 'vehicle_id', 'language'];

    const MAX_NAME = 70;
}
