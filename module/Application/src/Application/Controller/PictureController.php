<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Brand;
use Application\Model\DbTable;
use Application\Model\Picture;

class PictureController extends AbstractActionController
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct(DbTable\Picture $pictureTable, Brand $brand)
    {
        $this->pictureTable = $pictureTable;
        $this->brand = $brand;
    }

    private function picture()
    {
        $identity = (string)$this->params('picture_id');

        return $this->pictureTable->fetchRow([
            'identity = ?' => $identity
        ]);
    }

    public function previewAction()
    {
        $picture = $this->picture();

        if (! $picture) {
            return $this->notFoundAction();
        }

        $picturesData = $this->pic()->listData([$picture]);
        $viewModel = new ViewModel([
            'picturesData' => $picturesData,
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }

    private function getPicturesFilter($picture): array
    {
        $galleryStatuses = [
            Picture::STATUS_ACCEPTED
        ];

        if (! in_array($picture['status'], $galleryStatuses)) {
            return [
                'id' => $picture['id']
            ];
        }

        return [
            'status' => $galleryStatuses,
            'order'  => 'resolution_desc',
            'item'   => [
                'contains_picture' => $picture['id']
            ]
        ];
    }

    public function indexAction()
    {
        if ($this->params()->fromQuery('preview')) {
            return $this->forward()->dispatch(self::class, [
                'action'     => 'preview',
                'picture_id' => $this->params('picture_id')
            ]);
        }

        if ($this->params()->fromQuery('gallery')) {
            return $this->forward()->dispatch(self::class, [
                'action'     => 'gallery',
                'picture_id' => $this->params('picture_id')
            ]);
        }

        $picture = $this->picture();

        if (! $picture) {
            return $this->notFoundAction();
        }

        $url = $this->pic()->href($picture->toArray(), [
            'fallback' => false
        ]);

        if ($url) {
            return $this->redirect()->toUrl($url);
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture['status'] == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if (! $isModer && ($user['id'] != $picture['owner_id'])) {
                return $this->notFoundAction();
            }

            $this->getResponse()->setStatusCode(404);
        }

        $picFilter = $this->getPicturesFilter($picture);

        $brands = [];

        $language = $this->language();
        $brandList = $this->brand->getList($language, function (Sql\Select $select) use ($picture) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->where(['picture_item.picture_id' => $picture['id']])
                ->group('item.id');
        });
        foreach ($brandList as $brand) {
            $brands[] = $brand['id'];
        }

        $data = $this->pic()->picPageData($picture, $picFilter, $brands, [
            'paginator' => [
                'route'     => 'picture/picture',
                'urlParams' => []
            ]
        ]);

        return array_replace($data, [
            'galleryUrl' => $this->url()->fromRoute('picture/picture', [
                'picture_id' => $picture['identity']
            ], [
                'query' => [
                    'gallery' => '1'
                ]
            ])
        ]);
    }

    public function galleryAction()
    {
        $picture = $this->picture();

        if (! $picture) {
            return $this->notFoundAction();
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture['status'] == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if (! $isModer && ($user['id'] != $picture['owner_id'])) {
                return $this->notFoundAction();
            }
        }

        $filter = $this->getPicturesFilter($picture);

        return new JsonModel($this->pic()->gallery2($filter, [
            'page'      => $this->params()->fromQuery('page'),
            'pictureId' => $this->params()->fromQuery('pictureId'),
            'reuseParams' => true,
            'urlParams' => [
                'action' => 'picture'
            ]
        ]));
    }
}
