<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Pic;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 * @method void log(string $message, array $objects)
 * @method Pic pic()
 */
class PictureItemController extends AbstractRestfulController
{
    private PictureItem $pictureItem;

    private AbstractRestHydrator $hydrator;

    private InputFilter $itemInputFilter;

    private InputFilter $listInputFilter;

    private Item $item;

    private Picture $picture;

    public function __construct(
        PictureItem $pictureItem,
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        Item $item,
        Picture $picture
    ) {
        $this->pictureItem     = $pictureItem;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->item            = $item;
        $this->picture         = $picture;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $table = $this->pictureItem->getTable();

        $select = $table->getSql()->select();

        if ($data['item_id']) {
            $select->where(['picture_item.item_id' => $data['item_id']]);
        }

        if ($data['picture_id']) {
            $select->where(['picture_item.picture_id' => $data['picture_id']]);
        }

        if ($data['type']) {
            $select->where(['picture_item.type' => $data['type']]);
        }

        if ($data['order']) {
            switch ($data['order']) {
                case 'status':
                    $select->join('pictures', 'picture_item.picture_id = pictures.id', [])
                        ->order(['pictures.status']);
                    break;
            }
        }

        /** @var Adapter $adapter */
        $adapter   = $table->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(500)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));

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
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $userId = $this->user()->get()['id'];

        /** @psalm-suppress InvalidCast */
        $picture = $this->picture->getRow(['id' => (int) $this->params('picture_id')]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        /** @psalm-suppress InvalidCast */
        $item = $this->item->getRow(['id' => (int) $this->params('item_id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        /** @psalm-suppress InvalidCast */
        $type = (int) $this->params('type');

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $userId,
        ]);

        $row = $this->pictureItem->getPictureItemData($picture['id'], $item['id'], $type);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }
}
