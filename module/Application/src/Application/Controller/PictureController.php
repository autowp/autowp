<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\Picture;

class PictureController extends AbstractActionController
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(DbTable\Picture $pictureTable)
    {
        $this->pictureTable = $pictureTable;
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

    private function getPicturesSelect(\Autowp\Commons\Db\Table\Row $picture)
    {
        $galleryStatuses = [
            Picture::STATUS_ACCEPTED
        ];

        if (in_array($picture->status, $galleryStatuses)) {
            $picSelect = $this->pictureTable->select(true)
                ->where('pictures.status IN (?)', $galleryStatuses)
                ->order($this->catalogue()->picturesOrdering());

            $picSelect
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join(
                    ['pi2' => 'picture_item'],
                    'picture_item.item_id = pi2.item_id',
                    null
                )
                ->where('pi2.picture_id = ?', $picture->id);
        } else {
            $picSelect = $this->pictureTable->select(true)
                ->where('pictures.id = ?', $picture->id);
        }

        return $picSelect;
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

        $brandModel = new BrandModel();

        $url = $this->pic()->href($picture->toArray(), [
            'fallback' => false
        ]);

        if ($url) {
            return $this->redirect()->toUrl($url);
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if (! $isModer && ($user->id != $picture->owner_id)) {
                return $this->notFoundAction();
            }

            $this->getResponse()->setStatusCode(404);
        }

        $picSelect = $this->getPicturesSelect($picture);

        $brands = [];

        $language = $this->language();
        $brandList = $brandModel->getList($language, function ($select) use ($picture) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                ->where('picture_item.picture_id = ?', $picture->id)
                ->group('item.id');
        });
        foreach ($brandList as $brand) {
            $brands[] = $brand['id'];
        }

        $data = $this->pic()->picPageData($picture, $picSelect, $brands, [
            'paginator' => [
                'route'     => 'picture/picture',
                'urlParams' => []
            ]
        ]);

        return array_replace($data, [
            'galleryUrl' => $this->url()->fromRoute('picture/picture', [
                'picture_id' => $picture->identity
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

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if (! $isModer && ($user->id != $picture->owner_id)) {
                return $this->notFoundAction();
            }
        }

        $select = $this->getPicturesSelect($picture);

        return new JsonModel($this->pic()->gallery2($select, [
            'page'      => $this->params()->fromQuery('page'),
            'pictureId' => $this->params()->fromQuery('pictureId'),
            'reuseParams' => true,
            'urlParams' => [
                'action' => 'picture'
            ]
        ]));
    }
}
