<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Brand;
use Picture;
use Pictures_Row;

class PictureController extends AbstractActionController
{
    private function picture()
    {
        $identity = (string)$this->params('picture_id');

        $pTable = $this->catalogue()->getPictureTable();

        $picture = $pTable->fetchRow([
            'id = ?'    => $identity,
            'identity IS NULL'
        ]);

        if (!$picture) {
            $picture = $pTable->fetchRow([
                'identity = ?' => $identity
            ]);
        }

        return $picture;
    }

    public function previewAction()
    {
        $picture = $this->picture();

        if (!$picture) {
            return $this->notFoundAction();
        }

        $picturesData = $this->pic()->listData([$picture]);
        $viewModel = new ViewModel([
            'picturesData' => $picturesData,
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }

    private function getPicturesSelect(Pictures_Row $picture)
    {
        $pictureTable = $this->catalogue()->getPictureTable();

        $galleryStatuses = [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW];

        if (in_array($picture->status, $galleryStatuses)) {


            $picSelect = $pictureTable->select(true)
                ->where('pictures.status IN (?)', $galleryStatuses)
                ->order($this->catalogue()->picturesOrdering());

            $galleryEnabled = false;
            switch ($picture->type) {
                case Picture::CAR_TYPE_ID:
                    if ($picture->car_id) {
                        $galleryEnabled = true;
                        $picSelect
                            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                            ->where('pictures.car_id = ?', $picture->car_id);
                    }
                    break;

                case Picture::UNSORTED_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::LOGO_TYPE_ID:
                    if ($picture->brand_id) {
                        $galleryEnabled = true;
                        $picSelect
                            ->where('pictures.type = ?', $picture->type)
                            ->where('pictures.brand_id = ?', $picture->brand_id);
                    }

                    break;

                case Picture::FACTORY_TYPE_ID:
                    if ($picture->factory_id) {
                        $galleryEnabled = true;
                        $picSelect
                            ->where('pictures.type = ?', $picture->type)
                            ->where('pictures.factory_id = ?', $picture->factory_id);
                    }

                    break;

                case Picture::ENGINE_TYPE_ID:
                    if ($picture->engine_id) {
                        $galleryEnabled = true;
                        $picSelect
                            ->where('pictures.type = ?', Picture::ENGINE_TYPE_ID)
                            ->where('pictures.engine_id = ?', $picture->engine_id);
                    }
                    break;

            }

            if (!$galleryEnabled) {
                $picSelect
                    ->where('pictures.id = ?', $picture->id);
            }
        } else {
            $picSelect = $pictureTable->select(true)
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

        if (!$picture) {
            return $this->notFoundAction();
        }

        $brandModel = new Brand();

        $url = $this->pic()->href($picture->toArray(), [
            'fallback' => false
        ]);

        if ($url) {
            return $this->redirect()->toUrl($url);
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (!$user) {
                return $this->notFoundAction();
            }

            if (!$isModer && ($user->id != $picture->owner_id)) {
                return $this->notFoundAction();
            }

            $this->getResponse()->setStatusCode(404);
        }

        $picSelect = $this->getPicturesSelect($picture);

        $brands = [];
        $car = null;
        $isEngines = false;

        switch ($picture->type) {
            case Picture::ENGINE_TYPE_ID:
                if ($engine = $picture->findParentEngines()) {
                    $cataloguePaths = $this->catalogue()->engineCataloguePaths($engine, [
                        'limit' => 1
                    ]);

                    foreach ($cataloguePaths as $cataloguePath) {
                        $brandId = $brandModel->getBrandIdByCatname($cataloguePath['brand_catname']);
                        if ($brandId) {
                            $brands[] = $brandId;
                        }
                    }
                }
                $isEngines = true;

                break;

            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
                if ($picture->brand_id) {
                    $brands[] = $picture->brand_id;
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                if ($picture->factory_id) {
                    $brandId = $brandModel->getFactoryBrandId($picture->factory_id);
                    if ($brandId) {
                        $brands[] = $brandId;
                    }
                }
                break;
            case Picture::CAR_TYPE_ID:
                if ($car = $picture->findParentCars()) {
                    $language = $this->language();
                    $brandList = $brandModel->getList($language, function($select) use ($car) {
                        $select
                            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                            ->group('brands.id');
                    });
                    foreach ($brandList as $brand) {
                        $brands[] = $brand['id'];
                    }

                }
                break;
        }

        /*$this->_helper->actionStack('brands', 'sidebar', 'default', [
            'brand_id'   => $brands,
            'car_id'     => $car ? $car->id : null,
            'type'       => (int)$picture->type,
            'is_engines' => $isEngines
        ]);*/

        $data = $this->pic()->picPageData($picture, $picSelect, $brands, [
            'paginator' => [
                'route'     => 'picture/picture',
                'urlParams' => []
            ]
        ]);

        return array_replace($data, [
            'galleryUrl' => $this->url()->fromRoute('picture/picture', [
                'picture_id' => $picture->identity ? $picture->identity : $picture->id
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

        if (!$picture) {
            return $this->notFoundAction();
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (!$user) {
                return $this->notFoundAction();
            }

            if (!$isModer && ($user->id != $picture->owner_id)) {
                return $this->notFoundAction();
            }
        }

        $select = $this->getPicturesSelect($picture);

        return new JsonModel($this->pic()->gallery($select));
    }
}
