<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class WidgetController extends AbstractActionController
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(DbTable\Picture $pictureTable)
    {
        $this->pictureTable = $pictureTable;
    }

    public function picturePreviewAction()
    {
        $picture = $this->pictureTable->find($this->params('picture_id'))->current();

        if (! $picture) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'ok'    => true,
            'html'  => $this->view->picture($picture, [
                'behaviour' => true
            ])
        ]);
    }
}
