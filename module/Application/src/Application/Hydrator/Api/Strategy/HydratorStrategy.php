<?php

namespace Application\Hydrator\Api\Strategy;

use Interop\Container\ContainerInterface;
use Zend\Hydrator\Strategy\StrategyInterface;

use Application\Hydrator\Api\Content as Hydrator;

abstract class HydratorStrategy implements StrategyInterface
{
    /**
     * @var ContainerInterface
     */
    protected $serviceManager;

    /**
     * @var Hydrator
     */
    protected $hydrator;

    /**
     * @var array
     */
    protected $fields = [];

    public function __construct(ContainerInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * @return Hydrator
     */
    abstract protected function getHydrator();

    public function extract($value)
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);

        return $hydrator->extract($value);
    }

    public function hydrate($value)
    {
        return null;
    }

    /**
     * @param array $fields
     * @return HydratorStrategy
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
