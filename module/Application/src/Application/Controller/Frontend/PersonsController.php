<?php

namespace Application\Controller\Frontend;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Item;
use Application\Model\Picture;

class PersonsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var TableGateway
     */
    private $itemLinkTable;

    public function __construct(
        $textStorage,
        Item $itemModel,
        Picture $picture,
        TableGateway $itemLinkTable
    ) {
        $this->textStorage = $textStorage;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
        $this->itemLinkTable = $itemLinkTable;
    }

    public function personAction()
    {
        $person = $this->itemModel->getRow([
            'id'           => (int)$this->params()->fromRoute('id'),
            'item_type_id' => Item::PERSON
        ]);
        if (! $person) {
            return $this->notFoundAction();
        }

        $paginator = $this->picture->getPaginator([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => $person['id']
        ]);

        $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params()->fromRoute('page'));

        $pictures = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 4
        ]);

        $language = $this->language();

        $description = $this->itemModel->getTextOfItem($person['id'], $language);

        $links = $this->itemLinkTable->select([
            'item_id' => $person['id']
        ]);

        return [
            'person'      => $person,
            'description' => $description,
            'pictures'    => $pictures,
            'canEdit'     => $this->user()->isAllowed('factory', 'edit'),
            'personName'  => $this->itemModel->getNameData($person, $language),
            'links'       => $links,
            'paginator'   => $paginator
        ];
    }
}
