<?php

namespace Application\Hydrator\Api;

use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrValueHydrator extends RestHydrator
{
    /**
     * @var Item
     */
    private $item;

    /**
     * @var SpecificationsService
     */
    private $specService;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->item = $serviceManager->get(Item::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);
    }

    public function extract($object)
    {
        $result = [
            'attribute_id' => (int)$object['attribute_id'],
            'item_id'      => (int)$object['item_id']
        ];

        if ($this->filterComposite->filter('value')) {
            $result['value'] = $this->specService->getActualValue($object['attribute_id'], $object['item_id']);
        }

        if ($this->filterComposite->filter('value_text')) {
            $result['value_text'] = $this->specService->getActualValueText($object['attribute_id'], $object['item_id'], $this->language);
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
