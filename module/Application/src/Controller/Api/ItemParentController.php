<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Car;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\ItemParent;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;
use function strlen;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 * @method ViewModel forbiddenAction()
 * @method void log(string $message, array $objects)
 * @method Car car()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ItemParentController extends AbstractRestfulController
{
    private AbstractRestHydrator $hydrator;

    private ItemParent $itemParent;

    private InputFilter $listInputFilter;

    private InputFilter $itemInputFilter;

    public function __construct(
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        ItemParent $itemParent
    ) {
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->itemParent      = $itemParent;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $user = $this->user()->get();

        $isModer = $this->user()->enforce('global', 'moderate');

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $group = false;

        $select = new Sql\Select($this->itemParent->getTable()->getTable());
        $select->join('item', 'item_parent.item_id = item.id', []);

        if (strlen($data['type_id']) > 0) {
            $select->where(['item_parent.type' => (int) $data['type_id']]);
        }

        if ($data['item_type_id']) {
            $select->where(['item.item_type_id' => $data['item_type_id']]);
        }

        if ($data['parent_id']) {
            $select->where(['item_parent.parent_id' => $data['parent_id']]);
        }

        if ($data['catname']) {
            $select->where(['item_parent.catname' => $data['catname']]);
        }

        if (strlen($data['concept'])) {
            if ($data['concept']) {
                $select->where(['item.is_concept']);
            } else {
                $select->where(['NOT item.is_concept']);
            }
        }

        if (strlen($data['concept_inherit'])) {
            if ($data['concept_inherit']) {
                $select->where(['item.is_concept_inherit']);
            } else {
                $select->where(['NOT item.is_concept_inherit']);
            }
        }

        if ($data['exclude_concept']) {
            $select->where(['not item.is_concept']);
        }

        if ($isModer) {
            if ($data['item_id']) {
                $select->where(['item_parent.item_id' => $data['item_id']]);
            }

            if ($data['is_group']) {
                $select->where(['item.is_group']);
            }
        }

        switch ($data['order']) {
            case 'categories_first':
                $select->order([
                    'item_parent.type',
                    new Sql\Expression('item.item_type_id = ? DESC', [Item::CATEGORY]),
                    'item.begin_order_cache',
                    'item.end_order_cache',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                ]);
                break;
            case 'type_auto':
                $select->order([
                    'item_parent.type',
                    'item.begin_order_cache',
                    'item.end_order_cache',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                ]);
                break;
            default:
                $select->order([
                    'item_parent.type',
                    'item.name',
                    'item.body',
                    'item.spec_id',
                    'item.begin_order_cache',
                    'item.end_order_cache',
                ]);
                break;
        }

        if ($group) {
            $select->group(['item_parent.item_id', 'item_parent.parent_id']);
        }

        /** @var Adapter $adapter */
        $adapter   = $this->itemParent->getTable()->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $limit = $data['limit'] ?: 1;

        $paginator
            ->setItemCountPerPage($limit)
            ->setCurrentPageNumber($data['page']);

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        $user = $this->user()->get();
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        /** @psalm-suppress InvalidCast */
        $row = $this->itemParent->getRow(
            (int) $this->params('parent_id'),
            (int) $this->params('item_id')
        );
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }
}
