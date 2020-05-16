<?php

namespace Application\Hydrator\Api;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use ArrayAccess;
use Exception;
use Laminas\ServiceManager\ServiceLocatorInterface;

class AttrConflictHydrator extends AbstractRestHydrator
{
    private Item $item;

    private SpecificationsService $specService;

    private ItemNameFormatter $itemNameFormatter;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->item              = $serviceManager->get(Item::class);
        $this->specService       = $serviceManager->get(SpecificationsService::class);
        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $result = [
            'item_id'   => (int) $object['item_id'],
            'attribute' => (string) $object['attribute'],
            'unit'      => $object['unit'],
        ];

        $item = $this->item->getRow(['id' => $object['item_id']]);

        if ($item) {
            $result['object'] = $this->itemNameFormatter->format(
                $this->item->getNameData($item, $this->language),
                $this->language
            );
        }

        if ($this->filterComposite->filter('values')) {
            $userValueTable = $this->specService->getUserValueTable();

            // other users values
            $userValueRows = $userValueTable->select([
                'attribute_id' => $object['attribute_id'],
                'item_id'      => $object['item_id'],
            ]);

            $values = [];
            foreach ($userValueRows as $userValueRow) {
                $values[] = [
                    'value'   => $this->specService->getUserValueText(
                        $userValueRow['attribute_id'],
                        $userValueRow['item_id'],
                        $userValueRow['user_id'],
                        $this->language
                    ),
                    'user_id' => (int) $userValueRow['user_id'],
                ];
            }

            $result['values'] = $values;
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
}
