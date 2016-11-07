<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;

class VehicleType extends Table
{
    protected $_name = 'vehicle_vehicle_type';
    protected $_primary = ['vehicle_id', 'vehicle_type_id'];
}