<?php

namespace Application\Validator\Brand;

use Zend\Validator\AbstractValidator;

use Application\Model\DbTable\BrandCar as BrandVehicle;

class VehicleCatnameNotExists extends AbstractValidator
{
    const EXISTS = 'brandVehicleCatnameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Brand vehicle catname '%value%' already exists"
    ];

    private $brandId;

    private $ignoreVehicleId;

    public function setBrandId($brandId)
    {
        $this->brandId = $brandId;

        return $this;
    }

    public function setIgnoreVehicleId($ignoreVehicleId)
    {
        $this->ignoreVehicleId = $ignoreVehicleId;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $filter = [
            'brand_id = ?' => (int)$this->brandId,
            'catname = ?'  => (string)$value
        ];
        if ($this->ignoreVehicleId) {
            $filter['car_id <> ?'] = $this->ignoreVehicleId;
        }

        $table = new BrandVehicle();
        $row = $table->fetchRow($filter);
        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}