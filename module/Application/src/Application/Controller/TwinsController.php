<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Picture;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

class TwinsController extends AbstractActionController
{
    /**
     * @var Twins
     */
    private $twins;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        Picture $picture,
        Twins $twins
    ) {
        $this->picture = $picture;
        $this->twins = $twins;
    }

    private function doPictureAction($callback)
    {
        $group = $this->twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $pictureId = (string)$this->params('picture_id');

        $picture = $this->picture->getRow([
            'identity' => $pictureId,
            'status'   => Picture::STATUS_ACCEPTED,
            'item'     => [
                'ancestor_or_self' => $group['id']
            ]
        ]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        return $callback($group, $picture);
    }

    public function pictureGalleryAction()
    {
        return $this->doPictureAction(function ($group, $picture) {

            $filter = [
                'order'  => 'resolution_desc',
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => $group['id']
                ]
            ];

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return new JsonModel($this->pic()->gallery2($filter, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'route'     => 'twins/group/pictures/picture',
                'urlParams' => [
                    'action'     => 'picture',
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity']
                ]
            ]));
        });
    }
}
