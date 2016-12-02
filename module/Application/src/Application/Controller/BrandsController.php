<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\BrandLanguage;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Vehicle;

class BrandsController extends AbstractActionController
{
    private $cache;

    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    public function indexAction()
    {
        $isHttps = (bool)$this->getRequest()->getServer('HTTPS');

        $language = $this->language();

        $cacheKey = 'brands_list_40_' . $language . '_' . ($isHttps ? 'HTTPS' : 'HTTP');

        $items = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $imageStorage = $this->imageStorage();

            $brandModel = new BrandModel();

            $items = $brandModel->getFullBrandsList($language);

            foreach ($items as &$line) {
                foreach ($line as &$char) {
                    foreach ($char['brands'] as &$item) {
                        $item['url'] = $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand',
                            'brand_catname' => $item['catname']
                        ]);
                        $item['newCarsUrl'] = $this->url()->fromRoute('brands/newcars', [
                            'brand_id' => $item['id'],
                        ]);

                        $img = false;
                        if ($item['img']) {
                            $imageInfo = $imageStorage->getFormatedImage($item['img'], 'brandicon');
                            if ($imageInfo) {
                                $img = $imageInfo->getSrc();
                            }
                        }

                        $item['logo'] = $img;
                    }
                }
            }
            unset($line, $char, $item);

            $this->cache->setItem($cacheKey, $items);
        }

        return [
            'brandList' => $items
        ];
    }

    public function newcarsAction()
    {
        /*if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->notFoundAction();
        }*/

        $brands = new BrandTable();

        $brand = $brands->find($this->params('brand_id'))->current();
        if (! $brand) {
            return $this->notFoundAction();
        }

        $language = $this->language();
        $brandLangTable = new BrandLanguage();
        $brandLang = $brandLangTable->fetchRow([
            'brand_id = ?' => $brand->id,
            'language = ?' => $language
        ]);

        $cars = new Vehicle();
        $carList = $cars->fetchAll(
            $cars->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brand_item', 'car_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brand->id)
                ->where('cars.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)')
                ->group('cars.id')
                ->order(['cars.add_datetime DESC'])
                ->limit(30)
        );

        $cars = [];
        foreach ($carList as $car) {
            $cars[] = $car->getNameData($language);
        }

        $viewModel = new ViewModel([
            'brand'     => $brand,
            'brandLang' => $brandLang,
            'carList'   => $cars
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
