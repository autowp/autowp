<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Filter\PropertyFilter;
use Application\Hydrator\Api\Strategy\AbstractHydratorStrategy;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use ArrayAccess;
use Autowp\User\Model\User;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_keys;
use function in_array;
use function is_array;

class AttrAttributeHydrator extends AbstractRestHydrator
{
    private int $userId = 0;

    private Item $item;

    private User $userModel;

    private SpecificationsService $specService;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->userId = 0;

        $this->item        = $serviceManager->get(Item::class);
        $this->userModel   = $serviceManager->get(User::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);

        $strategy = new Strategy\AttrAttributes($serviceManager);
        $this->addStrategy('childs', $strategy);
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
        $this->userId = $userId;

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $result = [
            'id'          => $object['id'],
            'name'        => $object['name'],
            'description' => $object['description'],
            'type_id'     => $object['typeId'],
            'unit_id'     => $object['unitId'],
            'is_multiple' => (bool) $object['isMultiple'],
            'precision'   => $object['precision'],
        ];

        if ($this->filterComposite->filter('unit')) {
            $result['unit'] = $this->specService->getUnit($object['unitId']);
        }

        if ($this->filterComposite->filter('options')) {
            if (in_array($object['typeId'], [6, 7])) {
                $result['options'] = $this->specService->getListOptionsArray($object['id']);
            }
        }

        if (isset($object['childs'])) {
            $result['childs'] = $this->extractValue('childs', $object['childs']);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }

    public function setFields(array $fields): self
    {
        $this->getFilter()->addFilter('fields', new PropertyFilter(array_keys($fields)));

        foreach ($fields as $name => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! isset($this->strategies[$name])) {
                continue;
            }

            $strategy = $this->strategies[$name];

            if ($strategy instanceof AbstractHydratorStrategy) {
                $strategy->setFields($value);
            }
        }

        if (isset($fields['childs'])) {
            $strategy = $this->strategies['childs'];

            if ($strategy instanceof AbstractHydratorStrategy) {
                $strategy->setFields($fields);
            }
        }

        return $this;
    }
}
