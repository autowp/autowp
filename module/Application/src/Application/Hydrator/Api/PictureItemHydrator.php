<?php

namespace Application\Hydrator\Api;

use Application\Hydrator\Api\Strategy\Item as HydratorItemStrategy;
use Application\Model\Item;

class PictureItemHydrator extends RestHydrator
{
    /**
     * @var Item
     */
    private $item;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->item = $serviceManager->get(Item::class);

        $strategy = new HydratorItemStrategy($serviceManager);
        $this->addStrategy('item', $strategy);
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

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($object)
    {
        $result = [
            'picture_id'     => (int)$object['picture_id'],
            'item_id'        => (int)$object['item_id'],
            'perspective_id' => (int)$object['perspective_id'],
        ];

        if ($this->filterComposite->filter('item')) {
            $row = $this->item->getRow(['id' => $object['item_id']]);

            $result['item'] = $row ? $this->extractValue('item', $row) : null;
        }

        if ($this->filterComposite->filter('area')) {
            $hasArea = $object['crop_width'] && $object['crop_height'];
            $result['area'] = null;
            if ($hasArea) {
                $result['area'] = [
                    'left'   => (int)$object['crop_left'],
                    'top'    => (int)$object['crop_top'],
                    'width'  => (int)$object['crop_width'],
                    'height' => (int)$object['crop_height'],
                ];
            }
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
}
