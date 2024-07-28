<?php

namespace Application\Hydrator\Api;

use Application\Model\Item;
use Application\Module;
use Application\Service\SpecificationsService;
use ArrayAccess;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_reverse;
use function Autowp\Commons\currentFromResultSetInterface;
use function is_array;

class AttrUserValueHydrator extends AbstractRestHydrator
{
    private Item $item;

    private SpecificationsService $specService;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->item        = $serviceManager->get(Item::class);
        $this->specService = $serviceManager->get(SpecificationsService::class);

        $strategy = new Strategy\Item($serviceManager);
        $this->addStrategy('item', $strategy);
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
        /** @var Strategy\Item $strategy */
        $strategy = $this->getStrategy('item');
        $strategy->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $updateDate = null;
        if ($object['update_date']) {
            $timezone   = new DateTimeZone(Module::MYSQL_TIMEZONE);
            $updateDate = DateTime::createFromFormat(Module::MYSQL_DATETIME_FORMAT, $object['update_date'], $timezone);
        }

        $result = [
            'update_date'  => $updateDate ? $updateDate->format(DateTimeInterface::ISO8601) : null,
            'item_id'      => (int) $object['item_id'],
            'attribute_id' => (int) $object['attribute_id'],
            'user_id'      => $object['user_id'],
        ];

        if ($this->filterComposite->filter('value')) {
            $value           = $this->specService->getUserValue2(
                $object['attribute_id'],
                $object['item_id'],
                $object['user_id']
            );
            $result['value'] = $value['value'];
            $result['empty'] = $value['empty'];
        }

        if ($this->filterComposite->filter('value_text')) {
            $result['value_text'] = $this->specService->getUserValueText(
                $object['attribute_id'],
                $object['item_id'],
                $object['user_id'],
                $this->language
            );
        }

        if ($this->filterComposite->filter('unit')) {
            $result['unit'] = $this->specService->getUnit($object['attribute_id']);
        }

        if ($this->filterComposite->filter('path')) {
            $attributeTable = $this->specService->getAttributeTable();

            $path      = [];
            $attribute = currentFromResultSetInterface($attributeTable->select(['id' => $object['attribute_id']]));
            if ($attribute) {
                $parents = [];
                $parent  = $attribute;
                do {
                    $parents[] = $parent['name'];
                    $parent    = currentFromResultSetInterface($attributeTable->select(['id' => $parent['parent_id']]));
                } while ($parent);

                $path = array_reverse($parents);
            }

            $result['path'] = $path;
        }

        if ($this->filterComposite->filter('item')) {
            $user = null;
            if ($object['item_id']) {
                $itemRow = $this->item->getRow([
                    'id' => (int) $object['item_id'],
                ]);
                if ($itemRow) {
                    $user = $this->extractValue('item', $itemRow);
                }
            }

            $result['item'] = $user;
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
