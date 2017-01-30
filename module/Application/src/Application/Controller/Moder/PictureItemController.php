<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\Row as PictureRow;
use Application\Model\DbTable\Item;
use Application\Model\PictureItem;

class PictureItemController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $pictureTable;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var Item
     */
    private $itemTable;

    public function __construct(
        Picture $pictureTable,
        PictureItem $pictureItem
    ) {
        $this->pictureItem = $pictureItem;
        $this->pictureTable = $pictureTable;
        $this->itemTable = new Item();
    }

    private function getPictureUrl(PictureRow $picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('moder/pictures/params', [
            'action'     => 'picture',
            'picture_id' => $picture->id
        ], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]);
    }

    public function removeAction()
    {
        $canMove = $this->user()->isAllowed('picture', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $picture = $this->pictureTable->find($this->params('picture_id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->itemTable->find($this->params('item_id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        $this->pictureItem->remove($picture->id, $item->id);
        
        $this->log(sprintf(
            'Картинка %s отвязана от %s',
            htmlspecialchars('#' . $picture->id),
            htmlspecialchars('#' . $item->id)
        ), [$item, $picture]);
        
        if ($picture->image_id) {
            $this->imageStorage()->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);
        }

        return $this->redirect()->toUrl($this->getPictureUrl($picture));
    }

    public function saveAreaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $picture = $this->pictureTable->find($this->params('picture_id'))->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        $item = $this->itemTable->find($this->params('item_id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        $left = round($this->params()->fromPost('x'));
        $top = round($this->params()->fromPost('y'));
        $width = round($this->params()->fromPost('w'));
        $height = round($this->params()->fromPost('h'));

        $left = max(0, $left);
        $left = min($picture->width, $left);
        $width = max(1, $width);
        $width = min($picture->width, $width);

        $top = max(0, $top);
        $top = min($picture->height, $top);
        $height = max(1, $height);
        $height = min($picture->height, $height);

        if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
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
        $this->pictureItem->setProperties($picture->id, $item->id, [
            'area' => $area
        ]);

        $this->log(sprintf(
            'Выделение области на картинке %s',
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [$picture]);

        return new JsonModel([
            'ok' => true
        ]);
    }
}
