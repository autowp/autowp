<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;

class PictureController extends AbstractActionController
{
    private function _picture()
    {
        $identity = (string)$this->_getParam('picture_id');

        $pTable = $this->_helper->catalogue()->getPictureTable();

        $picture = $pTable->fetchRow(array(
            'id = ?'    => $identity,
            'identity IS NULL'
        ));

        if (!$picture) {
            $picture = $pTable->fetchRow(array(
                'identity = ?' => $identity
            ));
        }

        return $picture;
    }

    public function previewAction()
    {
        $picture = $this->_picture();

        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        $picturesData = $this->_helper->pic->listData(array($picture));

        $this->view->assign(array(
            'picturesData' => $picturesData,
        ));
    }

    private function _getPicturesSelect(Pictures_Row $picture)
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $galleryStatuses = array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW);

        if (in_array($picture->status, $galleryStatuses)) {


            $picSelect = $pictureTable->select(true)
                ->where('pictures.status IN (?)', $galleryStatuses)
                ->order($this->_helper->catalogue()->picturesOrdering());

            $galleryEnabled = false;
            switch ($picture->type)
            {
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
        if ($this->_getParam('preview')) {
            return $this->_forward('preview');
        }

        if ($this->_getParam('gallery')) {
            return $this->_forward('gallery');
        }

        $picture = $this->_picture();

        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        $brandTable = new Brands();
        $brandModel = new Brand();

        $url = $this->_helper->pic->href($picture->toArray(), array(
            'fallback' => false
        ));

        if ($url) {
            return $this->redirect($url, array(
                'code' => 301
            ));
        }

        $isModer = $this->_helper->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->_helper->user()->get();
            if (!$user) {
                return $this->_forward('notfound', 'error');
            }

            if ($isModer || ($user->id == $picture->owner_id)) {
                $this->getResponse()->setHttpResponseCode(404);
            } else {
                return $this->_forward('notfound', 'error');
            }
        }

        $picSelect = $this->_getPicturesSelect($picture);

        $brands = [];
        $car = null;
        $isEngines = false;

        switch ($picture->type) {
            case Picture::ENGINE_TYPE_ID:
                if ($engine = $picture->findParentEngines()) {
                    $cataloguePaths = $this->_helper->catalogue()->engineCataloguePaths($engine, array(
                        'limit' => 1
                    ));

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
                    $language = $this->_helper->language();
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

        $this->_helper->actionStack('brands', 'sidebar', 'default', array(
            'brand_id'   => $brands,
            'car_id'     => $car ? $car->id : null,
            'type'       => (int)$picture->type,
            'is_engines' => $isEngines
        ));

        $data = $this->_helper->pic->picPageData($picture, $picSelect, $brands);

        $this->view->assign($data);

        $this->view->assign(array(
            'galleryUrl' => $this->_helper->url->url(array(
                'gallery' => '1'
            ))
        ));
    }

    public function galleryAction()
    {
        $picture = $this->_picture();

        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        $isModer = $this->_helper->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->_helper->user()->get();
            if (!$user) {
                return $this->_forward('notfound', 'error');
            }

            if ($isModer || ($user->id == $picture->owner_id)) {
                //$this->getResponse()->setHttpResponseCode(404);
            } else {
                return $this->_forward('notfound', 'error');
            }
        }

        $select = $this->_getPicturesSelect($picture);

        return $this->_helper->json($this->_helper->pic->gallery($select));
    }
}