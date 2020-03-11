<?php

namespace Application\Hydrator\Api;

use Application\Model\Item;
use Application\Service\SpecificationsService;
use Exception;
use Laminas\ServiceManager\ServiceLocatorInterface;

class AttrValueHydrator extends RestHydrator
{
    private Item $item;

    private SpecificationsService $specService;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->item        = $serviceManager->get(Item::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);
    }

    public function extract($object): ?array
    {
        $result = [
            'attribute_id' => (int) $object['attribute_id'],
            'item_id'      => (int) $object['item_id'],
        ];

        if ($this->filterComposite->filter('value')) {
            $result['value'] = $this->specService->getActualValue($object['attribute_id'], $object['item_id']);
        }

        if ($this->filterComposite->filter('value_text')) {
            $result['value_text'] = $this->specService->getActualValueText(
                $object['attribute_id'],
                $object['item_id'],
                $this->language
            );
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
