<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\PictureItem;

class PictureItemController extends AbstractRestfulController
{
    /**
     * @var PictureItem
     */
    private $pictureItem;

    public function __construct(PictureItem $pictureItem)
    {
        $this->pictureItem = $pictureItem;
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

        if ($picture->owner_id == $currentUser->id) {
            if ($picture->status == DbTable\Picture::STATUS_INBOX) {
                return true;
            }
        }

        return false;
    }

    public function itemAction()
    {
        $pictureId = (int)$this->params('picture_id');
        $itemId    = (int)$this->params('item_id');

        $pictureTable = new DbTable\Picture();

        $picture = $pictureTable->find($pictureId)->current();
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $this->canChangePerspective($picture)) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();

        if ($request->isPost() || $request->isPut()) {
            $perspectiveId = (int)$this->params()->fromPost('perspective_id');

            $this->pictureItem->setProperties($picture->id, $itemId, [
                'perspective' => $perspectiveId ? $perspectiveId : null
            ]);

            $this->log(sprintf(
                'Установка ракурса картинки %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [$picture]);
        }

        $this->getResponse()->setStatusCode(200);

        return new JsonModel([
            'status' => true
        ]);
    }
}
