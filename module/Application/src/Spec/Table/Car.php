<?php

namespace Application\Spec\Table;

class Car extends AbstractTable
{
    protected array $cars;

    public function __construct(array $cars, array $attributes)
    {
        $this->cars       = $cars;
        $this->attributes = $attributes;
    }

    public function getCars(): array
    {
        return $this->cars;
    }
}
