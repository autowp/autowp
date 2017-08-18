<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;

class PictureItemController extends AbstractRestfulController
{
    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        PictureItem $pictureItem,
        Log $log,
        RestHydrator $hydrator,
        InputFilter $itemInputFilter,
        Item $item,
        Picture $picture
    ) {
        $this->pictureItem = $pictureItem;
        $this->log = $log;
        $this->hydrator = $hydrator;
        $this->itemInputFilter = $itemInputFilter;
        $this->item = $item;
        $this->picture = $picture;
    }

    private function canChangePerspective($picture)
    {
        if ($this->user()->inheritsRole('moder')) {
            return true;
        }

        $currentUser = $this->user()->get();
        if (! $currentUser) {
            return false;
        }

        if ($picture['owner_id'] == $currentUser['id']) {
            if ($picture['status'] == Picture::STATUS_INBOX) {
                return true;
            }
        }

        return false;
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$this->params('picture_id')]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields']
        ]);

        $row = $this->pictureItem->getPictureItemData($picture['id'], $item['id']);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    public function deleteAction()
    {
        $canMove = $this->user()->isAllowed('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$this->params('picture_id')]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        if ($this->pictureItem->isExists($picture['id'], $item['id'])) {
            $this->pictureItem->remove($picture['id'], $item['id']);

            $this->log(sprintf(
                'Картинка %s отвязана от %s',
                htmlspecialchars('#' . $picture['id']),
                htmlspecialchars('#' . $item['id'])
            ), [
                'items'    => $item['id'],
                'pictures' => $picture['id']
            ]);

            if ($picture['image_id']) {
                $this->imageStorage()->changeImageName($picture['image_id'], [
                    'pattern' => $this->picture->getFileNamePattern($picture)
                ]);
            }
        }

        return $this->getResponse()->setStatusCode(204);
    }

    public function createAction()
    {
        $canMove = $this->user()->isAllowed('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $userId = $this->user()->get()['id'];

        $pictureId = (int)$this->params('picture_id');
        $itemId    = (int)$this->params('item_id');

        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->item->getRow(['id' => $itemId]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $this->pictureItem->add($picture['id'], $item['id']);

        $perspectiveId = isset($data['perspective_id']) ? (int)$data['perspective_id'] : null;

        $this->pictureItem->setProperties($picture['id'], $item['id'], [
            'perspective' => $perspectiveId ? $perspectiveId : null
        ]);

        $namespace = new \Zend\Session\Container('Moder_Car');
        $namespace->lastCarId = $item['id'];

        $this->log->addEvent($userId, sprintf(
            'Картинка %s связана с %s',
            htmlspecialchars('#' . $picture['id']),
            htmlspecialchars('#' . $item['id'])
        ), [
            'items'    => $item['id'],
            'pictures' => $picture['id']
        ]);

        $url = $this->url()->fromRoute('api/picture-item/create', [
            'picture_id' => $picture['id'],
            'item_id'    => $item['id']
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
    }

    public function updateAction()
    {
        $pictureId = (int)$this->params('picture_id');
        $itemId    = (int)$this->params('item_id');

        $picture = $this->picture->getRow(['id' => $pictureId]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $this->canChangePerspective($picture)) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        if (isset($data['perspective_id'])) {
            $perspectiveId = (int)$data['perspective_id'];

            $this->pictureItem->setProperties($picture['id'], $itemId, [
                'perspective' => $perspectiveId ? $perspectiveId : null
            ]);

            $this->log(sprintf(
                'Установка ракурса картинки %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id']
            ]);
        }

        if (isset($data['area'])) {
            if (! $this->user()->inheritsRole('moder')) {
                return $this->forbiddenAction();
            }

            $item = $this->item->getRow(['id' => $itemId]);
            if (! $item) {
                return $this->notFoundAction();
            }

            $left = round($data['area']['left']);
            $top = round($data['area']['top']);
            $width = round($data['area']['width']);
            $height = round($data['area']['height']);

            $left = max(0, $left);
            $left = min($picture['width'], $left);
            $width = max(1, $width);
            $width = min($picture['width'], $width);

            $top = max(0, $top);
            $top = min($picture['height'], $top);
            $height = max(1, $height);
            $height = min($picture['height'], $height);

            if ($left > 0 || $top > 0 || $width < $picture['width'] || $height < $picture['height']) {
                $area = [
                    'left'   => $left,
                    'top'    => $top,
                    'width'  => $width,
                    'height' => $height
                ];
            } else {
                $area = [
                    'left'   => null,
                    'top'    => null,
                    'width'  => null,
                    'height' => null
                ];
            }
            $this->pictureItem->setProperties($picture['id'], $item['id'], [
                'area' => $area
            ]);

            $this->log(sprintf(
                'Выделение области на картинке %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id'],
                'items'    => $item['id']
            ]);
        }

        if (isset($data['item_id'])) {
            $canMove = $this->user()->isAllowed('picture', 'move');
            if (! $canMove) {
                return $this->forbiddenAction();
            }

            $srcItem = $this->item->getRow(['id' => $itemId]);
            if (! $srcItem) {
                return $this->notFoundAction();
            }
            $dstItem = $this->item->getRow(['id' => (int)$data['item_id']]);
            if (! $dstItem) {
                return $this->notFoundAction();
            }

            $this->pictureItem->changePictureItem($picture['id'], $srcItem['id'], $dstItem['id']);

            $userId = $this->user()->get()['id'];

            $this->log->addEvent($userId, sprintf(
                'Картинка %s перемещена из %s в %s',
                htmlspecialchars('#' . $picture['id']),
                htmlspecialchars('#' . $srcItem['id']),
                htmlspecialchars('#' . $dstItem['id'])
            ), [
                'items'    => [$srcItem['id'], $dstItem['id']],
                'pictures' => $picture['id']
            ]);

            $namespace = new \Zend\Session\Container('Moder_Car');
            $namespace->lastCarId = $dstItem['id'];
        }

        return $this->getResponse()->setStatusCode(200);
    }
}
