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

    private $cache;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        $cache,
        Picture $picture,
        Twins $twins
    ) {
        $this->cache = $cache;
        $this->picture = $picture;
        $this->twins = $twins;
    }

    private function getBrands(array $selectedIds)
    {
        $language = $this->language();

        $key = 'TWINS_SIDEBAR_8_' . $language;

        $arr = $this->cache->getItem($key, $success);
        if (! $success) {
            $arr = $this->twins->getBrands([
                'language' => $language
            ]);

            foreach ($arr as &$brand) {
                $brand['url'] = '/ng/twins/' . $brand['catname'];
            }
            unset($brand);

            $this->cache->setItem($key, $arr);
        }

        foreach ($arr as &$brand) {
            $brand['selected'] = in_array($brand['id'], $selectedIds);
        }

        $sideBarModel = new ViewModel([
            'brands' => $arr
        ]);
        $sideBarModel->setTemplate('application/twins/partial/sidebar');
        $this->layout()->addChild($sideBarModel, 'sidebar');
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

    public function pictureAction()
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
            $data = $this->pic()->picPageData($picture, $filter, [], [
                'paginator' => [
                    'route'     => 'twins/group/pictures/picture',
                    'urlParams' => [
                        'id' => $group['id'],
                    ]
                ]
            ]);

            $this->getBrands($this->twins->getGroupBrandIds($group['id']));

            return array_replace($data, [
                'group'      => $group,
                'galleryUrl' => $this->url()->fromRoute('twins/group/pictures/picture/gallery', [
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity']
                ])
            ]);
        });
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
