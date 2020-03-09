<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\RestHydrator;
use ArrayAccess;
use Interop\Container\ContainerInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class HydratorStrategy implements StrategyInterface
{
    protected ContainerInterface $serviceManager;

    protected RestHydrator $hydrator;

    protected array $fields = [];

    protected string $language;

    public function __construct(ContainerInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    abstract protected function getHydrator(): RestHydrator;

    /**
     * @param array|ArrayAccess $value
     */
    public function extract($value): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        return $hydrator->extract($value);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param mixed $value
     * @return ?mixed
     */
    public function hydrate($value)
    {
        return null;
    }

    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }
}
