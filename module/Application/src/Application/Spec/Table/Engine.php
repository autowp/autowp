<?php

namespace Application\Spec\Table;

class Engine extends AbstractTable
{
    protected $engines;

    public function __construct($engines, $attributes, array $options = [])
    {
        $this->engines = $engines;
        $this->attributes = $attributes;
    }

    public function getEngines()
    {
        return $this->engines;
    }
}