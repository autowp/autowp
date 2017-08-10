<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\Picture;

class WidgetController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
    }

    public function picturePreviewAction()
    {
        $picture = $this->picture->getRow(['id' => (int)$this->params('picture_id')]);

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
