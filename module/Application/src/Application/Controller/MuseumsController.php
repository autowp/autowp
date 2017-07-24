<?php

namespace Application\Controller;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;
use Application\Model\Item;

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
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        $textStorage,
        TableGateway $itemLinkTable,
        Item $itemModel,
        DbTable\Picture $pictureTable
    ) {
        $this->textStorage = $textStorage;
        $this->itemLinkTable = $itemLinkTable;
        $this->itemModel = $itemModel;
        $this->pictureTable = $pictureTable;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/map');
    }

    public function museumAction()
    {
        $table = new DbTable\Item();

        $museum = $table->fetchRow([
            'id = ?'           => (int)$this->params()->fromRoute('id'),
            'item_type_id = ?' => Item::MUSEUM
        ]);
        if (! $museum) {
            return $this->notFoundAction();
        }

        $point = $this->itemModel->getPoint($museum->id);

        $links = $this->itemLinkTable->select([
            'item_id' => $museum['id']
        ]);

        $description = $this->itemModel->getTextOfItem($museum['id'], $this->language());

        $select = $this->pictureTable->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $museum->id)
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);

        $pictures = $this->pic()->listData($select, [
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
