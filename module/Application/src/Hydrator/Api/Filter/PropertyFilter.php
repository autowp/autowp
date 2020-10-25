<?php

namespace Application\Hydrator\Api\Filter;

use Laminas\Hydrator\Filter\FilterInterface;

use function in_array;

class PropertyFilter implements FilterInterface
{
    private array $properties = [];

    public function __construct(array $properties)
    {
        $this->setProperties($properties);
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * @param string $property
     */
    public function filter($property): bool
    {
        return in_array($property, $this->properties);
    }
}
