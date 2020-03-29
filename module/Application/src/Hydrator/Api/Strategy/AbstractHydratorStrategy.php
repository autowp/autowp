<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\AbstractRestHydrator;
use ArrayAccess;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractHydratorStrategy implements StrategyInterface
{
    protected ServiceLocatorInterface $serviceManager;

    protected AbstractRestHydrator $hydrator;

    protected array $fields = [];

    protected string $language;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    abstract protected function getHydrator(): AbstractRestHydrator;

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
     * @return mixed|null
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
