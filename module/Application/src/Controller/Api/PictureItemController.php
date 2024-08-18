<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Pic;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Autowp\Image\Storage;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;
use function htmlspecialchars;
use function sprintf;

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

    private Log $log;

    private AbstractRestHydrator $hydrator;

    private InputFilter $itemInputFilter;

    private InputFilter $listInputFilter;

    private Item $item;

    private Picture $picture;

    private Storage $imageStorage;

    public function __construct(
        PictureItem $pictureItem,
        Log $log,
        AbstractRestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        Item $item,
        Picture $picture,
        Storage $imageStorage
    ) {
        $this->pictureItem     = $pictureItem;
        $this->log             = $log;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->item            = $item;
        $this->picture         = $picture;
        $this->imageStorage    = $imageStorage;
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

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        $canMove = $this->user()->enforce('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

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

        if ($this->pictureItem->isExists($picture['id'], $item['id'], $type)) {
            $this->pictureItem->remove($picture['id'], $item['id'], $type);

            $this->log(sprintf(
                'Картинка %s отвязана от %s',
                htmlspecialchars('#' . $picture['id']),
                htmlspecialchars('#' . $item['id'])
            ), [
                'items'    => $item['id'],
                'pictures' => $picture['id'],
            ]);

            if ($picture['image_id']) {
                $this->imageStorage->changeImageName($picture['image_id'], [
                    'pattern' => $this->picture->getFileNamePattern($picture['id']),
                ]);
            }
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function createAction()
    {
        $canMove = $this->user()->enforce('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $userId = $this->user()->get()['id'];

        /** @psalm-suppress InvalidCast */
        $pictureId = (int) $this->params('picture_id');
        /** @psalm-suppress InvalidCast */
        $itemId = (int) $this->params('item_id');
        /** @psalm-suppress InvalidCast */
        $type = (int) $this->params('type');

        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->item->getRow(['id' => $itemId]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $this->pictureItem->add($picture['id'], $item['id'], $type);

        $perspectiveId = isset($data['perspective_id']) ? (int) $data['perspective_id'] : null;

        $this->pictureItem->setProperties($picture['id'], $item['id'], PictureItem::PICTURE_CONTENT, [
            'perspective' => $perspectiveId ? $perspectiveId : null,
        ]);

        if ($picture['image_id']) {
            $this->imageStorage->changeImageName($picture['image_id'], [
                'pattern' => $this->picture->getFileNamePattern($picture['id']),
            ]);
        }

        $this->log->addEvent($userId, sprintf(
            'Картинка %s связана с %s',
            htmlspecialchars('#' . $picture['id']),
            htmlspecialchars('#' . $item['id'])
        ), [
            'items'    => $item['id'],
            'pictures' => $picture['id'],
        ]);

        $url = $this->url()->fromRoute('api/picture-item/item/create', [
            'picture_id' => $picture['id'],
            'item_id'    => $item['id'],
            'type'       => $type,
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function updateAction()
    {
        /** @psalm-suppress InvalidCast */
        $pictureId = (int) $this->params('picture_id');
        /** @psalm-suppress InvalidCast */
        $itemId = (int) $this->params('item_id');
        /** @psalm-suppress InvalidCast */
        $type = (int) $this->params('type');

        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        if (isset($data['item_id'])) {
            $canMove = $this->user()->enforce('picture', 'move');
            if (! $canMove) {
                return $this->forbiddenAction();
            }

            $srcItem = $this->item->getRow(['id' => $itemId]);
            if (! $srcItem) {
                return $this->notFoundAction();
            }
            $dstItem = $this->item->getRow(['id' => (int) $data['item_id']]);
            if (! $dstItem) {
                return $this->notFoundAction();
            }

            $this->pictureItem->changePictureItem($picture['id'], $type, $srcItem['id'], $dstItem['id']);

            $userId = $this->user()->get()['id'];

            $this->log->addEvent($userId, sprintf(
                'Картинка %s перемещена из %s в %s',
                htmlspecialchars('#' . $picture['id']),
                htmlspecialchars('#' . $srcItem['id']),
                htmlspecialchars('#' . $dstItem['id'])
            ), [
                'items'    => [$srcItem['id'], $dstItem['id']],
                'pictures' => $picture['id'],
            ]);
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
