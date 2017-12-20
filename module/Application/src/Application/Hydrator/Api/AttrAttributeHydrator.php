<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Router\Http\TreeRouteStack;
use Zend\Stdlib\ArrayUtils;

use Autowp\User\Model\User;

use Application\Hydrator\Api\Filter\PropertyFilter;
use Application\Hydrator\Api\Strategy\HydratorStrategy;
use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrAttributeHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var SpecificationsService
     */
    private $specService;

    /**
     * @var TreeRouteStack
     */
    private $router;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->userId = null;

        $this->item = $serviceManager->get(Item::class);
        $this->userModel = $serviceManager->get(User::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);

        $strategy = new Strategy\AttrAttributes($serviceManager);
        $this->addStrategy('childs', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
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
     * @return AttrConflictHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'id'          => $object['id'],
            'name'        => $object['name'],
            'description' => $object['description'],
            'type_id'     => $object['typeId'],
            'unit_id'     => $object['unitId'],
            'is_multiple' => (bool)$object['isMultiple'],
            'precision'   => $object['precision']
        ];

        if ($this->filterComposite->filter('unit')) {
            $result['unit'] = $this->specService->getUnit($object['unitId']);
        }

        if (isset($object['childs'])) {
            $result['childs'] = $this->extractValue('childs', $object['childs']);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
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

        if (isset($fields['childs'])) {
            $strategy = $this->strategies['childs'];

            if ($strategy instanceof HydratorStrategy) {
                $strategy->setFields($fields);
            }
        }

        return $this;
    }
}
