<?php

namespace Application\Controller\Frontend;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;

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

        $authorPaginator = $this->picture->getPaginator([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'        => $person['id'],
                'link_type' => PictureItem::PICTURE_AUTHOR
            ]
        ]);

        $authorPaginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params()->fromRoute('page'));

        $authorPictures = $this->pic()->listData($authorPaginator->getCurrentItems(), [
            'width' => 4
        ]);


        $contentPaginator = $this->picture->getPaginator([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'        => $person['id'],
                'link_type' => PictureItem::PICTURE_CONTENT
            ]
        ]);

        $contentPaginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params()->fromRoute('page'));

        $contentPictures = $this->pic()->listData($contentPaginator->getCurrentItems(), [
            'width' => 4
        ]);


        $language = $this->language();

        $description = $this->itemModel->getTextOfItem($person['id'], $language);

        $links = $this->itemLinkTable->select([
            'item_id' => $person['id']
        ]);

        return [
            'person'           => $person,
            'description'      => $description,
            'canEdit'          => $this->user()->isAllowed('factory', 'edit'),
            'personName'       => $this->itemModel->getNameData($person, $language),
            'links'            => $links,
            'authorPaginator'  => $authorPaginator,
            'authorPictures'   => $authorPictures,
            'contentPaginator' => $contentPaginator,
            'contentPictures'  => $contentPictures,
        ];
    }
}
