<?php

namespace Application\Hydrator\Api;

use Exception;
use Traversable;

use Zend\Hydrator\AbstractHydrator;
use Zend\Hydrator\HydratorOptionsInterface;
use Zend\Stdlib\ArrayUtils;

use Application\Hydrator\Api\Filter\PropertyFilter;
use Application\Hydrator\Api\Strategy\HydratorStrategy;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 * @author dmitry
 *
 */
abstract class RestHydrator extends AbstractHydrator implements HydratorOptionsInterface
{
    protected $language;

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
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
        $this->getFilter('fields')->addFilter('fields', new PropertyFilter(array_keys($fields)));

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
