<?php

namespace Application\Hydrator\Api;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use ArrayAccess;
use Autowp\User\Model\User;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_reverse;
use function is_array;

class AttrUserValueHydrator extends AbstractRestHydrator
{
    private int $userId;

    private Item $item;

    private User $userModel;

    private SpecificationsService $specService;

    private ItemNameFormatter $itemNameFormatter;

    private TreeRouteStack $router;

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $this->userId = 0;

        $this->item              = $serviceManager->get(Item::class);
        $this->userModel         = $serviceManager->get(User::class);
        $this->specService       = $serviceManager->get(SpecificationsService::class);
        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
        $this->router            = $serviceManager->get('HttpRouter');

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);

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
        $this->userId = $userId;

        $this->getStrategy('user')->setUserId($userId);
        $this->getStrategy('item')->setUserId($userId);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        $updateDate = null;
        if ($object['update_date']) {
            $timezone   = new DateTimeZone(MYSQL_TIMEZONE);
            $updateDate = DateTime::createFromFormat(MYSQL_DATETIME_FORMAT, $object['update_date'], $timezone);
        }

        $result = [
            'update_date'  => $updateDate ? $updateDate->format(DateTime::ISO8601) : null,
            'item_id'      => (int) $object['item_id'],
            'attribute_id' => (int) $object['attribute_id'],
            'user_id'      => (int) $object['user_id'],
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
            $attribute = $attributeTable->select(['id' => $object['attribute_id']])->current();
            if ($attribute) {
                $parents = [];
                $parent  = $attribute;
                do {
                    $parents[] = $parent['name'];
                    $parent    = $attributeTable->select(['id' => $parent['parent_id']])->current();
                } while ($parent);

                $path = array_reverse($parents);
            }

            $result['path'] = $path;
        }

        if ($this->filterComposite->filter('user')) {
            $user = null;
            if ($object['user_id']) {
                $userRow = $this->userModel->getRow((int) $object['user_id']);
                if ($userRow) {
                    $user = $this->extractValue('user', $userRow);
                }
            }

            $result['user'] = $user;
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
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
