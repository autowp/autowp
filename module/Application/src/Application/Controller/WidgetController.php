<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class WidgetController extends AbstractActionController
{
    public function picturePreviewAction()
    {
        $pictures = $this->catalogue()->getPictureTable();
        $picture = $pictures->find($this->params('picture_id'))->current();

        if (!$picture) {
            return $this->getResponse()->setStatusCode(404);
        }

        return new JsonModel([
            'ok'    =>  true,
            'html'  =>  $this->view->picture($picture, [
                'behaviour' => true
            ])
        ]);
    }
}