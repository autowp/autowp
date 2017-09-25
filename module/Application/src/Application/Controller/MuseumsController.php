<?php

namespace Application\Controller;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Item;
use Application\Model\Picture;

class MuseumsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var TableGateway
     */
    private $itemLinkTable;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        $textStorage,
        TableGateway $itemLinkTable,
        Item $itemModel,
        Picture $picture
    ) {
        $this->textStorage = $textStorage;
        $this->itemLinkTable = $itemLinkTable;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/ng/map');
    }

    public function museumAction()
    {
        $museum = $this->itemModel->getRow([
            'id'           => (int)$this->params()->fromRoute('id'),
            'item_type_id' => Item::MUSEUM
        ]);
        if (! $museum) {
            return $this->notFoundAction();
        }

        $point = $this->itemModel->getPoint($museum['id']);

        $links = $this->itemLinkTable->select([
            'item_id' => $museum['id']
        ]);

        $description = $this->itemModel->getTextOfItem($museum['id'], $this->language());

        $rows = $this->picture->getRows([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => $museum['id']
        ]);

        $pictures = $this->pic()->listData($rows, [
            'width' => 4
        ]);

        return [
            'museum'      => $museum,
            'point'       => $point,
            'links'       => $links,
            'description' => $description,
            'pictures'    => $pictures,
        ];
    }
}
