<?php

namespace Application\Hydrator\Api;

use Autowp\User\Model\User;

use Application\Model\Item;

class AttrConflictHydrator extends RestHydrator
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

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->userId = null;

        $this->item = $serviceManager->get(Item::class);
        $this->userModel = $serviceManager->get(User::class);
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
     * @return Comment
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'item_id'   => (int)$object['item_id'],
            'attribute' => (string) $object['attribute'],
            'unit'      => $object['unit'],
            'values'    => $object['values'],
            'value'     => $object['value'],
        ];

        foreach ($object['values'] as &$value) {
            $value['user'] = $this->userModel->getRow((int)$value['userId']);
        }

        $car = $this->item->getRow(['id' => $object['item_id']]);
        $result['object'] = $car ? $this->car()->formatName($car, $this->language) : null;
        $result['url'] = $this->url()->fromRoute('cars/params', [
            'action'  => 'car-specifications-editor',
            'item_id' => $object['itemId'],
            'tab'     => 'spec'
        ]);

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
