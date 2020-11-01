<?php

namespace Application\Hydrator\Api;

use Application\Service\SpecificationsService;
use ArrayAccess;
use Exception;
use Laminas\ServiceManager\ServiceLocatorInterface;

class AttrValueHydrator extends AbstractRestHydrator
{
    private SpecificationsService $specService;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->specService = $serviceManager->get(SpecificationsService::class);
    }

    /**
     * @param array|ArrayAccess $object
     */
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
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): object
    {
        throw new Exception("Not supported");
    }
}
