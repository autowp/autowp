<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class LogHydrator extends AbstractRestHydrator
{
    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('date', $strategy);

        $strategy = new Strategy\Pictures($serviceManager);
        $this->addStrategy('pictures', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('items', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId($userId = null): self
    {
        /** @var Strategy\User $strategy */
        $strategy = $this->getStrategy('user');
        $strategy->setUserId($userId);

        /** @var Strategy\Pictures $strategy */
        $strategy = $this->getStrategy('pictures');
        $strategy->setUserId($userId);

        /** @var Strategy\Items $strategy */
        $strategy = $this->getStrategy('items');
        $strategy->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $result = [
            'date' => $this->extractValue('date', $object['date']),
            'desc' => $object['desc'],
        ];

        if ($this->filterComposite->filter('user')) {
            $result['user'] = $object['user'] ? $this->extractValue('user', $object['user']) : null;
        }

        if ($this->filterComposite->filter('pictures')) {
            $result['pictures'] = $this->extractValue('pictures', $object['pictures']);
        }

        if ($this->filterComposite->filter('items')) {
            $result['items'] = $this->extractValue('items', $object['items']);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }
}
