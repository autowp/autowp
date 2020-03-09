<?php

namespace Application\Spec\Table;

class Car extends AbstractTable
{
    protected $cars;

    public function __construct($cars, $attributes)
    {
        $this->cars       = $cars;
        $this->attributes = $attributes;
    }

    public function getCars()
    {
        return $this->cars;
    }
}
