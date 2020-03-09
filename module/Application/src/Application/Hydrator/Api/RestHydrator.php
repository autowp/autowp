<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Filter\PropertyFilter;
use Application\Hydrator\Api\Strategy\HydratorStrategy;
use Laminas\Hydrator\AbstractHydrator;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\HydratorOptionsInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_keys;
use function is_array;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class RestHydrator extends AbstractHydrator implements HydratorOptionsInterface
{
    protected string $language;

    /** @var */
    protected array $fields = [];

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }
        if (isset($options['language'])) {
            $this->setLanguage($options['language']);
        }

        if (isset($options['fields'])) {
            $this->setFields($options['fields']);
        } else {
            $this->setFields([]);
        }

        return $this;
    }

    public function setFields(array $fields)
    {
        $this->fields = $fields;
        $this->getFilter()->addFilter('fields', new PropertyFilter(array_keys($fields)));

        foreach ($this->strategies as $strategy) {
            if ($strategy instanceof HydratorStrategy) {
                $strategy->setFields([]);
            }
        }

        foreach ($fields as $name => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! isset($this->strategies[$name])) {
                continue;
            }

            $strategy = $this->strategies[$name];

            if ($strategy instanceof HydratorStrategy) {
                $strategy->setFields($value);
            }
        }

        return $this;
    }

    public function setLanguage($language)
    {
        $this->language = $language;

        foreach ($this->strategies as $strategy) {
            if ($strategy instanceof HydratorStrategy) {
                $strategy->setLanguage($language);
            }
        }

        return $this;
    }
}
